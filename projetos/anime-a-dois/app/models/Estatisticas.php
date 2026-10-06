<?php
// Números da página de Estatísticas de uma série, para os dois.

use Illuminate\Database\Capsule\Manager as Capsule;

class Estatisticas
{
    // Duração de um episódio, em minutos, quando a série não a tem (as do Naruto)
    const MINUTOS_EP = 23;

    // Quantas semanas mostra o gráfico do ritmo
    const SEMANAS = 6;

    // $companheiros = quem vê esta série contigo (Users); vazio se a vês sozinho
    private $companheiros;

    public function __construct(private Serie $serie, private User $tu, $companheiros = [])
    {
        $this->companheiros = collect($companheiros)->values();
    }

    // Episódios (número + filler) vistos por uma pessoa nesta série, com a data
    private function vistosDe(?User $u)
    {
        if ($u === null) {
            return collect();
        }
        return Capsule::table('vistos')
            ->join('episodios', 'episodios.id', '=', 'vistos.episodio_id')
            ->where('vistos.user_id', $u->id)
            ->where('episodios.serie_id', $this->serie->id)
            ->get(['episodios.numero', 'episodios.filler', 'vistos.visto_em']);
    }

    // Tudo o que a view precisa, num só array
    public function calcular(): array
    {
        $vTu  = $this->vistosDe($this->tu);
        $vCom = $this->companheiros->map(fn ($c) => $this->vistosDe($c))->all();   // na ordem dos companheiros

        $pessoas = [$this->resumo($this->tu, $vTu, 'tu')];
        foreach ($this->companheiros as $i => $c) {
            $pessoas[] = $this->resumo($c, $vCom[$i], 'par');
        }

        return [
            'pessoas'    => $pessoas,
            'semanas'    => $this->ritmo($vTu, $vCom),
            'previsao'   => $this->previsao($vTu),
            'arcos'      => $this->arcos($vTu, $vCom),
            // Só os comentários teus e dos teus companheiros nesta série
            'comentarios'=> Capsule::table('comentarios')
                ->join('episodios', 'episodios.id', '=', 'comentarios.episodio_id')
                ->where('episodios.serie_id', $this->serie->id)
                ->whereIn('comentarios.user_id', array_merge([$this->tu->id], $this->companheiros->pluck('id')->all()))
                ->count(),
            'recorde'    => $this->recorde($vTu, $vCom),
        ];
    }

    // Cartão de cada pessoa: vistos, horas e fillers
    private function resumo(User $u, $vistos, string $cor): array
    {
        $n = $vistos->count();
        $horas = $n * ($this->serie->minutos_ep ?: self::MINUTOS_EP) / 60;   // a duração vem do MyAnimeList nas séries novas
        $f = $vistos->where('filler', 1)->count();
        return [
            'user'    => $u,
            'cor'     => $cor,
            'vistos'  => $n,
            'horas'   => $horas < 10 ? str_replace('.', ',', (string) round($horas, 1)) . ' h' : round($horas) . ' h',
            'fillers' => $f === 0 ? 'nenhum filler' : plural($f, 'filler visto', 'fillers vistos'),
        ];
    }

    // Episódios por semana (segunda a domingo), das mais antigas para a atual.
    // Cada semana: tu, nivelTu e, por companheiro, com[i] e nivelCom[i]
    private function ritmo($vTu, array $vCom): array
    {
        $contar = function ($vistos, string $inicio, string $fim) {
            return $vistos->filter(fn ($v) => $v->visto_em >= $inicio && $v->visto_em < $fim)->count();
        };

        $segunda = new DateTime('monday this week');
        $semanas = [];
        for ($i = self::SEMANAS - 1; $i >= 0; $i--) {
            $dataIni = (clone $segunda)->modify("-$i week");
            $ini = $dataIni->format('Y-m-d H:i:s');
            $fim = (clone $dataIni)->modify('+1 week')->format('Y-m-d H:i:s');
            $semanas[] = [
                'rotulo' => $i === 0 ? 'esta' : '-' . $i,
                'tu'     => $contar($vTu, $ini, $fim),
                'com'    => array_map(fn ($v) => $contar($v, $ini, $fim), $vCom),
            ];
        }

        // Altura de cada barra em classes de 0 a 10 (o CSS tem uma altura por classe, sem estilos inline)
        $max = max(1, ...array_map(fn ($s) => max(array_merge([$s['tu']], $s['com'])), $semanas));
        foreach ($semanas as &$s) {
            $s['nivelTu']  = (int) round($s['tu'] / $max * 10);
            $s['nivelCom'] = array_map(fn ($n) => (int) round($n / $max * 10), $s['com']);
        }
        return $semanas;
    }

    // "Ao teu ritmo acabas em ~N semanas": média das últimas 4 semanas
    private function previsao($vTu): ?string
    {
        $falta = $this->serie->total_episodios - $vTu->count();
        if ($falta <= 0) {
            return 'Já acabaste ' . $this->serie->nome . '. 🎉';
        }
        $desde = (new DateTime('monday this week'))->modify('-3 week')->format('Y-m-d H:i:s');
        $media = $vTu->filter(fn ($v) => $v->visto_em >= $desde)->count() / 4;
        if ($media < 0.25) {
            return null;   // sem ritmo suficiente para prever
        }
        $semanas = (int) ceil($falta / $media);
        return 'Ao teu ritmo acabas ' . $this->serie->nome . ' em ~' . plural($semanas, 'semana', 'semanas') . '.';
    }

    // Progresso de cada um em cada arco (percentagem 0–100 e selo).
    // Cada arco: pctTu, pctCom[i] (por companheiro) e selo: os-dois (todos acabaram), tu, par (algum companheiro, não tu) ou vazio
    private function arcos($vTu, array $vCom): array
    {
        $numeros = fn ($vistos) => $vistos->pluck('numero')->map(fn ($n) => (int) $n)->all();
        $nTu  = $numeros($vTu);
        $nCom = array_map($numeros, $vCom);
        $dentro = fn (array $nums, int $de, int $ate) => count(array_filter($nums, fn ($n) => $n >= $de && $n <= $ate));

        $lista = [];
        foreach ($this->serie->arcos() as $a) {
            $total = $a['ate'] - $a['de'] + 1;
            $t = $dentro($nTu, $a['de'], $a['ate']);
            $c = array_map(fn ($nums) => $dentro($nums, $a['de'], $a['ate']), $nCom);
            $tuAcabou   = $t === $total;
            $comAcabaram = array_filter($c, fn ($x) => $x === $total);
            $todos = $tuAcabou && count($comAcabaram) === count($c);
            $lista[] = $a + [
                'total'  => $total,
                'pctTu'  => (int) round($t / $total * 100),
                'pctCom' => array_map(fn ($x) => (int) round($x / $total * 100), $c),
                // Selo: todos acabaram (✓), só alguns (½, na cor de quem acabou) ou ninguém
                'selo'   => $todos ? 'os-dois' : ($tuAcabou ? 'tu' : ($comAcabaram !== [] ? 'par' : '')),
            ];
        }
        return $lista;
    }

    // Mais episódios marcados num só dia, e por quem
    private function recorde($vTu, array $vCom): ?array
    {
        $porDia = fn ($vistos) => $vistos->groupBy(fn ($v) => substr($v->visto_em, 0, 10))->map->count()->max() ?? 0;
        $melhor = ['n' => $porDia($vTu), 'quem' => 'tu'];
        foreach ($vCom as $i => $v) {
            $n = $porDia($v);
            if ($n > $melhor['n']) {
                $melhor = ['n' => $n, 'quem' => $this->companheiros[$i]->nome];
            }
        }
        return $melhor['n'] === 0 ? null : $melhor;
    }
}
