<?php
// Uma série (Naruto, Naruto Shippuden, Boruto) e os seus episódios.

use Illuminate\Database\Eloquent\Model;

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
}
