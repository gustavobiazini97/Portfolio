<?php
// Números da página de Estatísticas de uma série, para os dois.

use Illuminate\Database\Capsule\Manager as Capsule;

class Estatisticas
{
    // Duração média de um episódio, em minutos (abertura e encerramento incluídos)
    const MINUTOS_EP = 23;

    // Quantas semanas mostra o gráfico do ritmo
    const SEMANAS = 6;

    public function __construct(private Serie $serie, private User $tu, private ?User $par)
    {
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
        $vPar = $this->vistosDe($this->par);

        return [
            'pessoas'    => array_values(array_filter([
                $this->resumo($this->tu, $vTu, 'tu'),
                $this->par ? $this->resumo($this->par, $vPar, 'par') : null,
            ])),
            'semanas'    => $this->ritmo($vTu, $vPar),
            'previsao'   => $this->previsao($vTu),
            'arcos'      => $this->arcos($vTu, $vPar),
            'comentarios'=> Capsule::table('comentarios')
                ->join('episodios', 'episodios.id', '=', 'comentarios.episodio_id')
                ->where('episodios.serie_id', $this->serie->id)->count(),
            'recorde'    => $this->recorde($vTu, $vPar),
        ];
    }

    // Cartão de cada pessoa: vistos, horas e fillers
    private function resumo(User $u, $vistos, string $cor): array
    {
        $n = $vistos->count();
        $horas = $n * self::MINUTOS_EP / 60;
        $f = $vistos->where('filler', 1)->count();
        return [
            'user'    => $u,
            'cor'     => $cor,
            'vistos'  => $n,
            'horas'   => $horas < 10 ? str_replace('.', ',', (string) round($horas, 1)) . ' h' : round($horas) . ' h',
            'fillers' => $f === 0 ? 'nenhum filler' : plural($f, 'filler visto', 'fillers vistos'),
        ];
    }

    // Episódios por semana (segunda a domingo), das mais antigas para a atual
    private function ritmo($vTu, $vPar): array
    {
        $contar = function ($vistos, string $inicio, string $fim) {
            return $vistos->filter(fn ($v) => $v->visto_em >= $inicio && $v->visto_em < $fim)->count();
        };

        $segunda = new DateTime('monday this week');
        $semanas = [];
        for ($i = self::SEMANAS - 1; $i >= 0; $i--) {
            $ini = (clone $segunda)->modify("-$i week");
            $fim = (clone $ini)->modify('+1 week');
            $semanas[] = [
                'rotulo' => $i === 0 ? 'esta' : '-' . $i,
                'tu'     => $contar($vTu, $ini->format('Y-m-d H:i:s'), $fim->format('Y-m-d H:i:s')),
                'par'    => $contar($vPar, $ini->format('Y-m-d H:i:s'), $fim->format('Y-m-d H:i:s')),
            ];
        }

        // Altura de cada barra em classes de 0 a 10 (o CSS tem uma altura por classe, sem estilos inline)
        $max = max(1, ...array_map(fn ($s) => max($s['tu'], $s['par']), $semanas));
        foreach ($semanas as &$s) {
            $s['nivelTu']  = (int) round($s['tu'] / $max * 10);
            $s['nivelPar'] = (int) round($s['par'] / $max * 10);
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

    // Progresso de cada um em cada arco (percentagem 0–100 e selo)
    private function arcos($vTu, $vPar): array
    {
        $nTu  = $vTu->pluck('numero')->map(fn ($n) => (int) $n)->all();
        $nPar = $vPar->pluck('numero')->map(fn ($n) => (int) $n)->all();
        $dentro = fn (array $nums, int $de, int $ate) => count(array_filter($nums, fn ($n) => $n >= $de && $n <= $ate));

        $lista = [];
        foreach ($this->serie->arcos() as $a) {
            $total = $a['ate'] - $a['de'] + 1;
            $t = $dentro($nTu, $a['de'], $a['ate']);
            $p = $dentro($nPar, $a['de'], $a['ate']);
            $tuAcabou  = $t === $total;
            $parAcabou = $this->par !== null && $p === $total;
            $lista[] = $a + [
                'total'  => $total,
                'pctTu'  => (int) round($t / $total * 100),
                'pctPar' => (int) round($p / $total * 100),
                // Selo: os dois acabaram (✓), só um (½, na cor de quem acabou) ou ninguém
                'selo'   => $tuAcabou && ($parAcabou || $this->par === null) ? 'os-dois' : ($tuAcabou ? 'tu' : ($parAcabou ? 'par' : '')),
            ];
        }
        return $lista;
    }

    // Mais episódios marcados num só dia, e por quem
    private function recorde($vTu, $vPar): ?array
    {
        $porDia = fn ($vistos) => $vistos->groupBy(fn ($v) => substr($v->visto_em, 0, 10))->map->count()->max() ?? 0;
        $t = $porDia($vTu);
        $p = $porDia($vPar);
        if ($t === 0 && $p === 0) {
            return null;
        }
        return $t >= $p
            ? ['n' => $t, 'quem' => 'tu']
            : ['n' => $p, 'quem' => $this->par->nome];
    }
}
