<?php
// Uma série da biblioteca (ou uma proposta), os seus episódios e o progresso de cada pessoa.
// As três do Naruto vêm do seed; as outras são adicionadas pela pesquisa no MyAnimeList (Jikan).

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Capsule\Manager as Capsule;

class Serie extends Model
{
    protected $table = 'series';
    public $timestamps = false;

    protected $fillable = ['slug', 'nome', 'anos', 'total_episodios', 'ordem', 'mal_id', 'capa', 'tipo', 'minutos_ep',
                           'em_emissao', 'estado', 'acento', 'adicionada_por', 'adicionada_em', 'sincronizada_em'];

    protected $casts = ['em_emissao' => 'boolean'];

    // Estados de uma série da biblioteca, pela ordem da fila (os acabados vão para o fim)
    const ESTADOS = ['a_ver' => 'A ver', 'pausa' => 'Em pausa', 'acabado' => 'Acabado'];

    // Série proposta por um ao outro ("Quero ver contigo"): ainda fora da biblioteca
    const PROPOSTA = 'proposta';

    // Número de cores de destaque para séries novas ([data-acento="1"] a "8" no CSS)
    const ACENTOS = 8;

    // Séries em emissão: de quanto em quanto tempo se vão buscar episódios novos
    const SINCRONIZAR_HORAS = 12;

    // Episódios desta série, sempre por ordem de número
    public function episodios()
    {
        return $this->hasMany(Episodio::class, 'serie_id')->orderBy('numero');
    }

    // Quem adicionou (ou propôs) a série; null nas do seed
    public function autor()
    {
        return $this->belongsTo(User::class, 'adicionada_por');
    }

    // ---------- Procurar ----------

    // Procura pelo slug do URL; null se não existir
    public static function porSlug(?string $slug): ?Serie
    {
        return $slug === null ? null : static::where('slug', $slug)->first();
    }

    // Só as da biblioteca (sem propostas), pela ordem simples: estado e depois a ordem de entrada.
    // Para a fila do Início (com progresso e atividade) usa-se biblioteca().
    public static function naBiblioteca()
    {
        return static::where('estado', '!=', self::PROPOSTA)
            ->orderByRaw("FIELD(estado, 'a_ver', 'pausa', 'acabado')")
            ->orderBy('ordem')->orderBy('id')
            ->get();
    }

    // Fila de capas do Início: cada item tem a série, a percentagem de cada um e a última atividade.
    // Ordem: a ver → em pausa → acabado; dentro de cada estado, a mais mexida (ou a mais recente) primeiro.
    public static function biblioteca(User $tu, ?User $par): array
    {
        $series = static::where('estado', '!=', self::PROPOSTA)->get();

        // Vistos de cada pessoa em cada série, numa só consulta
        $contagens = Capsule::table('vistos')
            ->join('episodios', 'episodios.id', '=', 'vistos.episodio_id')
            ->groupBy('episodios.serie_id', 'vistos.user_id')
            ->selectRaw('episodios.serie_id AS serie, vistos.user_id AS pessoa, COUNT(*) AS n, MAX(vistos.visto_em) AS ultimo')
            ->get();
        $porSerie = [];
        foreach ($contagens as $c) {
            $porSerie[$c->serie][$c->pessoa] = $c;
        }

        $pct = fn (Serie $s, ?User $u) => ($u && isset($porSerie[$s->id][$u->id]) && $s->total_episodios > 0)
            ? min(100, (int) round($porSerie[$s->id][$u->id]->n / $s->total_episodios * 100))
            : 0;

        $itens = [];
        foreach ($series as $s) {
            // Atividade: o último episódio marcado por qualquer um, ou o dia em que entrou na biblioteca
            $atividade = (string) $s->adicionada_em;
            foreach ($porSerie[$s->id] ?? [] as $c) {
                $atividade = max($atividade, (string) $c->ultimo);
            }
            $itens[] = ['serie' => $s, 'tu' => $pct($s, $tu), 'par' => $pct($s, $par), 'atividade' => $atividade];
        }

        $rank = array_flip(array_keys(self::ESTADOS));
        usort($itens, fn ($a, $b) =>
            [$rank[$a['serie']->estado] ?? 0, $b['atividade'], $a['serie']->ordem, $a['serie']->id]
            <=> [$rank[$b['serie']->estado] ?? 0, $a['atividade'], $b['serie']->ordem, $b['serie']->id]);

        return $itens;
    }

    // Propostas ("Quero ver contigo"), as mais recentes primeiro, com quem propôs
    public static function propostas()
    {
        return static::with('autor')->where('estado', self::PROPOSTA)->orderByDesc('adicionada_em')->get();
    }

    // ---------- Nomes ----------

    // Nome curto para a fila e os separadores:
    // "Naruto Shippuden" → "Shippuden"; "Frieren: Beyond Journey's End" → "Frieren"
    public function nomeCurto(): string
    {
        if ($this->slug === 'shippuden') {
            return 'Shippuden';
        }
        $antes = strstr($this->nome, ': ', true);
        return ($antes !== false && mb_strlen($antes) >= 4) ? $antes : $this->nome;
    }

    // "A ver", "Em pausa", "Acabado" (ou "Proposta")
    public function estadoTexto(): string
    {
        return self::ESTADOS[$this->estado] ?? 'Proposta';
    }

    // ---------- Adicionar / propor (a partir do MyAnimeList) ----------

    // Cria a série com todos os episódios. $proposta = true → fica em "Quero ver contigo".
    // Lança InvalidArgumentException (repetida, sem episódios) ou RuntimeException (Jikan em baixo).
    public static function adicionarDoMal(User $autor, int $malId, bool $proposta): Serie
    {
        if ($malId < 1) {
            throw new InvalidArgumentException('Escolhe uma série da lista.');
        }

        // Já existe? (o mal_id é único)
        $existe = static::where('mal_id', $malId)->first();
        if ($existe !== null) {
            throw new InvalidArgumentException($existe->estado === self::PROPOSTA
                ? $existe->nome . ' já está em "Quero ver contigo".'
                : $existe->nome . ' já está na biblioteca.');
        }

        // Os pedidos ao Jikan ficam FORA da transação (podem demorar uns segundos)
        $info      = Jikan::anime($malId);
        $episodios = Jikan::episodios($malId);
        $total     = self::totalDe($info, $episodios);
        if ($total < 1) {
            throw new InvalidArgumentException('Ainda não se sabe quantos episódios tem ' . $info['nome'] . '.');
        }

        return Capsule::connection()->transaction(function () use ($autor, $info, $episodios, $total, $proposta) {
            $agora = date('Y-m-d H:i:s');
            $serie = static::create([
                'slug'            => self::slugLivre($info['nome']),
                'nome'            => $info['nome'],
                'anos'            => $info['ano'] ? (string) $info['ano'] : null,
                'total_episodios' => $total,
                'ordem'           => min(255, (int) static::max('ordem') + 1),
                'mal_id'          => $info['mal_id'],
                'capa'            => $info['capa'],
                'tipo'            => $info['tipo'],
                'minutos_ep'      => $info['minutos'],
                'em_emissao'      => $info['em_emissao'],
                'estado'          => $proposta ? self::PROPOSTA : 'a_ver',
                'acento'          => $info['mal_id'] % self::ACENTOS + 1,   // cor fixa por série, "ao calhas"
                'adicionada_por'  => $autor->id,
                'adicionada_em'   => $agora,
                'sincronizada_em' => $agora,
            ]);
            $serie->guardarEpisodios($episodios, $total);
            return $serie;
        });
    }

    // Busca outra vez os episódios no MyAnimeList (séries em emissão: aparecem os novos).
    // O total nunca diminui, para não apagar episódios já marcados.
    public function sincronizar(): void
    {
        if ($this->mal_id === null) {
            return;   // as do Naruto têm os dados do Naruto Fillers
        }

        // Marca já a hora: se outro pedido chegar entretanto, não repete o trabalho
        $this->sincronizada_em = date('Y-m-d H:i:s');
        $this->save();

        $info      = Jikan::anime((int) $this->mal_id);
        $episodios = Jikan::episodios((int) $this->mal_id);
        $total     = max((int) $this->total_episodios, self::totalDe($info, $episodios));

        Capsule::connection()->transaction(function () use ($info, $episodios, $total) {
            $this->total_episodios = $total;
            $this->em_emissao = $info['em_emissao'];
            $this->capa = $info['capa'] ?? $this->capa;
            $this->save();
            $this->guardarEpisodios($episodios, $total);
        });
    }

    // Está na hora de ir buscar episódios novos? (só séries do MyAnimeList ainda em emissão)
    public function precisaSincronizar(): bool
    {
        if ($this->mal_id === null || !$this->em_emissao) {
            return false;
        }
        return $this->sincronizada_em === null
            || strtotime($this->sincronizada_em) < time() - self::SINCRONIZAR_HORAS * 3600;
    }

    // Episódios de 1 ao total (upsert: atualiza títulos/filler/recap sem duplicar nem mexer nos vistos)
    private function guardarEpisodios(array $episodios, int $total): void
    {
        $linhas = [];
        for ($n = 1; $n <= $total; $n++) {
            $linhas[] = [
                'serie_id' => $this->id,
                'numero'   => $n,
                'titulo'   => $episodios[$n]['titulo'] ?? null,
                'filler'   => !empty($episodios[$n]['filler']) ? 1 : 0,
                'recap'    => !empty($episodios[$n]['recap']) ? 1 : 0,
            ];
        }
        foreach (array_chunk($linhas, 500) as $bloco) {   // blocos para não fazer uma consulta gigante
            Episodio::upsert($bloco, ['serie_id', 'numero'], ['titulo', 'filler', 'recap']);
        }
    }

    // Quantos episódios: o número anunciado ou, se for maior, o último que já saiu
    private static function totalDe(array $info, array $episodios): int
    {
        $ultimo = $episodios === [] ? 0 : (int) array_key_last($episodios);
        return min(65000, max((int) ($info['episodios'] ?? 0), $ultimo));
    }

    // Slug a partir do nome ("Frieren: Beyond Journey's End" → "frieren-beyond-journeys-end"), sem repetir
    private static function slugLivre(string $nome): string
    {
        $base = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome) ?: $nome;
        $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', str_replace("'", '', $base)), '-'));
        $base = substr($base, 0, 50) ?: 'serie';
        $base = rtrim($base, '-');

        $slug = $base;
        for ($i = 2; static::where('slug', $slug)->exists(); $i++) {
            $slug = $base . '-' . $i;
        }
        return $slug;
    }

    // ---------- Estado e propostas ----------

    // Muda o estado de uma série da biblioteca (a ver, em pausa, acabado)
    public function definirEstado(string $estado): void
    {
        if (!isset(self::ESTADOS[$estado])) {
            throw new InvalidArgumentException('Estado desconhecido.');
        }
        if ($this->estado === self::PROPOSTA) {
            throw new InvalidArgumentException('Primeiro, o teu par tem de aceitar a proposta.');
        }
        $this->estado = $estado;
        $this->save();
    }

    // O par aceita a proposta: passa para "a ver" e vai para o início da fila
    public function aceitar(User $quem): void
    {
        if ($this->estado !== self::PROPOSTA) {
            throw new InvalidArgumentException($this->nome . ' já está na biblioteca.');
        }
        if ((int) $this->adicionada_por === $quem->id) {
            throw new InvalidArgumentException('Quem aceita é o teu par.');
        }
        $this->estado = 'a_ver';
        $this->adicionada_em = date('Y-m-d H:i:s');
        $this->save();
    }

    // Só se pode tirar uma série sem episódios vistos (ou uma proposta): não se perde nada
    public function podeSerRemovida(): bool
    {
        return $this->estado === self::PROPOSTA
            || !Capsule::table('vistos')
                ->join('episodios', 'episodios.id', '=', 'vistos.episodio_id')
                ->where('episodios.serie_id', $this->id)
                ->exists();
    }

    // Apaga a série (os episódios e comentários vão com ela, pelo ON DELETE CASCADE)
    public function remover(): void
    {
        if (!$this->podeSerRemovida()) {
            throw new InvalidArgumentException('Já há episódios vistos de ' . $this->nome . ': não dá para tirar.');
        }
        $this->delete();
    }

    // ---------- Arcos e progresso ----------

    // Arcos da série, de 1 ao último episódio, sem buracos: os canónicos vêm de database/arcos.json
    // e os intervalos entre eles viram "Filler" (se a maioria for filler) ou "Episódios avulsos".
    // Devolve [] se a série não tiver arcos no ficheiro (ex.: Boruto e as séries do MyAnimeList).
    public function arcos(): array
    {
        static $todos = null;
        $todos ??= json_decode((string) @file_get_contents(__DIR__ . '/../../database/arcos.json'), true) ?: [];
        $canon = $todos[$this->slug] ?? [];
        if ($canon === []) {
            return [];
        }
        usort($canon, fn ($a, $b) => $a['de'] <=> $b['de']);

        // Números dos episódios filler desta série, para classificar os intervalos
        $fillers = array_flip($this->episodios()->where('filler', 1)->pluck('numero')->map(fn ($n) => (int) $n)->all());
        $intervalo = function (int $de, int $ate) use ($fillers): array {
            $n = $ate - $de + 1;
            $f = 0;
            for ($i = $de; $i <= $ate; $i++) {
                $f += isset($fillers[$i]) ? 1 : 0;
            }
            $quaseTodos = $f * 2 > $n;
            return ['nome' => $quaseTodos ? 'Filler' : 'Episódios avulsos', 'de' => $de, 'ate' => $ate, 'filler' => $quaseTodos];
        };

        $arcos = [];
        $seguinte = 1;
        foreach ($canon as $a) {
            if ($a['de'] > $seguinte) {
                $arcos[] = $intervalo($seguinte, $a['de'] - 1);
            }
            $arcos[] = ['nome' => $a['nome'], 'de' => (int) $a['de'], 'ate' => (int) $a['ate'], 'filler' => false];
            $seguinte = (int) $a['ate'] + 1;
        }
        if ($seguinte <= $this->total_episodios) {
            $arcos[] = $intervalo($seguinte, (int) $this->total_episodios);
        }
        return $arcos;
    }

    // Quantos episódios filler a série tem
    public function totalFillers(): int
    {
        return $this->episodios()->where('filler', 1)->count();
    }

    // Progresso de uma pessoa nesta série:
    //   vistos  = quantos episódios marcou
    //   pct     = percentagem da série (arredondada)
    //   posicao = o episódio mais avançado que já viu (0 se nenhum) — é o que conta no Vs
    public function progressoDe(?User $user): array
    {
        if ($user === null) {
            return ['vistos' => 0, 'pct' => 0, 'posicao' => 0];
        }

        $linha = Capsule::table('vistos')
            ->join('episodios', 'episodios.id', '=', 'vistos.episodio_id')
            ->where('vistos.user_id', $user->id)
            ->where('episodios.serie_id', $this->id)
            ->selectRaw('COUNT(*) AS vistos, COALESCE(MAX(episodios.numero), 0) AS posicao')
            ->first();

        $vistos = (int) $linha->vistos;
        return [
            'vistos'  => $vistos,
            'pct'     => $this->total_episodios > 0 ? (int) round($vistos / $this->total_episodios * 100) : 0,
            'posicao' => (int) $linha->posicao,
        ];
    }

    // Episódios para a página da série, já com quem viu cada um:
    // cada item tem id, numero, titulo, filler, recap, tu (bool), par (bool) e coment
    public function episodiosPara(User $tu, ?User $par): array
    {
        // IDs dos episódios desta série que cada um já viu (consulta única por pessoa)
        $vistosDe = function (?User $u): array {
            if ($u === null) {
                return [];
            }
            return Capsule::table('vistos')
                ->join('episodios', 'episodios.id', '=', 'vistos.episodio_id')
                ->where('vistos.user_id', $u->id)
                ->where('episodios.serie_id', $this->id)
                ->pluck('vistos.episodio_id')
                ->flip()        // id => posição, para procurar com isset() em vez de in_array()
                ->all();
        };
        $doTu  = $vistosDe($tu);
        $doPar = $vistosDe($par);

        // Quantos comentários tem cada episódio desta série (uma só consulta)
        $comentarios = Capsule::table('comentarios')
            ->join('episodios', 'episodios.id', '=', 'comentarios.episodio_id')
            ->where('episodios.serie_id', $this->id)
            ->groupBy('comentarios.episodio_id')
            ->selectRaw('comentarios.episodio_id AS id, COUNT(*) AS n')
            ->pluck('n', 'id')
            ->all();

        $lista = [];
        foreach ($this->episodios()->get(['id', 'numero', 'titulo', 'filler', 'recap']) as $ep) {
            $lista[] = [
                'id'     => (int) $ep->id,
                'numero' => (int) $ep->numero,
                'titulo' => $ep->titulo,
                'filler' => $ep->filler,
                'recap'  => $ep->recap,
                'tu'     => isset($doTu[$ep->id]),
                'par'    => isset($doPar[$ep->id]),
                'coment' => (int) ($comentarios[$ep->id] ?? 0),
            ];
        }
        return $lista;
    }
}
