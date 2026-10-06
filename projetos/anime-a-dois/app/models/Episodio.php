<?php
// Um episódio de uma série. "filler" vem do Naruto Fillers (Naruto) ou do MyAnimeList; "recap" só do MyAnimeList.

use Illuminate\Database\Eloquent\Model;

class Episodio extends Model
{
    protected $table = 'episodios';
    public $timestamps = false;

    protected $fillable = ['serie_id', 'numero', 'titulo', 'filler', 'recap'];

    // filler e recap chegam da base de dados como 0/1; aqui passam a true/false
    protected $casts = ['filler' => 'boolean', 'recap' => 'boolean'];

    // Série a que o episódio pertence
    public function serie()
    {
        return $this->belongsTo(Serie::class, 'serie_id');
    }
}
