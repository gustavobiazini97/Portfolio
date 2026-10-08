<?php
// Medalhas de uma pessoa: calculadas a partir do que já existe na base de dados (vistos, comentários,
// amizades, bibliotecas), por isso não há tabela própria e contam desde o primeiro dia.
// Cada medalha: id, emoji, nome, descrição, grupo, meta, atual (progresso) e obtida.

use Illuminate\Database\Capsule\Manager as Capsule;

class Conquistas
{
    public function __construct(private User $user)
    {
    }

    // Lista de todas as medalhas, com o progresso desta pessoa
    public function lista(): array
    {
        $uid = $this->user->id;

        // ---------- Números de base (poucas consultas) ----------
        $episodios = Capsule::table('vistos')->where('user_id', $uid)->count();

        $fillers = Capsule::table('vistos')->join('episodios', 'episodios.id', '=', 'vistos.episodio_id')
            ->where('vistos.user_id', $uid)->where(fn ($q) => $q->where('episodios.filler', 1)->orWhere('episodios.recap', 1))->count();

        // Minutos vistos (duração de cada série, ou 23 min)
        $minutos = (int) Capsule::table('vistos')->join('episodios', 'episodios.id', '=', 'vistos.episodio_id')
            ->join('series', 'series.id', '=', 'episodios.serie_id')
            ->where('vistos.user_id', $uid)
            ->selectRaw('COALESCE(SUM(COALESCE(series.minutos_ep, ' . Resumo::MINUTOS_EP . ')), 0) AS m')->value('m');

        // Por dia: o recorde num dia e a maior sequência de dias seguidos
        $porDia = Capsule::table('vistos')->where('user_id', $uid)
            ->selectRaw('DATE(visto_em) AS d, COUNT(*) AS n')->groupBy('d')->pluck('n', 'd');
        $recordeDia = (int) ($porDia->max() ?? 0);
        $sequencia = Resumo::maiorSequencia($porDia->keys()->all());

        // Episódios marcados de madrugada (0h–5h)
        $madrugada = Capsule::table('vistos')->where('user_id', $uid)->whereRaw('HOUR(visto_em) < 5')->count();

        $comentarios = Capsule::table('comentarios')->where('user_id', $uid)->count();
        $amigos = Capsule::table('amizades')->where('user_id', $uid)->count();
        $naBiblioteca = Capsule::table('bibliotecas')->where('user_id', $uid)->count();

        // Séries acabadas (todos os episódios vistos)
        $acabadas = Capsule::table('vistos')->join('episodios', 'episodios.id', '=', 'vistos.episodio_id')
            ->join('series', 'series.id', '=', 'episodios.serie_id')
            ->where('vistos.user_id', $uid)
            ->groupBy('series.id', 'series.total_episodios')
            ->havingRaw('COUNT(*) >= series.total_episodios')
            ->selectRaw('series.id')->get()->count();

        // Sincronizados: numa série vista com alguém, os dois no mesmo episódio mais avançado (e já começaram)
        $sincronizados = $this->sincronizados() ? 1 : 0;

        // Séries vistas com outras pessoas (companheiros)
        $conjuntas = Capsule::table('series_juntos')->where('user_id', $uid)->distinct()->count('serie_id');

        // ---------- As medalhas ----------
        $m = [];
        $add = function (string $id, string $emoji, string $nome, string $descricao, string $grupo, int $meta, int $atual) use (&$m) {
            $m[] = [
                'id'        => $id,
                'emoji'     => $emoji,
                'nome'      => $nome,
                'descricao' => $descricao,
                'grupo'     => $grupo,
                'meta'      => $meta,
                'atual'     => min($atual, $meta),
                'obtida'    => $atual >= $meta,
            ];
        };

        // Episódios (degraus)
        $add('ep-1',    '🌱', 'Primeiro passo',  'Marca o primeiro episódio.',        'Episódios', 1, $episodios);
        $add('ep-10',   '🎬', 'Aquecimento',      'Vê 10 episódios.',                   'Episódios', 10, $episodios);
        $add('ep-100',  '💯', 'Centenário',       'Vê 100 episódios.',                  'Episódios', 100, $episodios);
        $add('ep-500',  '🏯', 'Meio milhar',      'Vê 500 episódios.',                  'Episódios', 500, $episodios);
        $add('ep-1000', '🐉', 'Lenda',            'Vê 1000 episódios.',                 'Episódios', 1000, $episodios);

        // Tempo
        $add('horas-24',  '🕐', 'Um dia inteiro', 'Soma 24 horas de anime.',            'Tempo', 24 * 60, $minutos);
        $add('horas-100', '⏳', 'Cem horas',      'Soma 100 horas de anime.',           'Tempo', 100 * 60, $minutos);

        // Ritmo
        $add('maratona-10', '🍿', 'Maratonista',     '10 episódios no mesmo dia.',      'Ritmo', 10, $recordeDia);
        $add('maratona-20', '🔥', 'Maratona épica',  '20 episódios no mesmo dia.',      'Ritmo', 20, $recordeDia);
        $add('seguidos-7',  '📅', 'Uma semana a fio', 'Vê episódios 7 dias seguidos.',  'Ritmo', 7, $sequencia);
        $add('seguidos-30', '🗓️', 'Um mês a fio',    'Vê episódios 30 dias seguidos.', 'Ritmo', 30, $sequencia);
        $add('madrugada',   '🌙', 'Noctívago',       '10 episódios entre a meia-noite e as 5h.', 'Ritmo', 10, $madrugada);

        // Séries
        $add('fillers-50', '🛡️', 'Sobrevivente dos fillers', 'Vê 50 fillers ou recaps.', 'Séries', 50, $fillers);
        $add('acabada-1',  '🏁', 'Até ao fim',     'Acaba uma série.',                  'Séries', 1, $acabadas);
        $add('acabada-5',  '🏆', 'Colecionador',   'Acaba 5 séries.',                   'Séries', 5, $acabadas);
        $add('biblio-10',  '📚', 'Estante cheia',  'Tem 10 séries na biblioteca.',      'Séries', 10, $naBiblioteca);

        // Juntos
        $add('juntos-1',  '🤝', 'A dois',          'Vê uma série com alguém.',          'Juntos', 1, $conjuntas);
        $add('sincro',    '🎯', 'Sincronizados',   'Fiquem no mesmo episódio numa série vista juntos.', 'Juntos', 1, $sincronizados);
        $add('coment-10', '💬', 'Conversador',     'Escreve 10 comentários.',           'Juntos', 10, $comentarios);
        $add('coment-50', '🗣️', 'Crítico de sofá', 'Escreve 50 comentários.',          'Juntos', 50, $comentarios);
        $add('amigos-3',  '👥', 'Clube do anime',  'Tem 3 amigos na app.',              'Juntos', 3, $amigos);

        return $m;
    }

    // Há alguma série vista com alguém em que estão os dois no mesmo episódio mais avançado (> 0)?
    private function sincronizados(): bool
    {
        $uid = $this->user->id;
        $ligacoes = Capsule::table('series_juntos')->where('user_id', $uid)->get(['serie_id', 'com_id']);
        foreach ($ligacoes as $l) {
            $pos = fn (int $u) => (int) Capsule::table('vistos')->join('episodios', 'episodios.id', '=', 'vistos.episodio_id')
                ->where('vistos.user_id', $u)->where('episodios.serie_id', $l->serie_id)->max('episodios.numero');
            $meu = $pos($uid);
            if ($meu > 0 && $meu === $pos((int) $l->com_id)) {
                return true;
            }
        }
        return false;
    }
}
