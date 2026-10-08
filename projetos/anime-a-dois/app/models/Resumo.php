<?php
// Retrospetiva de uma pessoa num mês ou num ano (tipo "Wrapped"): episódios, horas, séries do período,
// melhor dia, hora preferida, sequência de dias, fillers, comentários e a comparação com o par.
// Só conta o que ESTA pessoa viu; o par aparece só com o número total dele (o par vê sempre tudo).

use Illuminate\Database\Capsule\Manager as Capsule;

class Resumo
{
    // Duração de um episódio quando a série não a tem (as do Naruto)
    const MINUTOS_EP = 23;

    const MESES = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
                   'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

    const DIAS_SEMANA = [1 => 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado', 'domingo'];

    // $mes = null → ano inteiro
    public function __construct(private User $user, private int $ano, private ?int $mes = null)
    {
        if ($ano < 2000 || $ano > 2100 || ($mes !== null && ($mes < 1 || $mes > 12))) {
            throw new InvalidArgumentException('Esse período não existe.');
        }
    }

    // Nome do período: "outubro de 2026" ou "2026"
    public function nome(): string
    {
        return $this->mes === null ? (string) $this->ano : self::MESES[$this->mes] . ' de ' . $this->ano;
    }

    // [início, fim) do período (e do anterior, para comparar)
    private function limites(int $desvio = 0): array
    {
        if ($this->mes === null) {
            $ini = new DateTime(($this->ano - $desvio) . '-01-01 00:00:00');
            $fim = (clone $ini)->modify('+1 year');
        } else {
            $ini = (new DateTime(sprintf('%04d-%02d-01 00:00:00', $this->ano, $this->mes)))->modify("-$desvio month");
            $fim = (clone $ini)->modify('+1 month');
        }
        return [$ini->format('Y-m-d H:i:s'), $fim->format('Y-m-d H:i:s')];
    }

    // Episódios vistos por uma pessoa num intervalo, com os dados da série
    private static function vistos(User $u, string $ini, string $fim)
    {
        return Capsule::table('vistos')
            ->join('episodios', 'episodios.id', '=', 'vistos.episodio_id')
            ->join('series', 'series.id', '=', 'episodios.serie_id')
            ->where('vistos.user_id', $u->id)
            ->where('vistos.visto_em', '>=', $ini)->where('vistos.visto_em', '<', $fim)
            ->orderBy('vistos.visto_em')
            ->get(['vistos.visto_em', 'episodios.numero', 'episodios.filler', 'episodios.recap', 'episodios.titulo',
                   'episodios.serie_id', 'series.minutos_ep']);
    }

    // Tudo o que os ecrãs da retrospetiva precisam (as séries vêm como Model, a API converte-as)
    public function calcular(): array
    {
        [$ini, $fim] = $this->limites();
        $v = self::vistos($this->user, $ini, $fim);
        $total = $v->count();

        // Minutos vistos (a duração de cada série, ou 23 min)
        $minutos = $v->sum(fn ($x) => (int) ($x->minutos_ep ?: self::MINUTOS_EP));

        // Por série: as 3 mais vistas
        $porSerie = $v->groupBy('serie_id')->map->count()->sortDesc();
        $series = Serie::whereIn('id', $porSerie->keys()->all())->get()->keyBy('id');
        $top = [];
        foreach ($porSerie->take(3) as $id => $n) {
            if (isset($series[$id])) {
                $top[] = ['serie' => $series[$id], 'episodios' => (int) $n];
            }
        }

        // Por dia: o melhor dia, quantos dias com episódios e a maior sequência de dias seguidos
        $porDia = $v->groupBy(fn ($x) => substr($x->visto_em, 0, 10))->map->count();
        $melhorDia = null;
        if ($porDia->isNotEmpty()) {
            $dia = $porDia->sortDesc()->keys()->first();
            $melhorDia = ['data' => $dia, 'episodios' => (int) $porDia[$dia], 'texto' => self::dataBonita($dia)];
        }

        // Hora preferida (a hora com mais episódios) e o "momento" do dia
        $porHora = $v->groupBy(fn ($x) => (int) substr($x->visto_em, 11, 2))->map->count();
        $hora = $porHora->isEmpty() ? null : (int) $porHora->sortDesc()->keys()->first();

        // Dia da semana preferido
        $porSemana = $v->groupBy(fn ($x) => (int) date('N', strtotime($x->visto_em)))->map->count();
        $diaSemana = $porSemana->isEmpty() ? null : self::DIAS_SEMANA[(int) $porSemana->sortDesc()->keys()->first()];

        // Comentários que escreveste no período
        $comentarios = Capsule::table('comentarios')->where('user_id', $this->user->id)
            ->where('criado_em', '>=', $ini)->where('criado_em', '<', $fim)->count();

        // O período anterior (para "+30% do que em setembro")
        [$aIni, $aFim] = $this->limites(1);
        $anterior = Capsule::table('vistos')->where('user_id', $this->user->id)
            ->where('visto_em', '>=', $aIni)->where('visto_em', '<', $aFim)->count();

        // O par: só o total dele no mesmo período
        $par = $this->user->parceiro();
        $doPar = $par ? Capsule::table('vistos')->where('user_id', $par->id)
            ->where('visto_em', '>=', $ini)->where('visto_em', '<', $fim)->count() : null;

        // Séries acabadas no período: viste todos os episódios e o último foi marcado neste período
        $acabadas = [];
        foreach ($series as $s) {
            $linha = Capsule::table('vistos')->join('episodios', 'episodios.id', '=', 'vistos.episodio_id')
                ->where('vistos.user_id', $this->user->id)->where('episodios.serie_id', $s->id)
                ->selectRaw('COUNT(*) AS n, MAX(vistos.visto_em) AS ultimo')->first();
            if ((int) $linha->n >= (int) $s->total_episodios && $s->total_episodios > 0 && $linha->ultimo >= $ini && $linha->ultimo < $fim) {
                $acabadas[] = $s;
            }
        }

        // Ano: episódios por mês (para o gráfico) e o mês mais forte
        $porMes = null;
        $melhorMes = null;
        if ($this->mes === null) {
            $contas = $v->groupBy(fn ($x) => (int) substr($x->visto_em, 5, 2))->map->count();
            $porMes = [];
            for ($m = 1; $m <= 12; $m++) {
                $porMes[] = ['mes' => $m, 'nome' => mb_substr(self::MESES[$m], 0, 3), 'episodios' => (int) ($contas[$m] ?? 0)];
            }
            if ($contas->isNotEmpty()) {
                $m = (int) $contas->sortDesc()->keys()->first();
                $melhorMes = ['mes' => $m, 'nome' => self::MESES[$m], 'episodios' => (int) $contas[$m]];
            }
        }

        $primeiro = $v->first();

        return [
            'periodo'      => ['ano' => $this->ano, 'mes' => $this->mes, 'nome' => $this->nome(), 'tipo' => $this->mes === null ? 'ano' : 'mes'],
            'episodios'    => $total,
            'minutos'      => $minutos,
            'horas'        => self::horas($minutos),
            'fillers'      => $v->filter(fn ($x) => $x->filler || $x->recap)->count(),
            'diasAtivos'   => $porDia->count(),
            'sequencia'    => self::maiorSequencia($porDia->keys()->all()),
            'melhorDia'    => $melhorDia,
            'hora'         => $hora,
            'momento'      => $hora === null ? null : self::momento($hora),
            'diaSemana'    => $diaSemana,
            'comentarios'  => $comentarios,
            'top'          => $top,
            'acabadas'     => $acabadas,
            'anterior'     => ['episodios' => $anterior, 'variacao' => $anterior > 0 ? (int) round(($total - $anterior) / $anterior * 100) : null],
            'par'          => $par ? ['user' => $par, 'episodios' => $doPar] : null,
            'porMes'       => $porMes,
            'melhorMes'    => $melhorMes,
            'primeiro'     => $primeiro ? ['serie' => $series[$primeiro->serie_id]?->nomeCurto(), 'numero' => (int) $primeiro->numero,
                                           'titulo' => $primeiro->titulo, 'data' => self::dataBonita(substr($primeiro->visto_em, 0, 10))] : null,
        ];
    }

    // Períodos com episódios desta pessoa: meses (mais recentes primeiro) e anos
    public static function periodos(User $u): array
    {
        $linhas = Capsule::table('vistos')->where('user_id', $u->id)
            ->selectRaw("DATE_FORMAT(visto_em, '%Y-%m') AS m, COUNT(*) AS n")
            ->groupBy('m')->orderByDesc('m')->get();

        $meses = [];
        $anos = [];
        foreach ($linhas as $l) {
            [$a, $m] = array_map('intval', explode('-', $l->m));
            $meses[] = ['ano' => $a, 'mes' => $m, 'nome' => self::MESES[$m] . ' de ' . $a, 'episodios' => (int) $l->n];
            $anos[$a] = ($anos[$a] ?? 0) + (int) $l->n;
        }
        krsort($anos);
        return [
            'meses' => $meses,
            'anos'  => array_map(fn ($a, $n) => ['ano' => $a, 'episodios' => $n], array_keys($anos), $anos),
        ];
    }

    // ---------- Ajudas ----------

    // Maior número de dias seguidos numa lista de datas 'Y-m-d'
    public static function maiorSequencia(array $datas): int
    {
        sort($datas);
        $melhor = 0;
        $atual = 0;
        $antes = null;
        foreach ($datas as $d) {
            $t = strtotime($d);
            $atual = ($antes !== null && $t - $antes <= 90000) ? $atual + 1 : 1;   // 25 h de folga (mudança de hora)
            $melhor = max($melhor, $atual);
            $antes = $t;
        }
        return $melhor;
    }

    // "12 h" / "3,5 h"
    private static function horas(int $minutos): string
    {
        $h = $minutos / 60;
        return $h < 10 ? str_replace('.', ',', (string) round($h, 1)) . ' h' : round($h) . ' h';
    }

    // "sábado, 4 de outubro"
    private static function dataBonita(string $data): string
    {
        $t = strtotime($data);
        return self::DIAS_SEMANA[(int) date('N', $t)] . ', ' . (int) date('j', $t) . ' de ' . self::MESES[(int) date('n', $t)];
    }

    // Momento do dia para a hora preferida
    private static function momento(int $hora): string
    {
        return match (true) {
            $hora < 6  => 'madrugada',
            $hora < 12 => 'manhã',
            $hora < 19 => 'tarde',
            default    => 'noite',
        };
    }
}
