<?php
// Um episódio de uma série. "filler" vem dos dados do Naruto Fillers.

use Illuminate\Database\Eloquent\Model;

class Episodio extends Model
{
    protected $table = 'episodios';
    public $timestamps = false;

    protected $fillable = ['serie_id', 'numero', 'titulo', 'filler'];

    // filler chega da base de dados como 0/1; aqui passa a true/false
    protected $casts = ['filler' => 'boolean'];

    // Série a que o episódio pertence
    public function serie()
    {
        return $this->belongsTo(Serie::class, 'serie_id');
    }
}
