<?php
// Conversa do pedido: respostas públicas e notas internas do TI.
declare(strict_types=1);

final class Comentario
{
    public static function criar(int $ticketId, int $autorId, string $corpo, bool $interno): int
    {
        Database::q(
            'INSERT INTO comentarios (ticket_id, autor_id, corpo, interno, criado_em) VALUES (?, ?, ?, ?, ?)',
            [$ticketId, $autorId, $corpo, $interno ? 1 : 0, agora()]
        );
        return Database::ultimoId();
    }

    // Comentários do pedido; as notas internas só entram para o TI
    public static function doTicket(int $ticketId, bool $incluirInternos): array
    {
        return Database::linhas(
            'SELECT c.*, u.nome, u.papel FROM comentarios c JOIN utilizadores u ON u.id = c.autor_id
              WHERE c.ticket_id = ?' . ($incluirInternos ? '' : ' AND c.interno = 0') . ' ORDER BY c.criado_em, c.id',
            [$ticketId]
        );
    }

    // Última resposta pública do TI (usada para criar um artigo da FAQ a partir do pedido)
    public static function ultimaRespostaTi(int $ticketId): ?array
    {
        return Database::linha(
            "SELECT c.* FROM comentarios c JOIN utilizadores u ON u.id = c.autor_id
              WHERE c.ticket_id = ? AND c.interno = 0 AND u.papel IN ('tecnico','admin')
              ORDER BY c.id DESC LIMIT 1",
            [$ticketId]
        );
    }
}
