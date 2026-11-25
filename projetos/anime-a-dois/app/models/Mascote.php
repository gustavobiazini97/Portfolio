<?php
// O mascote do casal: um bicho por par (Raposa ou Gato) que cresce com os episódios vistos pelos dois.
// Na base de dados só ficam a espécie, o nome e a cor (tabela mascotes). Tudo o resto calcula-se dos vistos:
//   fase  — episódios vistos pelos dois (ovo → cria → jovem → adulta → lendária)
//   humor — dias desde o último episódio visto pelos dois (feliz / aborrecida / a dormir; nunca morre)
//   costas — quem está 15+ episódios atrás numa série que veem juntos vê o bicho de costas

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Capsule\Manager as Capsule;

class Mascote extends Model
{
    protected $table = 'mascotes';
    public $timestamps = false;

    protected $fillable = ['user_a', 'user_b', 'especie', 'nome', 'cor'];

    // Espécies da primeira versão (a escolha faz-se uma só vez, quando o ovo choca)
    const ESPECIES = ['raposa', 'gato'];

    // Fase → primeiro episódio visto pelos dois que a alcança (por ordem crescente)
    const FASES = ['ovo' => 0, 'cria' => 10, 'jovem' => 100, 'adulta' => 300, 'lendaria' => 1000];

    const CORES = 6;            // paletas 0–5 (as mesmas das paletas por pessoa)
    const NOME_MAX = 20;
    const DIAS_FELIZ = 2;       // até 2 dias sem verem juntos: feliz
    const DIAS_ABORRECIDA = 6;  // de 3 a 6: aborrecida; 7 ou mais: a dormir
    const ATRAS_MIN = 15;       // episódios de atraso a partir dos quais o bicho vira as costas

    // ---------- Cálculos puros (sem base de dados) ----------

    // Fase pelos episódios vistos pelos dois
    public static function faseDe(int $episodiosJuntos): string
    {
        $fase = 'ovo';
        foreach (self::FASES as $nome => $minimo) {
            if ($episodiosJuntos >= $minimo) {
                $fase = $nome;
            }
        }
        return $fase;
    }

    // Humor pelos dias desde o último episódio visto pelos dois (datas "Y-m-d" ou "Y-m-d H:i:s").
    // Nunca viram nada juntos (null) → feliz: ainda é um ovo à espera.
    public static function humorDe(?string $ultimaVezJuntos, ?string $hoje = null): string
    {
        if ($ultimaVezJuntos === null) {
            return 'feliz';
        }
        $ultima = new DateTimeImmutable(substr($ultimaVezJuntos, 0, 10));
        $agora  = new DateTimeImmutable(substr($hoje ?? date('Y-m-d'), 0, 10));
        $dif    = $ultima->diff($agora);
        $dias   = $dif->invert ? 0 : (int) $dif->days;   // datas no futuro (relógios) contam como hoje

        if ($dias <= self::DIAS_FELIZ) {
            return 'feliz';
        }
        return $dias <= self::DIAS_ABORRECIDA ? 'aborrecida' : 'a_dormir';
    }

    // ---------- Dados do par (a partir dos vistos) ----------

    // Os vistos de $u cruzados com os do par, só nas séries que veem juntos (series_juntos)
    private static function vistosJuntos(User $u, User $par)
    {
        return Capsule::table('vistos AS a')
            ->join('episodios', 'episodios.id', '=', 'a.episodio_id')
            ->join('series_juntos AS sj', fn ($j) => $j->on('sj.serie_id', '=', 'episodios.serie_id')->on('sj.user_id', '=', 'a.user_id'))
            ->join('vistos AS b', fn ($j) => $j->on('b.episodio_id', '=', 'a.episodio_id'))
            ->where('a.user_id', $u->id)
            ->where('sj.com_id', $par->id)
            ->where('b.user_id', $par->id);
    }

    // Episódios que os dois já viram (nas séries que veem juntos)
    public static function episodiosJuntos(User $u, User $par): int
    {
        return (int) self::vistosJuntos($u, $par)->distinct()->count('a.episodio_id');
    }

    // Dia (Y-m-d) em que viram juntos o último episódio: conta-se a hora do segundo a vê-lo. null se nunca viram.
    public static function ultimaVezJuntos(User $u, User $par): ?string
    {
        $d = self::vistosJuntos($u, $par)->selectRaw('MAX(GREATEST(a.visto_em, b.visto_em)) AS d')->value('d');
        return $d === null ? null : substr((string) $d, 0, 10);
    }

    // O maior atraso de cada um numa série que veem juntos: ['eu' => episódios atrás do par, 'par' => atrás de mim]
    public static function episodiosAtras(User $u, User $par): array
    {
        $atras = ['eu' => 0, 'par' => 0];
        $ids = Capsule::table('series_juntos')->where('user_id', $u->id)->where('com_id', $par->id)->pluck('serie_id');
        foreach (Serie::whereIn('id', $ids)->get() as $serie) {
            $meu = $serie->progressoDe($u)['posicao'];
            $dele = $serie->progressoDe($par)['posicao'];
            $atras['eu']  = max($atras['eu'], $dele - $meu);
            $atras['par'] = max($atras['par'], $meu - $dele);
        }
        return $atras;
    }

    // ---------- O mascote de uma pessoa ----------

    // O mascote do par de $u (cria o ovo da primeira vez); null se não tem par
    public static function obter(User $u): ?Mascote
    {
        $par = $u->parceiro();
        if ($par === null) {
            return null;
        }
        $a = min($u->id, $par->id);
        $b = max($u->id, $par->id);
        // insertOrIgnore: dois pedidos ao mesmo tempo não criam dois ovos (chave única user_a+user_b)
        Capsule::table('mascotes')->insertOrIgnore(['user_a' => $a, 'user_b' => $b]);
        return static::where('user_a', $a)->where('user_b', $b)->first();
    }

    // Tudo o que quem desenha precisa; null se não tem par (nunca traz ids de utilizadores)
    public static function estado(User $u): ?array
    {
        $m = self::obter($u);
        if ($m === null) {
            return null;
        }
        $par = $u->parceiro();
        $juntos = self::episodiosJuntos($u, $par);
        $fase = self::faseDe($juntos);

        return [
            'especie'         => $m->especie,
            'nome'            => $m->nome,
            'cor'             => (int) $m->cor,
            'fase'            => $fase,
            'humor'           => self::humorDe(self::ultimaVezJuntos($u, $par)),
            'episodiosJuntos' => $juntos,
            'deCostas'        => self::episodiosAtras($u, $par)['eu'] >= self::ATRAS_MIN,
            'podeEscolher'    => $m->especie === null && $fase !== 'ovo',
        ];
    }

    // Guarda nome, cor e/ou espécie (só os campos que vierem). Valida tudo antes de escrever; mensagens para o utilizador.
    public static function guardar(User $u, array $campos): void
    {
        $m = self::obter($u);
        if ($m === null) {
            throw new InvalidArgumentException('Ainda não tens par, por isso ainda não há mascote.');
        }

        $mudar = [];

        if (array_key_exists('nome', $campos)) {
            $nome = $campos['nome'];
            if (!is_string($nome)) {
                throw new InvalidArgumentException('O nome não é válido.');
            }
            $nome = trim($nome);
            if ($nome === '') {
                throw new InvalidArgumentException('Escreve um nome para o vosso bicho.');
            }
            // preg_match com /u devolve false se o texto não for UTF-8 válido: também se recusa
            if (preg_match('/[\x00-\x1F\x7F]/u', $nome) !== 0) {
                throw new InvalidArgumentException('O nome tem caracteres que não dá para usar.');
            }
            if (mb_strlen($nome) > self::NOME_MAX) {
                throw new InvalidArgumentException('O nome pode ter até ' . self::NOME_MAX . ' letras.');
            }
            $mudar['nome'] = $nome;
        }

        if (array_key_exists('cor', $campos)) {
            $cor = $campos['cor'];
            if (is_string($cor) && preg_match('/^\d$/', $cor)) {
                $cor = (int) $cor;
            }
            if (!is_int($cor) || $cor < 0 || $cor >= self::CORES) {
                throw new InvalidArgumentException('Essa cor não existe.');
            }
            $mudar['cor'] = $cor;
        }

        $especie = null;
        if (array_key_exists('especie', $campos)) {
            $especie = $campos['especie'];
            if (!is_string($especie) || !in_array($especie, self::ESPECIES, true)) {
                throw new InvalidArgumentException('Essa espécie não existe.');
            }
            if (self::faseDe(self::episodiosJuntos($u, $u->parceiro())) === 'ovo') {
                throw new InvalidArgumentException('O ovo ainda não chocou.');
            }
        }

        // Tudo ou nada: se a espécie já tinha sido escolhida, o nome e a cor deste pedido também não ficam
        Capsule::connection()->transaction(function () use ($m, $mudar, $especie) {
            if ($especie !== null) {
                // A condição "especie IS NULL" no UPDATE é o que garante uma só escolha, mesmo com dois pedidos ao mesmo tempo
                $linhas = Capsule::table('mascotes')->where('id', $m->id)->whereNull('especie')->update(['especie' => $especie]);
                if ($linhas === 0) {
                    throw new InvalidArgumentException('Já escolheram a espécie do vosso bicho.');
                }
            }
            if ($mudar !== []) {
                Capsule::table('mascotes')->where('id', $m->id)->update($mudar);
            }
        });
    }
}
