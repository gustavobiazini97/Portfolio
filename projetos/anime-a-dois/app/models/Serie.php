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

        $lista = [];
        foreach ($this->episodios()->get(['id', 'numero', 'titulo', 'filler']) as $ep) {
            $lista[] = [
                'id'     => (int) $ep->id,
                'numero' => (int) $ep->numero,
                'titulo' => $ep->titulo,
                'filler' => $ep->filler,
                'tu'     => isset($doTu[$ep->id]),
                'par'    => isset($doPar[$ep->id]),
            ];
        }
        return $lista;
    }
}
