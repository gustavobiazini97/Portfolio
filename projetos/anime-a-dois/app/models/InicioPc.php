<?php
// Blocos do Início no computador (só aparecem a partir de 1280 px; ver home/index.php e app.css):
//   tu()     — coluna da esquerda: esta semana, dias seguidos, próxima medalha, retrospetiva do mês
//   par()    — meio: quem espera por quem, números a dois, última vez juntos e marcos do dia
//   amigos() — coluna da direita: o que os amigos estão a ver (últimos 7 dias)
// Tudo calculado a partir de vistos/series_juntos, sem tabelas novas.

use Illuminate\Database\Capsule\Manager as Capsule;

class InicioPc
{
    private const MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    private const MARCOS = [50, 100, 250, 500, 1000, 2000, 3000];   // episódios vistos pelos dois que merecem 🎉
    private const ESPERAS = 5;          // linhas em "Quem espera por quem"
    private const AMIGOS_DIAS = 7;      // "estão a ver" = marcaram nos últimos 7 dias
    private const AMIGOS_SERIES = 6;    // capas na fila dos amigos

    // ---------- Esquerda: tu ----------

    // semana (episódios, horas), seguidos (dias até hoje/ontem), recorde, medalha (a mais perto) e retro (ano, mes, nome)
    public static function tu(User $u): array
    {
        // Esta semana (de segunda até agora): episódios e minutos
        $segunda = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $semana = Capsule::table('vistos')
            ->join('episodios', 'episodios.id', '=', 'vistos.episodio_id')
            ->join('series', 'series.id', '=', 'episodios.serie_id')
            ->where('vistos.user_id', $u->id)
            ->where('vistos.visto_em', '>=', $segunda)
            ->selectRaw('COUNT(*) AS n, COALESCE(SUM(COALESCE(series.minutos_ep, ' . Resumo::MINUTOS_EP . ')), 0) AS m')
            ->first();

        // Dias com episódios: a sequência atual (conta se o último dia foi hoje ou ontem) e o recorde
        $dias = Capsule::table('vistos')->where('user_id', $u->id)
            ->selectRaw('DISTINCT DATE(visto_em) AS d')->pluck('d')->all();
        $seguidos = self::sequenciaAtual($dias);

        // Retrospetiva do mês atual, só se já marcaste alguma coisa este mês
        $esteMes = Capsule::table('vistos')->where('user_id', $u->id)->where('visto_em', '>=', date('Y-m-01 00:00:00'))->exists();

        return [
            'episodios' => (int) $semana->n,
            'horas'     => self::horas((int) $semana->m),
            'seguidos'  => $seguidos,
            'recorde'   => Resumo::maiorSequencia($dias),
            'medalha'   => self::proximaMedalha((new Conquistas($u))->lista()),
            'retro'     => $esteMes ? ['ano' => (int) date('Y'), 'mes' => (int) date('n'), 'nome' => self::MESES[(int) date('n') - 1]] : null,
        ];
    }

    // Dias seguidos até hoje (ou até ontem, para não "perder" a sequência antes de veres o de hoje)
    public static function sequenciaAtual(array $dias): int
    {
        $tem = array_flip($dias);
        $dia = isset($tem[date('Y-m-d')]) ? strtotime('today') : strtotime('yesterday');
        $n = 0;
        while (isset($tem[date('Y-m-d', $dia)])) {
            $n++;
            $dia = strtotime('-1 day', $dia);
        }
        return $n;
    }

    // A medalha por obter mais perto (maior % já feita); null se já tens todas
    public static function proximaMedalha(array $lista): ?array
    {
        $melhor = null;
        foreach ($lista as $m) {
            if ($m['obtida'] || $m['meta'] <= 0) {
                continue;
            }
            $pct = $m['atual'] / $m['meta'];
            if ($melhor === null || $pct > $melhor['_pct']) {
                $melhor = $m + ['_pct' => $pct];
            }
        }
        if ($melhor === null) {
            return null;
        }
        return [
            'emoji'     => $melhor['emoji'],
            'nome'      => $melhor['nome'],
            'descricao' => $melhor['descricao'],
            'atual'     => (int) $melhor['atual'],
            'meta'      => (int) $melhor['meta'],
            'pct'       => (int) floor($melhor['_pct'] * 100),
        ];
    }

    // ---------- Meio: tu e o par ----------

    // Só para quem tem par (o controlador não chama sem par). esperas (quem vai à frente em cada série), juntos (números), ultima (última vez juntos), marco (texto ou null)
    public static function par(User $u, User $par): array
    {
        // Séries que vês com o par
        $idsJuntos = Capsule::table('series_juntos')->where('user_id', $u->id)->where('com_id', $par->id)
            ->pluck('serie_id')->map(fn ($i) => (int) $i)->all();

        // Episódios que os dois viram nessas séries, com a hora de cada um
        $linhas = $idsJuntos === [] ? [] : Capsule::table('vistos AS a')
            ->join('vistos AS b', 'b.episodio_id', '=', 'a.episodio_id')
            ->join('episodios', 'episodios.id', '=', 'a.episodio_id')
            ->join('series', 'series.id', '=', 'episodios.serie_id')
            ->where('a.user_id', $u->id)
            ->where('b.user_id', $par->id)
            ->whereIn('episodios.serie_id', $idsJuntos)
            ->get(['episodios.serie_id', 'episodios.numero', 'series.minutos_ep', 'series.total_episodios', 'a.visto_em AS ta', 'b.visto_em AS tb'])
            ->map(fn ($l) => [
                'serie'  => (int) $l->serie_id,
                'numero' => (int) $l->numero,
                'min'    => (int) ($l->minutos_ep ?: Resumo::MINUTOS_EP),
                'total'  => (int) $l->total_episodios,
                'ta'     => $l->ta,
                'tb'     => $l->tb,
            ])->all();

        $series = $idsJuntos === [] ? collect() : Serie::whereIn('id', $idsJuntos)->get()->keyBy('id');

        return [
            'esperas' => self::esperas($u, $par, $series),
            'juntos'  => self::juntos($linhas, count($idsJuntos)),
            'ultima'  => self::ultimaVez($linhas, $series),
            'marco'   => self::marco($linhas, $series, $par),
        ];
    }

    // Quem espera por quem: uma linha por série que veem juntos e ainda não acabaram os dois; mais distância primeiro
    private static function esperas(User $u, User $par, $series): array
    {
        $lista = [];
        foreach ($series as $s) {
            $eu = $s->progressoDe($u);
            $ele = $s->progressoDe($par);
            if ($eu['vistos'] >= $s->total_episodios && $ele['vistos'] >= $s->total_episodios) {
                continue;   // acabaram os dois: já ninguém espera
            }
            $d = $eu['posicao'] - $ele['posicao'];
            $lista[] = [
                'serie' => $s,
                'dif'   => $d,
                'texto' => match (true) {
                    $d > 0  => 'Vais ' . plural($d, 'episódio', 'episódios') . ' à frente',
                    $d < 0  => $par->nome . ' espera por ti · ' . plural(-$d, 'ep.', 'eps.'),
                    default => 'Estão no mesmo episódio',
                },
            ];
        }
        usort($lista, fn ($a, $b) => abs($b['dif']) <=> abs($a['dif']));
        return array_slice($lista, 0, self::ESPERAS);
    }

    // Episódios vistos pelos dois, horas, séries juntos e desde quando (o 1.º episódio que os dois viram)
    public static function juntos(array $linhas, int $series): array
    {
        $min = array_sum(array_column($linhas, 'min'));
        $desde = null;
        foreach ($linhas as $l) {
            $t = max($l['ta'], $l['tb']);   // o episódio conta como "visto pelos dois" quando o segundo o viu
            $desde = $desde === null ? $t : min($desde, $t);
        }
        return [
            'episodios' => count($linhas),
            'horas'     => self::horas($min),
            'series'    => $series,
            'desde'     => $desde === null ? null : self::MESES[(int) date('n', strtotime($desde)) - 1] . ' de ' . date('Y', strtotime($desde)),
        ];
    }

    // Última vez juntos: o dia mais recente em que os dois marcaram o mesmo episódio nesse mesmo dia,
    // e os episódios dessa série nesse dia. null se nunca aconteceu.
    public static function ultimaVez(array $linhas, $series): ?array
    {
        $mesmoDia = array_filter($linhas, fn ($l) => substr($l['ta'], 0, 10) === substr($l['tb'], 0, 10));
        if ($mesmoDia === []) {
            return null;
        }
        // A mais recente decide o dia e a série
        usort($mesmoDia, fn ($a, $b) => strcmp(max($b['ta'], $b['tb']), max($a['ta'], $a['tb'])));
        $topo = $mesmoDia[0];
        $dia = substr($topo['ta'], 0, 10);
        $nums = array_column(array_filter($mesmoDia, fn ($l) => $l['serie'] === $topo['serie'] && substr($l['ta'], 0, 10) === $dia), 'numero');
        $serie = $series->get($topo['serie']);
        if ($serie === null) {
            return null;
        }
        $de = min($nums);
        $ate = max($nums);
        return [
            'serie'  => $serie,
            'ate'    => $ate,
            'texto'  => $serie->nomeCurto() . ' ' . ($de === $ate ? 'ep. ' . $de : $de . '–' . $ate),
            'quando' => LadoSerie::quando(strtotime(max($topo['ta'], $topo['tb']))),
        ];
    }

    // Marco de hoje (só aparece no dia): N episódios juntos, uma série acabada pelos dois, ou o aniversário
    // do primeiro episódio visto juntos. null nos outros dias.
    public static function marco(array $linhas, $series, User $par, ?string $hoje = null): ?string
    {
        $hoje ??= date('Y-m-d');
        if ($linhas === []) {
            return null;
        }
        // Quando cada episódio passou a ser "dos dois", por ordem
        $quando = array_map(fn ($l) => max($l['ta'], $l['tb']), $linhas);
        sort($quando);

        // N episódios juntos (o maior marco atingido hoje)
        foreach (array_reverse(self::MARCOS) as $n) {
            if (count($quando) >= $n && substr($quando[$n - 1], 0, 10) === $hoje) {
                return 'Hoje fazem ' . $n . ' episódios vistos juntos';
            }
        }

        // Série acabada pelos dois hoje (todos os episódios vistos pelos dois e o último foi hoje)
        $porSerie = [];
        foreach ($linhas as $l) {
            $porSerie[$l['serie']][] = max($l['ta'], $l['tb']);
        }
        foreach ($porSerie as $id => $ts) {
            $s = $series->get($id);
            if ($s && count($ts) >= $s->total_episodios && substr(max($ts), 0, 10) === $hoje) {
                return 'Acabaram ' . $s->nomeCurto() . ' juntos hoje';
            }
        }

        // Aniversário do primeiro episódio visto juntos
        $primeiro = $quando[0];
        $anos = (int) substr($hoje, 0, 4) - (int) substr($primeiro, 0, 4);
        if ($anos > 0 && substr($primeiro, 5, 5) === substr($hoje, 5, 5)) {
            return 'Fazem hoje ' . plural($anos, 'ano', 'anos') . ' a ver anime com ' . $par->nome;
        }
        return null;
    }

    // ---------- Direita: amigos ----------

    // Séries que os amigos marcaram nos últimos 7 dias (sem o par, sem quem tem "só o que vemos juntos").
    // Cada uma: serie, pessoas ([user, numero, quando]), tens (já está na tua biblioteca). Mais amigos primeiro.
    public static function amigos(User $u): array
    {
        $amigos = Amizade::amigosDe($u)->reject(fn ($a) => $a->id === $u->par_id || !$a->veTudo($u))->keyBy('id');
        if ($amigos->isEmpty()) {
            return [];
        }

        $linhas = Capsule::table('vistos')
            ->join('episodios', 'episodios.id', '=', 'vistos.episodio_id')
            ->whereIn('vistos.user_id', $amigos->keys()->all())
            ->where('vistos.visto_em', '>=', date('Y-m-d H:i:s', strtotime('-' . self::AMIGOS_DIAS . ' days')))
            ->orderBy('vistos.visto_em', 'desc')
            ->orderBy('episodios.numero', 'desc')
            ->get(['vistos.user_id', 'episodios.serie_id', 'episodios.numero', 'vistos.visto_em']);

        // O registo mais recente de cada amigo em cada série
        $porSerie = [];
        foreach ($linhas as $l) {
            $sid = (int) $l->serie_id;
            $uid = (int) $l->user_id;
            if (isset($porSerie[$sid]['pessoas'][$uid])) {
                continue;
            }
            $porSerie[$sid]['ts'] ??= $l->visto_em;
            $porSerie[$sid]['pessoas'][$uid] = ['user' => $amigos->get($uid), 'numero' => (int) $l->numero, 'quando' => LadoSerie::quando(strtotime($l->visto_em))];
        }
        if ($porSerie === []) {
            return [];
        }

        // Mais amigos primeiro; empate → a mais recente
        uasort($porSerie, fn ($a, $b) => [count($b['pessoas']), $b['ts']] <=> [count($a['pessoas']), $a['ts']]);
        $porSerie = array_slice($porSerie, 0, self::AMIGOS_SERIES, true);

        $series = Serie::whereIn('id', array_keys($porSerie))->get()->keyBy('id');
        $minhas = Capsule::table('bibliotecas')->where('user_id', $u->id)->pluck('serie_id')->map(fn ($i) => (int) $i)->flip()->all();

        $lista = [];
        foreach ($porSerie as $sid => $info) {
            if (!$series->has($sid)) {
                continue;
            }
            $lista[] = ['serie' => $series->get($sid), 'pessoas' => array_values($info['pessoas']), 'tens' => isset($minhas[$sid])];
        }
        return $lista;
    }

    // ---------- Ajudas ----------

    // "12 h" / "3,5 h"
    private static function horas(int $minutos): string
    {
        $h = $minutos / 60;
        return ($h < 10 ? str_replace('.', ',', (string) round($h, 1)) : (string) round($h)) . ' h';
    }
}
