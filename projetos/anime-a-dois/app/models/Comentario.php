<?php
// Comentário de uma pessoa sobre um episódio.

use Illuminate\Database\Eloquent\Model;

class Comentario extends Model
{
    protected $table = 'comentarios';
    public $timestamps = false;

    protected $fillable = ['user_id', 'episodio_id', 'texto', 'criado_em'];

    // Tamanho máximo (igual ao VARCHAR da tabela)
    const MAX = 500;

    public function autor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function episodio()
    {
        return $this->belongsTo(Episodio::class, 'episodio_id');
    }

    // Cria um comentário; lança InvalidArgumentException com a mensagem para o utilizador
    public static function escrever(User $autor, Episodio $episodio, string $texto): Comentario
    {
        // Espaços a mais fora; quebras de linha seguidas reduzidas a uma linha em branco
        $texto = trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', $texto)));

        if ($texto === '') {
            throw new InvalidArgumentException('Escreve alguma coisa primeiro.');
        }
        if (mb_strlen($texto) > self::MAX) {
            throw new InvalidArgumentException('O comentário pode ter no máximo ' . self::MAX . ' caracteres.');
        }

        return static::create([
            'user_id'     => $autor->id,
            'episodio_id' => $episodio->id,
            'texto'       => $texto,
            'criado_em'   => date('Y-m-d H:i:s'),
        ]);
    }

    // Formato enviado ao JavaScript da folha de comentários
    public function paraJson(User $quemVe): array
    {
        $autor = $this->autor;
        return [
            'id'      => $this->id,
            'texto'   => $this->texto,
            'autor'   => $autor->nome,
            'meu'     => $autor->id === $quemVe->id,
            'foto'    => $autor->fotoUrl(),
            'inicial' => $autor->inicial(),
            'quando'  => tempo_relativo($this->criado_em),
        ];
    }
}
