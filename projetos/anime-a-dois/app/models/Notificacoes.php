<?php
// O sininho (🔔), na app e no site: o que aconteceu à tua volta nos últimos 30 dias, calculado
// a partir dos dados que já existem (não há tabela própria):
//   - episódios marcados pelo par, amigos e quem vê séries contigo (marcações seguidas juntam-se numa linha);
//     cada um diz se a série é vista contigo (juntos) e quem é a pessoa (relacao: par | amigo)
//   - comentários nas séries que vês com alguém
//   - convites "Quero ver contigo" por responder
//   - pedidos de amizade por responder
//   - amizades novas (pedido aceite ou conta criada por convite), para os dois lados
// Quem tem "só o que vemos juntos" só aparece nas séries em comum.
// O "já vi" e os filtros ficam em quem mostra a lista (a app no telemóvel; o site em cookies).

use Illuminate\Database\Capsule\Manager as Capsule;

class Notificacoes
{
    public const DIAS      = 30;            // quanto para trás se olha
    public const MAXIMO    = 60;            // linhas devolvidas
    private const PAUSA_SEG = 3 * 3600;     // marcações com mais de 3 h de intervalo são "sessões" diferentes

    // Filtros (o que aparece) e o valor por omissão de cada um — iguais aos da app
    public const FILTROS = [
        'juntos'       => ['Séries que vês juntos', true],
        'outrasPar'    => ['Outras séries do teu par', true],
        'outrasAmigos' => ['Outras séries dos amigos', false],
        'comentarios'  => ['Comentários', true],
        'convites'     => ['Convites e pedidos de amizade', true],
    ];

    // A lista, mais recentes primeiro. Cada item: id, tipo (ep | com | convite | amigo), ts ('Y-m-d H:i:s'),
    // quem (User), texto ("Andreia viu os episódios 12 a 14"), detalhe, serie (slug ou null), episodio (número ou null),
    // juntos (episódios: série vista contigo?) e relacao (episódios: par | amigo)
    public static function lista(User $user): array
    {
        $desde = date('Y-m-d H:i:s', strtotime('-' . self::DIAS . ' days'));

        $itens = array_merge(
            self::episodios($user, $desde),
            self::comentarios($user, $desde),
            self::convites($user),
            self::pedidos($user),
            self::amizades($user, $desde)
        );

        // Mais recentes primeiro (datas 'Y-m-d H:i:s' comparam-se como texto)
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
                'tipo'     => $i['tipo'],
                'ts'       => $i['_ts'],
                'quem'     => $quem,
                'texto'    => $quem->nome . ' ' . $i['texto'],
                'detalhe'  => $i['detalhe'],
                'serie'    => $i['serie'],
                'episodio' => $i['episodio'],
                'juntos'   => $i['juntos'] ?? true,
                'relacao'  => $i['relacao'] ?? null,
                'quantos'  => $i['quantos'] ?? 1,   // episódios vistos nesta sessão (1 nos outros tipos)
            ];
        }
        return $saida;
    }

    // Resumo para o topo do Início, a partir das notificações por ver (mais recentes primeiro); null = nada de novo.
    //   só episódios de uma pessoa → "Andreia viu 3 episódios desde ontem"
    //   uma só novidade de outro tipo → o texto dela ("nês quer ser teu amigo")
    //   o resto → "4 novidades"
    // (a app faz o mesmo em resumoDoSino, em lib/ecras/notificacoes.dart)
    public static function resumo(array $novas): ?string
    {
        if ($novas === []) {
            return null;
        }

        $quem = $novas[0]['quem']->id;
        $soEpisodiosDeUm = true;
        foreach ($novas as $n) {
            if ($n['tipo'] !== 'ep' || $n['quem']->id !== $quem) {
                $soEpisodiosDeUm = false;
                break;
            }
        }

        if ($soEpisodiosDeUm) {
            $total = array_sum(array_map(fn ($n) => (int) ($n['quantos'] ?? 1), $novas));
            $desde = self::desde($novas[count($novas) - 1]['ts']);   // a mais antiga é a última da lista
            return $novas[0]['quem']->nome . ' viu ' . $total . ' ' . ($total === 1 ? 'episódio' : 'episódios') . ' ' . $desde;
        }

        if (count($novas) === 1) {
            return $novas[0]['texto'];
        }
        return count($novas) . ' novidades';
    }

    // "hoje", "desde ontem", "desde sábado", "desde 17 out" (a partir de 'Y-m-d H:i:s')
    private static function desde(string $ts): string
    {
        $dia       = strtotime(substr($ts, 0, 10));
        $diasAtras = (int) round((strtotime(date('Y-m-d')) - $dia) / 86400);
        $semana    = ['segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado', 'domingo'];
        $meses     = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

        if ($diasAtras <= 0) {
            return 'hoje';
        }
        if ($diasAtras === 1) {
            return 'desde ontem';
        }
        if ($diasAtras < 7) {
            return 'desde ' . $semana[(int) date('N', $dia) - 1];
        }
        $data = (int) date('j', $dia) . ' ' . $meses[(int) date('n', $dia) - 1];
        return 'desde ' . (date('Y', $dia) === date('Y') ? $data : $data . ' ' . date('Y', $dia));
    }

    // Esta notificação passa nos filtros? ($filtros: nome → bool, como em FILTROS)
    public static function deixa(array $n, array $filtros): bool
    {
        return match ($n['tipo']) {
            'ep'              => $n['juntos'] ? $filtros['juntos'] : ($n['relacao'] === 'par' ? $filtros['outrasPar'] : $filtros['outrasAmigos']),
            'com'             => $filtros['comentarios'],
            'convite', 'amigo' => $filtros['convites'],
            default           => true,
        };
    }

    // Episódios marcados pelo teu par, pelos amigos e por quem vê séries contigo, juntos por sessão.
    // Cada linha diz se a série é vista contigo (juntos) e quem é a pessoa (relacao: par | amigo);
    // os filtros do 🔔 são escolhidos na app. Privacidade: quem tem "só o que vemos juntos" só
    // aparece nas séries que vê contigo (como no perfil).
    private static function episodios(User $user, string $desde): array
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
                'quantos'  => $n,                                             // para o resumo do topo do Início
            ];
        }, $grupos);
    }

    // Comentários de outras pessoas nas séries que vêem contigo
    private static function comentarios(User $user, string $desde): array
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
    private static function convites(User $user): array
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
    private static function pedidos(User $user): array
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

    // Amizades novas (pedido aceite ou conta criada pelo teu convite): "nês e tu são agora amigos".
    // A tabela tem uma linha por sentido, por isso cada um vê a sua. O par não conta (não é uma amizade).
    private static function amizades(User $user, string $desde): array
    {
        $linhas = Capsule::table('amizades')
            ->where('user_id', $user->id)
            ->where('desde', '>=', $desde)
            ->when($user->par_id, fn ($q) => $q->where('amigo_id', '!=', $user->par_id))
            ->get(['amigo_id', 'desde']);

        return $linhas->map(fn ($a) => [
            'id'       => 'amizade-' . $a->amigo_id,
            'tipo'     => 'amigo',            // mesmo filtro e destino dos pedidos (Amigos)
            '_ts'      => $a->desde,
            '_quem'    => (int) $a->amigo_id,
            'texto'    => 'e tu são agora amigos',
            'detalhe'  => 'Já podem ver o perfil um do outro',
            'serie'    => null,
            'episodio' => null,
        ])->all();
    }
}
