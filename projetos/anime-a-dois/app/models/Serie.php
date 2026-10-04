<?php
// Uma série da biblioteca (ou uma proposta), os seus episódios e o progresso de cada pessoa.
// As três do Naruto vêm do seed; as outras são adicionadas pela pesquisa (AniList/Kitsu/Jikan, pelo browser).

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Capsule\Manager as Capsule;

class Serie extends Model
{
    protected $table = 'series';
    public $timestamps = false;

    protected $fillable = ['slug', 'nome', 'anos', 'total_episodios', 'ordem', 'mal_id', 'capa', 'tipo', 'minutos_ep',
                           'em_emissao', 'estado', 'acento', 'adicionada_por', 'adicionada_em', 'sincronizada_em'];

    protected $casts = ['em_emissao' => 'boolean'];

    // Estados de uma série na biblioteca de cada pessoa, pela ordem da fila (os acabados vão para o fim)
    const ESTADOS = ['a_ver' => 'A ver', 'pausa' => 'Em pausa', 'acabado' => 'Acabado'];

    // Na biblioteca de alguém, 'convite' = o outro convidou-o a ver esta série com ele ("Quero ver contigo")
    const CONVITE = 'convite';

    // Número de cores de destaque para séries novas ([data-acento="1"] a "8" no CSS)
    const ACENTOS = 8;

    // Séries em emissão: de quanto em quanto tempo o browser vai buscar episódios novos
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

    // ---------- Bibliotecas (cada pessoa tem a sua; tabela bibliotecas) ----------

    // Estado desta série na biblioteca de uma pessoa: a_ver | pausa | acabado | convite, ou null se não a tem
    public function estadoDe(?User $u): ?string
    {
        if ($u === null) {
            return null;
        }
        return Capsule::table('bibliotecas')->where('user_id', $u->id)->where('serie_id', $this->id)->value('estado');
    }

    // Está na biblioteca desta pessoa (convites não contam)?
    public function naBibliotecaDe(?User $u): bool
    {
        $estado = $this->estadoDe($u);
        return $estado !== null && $estado !== self::CONVITE;
    }

    // Conjunta = os dois têm-na na biblioteca
    public function conjunta(User $u, ?User $par): bool
    {
        return $par !== null && $this->naBibliotecaDe($u) && $this->naBibliotecaDe($par);
    }

    // Séries da biblioteca de uma pessoa (sem convites), pela ordem simples: estado e ordem de entrada.
    // Para a fila do Início (com progresso e atividade) usa-se biblioteca().
    public static function naBiblioteca(User $u)
    {
        return static::join('bibliotecas', 'bibliotecas.serie_id', '=', 'series.id')
            ->where('bibliotecas.user_id', $u->id)
            ->where('bibliotecas.estado', '!=', self::CONVITE)
            ->orderByRaw("FIELD(bibliotecas.estado, 'a_ver', 'pausa', 'acabado')")
            ->orderBy('series.ordem')->orderBy('series.id')
            ->select('series.*', 'bibliotecas.estado AS meu_estado')
            ->get();
    }

    // Fila de capas: as séries da biblioteca de $dono, cada uma com a percentagem dele, a do outro
    // (só se for conjunta), o estado e a última atividade. Ordem: a ver → em pausa → acabado; dentro
    // de cada estado, a mais mexida (ou a que entrou mais recentemente) primeiro.
    //   $dono = quem é dono da fila; $outro = a outra pessoa (para as barrinhas das conjuntas)
    //   $excluirDe = não mostrar as que esta pessoa também tem (fila "A Andreia está a ver")
    public static function biblioteca(User $dono, ?User $outro, ?User $excluirDe = null): array
    {
        $linhas = Capsule::table('bibliotecas')->where('user_id', $dono->id)->where('estado', '!=', self::CONVITE)->get()->keyBy('serie_id');
        if ($excluirDe !== null) {
            $tem = Capsule::table('bibliotecas')->where('user_id', $excluirDe->id)->pluck('serie_id')->flip();
            $linhas = $linhas->reject(fn ($l) => isset($tem[$l->serie_id]));
        }
        if ($linhas->isEmpty()) {
            return [];
        }
        $series = static::whereIn('id', $linhas->keys()->all())->get();

        // Do outro: que séries tem na biblioteca (para saber quais são conjuntas)
        $doOutro = $outro ? Capsule::table('bibliotecas')->where('user_id', $outro->id)->where('estado', '!=', self::CONVITE)->pluck('serie_id')->flip() : collect();

        // Vistos de cada pessoa em cada série, numa só consulta
        $contagens = Capsule::table('vistos')
            ->join('episodios', 'episodios.id', '=', 'vistos.episodio_id')
            ->whereIn('episodios.serie_id', $linhas->keys()->all())
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
            $linha = $linhas[$s->id];
            $conjunta = isset($doOutro[$s->id]);
            // Atividade: o último episódio marcado pelo dono (ou pelos dois, se conjunta), ou quando entrou
            $atividade = (string) $linha->desde;
            foreach ($porSerie[$s->id] ?? [] as $pessoa => $c) {
                if ($pessoa == $dono->id || $conjunta) {
                    $atividade = max($atividade, (string) $c->ultimo);
                }
            }
            $itens[] = [
                'serie'     => $s,
                'estado'    => $linha->estado,
                'conjunta'  => $conjunta,
                'tu'        => $pct($s, $dono),
                'par'       => $conjunta ? $pct($s, $outro) : null,
                'atividade' => $atividade,
            ];
        }

        $rank = array_flip(array_keys(self::ESTADOS));
        usort($itens, fn ($a, $b) =>
            [$rank[$a['estado']] ?? 0, $b['atividade'], $a['serie']->ordem, $a['serie']->id]
            <=> [$rank[$b['estado']] ?? 0, $a['atividade'], $b['serie']->ordem, $b['serie']->id]);

        return $itens;
    }

    // Convites ("Quero ver contigo"): os que recebeste e os que enviaste e ainda estão por responder.
    // Cada item: ['serie' => Serie, 'recebido' => bool]
    public static function convites(User $tu, ?User $par): array
    {
        $ids = [$tu->id];
        if ($par !== null) {
            $ids[] = $par->id;
        }
        $linhas = Capsule::table('bibliotecas')->whereIn('user_id', $ids)->where('estado', self::CONVITE)->orderByDesc('desde')->get();
        $series = static::whereIn('id', $linhas->pluck('serie_id')->all())->get()->keyBy('id');

        $lista = [];
        foreach ($linhas as $l) {
            if (isset($series[$l->serie_id])) {
                $lista[] = ['serie' => $series[$l->serie_id], 'recebido' => $l->user_id == $tu->id];
            }
        }
        return $lista;
    }

    // Para a pesquisa: mal_id → 'biblioteca' (já tens), 'convite' (estás convidado) ou 'par' (só o teu par tem)
    public static function situacaoNaPesquisa(User $tu, ?User $par): array
    {
        $mapa = [];
        $linhas = Capsule::table('bibliotecas')
            ->join('series', 'series.id', '=', 'bibliotecas.serie_id')
            ->whereNotNull('series.mal_id')
            ->whereIn('bibliotecas.user_id', array_filter([$tu->id, $par?->id]))
            ->get(['series.mal_id', 'bibliotecas.user_id', 'bibliotecas.estado']);
        foreach ($linhas as $l) {
            if ($l->user_id == $tu->id) {
                $mapa[$l->mal_id] = $l->estado === self::CONVITE ? 'convite' : 'biblioteca';
            } elseif ($l->estado !== self::CONVITE) {
                $mapa[$l->mal_id] ??= 'par';
            }
        }
        return $mapa;
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

    // "A ver", "Em pausa", "Acabado" (ou "Convite")
    public static function estadoTexto(?string $estado): string
    {
        return self::ESTADOS[$estado] ?? 'Convite';
    }

    // ---------- Adicionar / propor ----------
    // Os dados chegam do browser, que os foi buscar às APIs de anime (ver app/models/DadosAnime.php).

    // Põe a série na biblioteca de $autor e, se $convidar, convida o par a vê-la com ele.
    // Se a série ainda não existe na app, é criada com todos os episódios.
    // $info e $episodios já validados por DadosAnime::info() e DadosAnime::episodios().
    // $comFillers = os episódios vieram do Jikan (com filler/recap); senão o browser tenta mais tarde.
    public static function adicionarDoMal(User $autor, array $info, array $episodios, bool $convidar, bool $comFillers = false): Serie
    {
        $existe = static::where('mal_id', $info['mal_id'])->first();
        if ($existe !== null) {
            $estado = $existe->estadoDe($autor);
            if ($estado === self::CONVITE) {
                throw new InvalidArgumentException('Já tens um convite para ' . $existe->nome . ': aceita-o no Início.');
            }
            if ($estado !== null) {
                throw new InvalidArgumentException($existe->nome . ' já está na tua biblioteca.');
            }
            // Já está na biblioteca do par: juntar-te tornava-a "dos dois" sem ele confirmar
            $par = $autor->parceiro();
            if ($existe->naBibliotecaDe($par)) {
                throw new InvalidArgumentException($existe->nome . ' já está na biblioteca de ' . $par->nome . ': pede-lhe para te convidar.');
            }
            // A série existe na app mas não é de ninguém (ex.: uma do seed que tiraste): volta para a tua
            return Capsule::connection()->transaction(function () use ($existe, $autor, $convidar) {
                $existe->juntarA($autor, 'a_ver');
                if ($convidar) {
                    $existe->convidar($autor);
                }
                return $existe;
            });
        }

        $total = self::totalDe($info, $episodios);
        if ($total < 1) {
            throw new InvalidArgumentException('Ainda não se sabe quantos episódios tem ' . $info['nome'] . '.');
        }

        return Capsule::connection()->transaction(function () use ($autor, $info, $episodios, $total, $convidar, $comFillers) {
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
                'acento'          => $info['mal_id'] % self::ACENTOS + 1,   // cor fixa por série, "ao calhas"
                'adicionada_por'  => $autor->id,
                'adicionada_em'   => $agora,
                'sincronizada_em' => $comFillers ? $agora : null,   // null = fillers ainda por buscar
            ]);
            $serie->guardarEpisodios($episodios, $total, $comFillers);
            $serie->juntarA($autor, 'a_ver');
            if ($convidar) {
                $serie->convidar($autor);
            }
            return $serie;
        });
    }

    // As do seed (Naruto, Shippuden, Boruto) têm os episódios do Naruto Fillers: do MyAnimeList só a capa
    public function doSeed(): bool
    {
        return $this->adicionada_por === null;
    }

    // Atualiza com dados novos (o browser manda-os quando a capa falta, quando os fillers ainda não
    // vieram ou quando a série em emissão está desatualizada). Devolve quantos episódios novos apareceram.
    // O total nunca diminui, para não apagar episódios já marcados.
    public function atualizarDoMal(array $info, ?array $episodios, bool $comFillers = false): int
    {
        $antes = (int) $this->total_episodios;
        $this->capa = $info['capa'] ?? $this->capa;

        // Série do seed: só a capa (os episódios vêm do Naruto Fillers)
        if ($this->doSeed() || $episodios === null) {
            $this->save();
            return 0;
        }

        $total = max($antes, self::totalDe($info, $episodios));
        Capsule::connection()->transaction(function () use ($info, $episodios, $total, $comFillers) {
            $this->total_episodios = $total;
            $this->em_emissao = $info['em_emissao'];
            $this->minutos_ep = $info['minutos'] ?? $this->minutos_ep;
            if ($comFillers) {
                $this->sincronizada_em = date('Y-m-d H:i:s');   // dados completos: só volta daqui a 12 h (se em emissão)
            }
            $this->save();
            $this->guardarEpisodios($episodios, $total, $comFillers);
        });
        return $total - $antes;
    }

    // O browser deve ir buscar dados? Sim se ainda faltam os fillers (sincronizada_em null)
    // ou se a série está em emissão e a última atualização completa tem mais de 12 h
    public function precisaSincronizar(): bool
    {
        if ($this->mal_id === null || $this->doSeed()) {
            return false;
        }
        if ($this->sincronizada_em === null) {
            return true;
        }
        return $this->em_emissao && strtotime($this->sincronizada_em) < time() - self::SINCRONIZAR_HORAS * 3600;
    }

    // Episódios de 1 ao total (upsert: atualiza sem duplicar nem mexer nos vistos).
    // Um título em falta nunca apaga o que já lá estava; filler/recap só mudam com dados do Jikan
    // (o Kitsu não os tem, e não pode apagar os que o Jikan já deu).
    private function guardarEpisodios(array $episodios, int $total, bool $comFillers): void
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
        $atualizar = ['titulo' => Capsule::raw('COALESCE(VALUES(`titulo`), `titulo`)')];
        if ($comFillers) {
            $atualizar[] = 'filler';
            $atualizar[] = 'recap';
        }
        foreach (array_chunk($linhas, 500) as $bloco) {   // blocos para não fazer uma consulta gigante
            Episodio::upsert($bloco, ['serie_id', 'numero'], $atualizar);
        }
    }

    // Quantos episódios: o número anunciado ou, se for maior, o último que já saiu
    private static function totalDe(array $info, array $episodios): int
    {
        $ultimo = $episodios === [] ? 0 : (int) array_key_last($episodios);
        return min(DadosAnime::MAX_EPISODIOS, max((int) ($info['episodios'] ?? 0), $ultimo));
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

    // ---------- Estado, convites e tirar (sempre na biblioteca de UMA pessoa) ----------

    // Põe (ou atualiza) a série na biblioteca de uma pessoa
    private function juntarA(User $u, string $estado): void
    {
        Capsule::table('bibliotecas')->updateOrInsert(
            ['user_id' => $u->id, 'serie_id' => $this->id],
            ['estado' => $estado, 'desde' => date('Y-m-d H:i:s')]
        );
    }

    // Muda o estado na TUA biblioteca (o do teu par fica como está)
    public function definirEstado(User $u, string $estado): void
    {
        if (!isset(self::ESTADOS[$estado])) {
            throw new InvalidArgumentException('Estado desconhecido.');
        }
        if (!$this->naBibliotecaDe($u)) {
            throw new InvalidArgumentException($this->nomeCurto() . ' não está na tua biblioteca.');
        }
        Capsule::table('bibliotecas')->where('user_id', $u->id)->where('serie_id', $this->id)->update(['estado' => $estado]);
    }

    // Convida o par a ver esta série contigo (tem de estar na tua biblioteca e ainda não na dele)
    public function convidar(User $de): void
    {
        $par = $de->parceiro();
        if ($par === null) {
            throw new InvalidArgumentException('Ainda não tens par a quem convidar.');
        }
        if (!$this->naBibliotecaDe($de)) {
            throw new InvalidArgumentException('Primeiro adiciona ' . $this->nomeCurto() . ' à tua biblioteca.');
        }
        $estadoPar = $this->estadoDe($par);
        if ($estadoPar === self::CONVITE) {
            throw new InvalidArgumentException('Já convidaste ' . $par->nome . ' para ' . $this->nomeCurto() . '.');
        }
        if ($estadoPar !== null) {
            throw new InvalidArgumentException($this->nomeCurto() . ' já é dos dois.');
        }
        $this->juntarA($par, self::CONVITE);
    }

    // Juntas-te a uma série que o teu par já tem (tocaste nela na fila "A <par> está a ver"):
    // entra na tua biblioteca em "a ver" e passa a ser dos dois. O teu progresso começa do zero.
    public function juntarSe(User $u): void
    {
        if ($this->naBibliotecaDe($u)) {
            throw new InvalidArgumentException($this->nomeCurto() . ' já está na tua biblioteca.');
        }
        $par = $u->parceiro();
        if ($par === null || !$this->naBibliotecaDe($par)) {
            throw new InvalidArgumentException($this->nomeCurto() . ' não está na biblioteca do teu par.');
        }
        $this->juntarA($u, 'a_ver');
    }

    // Aceitas o convite: a série entra na tua biblioteca, em "a ver" (passa a conjunta)
    public function aceitar(User $quem): void
    {
        if ($this->estadoDe($quem) !== self::CONVITE) {
            throw new InvalidArgumentException('Esse convite já não existe.');
        }
        $this->juntarA($quem, 'a_ver');
    }

    // Recusas o convite (quem foi convidado) ou cancelas o que enviaste (quem convidou)
    public function retirarConvite(User $quem): void
    {
        $par = $quem->parceiro();
        $apagados = Capsule::table('bibliotecas')
            ->where('serie_id', $this->id)
            ->where('estado', self::CONVITE)
            ->whereIn('user_id', array_filter([$quem->id, $par?->id]))
            ->delete();
        if ($apagados === 0) {
            throw new InvalidArgumentException('Esse convite já não existe.');
        }
        $this->apagarSeOrfa();
    }

    // Só podes tirar da TUA biblioteca uma série em que ainda não marcaste episódios (não perdes nada)
    public function podeSerRemovidaPor(User $u): bool
    {
        return $this->naBibliotecaDe($u)
            && !Capsule::table('vistos')
                ->join('episodios', 'episodios.id', '=', 'vistos.episodio_id')
                ->where('episodios.serie_id', $this->id)
                ->where('vistos.user_id', $u->id)
                ->exists();
    }

    // Tira da tua biblioteca (a do teu par fica igual). Um convite que tinhas enviado vai com ela.
    public function removerDe(User $u): void
    {
        if (!$this->podeSerRemovidaPor($u)) {
            throw new InvalidArgumentException('Já marcaste episódios de ' . $this->nomeCurto() . ': não dá para tirar.');
        }
        Capsule::connection()->transaction(function () use ($u) {
            Capsule::table('bibliotecas')->where('user_id', $u->id)->where('serie_id', $this->id)->delete();
            $par = $u->parceiro();
            if ($par !== null) {
                Capsule::table('bibliotecas')->where('user_id', $par->id)->where('serie_id', $this->id)->where('estado', self::CONVITE)->delete();
            }
            $this->apagarSeOrfa();
        });
    }

    // Série do MyAnimeList que já não está na biblioteca de ninguém e sem episódios vistos: apaga-se
    // (as do seed ficam sempre: o seed volta a criá-las a cada deploy)
    private function apagarSeOrfa(): void
    {
        if ($this->doSeed() || Capsule::table('bibliotecas')->where('serie_id', $this->id)->exists()) {
            return;
        }
        $temVistos = Capsule::table('vistos')
            ->join('episodios', 'episodios.id', '=', 'vistos.episodio_id')
            ->where('episodios.serie_id', $this->id)->exists();
        if (!$temVistos) {
            $this->delete();
        }
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
