<?php
// Anexos: valida o tipo REAL do ficheiro (finfo), a extensão e o tamanho;
// guarda com nome aleatório em storage/anexos (fora da pasta pública).
declare(strict_types=1);

final class Upload
{
    // Extensão permitida → tipos MIME aceites para ela (o conteúdo tem de bater com a extensão)
    public const PERMITIDOS = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf'  => ['application/pdf'],
        'txt'  => ['text/plain'],
        'log'  => ['text/plain'],
        'csv'  => ['text/plain', 'text/csv', 'application/csv'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'zip'  => ['application/zip'],
    ];

    // Pasta onde ficam os ficheiros (fora do public/)
    public static function pasta(): string
    {
        return RAIZ . '/storage/anexos';
    }

    // Normaliza o $_FILES de um <input type="file" multiple name="anexos[]"> numa lista simples
    public static function lista(string $campo): array
    {
        $f = $_FILES[$campo] ?? null;
        if (!$f || !is_array($f['name'] ?? null)) {
            return [];
        }
        $lista = [];
        foreach ($f['name'] as $i => $nome) {
            if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $lista[] = [
                'name' => (string) $nome,
                'tmp_name' => (string) $f['tmp_name'][$i],
                'error' => (int) $f['error'][$i],
                'size' => (int) $f['size'][$i],
            ];
        }
        return $lista;
    }

    // Valida todos os ficheiros ANTES de guardar algum. Devolve a lista de erros (vazia = tudo bem).
    public static function validar(array $ficheiros): array
    {
        $erros = [];
        $max = (int) config('anexos.max_por_envio', 3);
        if (count($ficheiros) > $max) {
            $erros[] = "Podes anexar no máximo $max ficheiros de cada vez.";
            return $erros;
        }
        $maxBytes = (int) config('anexos.max_mb', 5) * 1024 * 1024;
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        foreach ($ficheiros as $f) {
            $nome = $f['name'];
            if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
                $erros[] = "Não foi possível receber \"$nome\".";
                continue;
            }
            if ($f['size'] > $maxBytes || $f['size'] === 0) {
                $erros[] = "\"$nome\" passa do limite de " . config('anexos.max_mb', 5) . ' MB.';
                continue;
            }
            $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
            if (!isset(self::PERMITIDOS[$ext])) {
                $erros[] = "\"$nome\": tipo de ficheiro não permitido.";
                continue;
            }
            $mime = (string) $finfo->file($f['tmp_name']);
            if (!in_array($mime, self::PERMITIDOS[$ext], true)) {
                $erros[] = "\"$nome\": o conteúdo não corresponde à extensão.";
            }
        }
        return $erros;
    }

    // Move um ficheiro já validado para storage/anexos com nome aleatório. Devolve os dados para a BD.
    public static function guardar(array $f): array
    {
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $nomeDisco = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], self::pasta() . '/' . $nomeDisco)) {
            throw new RuntimeException('Falhou a gravação do anexo.');
        }
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file(self::pasta() . '/' . $nomeDisco);
        // Nome original só para mostrar: sem caminhos nem caracteres de controlo, até 150 letras
        $original = mb_substr(preg_replace('/[\x00-\x1F\x7F\/\\\\]/u', '', basename($f['name'])) ?? 'ficheiro', 0, 150);
        return ['nome_original' => $original, 'nome_disco' => $nomeDisco, 'mime' => $mime, 'tamanho' => $f['size']];
    }
}
