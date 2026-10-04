<?php
// Foto de perfil de um utilizador (tabela fotos, uma linha por pessoa).

use Illuminate\Database\Eloquent\Model;

class Foto extends Model
{
    protected $table = 'fotos';
    protected $primaryKey = 'user_id';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['user_id', 'imagem', 'tipo', 'atualizada_em'];

    // A imagem nunca vai para arrays/JSON (são bytes)
    protected $hidden = ['imagem'];

    // Lado do quadrado guardado, em píxeis (o maior avatar da app tem 96px; 320 cobre ecrãs 3x)
    const LADO = 320;

    // Recebe o caminho de um ficheiro enviado, valida que é imagem, corta ao centro e reduz.
    // Devolve [bytes, tipo]; lança InvalidArgumentException com a mensagem para o utilizador.
    public static function processar(string $caminho): array
    {
        if (!function_exists('imagecreatefromstring')) {
            throw new InvalidArgumentException('O servidor não consegue processar imagens.');
        }

        // getimagesize lê o cabeçalho: um ficheiro que não é imagem falha aqui
        $info = @getimagesize($caminho);
        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
            throw new InvalidArgumentException('Escolhe uma imagem JPG, PNG ou WebP.');
        }

        $origem = @imagecreatefromstring(file_get_contents($caminho));
        if ($origem === false) {
            throw new InvalidArgumentException('Não consegui ler essa imagem.');
        }

        // Corte quadrado ao centro
        $w = imagesx($origem);
        $h = imagesy($origem);
        $lado = min($w, $h);
        $x = intdiv($w - $lado, 2);
        $y = intdiv($h - $lado, 2);

        $final = imagecreatetruecolor(self::LADO, self::LADO);
        imagecopyresampled($final, $origem, 0, 0, $x, $y, self::LADO, self::LADO, $lado, $lado);

        // Reescrever a imagem também apaga os metadados (EXIF, GPS) da foto original
        ob_start();
        if (function_exists('imagewebp')) {
            imagewebp($final, null, 82);
            $tipo = 'image/webp';
        } else {
            imagejpeg($final, null, 85);
            $tipo = 'image/jpeg';
        }
        $bytes = ob_get_clean();

        imagedestroy($origem);
        imagedestroy($final);

        return [$bytes, $tipo];
    }
}
