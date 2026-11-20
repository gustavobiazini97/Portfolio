<?php
// Colunas laterais da página da série (só em ecrãs largos): o que tem acontecido nesta série
// entre ti e quem a vê contigo — sessões de episódios marcados e os últimos comentários.
// Calculado a partir dos dados que já existem (vistos e comentarios), sem tabela própria.

use Illuminate\Database\Capsule\Manager as Capsule;

class LadoSerie
{
    private const PAUSA_SEG   = 3 * 3600;   // marcações com mais de 3 h de intervalo são sessões diferentes (como no 🔔)
    private const SESSOES     = 8;          // quantas sessões aparecem
    private const COMENTARIOS = 5;          // quantos comentários aparecem
    private const LINHAS      = 400;        // registos lidos da BD para montar as sessões

    private const MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

    // Sessões de episódios marcados, mais recentes primeiro.
    // Cada uma: quem (User), cor ('tu' | 'par'), de, ate, quantos, ts, texto ("viu os eps. 70–77"), quando ("ontem, 22:10")
    public static function registos(Serie $serie, User $tu, $companheiros): array
    {
        $pessoas = self::pessoas($tu, $companheiros);

        $linhas = Capsule::table('vistos')
            ->join('episodios', 'episodios.id', '=', 'vistos.episodio_id')
            ->where('episodios.serie_id', $serie->id)
            ->whereIn('vistos.user_id', array_keys($pessoas))
            ->orderBy('vistos.visto_em', 'desc')
            ->limit(self::LINHAS)
            ->get(['vistos.user_id', 'episodios.numero', 'vistos.visto_em']);

        // Agrupa por pessoa: cada pessoa tem as suas sessões (vêem juntos ao mesmo tempo, por isso
        // misturar as duas pessoas partia as sessões a meio)
        $sessoes = [];
        $aberta  = [];   // user_id → índice da sessão aberta em $sessoes
        foreach ($linhas as $l) {
            $uid = (int) $l->user_id;
            $ts  = strtotime($l->visto_em);
            $n   = (int) $l->numero;
            $i   = $aberta[$uid] ?? null;
            // Continua a sessão se o registo anterior (mais recente) desta pessoa foi há menos de 3 h
            if ($i !== null && $sessoes[$i]['_ultimo'] - $ts <= self::PAUSA_SEG) {
                $sessoes[$i]['de'] = min($sessoes[$i]['de'], $n);
                $sessoes[$i]['ate'] = max($sessoes[$i]['ate'], $n);
                $sessoes[$i]['quantos']++;
                $sessoes[$i]['_ultimo'] = $ts;
                continue;
            }
            $sessoes[] = ['_uid' => $uid, 'de' => $n, 'ate' => $n, 'quantos' => 1, 'ts' => $ts, '_ultimo' => $ts];
            $aberta[$uid] = count($sessoes) - 1;
        }

        // As mais recentes primeiro (a ordem de criação já o é por pessoa, não no total)
        usort($sessoes, fn ($a, $b) => $b['ts'] <=> $a['ts']);
        $sessoes = array_slice($sessoes, 0, self::SESSOES);

        return array_map(function ($s) use ($pessoas) {
            [$user, $cor] = $pessoas[$s['_uid']];
            return [
                'quem'    => $user,
                'cor'     => $cor,
                'de'      => $s['de'],
                'ate'     => $s['ate'],
                'quantos' => $s['quantos'],
                'texto'   => ($cor === 'tu' ? 'Viste ' : $user->nome . ' viu ') . self::episodios($s['de'], $s['ate'], $s['quantos']),
                'quando'  => self::quando($s['ts']),
            ];
        }, $sessoes);
    }

    // Últimos comentários desta série (teus e de quem a vê contigo), mais recentes primeiro.
    // Cada um: quem (User), cor, numero (episódio), texto, quando
    public static function comentarios(Serie $serie, User $tu, $companheiros): array
    {
        $pessoas = self::pessoas($tu, $companheiros);

        $linhas = Capsule::table('comentarios')
            ->join('episodios', 'episodios.id', '=', 'comentarios.episodio_id')
            ->where('episodios.serie_id', $serie->id)
            ->whereIn('comentarios.user_id', array_keys($pessoas))
            ->orderBy('comentarios.criado_em', 'desc')
            ->limit(self::COMENTARIOS)
            ->get(['comentarios.user_id', 'comentarios.texto', 'comentarios.criado_em', 'episodios.numero']);

        $lista = [];
        foreach ($linhas as $l) {
            [$user, $cor] = $pessoas[(int) $l->user_id];
            $lista[] = [
                'quem'   => $user,
                'cor'    => $cor,
                'numero' => (int) $l->numero,
                'texto'  => $l->texto,
                'quando' => self::quando(strtotime($l->criado_em)),
            ];
        }
        return $lista;
    }

    // "Estás 73 episódios à frente da Andreia" — um por companheiro, pelo episódio mais avançado de cada um
    public static function distancias(array $meu, $companheiros, array $deles): array
    {
        $frases = [];
        foreach (collect($companheiros)->values() as $i => $c) {
            $d = $meu['posicao'] - $deles[$i]['posicao'];
            $frases[] = match (true) {
                $d > 0  => 'Estás ' . plural($d, 'episódio', 'episódios') . ' à frente de ' . $c->nome,
                $d < 0  => $c->nome . ' está ' . plural(-$d, 'episódio', 'episódios') . ' à tua frente',
                default => 'Tu e ' . $c->nome . ' estão no mesmo episódio',
            };
        }
        return $frases;
    }

    // Arco em que vais (o do teu próximo episódio), a partir dos arcos já calculados pelas Estatisticas; null se não houver
    public static function arcoAtual(array $arcos, array $meu, int $total): ?array
    {
        $proximo = min($meu['posicao'] + 1, $total);
        foreach ($arcos as $a) {
            if ($proximo >= $a['de'] && $proximo <= $a['ate']) {
                return $a;
            }
        }
        return null;
    }

    // ---------- Ajudas ----------

    // user_id → [User, 'tu' | 'par']
    private static function pessoas(User $tu, $companheiros): array
    {
        $pessoas = [$tu->id => [$tu, 'tu']];
        foreach ($companheiros as $c) {
            $pessoas[$c->id] = [$c, 'par'];
        }
        return $pessoas;
    }

    // "o ep. 4" · "os eps. 70–77" · "5 episódios (12–30)" (quando há buracos pelo meio)
    private static function episodios(int $de, int $ate, int $quantos): string
    {
        if ($quantos === 1) {
            return 'o ep. ' . $de;
        }
        if ($ate - $de + 1 === $quantos) {
            return 'os eps. ' . $de . '–' . $ate;
        }
        return $quantos . ' episódios (' . $de . '–' . $ate . ')';
    }

    // "hoje, 22:10" · "ontem, 22:10" · "sexta, 22:10" (até 6 dias) · "12 out" · "12 out 2025" (outro ano) — como na app
    public static function quando(int $ts): string
    {
        $hora = date('H:i', $ts);
        $dias = (int) ((strtotime('today') - strtotime(date('Y-m-d', $ts))) / 86400);
        if ($dias <= 0) return 'hoje, ' . $hora;
        if ($dias === 1) return 'ontem, ' . $hora;
        if ($dias < 7) {
            $semana = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
            return $semana[(int) date('w', $ts)] . ', ' . $hora;
        }
        $texto = (int) date('j', $ts) . ' ' . self::MESES[(int) date('n', $ts) - 1];
        return date('Y', $ts) === date('Y') ? $texto : $texto . ' ' . date('Y', $ts);
    }
}
