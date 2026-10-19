<?php
// Dados do perfil de uma pessoa (o teu, o do par ou o de um amigo), na app e no site:
// números (episódios, horas, séries, acabadas, o que vêem juntos) e o resumo das medalhas.

use Illuminate\Database\Capsule\Manager as Capsule;

class Perfil
{
    // Números do perfil: episódios, horas, séries e acabadas dele + o que vêem juntos.
    // $biblioteca: os itens de Serie::biblioteca($pessoa) que se mostram (já filtrados com "só juntos").
    // Com "só juntos", os números dele contam só as séries que se veem (as que vê contigo).
    // No teu próprio perfil ($proprio) não há "juntos" contigo mesmo.
    public static function numeros(User $pessoa, User $user, array $biblioteca, bool $soJuntos, bool $proprio = false): array
    {
        // Séries visíveis e as que vêem juntos (por id)
        $visiveis = array_map(fn ($i) => (int) $i['serie']->id, $biblioteca);
        $idsJuntos = $proprio ? [] : array_values(array_map(fn ($i) => (int) $i['serie']->id,
            array_filter($biblioteca, fn ($i) => $i['serie']->verCom($user, $pessoa))));

        // Episódios e minutos vistos por ele (todos, ou só nas séries visíveis)
        $q = Capsule::table('vistos')
            ->join('episodios', 'episodios.id', '=', 'vistos.episodio_id')
            ->join('series', 'series.id', '=', 'episodios.serie_id')
            ->where('vistos.user_id', $pessoa->id);
        if ($soJuntos) {
            $q->whereIn('series.id', $visiveis === [] ? [0] : $visiveis);   // [0] = nenhuma
        }
        $linha = $q->selectRaw('COUNT(*) AS n, COALESCE(SUM(COALESCE(series.minutos_ep, ' . Resumo::MINUTOS_EP . ')), 0) AS m')->first();

        // Episódios que os dois já viram nas séries que vêem juntos
        $juntos = 0;
        if ($idsJuntos !== []) {
            $juntos = Capsule::table('vistos AS a')
                ->join('vistos AS b', 'b.episodio_id', '=', 'a.episodio_id')
                ->join('episodios', 'episodios.id', '=', 'a.episodio_id')
                ->where('a.user_id', $pessoa->id)
                ->where('b.user_id', $user->id)
                ->whereIn('episodios.serie_id', $idsJuntos)
                ->count();
        }

        return [
            'episodios'       => (int) $linha->n,
            'horas'           => intdiv((int) $linha->m, 60),
            'series'          => count($biblioteca),
            'acabadas'        => count(array_filter($biblioteca, fn ($i) => $i['estado'] === 'acabado')),
            'seriesJuntos'    => count($idsJuntos),
            'episodiosJuntos' => $juntos,
        ];
    }

    // Medalhas de uma pessoa: { obtidas, total, lista, destaque (as 3 melhores obtidas: ouro primeiro) }
    public static function medalhas(User $pessoa): array
    {
        $lista = (new Conquistas($pessoa))->lista();
        $obtidas = array_values(array_filter($lista, fn ($m) => $m['obtida']));

        // Destaque: ouro > prata > bronze; empate → a mais difícil (mais abaixo na lista)
        $peso = ['ouro' => 3, 'prata' => 2, 'bronze' => 1];
        $destaque = array_reverse($obtidas);
        usort($destaque, fn ($a, $b) => ($peso[$b['nivel']] ?? 0) <=> ($peso[$a['nivel']] ?? 0));

        return [
            'obtidas'  => count($obtidas),
            'total'    => count($lista),
            'lista'    => $lista,
            'destaque' => array_slice($destaque, 0, 3),
        ];
    }
}
