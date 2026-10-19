<?php
// API: o sininho da app (🔔). Lista do que aconteceu nos últimos 30 dias à tua volta, calculada
// a partir dos dados que já existem (não há tabela própria):
//   - episódios marcados pelo par, amigos e quem vê séries contigo (marcações seguidas juntam-se numa linha);
//     cada um diz se a série é vista contigo (juntos), para os filtros da app
//   - comentários nessas séries
//   - convites "Quero ver contigo" por responder
//   - pedidos de amizade por responder
// Comentários, convites e pedidos seguem as regras do Notificador. Quem tem "só juntos" só aparece nas séries em comum.
// O "já vi" fica guardado na própria app (só conta como novo o que for mais recente).

use Illuminate\Database\Capsule\Manager as Capsule;

class NotificacoesApi extends ApiController
{
    private const DIAS      = 30;            // quanto para trás se olha
    private const MAXIMO    = 60;            // linhas devolvidas
    private const PAUSA_SEG = 3 * 3600;      // marcações com mais de 3 h de intervalo são "sessões" diferentes

    // GET /notificacoes — { ok, notificacoes: [ {id, tipo, em, quem, texto, detalhe, serie, episodio} ] }
    public function lista(): void
    {
        $user  = $this->exigirToken();
        $desde = date('Y-m-d H:i:s', strtotime('-' . self::DIAS . ' days'));

        $itens = array_merge(
            $this->episodios($user, $desde),
            $this->comentarios($user, $desde),
            $this->convites($user),
            $this->pedidos($user)
        );

        // Mais recentes primeiro (o "em" é ISO 8601 com o mesmo fuso: dá para comparar como texto)
        usort($itens, fn ($a, $b) => strcmp($b['_ts'], $a['_ts']));
        $itens = array_slice($itens, 0, self::MAXIMO);

        // Troca o id de quem fez pela pessoa completa (uma só consulta para todos)
        $ids     = array_values(array_unique(array_map(fn ($i) => $i['_quem'], $itens)));
        $pessoas = $ids === [] ? collect() : User::with('foto')->whereIn('id', $ids)->get()->keyBy('id');

        $saida = [];
        foreach ($itens as $i) {
            $quem = $pessoas->get($i['_quem']);
            if ($quem === null) {
                continue;   // conta apagada entretanto
            }
            $saida[] = [
                'id'       => $i['id'],
                'tipo'     => $i['tipo'],                               // ep | com | convite | amigo
                'em'       => date(DATE_ATOM, strtotime($i['_ts'])),    // ISO 8601, com fuso
                'quem'     => $this->userJson($quem),
                'texto'    => $quem->nome . ' ' . $i['texto'],          // "Andreia viu os episódios 12 a 14"
                'detalhe'  => $i['detalhe'],                            // nome da série, comentário…
                'serie'    => $i['serie'],                              // slug, ou null
                'episodio' => $i['episodio'],                           // número, ou null
                'juntos'   => $i['juntos'] ?? true,                     // ep: série vista contigo?
                'relacao'  => $i['relacao'] ?? null,                    // ep: par | amigo
            ];
        }

        $this->json(['ok' => true, 'notificacoes' => $saida]);
    }

    // Episódios marcados pelo teu par, pelos amigos e por quem vê séries contigo, juntos por sessão.
    // Cada linha diz se a série é vista contigo (juntos) e quem é a pessoa (relacao: par | amigo);
    // os filtros do 🔔 são escolhidos na app. Privacidade: quem tem "só o que vemos juntos" só
    // aparece nas séries que vê contigo (como no perfil).
    private function episodios(User $user, string $desde): array
    {
        // Círculo: par + amigos + quem vê alguma série contigo
        $parId   = $user->par_id ? (int) $user->par_id : null;
        $amigos  = Capsule::table('amizades')->where('user_id', $user->id)->pluck('amigo_id')->map(fn ($i) => (int) $i)->all();
        $comigo  = Capsule::table('series_juntos')->where('user_id', $user->id)->pluck('com_id')->map(fn ($i) => (int) $i)->all();
        $ids     = array_values(array_unique(array_filter(array_merge([$parId], $amigos, $comigo))));
        if ($ids === []) {
            return [];
        }

        // Quem te deixa ver tudo (sem "só juntos" ou é o teu par)
        $veTudo = User::whereIn('id', $ids)->get()->mapWithKeys(fn ($u) => [(int) $u->id => $u->veTudo($user)])->all();

        $linhas = Capsule::table('vistos')
            ->join('episodios', 'episodios.id', '=', 'vistos.episodio_id')
            ->join('series', 'series.id', '=', 'episodios.serie_id')
            // Vês esta série com quem marcou? (left join: as outras séries também vêm)
            ->leftJoin('series_juntos', function ($j) use ($user) {
                $j->on('series_juntos.serie_id', '=', 'episodios.serie_id')
                  ->on('series_juntos.com_id', '=', 'vistos.user_id')
                  ->where('series_juntos.user_id', '=', $user->id);
            })
            ->whereIn('vistos.user_id', $ids)
            ->where('vistos.visto_em', '>=', $desde)
            ->orderBy('vistos.user_id')->orderBy('episodios.serie_id')
            ->orderBy('vistos.visto_em')->orderBy('episodios.numero')
            ->limit(5000)   // travão de segurança
            ->get(['vistos.user_id', 'episodios.serie_id', 'series.slug', 'series.nome', 'episodios.numero', 'vistos.visto_em',
                   'series_juntos.com_id AS juntos']);

        // Agrupa: mesma pessoa, mesma série e sem pausas grandes entre marcações
        $grupos = [];
        $atual  = null;
        foreach ($linhas as $l) {
            $uid    = (int) $l->user_id;
            $juntos = $l->juntos !== null;
            if (!$juntos && !($veTudo[$uid] ?? false)) {
                continue;   // "só juntos": as outras séries dele são privadas
            }
            $ts = strtotime($l->visto_em);
            $continua = $atual !== null
                && $atual['user'] === $uid
                && $atual['serie_id'] === (int) $l->serie_id
                && $ts - $atual['fim'] <= self::PAUSA_SEG;
            if (!$continua) {
                if ($atual !== null) {
                    $grupos[] = $atual;
                }
                $atual = ['user' => $uid, 'serie_id' => (int) $l->serie_id, 'slug' => $l->slug, 'nome' => $l->nome,
                          'juntos' => $juntos, 'numeros' => [], 'fim' => $ts, 'fimTexto' => $l->visto_em];
            }
            $atual['numeros'][] = (int) $l->numero;
            $atual['fim'] = $ts;
            $atual['fimTexto'] = $l->visto_em;
        }
        if ($atual !== null) {
            $grupos[] = $atual;
        }

        return array_map(function ($g) use ($parId) {
            $numeros = array_values(array_unique($g['numeros']));
            sort($numeros);
            $n = count($numeros);
            $seguidos = $numeros[$n - 1] - $numeros[0] === $n - 1;   // 12, 13, 14 → "12 a 14"

            if ($n === 1) {
                $texto = 'viu o episódio ' . $numeros[0];
            } elseif ($seguidos) {
                $texto = 'viu os episódios ' . $numeros[0] . ' a ' . $numeros[$n - 1];
            } else {
                $texto = 'viu ' . $n . ' episódios';
            }

            return [
                'id'       => 'ep-' . $g['user'] . '-' . $g['serie_id'] . '-' . $g['fim'],
                'tipo'     => 'ep',
                '_ts'      => $g['fimTexto'],
                '_quem'    => $g['user'],
                'texto'    => $texto,
                'detalhe'  => $g['nome'],
                'serie'    => $g['slug'],
                'episodio' => $numeros[$n - 1],   // abre no último que viu
                'juntos'   => $g['juntos'],                                   // série vista contigo?
                'relacao'  => $g['user'] === $parId ? 'par' : 'amigo',
            ];
        }, $grupos);
    }

    // Comentários de outras pessoas nas séries que vêem contigo
    private function comentarios(User $user, string $desde): array
    {
        $linhas = Capsule::table('comentarios')
            ->join('episodios', 'episodios.id', '=', 'comentarios.episodio_id')
            ->join('series', 'series.id', '=', 'episodios.serie_id')
            ->join('series_juntos', function ($j) use ($user) {
                $j->on('series_juntos.serie_id', '=', 'episodios.serie_id')
                  ->on('series_juntos.com_id', '=', 'comentarios.user_id')
                  ->where('series_juntos.user_id', '=', $user->id);
            })
            ->where('comentarios.criado_em', '>=', $desde)
            ->orderBy('comentarios.criado_em', 'desc')
            ->limit(self::MAXIMO)
            ->get(['comentarios.id', 'comentarios.user_id', 'comentarios.texto', 'comentarios.criado_em',
                   'episodios.numero', 'series.slug', 'series.nome']);

        return $linhas->map(function ($c) {
            // Pedaço do comentário (o resto vê-se na série)
            $texto = mb_strlen($c->texto) > 80 ? mb_substr($c->texto, 0, 79) . '…' : $c->texto;
            return [
                'id'       => 'com-' . $c->id,
                'tipo'     => 'com',
                '_ts'      => $c->criado_em,
                '_quem'    => (int) $c->user_id,
                'texto'    => 'comentou o episódio ' . (int) $c->numero,
                'detalhe'  => $c->nome . ' · "' . $texto . '"',
                'serie'    => $c->slug,
                'episodio' => (int) $c->numero,
            ];
        })->all();
    }

    // Convites "Quero ver contigo" que ainda não respondeste
    private function convites(User $user): array
    {
        $linhas = Capsule::table('convites_serie')
            ->join('series', 'series.id', '=', 'convites_serie.serie_id')
            ->where('convites_serie.para_id', $user->id)
            ->get(['convites_serie.serie_id', 'convites_serie.de_id', 'convites_serie.criado_em', 'series.nome']);

        return $linhas->map(fn ($c) => [
            'id'       => 'convite-' . $c->serie_id . '-' . $c->de_id,
            'tipo'     => 'convite',
            '_ts'      => $c->criado_em,
            '_quem'    => (int) $c->de_id,
            'texto'    => 'quer ver ' . $c->nome . ' contigo',
            'detalhe'  => 'Aceita ou recusa no Início',
            'serie'    => null,   // ainda não está na tua biblioteca
            'episodio' => null,
        ])->all();
    }

    // Pedidos de amizade por responder
    private function pedidos(User $user): array
    {
        $linhas = Capsule::table('pedidos_amizade')
            ->where('para_id', $user->id)
            ->get(['de_id', 'criado_em']);

        return $linhas->map(fn ($p) => [
            'id'       => 'amigo-' . $p->de_id,
            'tipo'     => 'amigo',
            '_ts'      => $p->criado_em,
            '_quem'    => (int) $p->de_id,
            'texto'    => 'quer ser teu amigo',
            'detalhe'  => 'Responde em Amigos',
            'serie'    => null,
            'episodio' => null,
        ])->all();
    }
}
