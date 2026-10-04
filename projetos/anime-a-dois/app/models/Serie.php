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

    // Está na biblioteca desta pessoa?
    public function naBibliotecaDe(?User $u): bool
    {
        return $this->estadoDe($u) !== null;
    }

    // Com quem esta pessoa vê esta série: os companheiros (par e/ou amigos), por ordem de entrada.
    // Cada ligação é de duas pessoas (tabela series_juntos, uma linha por sentido); podes ver a mesma
    // série com várias pessoas, e cada uma delas com outras. Coleção vazia = vê sozinha.
    public function companheirosDe(?User $u)
    {
        if ($u === null) {
            return collect();
        }
        $ids = Capsule::table('series_juntos')->where('serie_id', $this->id)->where('user_id', $u->id)->orderBy('com_id')->pluck('com_id')->all();
        return $ids === [] ? collect() : User::whereIn('id', $ids)->orderBy('id')->get();
    }

    // Estas duas pessoas vêem esta série juntas?
    public function verCom(User $a, User $b): bool
    {
        return Capsule::table('series_juntos')->where('serie_id', $this->id)->where('user_id', $a->id)->where('com_id', $b->id)->exists();
    }

    // Séries da biblioteca de uma pessoa, pela ordem simples: estado e ordem de entrada.
    // Para a fila do Início (com progresso e atividade) usa-se biblioteca().
    public static function naBiblioteca(User $u)
    {
        return static::join('bibliotecas', 'bibliotecas.serie_id', '=', 'series.id')
            ->where('bibliotecas.user_id', $u->id)
            ->orderByRaw("FIELD(bibliotecas.estado, 'a_ver', 'pausa', 'acabado')")
            ->orderBy('series.ordem')->orderBy('series.id')
            ->select('series.*', 'bibliotecas.estado AS meu_estado')
            ->get();
    }

    // Fila de capas: as séries da biblioteca de $dono, cada uma com a percentagem dele, os companheiros
    // (quem a vê com ele) e a percentagem de cada um, o estado e a última atividade.
    // Ordem: a ver → em pausa → acabado; dentro de cada estado, a mais mexida (ou a mais recente) primeiro.
    //   $excluirDe = não mostrar as que esta pessoa também tem (fila "A Andreia está a ver")
    // Cada item: serie, estado, conjunta (bool), companheiros ([user, pct]), tu (%), atividade
    public static function biblioteca(User $dono, ?User $excluirDe = null): array
    {
        $linhas = Capsule::table('bibliotecas')->where('user_id', $dono->id)->get()->keyBy('serie_id');
        if ($excluirDe !== null) {
            $tem = Capsule::table('bibliotecas')->where('user_id', $excluirDe->id)->pluck('serie_id')->flip();
            $linhas = $linhas->reject(fn ($l) => isset($tem[$l->serie_id]));
        }
        if ($linhas->isEmpty()) {
            return [];
        }
        $series = static::whereIn('id', $linhas->keys()->all())->get();

        // Companheiros de cada série (quem a vê com o dono)
        $juntos = Capsule::table('series_juntos')->where('user_id', $dono->id)->whereIn('serie_id', $linhas->keys()->all())
            ->orderBy('com_id')->get()->groupBy('serie_id');
        $pessoasIds = $juntos->flatten()->pluck('com_id')->unique()->all();
        $pessoas = $pessoasIds === [] ? collect() : User::whereIn('id', $pessoasIds)->get()->keyBy('id');

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

        $pct = fn (Serie $s, ?int $uid) => ($uid && isset($porSerie[$s->id][$uid]) && $s->total_episodios > 0)
            ? min(100, (int) round($porSerie[$s->id][$uid]->n / $s->total_episodios * 100))
            : 0;

        $itens = [];
        foreach ($series as $s) {
            $linha = $linhas[$s->id];
            $comIds = ($juntos[$s->id] ?? collect())->pluck('com_id')->all();
            // Atividade: o último episódio marcado pelo dono (ou pelos companheiros), ou quando entrou
            $atividade = (string) $linha->desde;
            foreach ($porSerie[$s->id] ?? [] as $pessoa => $c) {
                if ($pessoa == $dono->id || in_array($pessoa, $comIds)) {
                    $atividade = max($atividade, (string) $c->ultimo);
                }
            }
            $companheiros = [];
            foreach ($comIds as $cid) {
                if (isset($pessoas[$cid])) {
                    $companheiros[] = ['user' => $pessoas[$cid], 'pct' => $pct($s, $cid)];
                }
            }
            $itens[] = [
                'serie'        => $s,
                'estado'       => $linha->estado,
                'conjunta'     => $companheiros !== [],
                'companheiros' => $companheiros,       // [['user' => User, 'pct' => int], ...]
                'tu'           => $pct($s, $dono->id),
                'atividade'    => $atividade,
            ];
        }

        $rank = array_flip(array_keys(self::ESTADOS));
        usort($itens, fn ($a, $b) =>
            [$rank[$a['estado']] ?? 0, $b['atividade'], $a['serie']->ordem, $a['serie']->id]
            <=> [$rank[$b['estado']] ?? 0, $a['atividade'], $b['serie']->ordem, $b['serie']->id]);

        return $itens;
    }

    // Convites "Quero ver contigo": os que recebeste e os que enviaste e ainda estão por responder.
    // Cada item: ['serie' => Serie, 'recebido' => bool, 'de' => User (quem convidou), 'para' => User (quem foi convidado)]
    public static function convites(User $tu): array
    {
        $linhas = Capsule::table('convites_serie')->where(fn ($q) => $q->where('de_id', $tu->id)->orWhere('para_id', $tu->id))
            ->orderByDesc('criado_em')->get();
        if ($linhas->isEmpty()) {
            return [];
        }
        $series  = static::whereIn('id', $linhas->pluck('serie_id')->all())->get()->keyBy('id');
        $pessoas = User::whereIn('id', $linhas->pluck('de_id')->merge($linhas->pluck('para_id'))->unique()->all())->get()->keyBy('id');

        $lista = [];
        foreach ($linhas as $l) {
            if (isset($series[$l->serie_id], $pessoas[$l->de_id], $pessoas[$l->para_id])) {
                $lista[] = ['serie' => $series[$l->serie_id], 'recebido' => (int) $l->para_id === $tu->id,
                            'de' => $pessoas[$l->de_id], 'para' => $pessoas[$l->para_id]];
            }
        }
        return $lista;
    }

    // Para a pesquisa: mal_id → 'biblioteca' (já tens) ou 'convite' (alguém te convidou para ela)
    public static function situacaoNaPesquisa(User $tu): array
    {
        $mapa = [];
        $linhas = Capsule::table('bibliotecas')
            ->join('series', 'series.id', '=', 'bibliotecas.serie_id')
            ->whereNotNull('series.mal_id')->where('bibliotecas.user_id', $tu->id)
            ->get(['series.mal_id']);
        foreach ($linhas as $l) {
            $mapa[$l->mal_id] = 'biblioteca';
        }
        $convidadas = Capsule::table('convites_serie')
            ->join('series', 'series.id', '=', 'convites_serie.serie_id')
            ->whereNotNull('series.mal_id')->where('convites_serie.para_id', $tu->id)
            ->get(['series.mal_id']);
        foreach ($convidadas as $l) {
            $mapa[$l->mal_id] ??= 'convite';
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

    // Põe a série na biblioteca de $autor e, se $convidarA, convida essa pessoa (par ou amigo) a vê-la com ele.
    // Se a série ainda não existe na app, é criada com todos os episódios.
    // $info e $episodios já validados por DadosAnime::info() e DadosAnime::episodios().
    // $comFillers = os episódios vieram do Jikan (com filler/recap); senão o browser tenta mais tarde.
    public static function adicionarDoMal(User $autor, array $info, array $episodios, ?User $convidarA, bool $comFillers = false): Serie
    {
        $existe = static::where('mal_id', $info['mal_id'])->first();
        if ($existe !== null) {
            if ($existe->naBibliotecaDe($autor)) {
                throw new InvalidArgumentException($existe->nome . ' já está na tua biblioteca.');
            }
            if (Capsule::table('convites_serie')->where('serie_id', $existe->id)->where('para_id', $autor->id)->exists()) {
                throw new InvalidArgumentException('Já tens um convite para ' . $existe->nome . ': aceita-o no Início.');
            }
            // A série já existe na app (de outra pessoa, ou de ninguém): entra na tua biblioteca, sem partilhar
            return Capsule::connection()->transaction(function () use ($existe, $autor, $convidarA) {
                $existe->juntarA($autor, 'a_ver');
                if ($convidarA !== null) {
                    $existe->convidar($autor, $convidarA);
                }
                return $existe;
            });
        }

        $total = self::totalDe($info, $episodios);
        if ($total < 1) {
            throw new InvalidArgumentException('Ainda não se sabe quantos episódios tem ' . $info['nome'] . '.');
        }

        return Capsule::connection()->transaction(function () use ($autor, $info, $episodios, $total, $convidarA, $comFillers) {
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
            if ($convidarA !== null) {
                $serie->convidar($autor, $convidarA);
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

    // ---------- Estado, companheiro, convites e tirar ----------
    // Cada pessoa tem a sua biblioteca. Uma série pode ser vista COM outras pessoas (par e/ou amigos):
    // os companheiros. Duas pessoas ficam ligadas (series_juntos) por convite aceite ou quando uma se
    // junta à série da outra. Pode haver vários companheiros por série.

    // Põe a série na biblioteca de uma pessoa (sem companheiro)
    private function juntarA(User $u, string $estado): void
    {
        Capsule::table('bibliotecas')->updateOrInsert(
            ['user_id' => $u->id, 'serie_id' => $this->id],
            ['estado' => $estado, 'desde' => date('Y-m-d H:i:s')]
        );
    }

    // Liga duas pessoas: passam a ver esta série juntas (uma linha por sentido)
    private function ligar(User $a, User $b): void
    {
        Capsule::table('series_juntos')->insertOrIgnore([
            ['serie_id' => $this->id, 'user_id' => $a->id, 'com_id' => $b->id],
            ['serie_id' => $this->id, 'user_id' => $b->id, 'com_id' => $a->id],
        ]);
        // Convites entre estas duas pessoas já não fazem sentido
        Capsule::table('convites_serie')->where('serie_id', $this->id)
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('de_id', $a->id)->where('para_id', $b->id))
                                 ->orWhere(fn ($w) => $w->where('de_id', $b->id)->where('para_id', $a->id)))->delete();
    }

    // Muda o estado na TUA biblioteca (o do companheiro fica como está)
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

    // Convida $para (o par ou um amigo) a ver esta série contigo. Tu tens de a ter.
    // Se $para já a tem sozinho, aceitar liga-vos sem perder o progresso dele.
    public function convidar(User $de, User $para): void
    {
        if (!$de->ligadoA($para)) {
            throw new InvalidArgumentException('Só podes convidar o teu par ou os teus amigos.');
        }
        if (!$this->naBibliotecaDe($de)) {
            throw new InvalidArgumentException('Primeiro adiciona ' . $this->nomeCurto() . ' à tua biblioteca.');
        }
        if ($this->verCom($de, $para)) {
            throw new InvalidArgumentException('Já vês ' . $this->nomeCurto() . ' com ' . $para->nome . '.');
        }
        if (Capsule::table('convites_serie')->where('serie_id', $this->id)->where('de_id', $de->id)->where('para_id', $para->id)->exists()) {
            throw new InvalidArgumentException('Já convidaste ' . $para->nome . ' para ' . $this->nomeCurto() . '.');
        }
        Capsule::table('convites_serie')->insert(['serie_id' => $this->id, 'de_id' => $de->id, 'para_id' => $para->id]);
    }

    // Aceitas o convite de $de: a série entra na tua biblioteca (se ainda não a tinhas) e passam a vê-la juntos
    public function aceitar(User $quem, User $de): void
    {
        $existe = Capsule::table('convites_serie')->where('serie_id', $this->id)->where('de_id', $de->id)->where('para_id', $quem->id)->exists();
        if (!$existe) {
            throw new InvalidArgumentException('Esse convite já não existe.');
        }
        if ($this->verCom($quem, $de)) {
            throw new InvalidArgumentException('Já vêem ' . $this->nomeCurto() . ' juntos.');
        }
        Capsule::connection()->transaction(function () use ($quem, $de) {
            if (!$this->naBibliotecaDe($quem)) {
                $this->juntarA($quem, 'a_ver');
            }
            $this->ligar($quem, $de);
        });
    }

    // Recusas o convite de $outro (ou cancelas o que lhe enviaste)
    public function retirarConvite(User $quem, User $outro): void
    {
        $apagados = Capsule::table('convites_serie')->where('serie_id', $this->id)
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('de_id', $outro->id)->where('para_id', $quem->id))
                                 ->orWhere(fn ($w) => $w->where('de_id', $quem->id)->where('para_id', $outro->id)))
            ->delete();
        if ($apagados === 0) {
            throw new InvalidArgumentException('Esse convite já não existe.');
        }
    }

    // Entras numa série que o teu par ou um amigo já tem (fila "está a ver", perfil de um amigo).
    //   $com = null → entra na tua biblioteca, cada um vê a sua (sem partilhar nada);
    //   $com = ele  → passam a vê-la juntos (ele é avisado)
    public function juntarSe(User $u, ?User $com): void
    {
        if ($this->naBibliotecaDe($u)) {
            throw new InvalidArgumentException($this->nomeCurto() . ' já está na tua biblioteca.');
        }
        if ($com !== null) {
            if (!$u->ligadoA($com) || !$this->naBibliotecaDe($com)) {
                throw new InvalidArgumentException($this->nomeCurto() . ' não está na biblioteca dessa pessoa.');
            }
        }
        Capsule::connection()->transaction(function () use ($u, $com) {
            $this->juntarA($u, 'a_ver');
            if ($com !== null) {
                $this->ligar($u, $com);
            }
        });
    }

    // Tu e $com deixam de ver a série juntos (cada um fica com a sua biblioteca e o seu progresso)
    public function deixarDeVerJuntos(User $u, User $com): void
    {
        $apagados = Capsule::table('series_juntos')->where('serie_id', $this->id)
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('user_id', $u->id)->where('com_id', $com->id))
                                 ->orWhere(fn ($w) => $w->where('user_id', $com->id)->where('com_id', $u->id)))->delete();
        if ($apagados === 0) {
            throw new InvalidArgumentException('Não vês ' . $this->nomeCurto() . ' com ' . $com->nome . '.');
        }
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

    // Tira da tua biblioteca (a dos outros fica igual). As ligações e os convites desta série vão-se.
    public function removerDe(User $u): void
    {
        if (!$this->podeSerRemovidaPor($u)) {
            throw new InvalidArgumentException('Já marcaste episódios de ' . $this->nomeCurto() . ': não dá para tirar.');
        }
        Capsule::connection()->transaction(function () use ($u) {
            Capsule::table('bibliotecas')->where('user_id', $u->id)->where('serie_id', $this->id)->delete();
            Capsule::table('series_juntos')->where('serie_id', $this->id)
                ->where(fn ($q) => $q->where('user_id', $u->id)->orWhere('com_id', $u->id))->delete();
            Capsule::table('convites_serie')->where('serie_id', $this->id)
                ->where(fn ($q) => $q->where('de_id', $u->id)->orWhere('para_id', $u->id))->delete();
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
    // cada item tem id, numero, titulo, filler, recap, tu (bool), com (bool por companheiro, na ordem de $companheiros) e coment
    public function episodiosPara(User $tu, $companheiros): array   // $companheiros = Users que vêem esta série contigo
    {
        $companheiros = collect($companheiros)->values();
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
        $doCom = $companheiros->map(fn ($c) => $vistosDe($c))->all();

        // Quantos comentários tem cada episódio desta série (uma só consulta)
        $comentarios = Capsule::table('comentarios')
            ->join('episodios', 'episodios.id', '=', 'comentarios.episodio_id')
            ->where('episodios.serie_id', $this->id)
            ->whereIn('comentarios.user_id', array_merge([$tu->id], $companheiros->pluck('id')->all()))   // só os teus e os dos companheiros
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
                'com'    => array_map(fn ($v) => isset($v[$ep->id]), $doCom),
                'coment' => (int) ($comentarios[$ep->id] ?? 0),
            ];
        }
        return $lista;
    }
}
