<?php
// Uma série (Naruto, Naruto Shippuden, Boruto), os seus episódios e o progresso de cada pessoa.

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Capsule\Manager as Capsule;

class Serie extends Model
{
    protected $table = 'series';
    public $timestamps = false;

    protected $fillable = ['slug', 'nome', 'anos', 'total_episodios', 'ordem'];

    // Episódios desta série, sempre por ordem de número
    public function episodios()
    {
        return $this->hasMany(Episodio::class, 'serie_id')->orderBy('numero');
    }

    // Todas as séries pela ordem do filtro (Naruto → Shippuden → Boruto)
    public static function ordenadas()
    {
        return static::orderBy('ordem')->get();
    }

    // Procura pelo slug do URL; null se não existir
    public static function porSlug(?string $slug): ?Serie
    {
        return $slug === null ? null : static::where('slug', $slug)->first();
    }

    // Nome curto para os separadores ("Naruto Shippuden" → "Shippuden")
    public function nomeCurto(): string
    {
        return $this->slug === 'shippuden' ? 'Shippuden' : $this->nome;
    }

    // Cor de acento da série (a mesma do app.css), em hexadecimal; usada pela app Flutter.
    // Séries novas sem cor definida ficam com um lilás neutro.
    public function cor(): string
    {
        return [
            'naruto'    => '#F7C59F',
            'shippuden' => '#F2A7A0',
            'boruto'    => '#A9C4EE',
        ][$this->slug] ?? '#C9C2E0';
    }

    // Frase do Vs: quem vai à frente e por quantos episódios.
    // $meu e $dele são o resultado de progressoDe(); $parceiro null = ainda sem conta
    public static function resumoVs(array $meu, array $dele, ?User $parceiro): string
    {
        if ($parceiro === null) {
            return 'Quando o teu par criar conta, aparece aqui ao teu lado.';
        }

        $dif = $meu['posicao'] - $dele['posicao'];
        if ($dif > 0) {
            return 'Vais ' . plural($dif, 'episódio', 'episódios') . ' à frente.';
        }
        if ($dif < 0) {
            return $parceiro->nome . ' vai ' . plural(-$dif, 'episódio', 'episódios') . ' à frente.';
        }
        return 'Estão os dois no mesmo episódio.';
    }

    // Arcos da série, de 1 ao último episódio, sem buracos: os canónicos vêm de database/arcos.json
    // e os intervalos entre eles viram "Filler" (se a maioria for filler) ou "Episódios avulsos".
    // Devolve [] se a série não tiver arcos no ficheiro (ex.: Boruto).
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
    // cada item tem id, numero, titulo, filler, tu (bool) e par (bool)
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
        foreach ($this->episodios()->get(['id', 'numero', 'titulo', 'filler']) as $ep) {
            $lista[] = [
                'id'     => (int) $ep->id,
                'numero' => (int) $ep->numero,
                'titulo' => $ep->titulo,
                'filler' => $ep->filler,
                'tu'     => isset($doTu[$ep->id]),
                'par'    => isset($doPar[$ep->id]),
                'coment' => (int) ($comentarios[$ep->id] ?? 0),
            ];
        }
        return $lista;
    }
}
