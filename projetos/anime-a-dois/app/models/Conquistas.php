<?php
// Medalhas de uma pessoa: calculadas a partir do que já existe na base de dados (vistos, comentários,
// amizades, bibliotecas), por isso contam desde o primeiro dia.
// Cada medalha: id, emoji, nome, descrição, grupo, nível (bronze/prata/ouro), meta, atual (progresso), obtida,
// obtidaEm (ISO 8601 ou null) e obtidaAprox (true = "antes de <obtidaEm>": não deu para calcular o dia).
// O dia em que cada uma foi conseguida fica na tabela medalhas_obtidas: da primeira vez que uma medalha
// aparece obtida, o dia é calculado a partir dos registos (ex.: o 100.º episódio) e guardado.
// Os ids das medalhas antigas não mudam (a app guarda os ids já vistos para anunciar só as novas).

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
        $vistos = fn () => Capsule::table('vistos')->where('vistos.user_id', $uid);   // consulta base (nova a cada uso)

        // ---------- Números de base ----------
        $episodios = $vistos()->count();

        // Fillers ou recaps vistos
        $fillers = $vistos()->join('episodios', 'episodios.id', '=', 'vistos.episodio_id')
            ->where(fn ($q) => $q->where('episodios.filler', 1)->orWhere('episodios.recap', 1))->count();

        // Minutos vistos (duração de cada série, ou 23 min)
        $minutos = (int) $vistos()->join('episodios', 'episodios.id', '=', 'vistos.episodio_id')
            ->join('series', 'series.id', '=', 'episodios.serie_id')
            ->selectRaw('COALESCE(SUM(COALESCE(series.minutos_ep, ' . Resumo::MINUTOS_EP . ')), 0) AS m')->value('m');

        // Por dia: o recorde num dia e a maior sequência de dias seguidos
        $porDia = $vistos()->selectRaw('DATE(visto_em) AS d, COUNT(*) AS n')->groupBy('d')->pluck('n', 'd');
        $recordeDia = (int) ($porDia->max() ?? 0);
        $sequencia = Resumo::maiorSequencia($porDia->keys()->all());

        // Horas do dia: de madrugada (0h–5h) e cedo (5h–8h)
        $madrugada = $vistos()->whereRaw('HOUR(visto_em) < 5')->count();
        $cedo = $vistos()->whereRaw('HOUR(visto_em) >= 5 AND HOUR(visto_em) < 8')->count();

        // Ao fim de semana (DAYOFWEEK: 1 = domingo, 7 = sábado)
        $fimDeSemana = $vistos()->whereRaw('DAYOFWEEK(visto_em) IN (1, 7)')->count();

        // Datas especiais: 1 de janeiro e 24 de dezembro; e o dia de anos da conta (a partir do 2.º ano)
        $anoNovo = $vistos()->whereRaw("DATE_FORMAT(visto_em, '%m-%d') = '01-01'")->count();
        $natal = $vistos()->whereRaw("DATE_FORMAT(visto_em, '%m-%d') = '12-24'")->count();
        $criada = $this->user->criado_em ? strtotime((string) $this->user->criado_em) : null;
        $aniversario = $criada === null ? 0 : $vistos()
            ->whereRaw("DATE_FORMAT(visto_em, '%m-%d') = ?", [date('m-d', $criada)])
            ->whereRaw('YEAR(visto_em) > ?', [(int) date('Y', $criada)])->count();

        // Explorador: o maior número de séries diferentes vistas num mesmo mês
        $explorador = (int) ($vistos()->join('episodios', 'episodios.id', '=', 'vistos.episodio_id')
            ->selectRaw("DATE_FORMAT(vistos.visto_em, '%Y-%m') AS mes, COUNT(DISTINCT episodios.serie_id) AS n")
            ->groupBy('mes')->pluck('n')->max() ?? 0);

        $comentarios = Capsule::table('comentarios')->where('user_id', $uid)->count();
        $amigos = Capsule::table('amizades')->where('user_id', $uid)->count();
        $naBiblioteca = Capsule::table('bibliotecas')->where('user_id', $uid)->count();

        // Séries acabadas (todos os episódios vistos), com o total e o primeiro/último dia em que se viram
        $acabadas = $vistos()->join('episodios', 'episodios.id', '=', 'vistos.episodio_id')
            ->join('series', 'series.id', '=', 'episodios.serie_id')
            ->where('series.total_episodios', '>', 0)
            ->groupBy('series.id', 'series.total_episodios')
            ->havingRaw('COUNT(*) >= series.total_episodios')
            ->selectRaw('series.id, series.total_episodios AS total, MIN(DATE(vistos.visto_em)) AS inicio, MAX(DATE(vistos.visto_em)) AS fim')
            ->get();
        $longas = $acabadas->filter(fn ($s) => (int) $s->total >= 500)->count();                        // 500+ episódios
        $numFolego = $acabadas->filter(fn ($s) => (int) $s->total <= 13 && $s->inicio === $s->fim)->count(); // curta, num só dia

        // Sincronizados: numa série vista com alguém, os dois no mesmo episódio mais avançado (e já começaram)
        $sincronizados = $this->sincronizados() ? 1 : 0;

        // Séries vistas com outras pessoas (companheiros)
        $conjuntas = Capsule::table('series_juntos')->where('user_id', $uid)->distinct()->count('serie_id');

        // Episódios vistos pelos dois: nas séries que vês com alguém, os que tu e essa pessoa já viram
        $episodiosJuntos = (int) Capsule::table('vistos AS a')
            ->join('episodios', 'episodios.id', '=', 'a.episodio_id')
            ->join('series_juntos AS sj', fn ($j) => $j->on('sj.serie_id', '=', 'episodios.serie_id')->on('sj.user_id', '=', 'a.user_id'))
            ->join('vistos AS b', fn ($j) => $j->on('b.episodio_id', '=', 'a.episodio_id')->on('b.user_id', '=', 'sj.com_id'))
            ->where('a.user_id', $uid)
            ->distinct()->count('a.episodio_id');

        // ---------- As medalhas ----------
        $m = [];
        $add = function (string $id, string $emoji, string $nome, string $descricao, string $grupo, string $nivel, int $meta, int $atual) use (&$m) {
            $m[] = [
                'id'        => $id,
                'emoji'     => $emoji,
                'nome'      => $nome,
                'descricao' => $descricao,
                'grupo'     => $grupo,
                'nivel'     => $nivel,             // bronze | prata | ouro (a cor do emblema na app)
                'meta'      => $meta,
                'atual'     => min($atual, $meta),
                'obtida'    => $atual >= $meta,
            ];
        };

        // Episódios (degraus)
        $add('ep-1',    '🌱', 'Primeiro passo', 'Marca o primeiro episódio.', 'Episódios', 'bronze', 1, $episodios);
        $add('ep-10',   '🎬', 'Aquecimento',    'Vê 10 episódios.',           'Episódios', 'bronze', 10, $episodios);
        $add('ep-50',   '🍥', 'Na pista',       'Vê 50 episódios.',           'Episódios', 'bronze', 50, $episodios);
        $add('ep-100',  '💯', 'Centenário',     'Vê 100 episódios.',          'Episódios', 'prata', 100, $episodios);
        $add('ep-250',  '🎯', 'Viciado',        'Vê 250 episódios.',          'Episódios', 'prata', 250, $episodios);
        $add('ep-500',  '🏯', 'Meio milhar',    'Vê 500 episódios.',          'Episódios', 'prata', 500, $episodios);
        $add('ep-750',  '⚔️', 'Veterano',       'Vê 750 episódios.',          'Episódios', 'ouro', 750, $episodios);
        $add('ep-1000', '🐉', 'Lenda',          'Vê 1000 episódios.',         'Episódios', 'ouro', 1000, $episodios);
        $add('ep-1500', '🌋', 'Imparável',      'Vê 1500 episódios.',         'Episódios', 'ouro', 1500, $episodios);
        $add('ep-2000', '🌌', 'Sem limites',    'Vê 2000 episódios.',         'Episódios', 'ouro', 2000, $episodios);
        $add('ep-3000', '👑', 'Rei do anime',   'Vê 3000 episódios.',         'Episódios', 'ouro', 3000, $episodios);

        // Tempo
        $add('horas-24',   '🕐', 'Um dia inteiro', 'Soma 24 horas de anime.',   'Tempo', 'bronze', 24 * 60, $minutos);
        $add('horas-50',   '🕰️', 'Fim de semana longo', 'Soma 50 horas de anime.', 'Tempo', 'bronze', 50 * 60, $minutos);
        $add('horas-100',  '⏳', 'Cem horas',      'Soma 100 horas de anime.',  'Tempo', 'prata', 100 * 60, $minutos);
        $add('horas-250',  '⌛', 'Uma vida no sofá', 'Soma 250 horas de anime.', 'Tempo', 'prata', 250 * 60, $minutos);
        $add('horas-500',  '🛋️', 'Morador do sofá', 'Soma 500 horas de anime.', 'Tempo', 'ouro', 500 * 60, $minutos);
        $add('horas-1000', '♾️', 'Mil horas',      'Soma 1000 horas de anime.', 'Tempo', 'ouro', 1000 * 60, $minutos);

        // Ritmo
        $add('maratona-10', '🍿', 'Maratonista',      '10 episódios no mesmo dia.',     'Ritmo', 'bronze', 10, $recordeDia);
        $add('maratona-20', '🔥', 'Maratona épica',   '20 episódios no mesmo dia.',     'Ritmo', 'prata', 20, $recordeDia);
        $add('maratona-30', '☄️', 'Sem pestanejar',   '30 episódios no mesmo dia.',     'Ritmo', 'ouro', 30, $recordeDia);
        $add('seguidos-7',  '📅', 'Uma semana a fio', 'Vê episódios 7 dias seguidos.',   'Ritmo', 'bronze', 7, $sequencia);
        $add('seguidos-14', '📆', 'Duas semanas a fio', 'Vê episódios 14 dias seguidos.', 'Ritmo', 'prata', 14, $sequencia);
        $add('seguidos-30', '🗓️', 'Um mês a fio',     'Vê episódios 30 dias seguidos.',  'Ritmo', 'prata', 30, $sequencia);
        $add('seguidos-100', '🧘', 'Disciplina ninja', 'Vê episódios 100 dias seguidos.', 'Ritmo', 'ouro', 100, $sequencia);
        $add('madrugada',   '🌙', 'Noctívago',        '10 episódios entre a meia-noite e as 5h.', 'Ritmo', 'bronze', 10, $madrugada);
        $add('madrugada-50', '🦉', 'Coruja',          '50 episódios entre a meia-noite e as 5h.', 'Ritmo', 'prata', 50, $madrugada);
        $add('cedo',        '🌅', 'Madrugador',       '10 episódios entre as 5h e as 8h.', 'Ritmo', 'prata', 10, $cedo);

        // Séries
        $add('acabada-1',  '🏁', 'Até ao fim',     'Acaba uma série.',                 'Séries', 'bronze', 1, $acabadas->count());
        $add('acabada-3',  '🎖️', 'Tripla',         'Acaba 3 séries.',                  'Séries', 'bronze', 3, $acabadas->count());
        $add('acabada-5',  '🏆', 'Colecionador',   'Acaba 5 séries.',                  'Séries', 'prata', 5, $acabadas->count());
        $add('acabada-10', '📜', 'Arquivista',     'Acaba 10 séries.',                 'Séries', 'prata', 10, $acabadas->count());
        $add('acabada-25', '🗿', 'Enciclopédia',   'Acaba 25 séries.',                 'Séries', 'ouro', 25, $acabadas->count());
        $add('biblio-10',  '📚', 'Estante cheia',  'Tem 10 séries na biblioteca.',     'Séries', 'bronze', 10, $naBiblioteca);
        $add('biblio-25',  '🗄️', 'Biblioteca',     'Tem 25 séries na biblioteca.',     'Séries', 'prata', 25, $naBiblioteca);
        $add('biblio-50',  '🏛️', 'Museu do anime', 'Tem 50 séries na biblioteca.',     'Séries', 'ouro', 50, $naBiblioteca);
        $add('longa',      '🐢', 'Maratona longa', 'Acaba uma série com 500 ou mais episódios.', 'Séries', 'ouro', 1, $longas);
        $add('folego',     '💨', 'Num fôlego',     'Acaba uma série de até 13 episódios num só dia.', 'Séries', 'prata', 1, $numFolego);
        $add('fillers-50', '🛡️', 'Sobrevivente dos fillers', 'Vê 50 fillers ou recaps.', 'Séries', 'bronze', 50, $fillers);
        $add('fillers-200', '🧱', 'Imune aos fillers', 'Vê 200 fillers ou recaps.',    'Séries', 'prata', 200, $fillers);

        // Juntos
        $add('juntos-1',   '🤝', 'A dois',          'Vê uma série com alguém.',        'Juntos', 'bronze', 1, $conjuntas);
        $add('juntos-3',   '🫶', 'Três em comum',   'Vê 3 séries com alguém.',         'Juntos', 'prata', 3, $conjuntas);
        $add('juntos-5',   '💞', 'Inseparáveis',    'Vê 5 séries com alguém.',         'Juntos', 'ouro', 5, $conjuntas);
        $add('eps-juntos-50',   '👫', 'Sofá partilhado', '50 episódios vistos pelos dois.',   'Juntos', 'bronze', 50, $episodiosJuntos);
        $add('eps-juntos-250',  '🍜', 'Ramen a dois',    '250 episódios vistos pelos dois.',  'Juntos', 'prata', 250, $episodiosJuntos);
        $add('eps-juntos-1000', '💍', 'Para sempre',     '1000 episódios vistos pelos dois.', 'Juntos', 'ouro', 1000, $episodiosJuntos);
        $add('sincro',     '🎯', 'Sincronizados',   'Fiquem no mesmo episódio numa série vista juntos.', 'Juntos', 'bronze', 1, $sincronizados);
        $add('amigos-1',   '👋', 'Primeiro amigo',  'Tem 1 amigo na app.',             'Juntos', 'bronze', 1, $amigos);
        $add('amigos-3',   '👥', 'Clube do anime',  'Tem 3 amigos na app.',            'Juntos', 'prata', 3, $amigos);
        $add('amigos-10',  '🎌', 'Aldeia da folha', 'Tem 10 amigos na app.',           'Juntos', 'ouro', 10, $amigos);
        $add('coment-10',  '💬', 'Conversador',     'Escreve 10 comentários.',         'Juntos', 'bronze', 10, $comentarios);
        $add('coment-50',  '🗣️', 'Crítico de sofá', 'Escreve 50 comentários.',         'Juntos', 'prata', 50, $comentarios);
        $add('coment-200', '📣', 'Comentador oficial', 'Escreve 200 comentários.',     'Juntos', 'ouro', 200, $comentarios);

        // Especiais
        $add('ano-novo',    '🎆', 'Ano novo, anime novo', 'Vê um episódio a 1 de janeiro.',    'Especiais', 'prata', 1, $anoNovo);
        $add('natal',       '🎄', 'Noite de Natal',       'Vê um episódio a 24 de dezembro.',  'Especiais', 'prata', 1, $natal);
        $add('fim-semana',  '🛌', 'Fim de semana sagrado', '100 episódios ao sábado ou domingo.', 'Especiais', 'prata', 100, $fimDeSemana);
        $add('aniversario', '🎂', 'Parabéns!',            'Vê um episódio no dia em que a tua conta faz anos.', 'Especiais', 'ouro', 1, $aniversario);
        $add('explorador',  '🧭', 'Explorador',           'Vê 5 séries diferentes no mesmo mês.', 'Especiais', 'prata', 5, $explorador);

        return $this->comDatas($m);
    }

    // ---------- Dia em que cada medalha foi conseguida ----------

    // Acrescenta obtidaEm/obtidaAprox a cada medalha; as obtidas sem data guardada ganham uma agora
    private function comDatas(array $m): array
    {
        $uid = $this->user->id;
        try {
            $guardadas = Capsule::table('medalhas_obtidas')->where('user_id', $uid)->get()->keyBy('medalha_id');
        } catch (Throwable) {
            // Tabela ainda não criada (deploy a meio): as medalhas aparecem como antes, sem datas
            return array_map(fn ($x) => $x + ['obtidaEm' => null, 'obtidaAprox' => false], $m);
        }

        // Medalhas que já existiam antes de haver datas: se o dia não der para calcular, fica "antes de hoje".
        // (Primeira vez que se guardam datas desta pessoa e a conta é de antes de hoje.)
        $antigas = $guardadas->isEmpty()
            && $this->user->criado_em !== null
            && date('Y-m-d', strtotime((string) $this->user->criado_em)) < date('Y-m-d');

        $agora = date('Y-m-d H:i:s');
        $novas = [];
        foreach ($m as &$medalha) {
            $g = $guardadas->get($medalha['id']);
            if ($medalha['obtida'] && $g === null) {
                try {
                    $dia = $this->calcularDia($medalha['id'], (int) $medalha['meta']);
                } catch (Throwable) {
                    $dia = null;   // uma conta que falhe não pode estragar a página das medalhas
                }
                $g = (object) [
                    'obtida_em' => $dia ?? $agora,
                    'aprox'     => ($dia === null && $antigas) ? 1 : 0,
                ];
                $novas[] = ['user_id' => $uid, 'medalha_id' => $medalha['id'], 'obtida_em' => $g->obtida_em, 'aprox' => $g->aprox];
            }
            // Só as obtidas mostram data (uma que se perdeu e volta a ganhar mantém a data da primeira vez)
            $medalha['obtidaEm']    = ($medalha['obtida'] && $g) ? date(DATE_ATOM, strtotime($g->obtida_em)) : null;
            $medalha['obtidaAprox'] = ($medalha['obtida'] && $g) ? (bool) $g->aprox : false;
        }
        unset($medalha);

        if ($novas !== []) {
            try {
                Capsule::table('medalhas_obtidas')->insertOrIgnore($novas);   // ignora se outro pedido já as guardou
            } catch (Throwable) {
                // não guardou: volta a tentar no próximo pedido
            }
        }
        return $m;
    }

    // Dia ('Y-m-d H:i:s') em que esta medalha foi conseguida, calculado dos registos; null se não der
    private function calcularDia(string $id, int $meta): ?string
    {
        $uid = $this->user->id;
        $grupo = preg_replace('/-\d+$/', '', $id);   // 'ep-100' → 'ep'; 'madrugada-50' → 'madrugada'

        // A N.ª data de uma lista ordenada (ou null se não chegar lá)
        $nth = fn (array $datas, int $n) => $datas[$n - 1] ?? null;

        return match ($grupo) {
            // Contas que vêm dos episódios vistos (resolvidas na função pura, que tem testes)
            'ep', 'horas', 'fillers', 'maratona', 'seguidos', 'madrugada', 'cedo', 'fim-semana',
            'ano-novo', 'natal', 'aniversario', 'explorador'
                => self::diaNosVistos($grupo, $meta, $this->linhasVistos(),
                    $this->user->criado_em ? (string) $this->user->criado_em : null),

            // Séries acabadas: o dia em que se viu o último episódio de cada uma
            'acabada' => $nth(array_column($this->acabadasComFim(), 'fim'), $meta),
            'longa'   => $nth(array_column(array_filter($this->acabadasComFim(), fn ($s) => $s['total'] >= 500), 'fim'), 1),
            'folego'  => $nth(array_column(array_filter($this->acabadasComFim(),
                            fn ($s) => $s['total'] <= 13 && substr($s['inicio'], 0, 10) === substr($s['fim'], 0, 10)), 'fim'), 1),

            // Biblioteca: quando cada série entrou
            'biblio' => $nth(Capsule::table('bibliotecas')->where('user_id', $uid)->orderBy('desde')->pluck('desde')->all(), $meta),

            // Séries vistas com alguém: quando essas séries entraram na tua biblioteca
            'juntos' => $nth(Capsule::table('series_juntos AS sj')
                ->join('bibliotecas AS b', fn ($j) => $j->on('b.serie_id', '=', 'sj.serie_id')->on('b.user_id', '=', 'sj.user_id'))
                ->where('sj.user_id', $uid)
                ->groupBy('sj.serie_id')
                ->selectRaw('MIN(b.desde) AS desde')
                ->orderBy('desde')->pluck('desde')->all(), $meta),

            // Episódios vistos pelos dois: cada um conta quando o segundo o viu
            'eps-juntos' => Capsule::table('vistos AS a')
                ->join('episodios', 'episodios.id', '=', 'a.episodio_id')
                ->join('series_juntos AS sj', fn ($j) => $j->on('sj.serie_id', '=', 'episodios.serie_id')->on('sj.user_id', '=', 'a.user_id'))
                ->join('vistos AS b', fn ($j) => $j->on('b.episodio_id', '=', 'a.episodio_id')->on('b.user_id', '=', 'sj.com_id'))
                ->where('a.user_id', $uid)
                ->groupBy('a.episodio_id')
                ->selectRaw('MIN(GREATEST(a.visto_em, b.visto_em)) AS quando')
                ->orderBy('quando')->offset($meta - 1)->limit(1)->value('quando'),

            // Amigos e comentários: o N.º
            'amigos' => $nth(Capsule::table('amizades')->where('user_id', $uid)->orderBy('desde')->pluck('desde')->all(), $meta),
            'coment' => Capsule::table('comentarios')->where('user_id', $uid)->orderBy('criado_em')
                ->offset($meta - 1)->limit(1)->value('criado_em'),

            // Sincronizados (e qualquer medalha nova sem regra): não dá para saber o dia
            default => null,
        };
    }

    // Episódios vistos desta pessoa, do primeiro ao último: [em, serie, filler, minutos] (lido uma vez só)
    private ?array $linhas = null;

    private function linhasVistos(): array
    {
        return $this->linhas ??= Capsule::table('vistos')
            ->join('episodios', 'episodios.id', '=', 'vistos.episodio_id')
            ->join('series', 'series.id', '=', 'episodios.serie_id')
            ->where('vistos.user_id', $this->user->id)
            ->orderBy('vistos.visto_em')->orderBy('episodios.numero')
            ->selectRaw('vistos.visto_em AS em, episodios.serie_id AS serie, '
                . '(episodios.filler = 1 OR episodios.recap = 1) AS filler, '
                . 'COALESCE(series.minutos_ep, ' . Resumo::MINUTOS_EP . ') AS minutos')
            ->get()
            ->map(fn ($l) => ['em' => (string) $l->em, 'serie' => (int) $l->serie, 'filler' => (bool) $l->filler, 'minutos' => (int) $l->minutos])
            ->all();
    }

    // Séries acabadas com o primeiro e o último registo, pela ordem em que foram acabadas
    private ?array $acabadas = null;

    private function acabadasComFim(): array
    {
        return $this->acabadas ??= Capsule::table('vistos')
            ->join('episodios', 'episodios.id', '=', 'vistos.episodio_id')
            ->join('series', 'series.id', '=', 'episodios.serie_id')
            ->where('vistos.user_id', $this->user->id)
            ->where('series.total_episodios', '>', 0)
            ->groupBy('series.id', 'series.total_episodios')
            ->havingRaw('COUNT(*) >= series.total_episodios')
            ->selectRaw('series.total_episodios AS total, MIN(vistos.visto_em) AS inicio, MAX(vistos.visto_em) AS fim')
            ->orderBy('fim')
            ->get()
            ->map(fn ($s) => ['total' => (int) $s->total, 'inicio' => (string) $s->inicio, 'fim' => (string) $s->fim])
            ->all();
    }

    // Função pura (sem base de dados, tem testes em tests/medalhas_datas_test.php): o momento em que a
    // meta foi atingida, a percorrer os episódios vistos por ordem. $linhas: [['em','serie','filler','minutos'], …]
    public static function diaNosVistos(string $grupo, int $meta, array $linhas, ?string $criadaEm): ?string
    {
        // A N.ª linha que cumpre uma condição
        $nesima = function (callable $cumpre, int $n) use ($linhas): ?string {
            foreach ($linhas as $l) {
                if ($cumpre($l) && --$n === 0) {
                    return $l['em'];
                }
            }
            return null;
        };
        $hora = fn (array $l) => (int) substr($l['em'], 11, 2);
        $diaMes = fn (array $l) => substr($l['em'], 5, 5);   // 'm-d'

        switch ($grupo) {
            case 'ep':
                return $nesima(fn () => true, $meta);

            case 'fillers':
                return $nesima(fn ($l) => $l['filler'], $meta);

            case 'madrugada':   // 0h–5h
                return $nesima(fn ($l) => $hora($l) < 5, $meta);

            case 'cedo':        // 5h–8h
                return $nesima(fn ($l) => $hora($l) >= 5 && $hora($l) < 8, $meta);

            case 'fim-semana':  // sábado ou domingo
                return $nesima(fn ($l) => (int) date('N', strtotime($l['em'])) >= 6, $meta);

            case 'ano-novo':
                return $nesima(fn ($l) => $diaMes($l) === '01-01', 1);

            case 'natal':
                return $nesima(fn ($l) => $diaMes($l) === '12-24', 1);

            case 'aniversario': // no dia de anos da conta, a partir do 2.º ano
                if ($criadaEm === null) {
                    return null;
                }
                return $nesima(fn ($l) => $diaMes($l) === substr($criadaEm, 5, 5) && substr($l['em'], 0, 4) > substr($criadaEm, 0, 4), 1);

            case 'horas':       // a meta está em minutos
                $soma = 0;
                foreach ($linhas as $l) {
                    $soma += $l['minutos'];
                    if ($soma >= $meta) {
                        return $l['em'];
                    }
                }
                return null;

            case 'maratona':    // a N.ª do primeiro dia que chegou a N episódios
                $porDia = [];
                foreach ($linhas as $l) {
                    $d = substr($l['em'], 0, 10);
                    $porDia[$d] = ($porDia[$d] ?? 0) + 1;
                    if ($porDia[$d] === $meta) {
                        return $l['em'];
                    }
                }
                return null;

            case 'seguidos':    // o primeiro episódio do dia em que a sequência chegou a N dias
                $sequencia = 0;
                $antes = null;
                foreach ($linhas as $l) {
                    $d = substr($l['em'], 0, 10);
                    if ($d === $antes) {
                        continue;   // mesmo dia: já foi contado
                    }
                    $seguido = $antes !== null && strtotime($d) - strtotime($antes) <= 90000;   // 25 h (mudança de hora)
                    $sequencia = $seguido ? $sequencia + 1 : 1;
                    $antes = $d;
                    if ($sequencia === $meta) {
                        return $l['em'];
                    }
                }
                return null;

            case 'explorador':  // a N.ª série diferente no mesmo mês
                $porMes = [];
                foreach ($linhas as $l) {
                    $mes = substr($l['em'], 0, 7);
                    $porMes[$mes][$l['serie']] = true;
                    if (count($porMes[$mes]) === $meta) {
                        return $l['em'];
                    }
                }
                return null;
        }
        return null;
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
