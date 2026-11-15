<?php
// Anexos dos pedidos (metadados na BD, ficheiro em storage/anexos).
declare(strict_types=1);

final class Anexo
{
    public static function criar(int $ticketId, ?int $comentarioId, int $uid, array $f): void
    {
        Database::q(
            'INSERT INTO anexos (ticket_id, comentario_id, nome_original, nome_disco, mime, tamanho, enviado_por, criado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$ticketId, $comentarioId, $f['nome_original'], $f['nome_disco'], $f['mime'], $f['tamanho'], $uid, agora()]
        );
    }

    public static function porId(int $id): ?array
    {
        return Database::linha('SELECT * FROM anexos WHERE id = ?', [$id]);
    }

    // Anexos do pedido agrupados por comentário (chave 0 = anexos da abertura do pedido)
    public static function doTicketAgrupados(int $ticketId): array
    {
        $grupos = [];
        foreach (Database::linhas('SELECT * FROM anexos WHERE ticket_id = ? ORDER BY id', [$ticketId]) as $a) {
            $grupos[(int) ($a['comentario_id'] ?? 0)][] = $a;
        }
        return $grupos;
    }

    // Guarda os ficheiros já validados e liga-os ao pedido/comentário
    public static function guardarTodos(array $ficheiros, int $ticketId, ?int $comentarioId, int $uid): void
    {
        foreach ($ficheiros as $f) {
            self::criar($ticketId, $comentarioId, $uid, Upload::guardar($f));
        }
    }

    // "1,2 MB" / "340 KB"
    public static function tamanhoLegivel(int $bytes): string
    {
        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1, ',', '') . ' MB'
            : max(1, (int) round($bytes / 1024)) . ' KB';
    }
}
