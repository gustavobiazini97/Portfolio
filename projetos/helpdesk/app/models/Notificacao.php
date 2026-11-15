<?php
// Notificações dentro da app (o sino no topo).
declare(strict_types=1);

final class Notificacao
{
    public static function criar(int $uid, ?int $ticketId, string $mensagem): void
    {
        Database::q(
            'INSERT INTO notificacoes (utilizador_id, ticket_id, mensagem, criado_em) VALUES (?, ?, ?, ?)',
            [$uid, $ticketId, mb_substr($mensagem, 0, 255), agora()]
        );
    }

    public static function naoLidas(int $uid): int
    {
        return (int) Database::valor('SELECT COUNT(*) FROM notificacoes WHERE utilizador_id = ? AND lida_em IS NULL', [$uid]);
    }

    public static function ultimas(int $uid, int $limite = 50): array
    {
        return Database::linhas(
            'SELECT * FROM notificacoes WHERE utilizador_id = ? ORDER BY id DESC LIMIT ' . max(1, min(200, $limite)),
            [$uid]
        );
    }

    // Só marca notificações do próprio (o WHERE inclui o utilizador: ninguém marca as dos outros)
    public static function marcarLida(int $uid, int $id): ?array
    {
        $n = Database::linha('SELECT * FROM notificacoes WHERE id = ? AND utilizador_id = ?', [$id, $uid]);
        if ($n) {
            Database::q('UPDATE notificacoes SET lida_em = ? WHERE id = ? AND lida_em IS NULL', [agora(), $id]);
        }
        return $n;
    }

    public static function marcarTodas(int $uid): void
    {
        Database::q('UPDATE notificacoes SET lida_em = ? WHERE utilizador_id = ? AND lida_em IS NULL', [agora(), $uid]);
    }

    // Abrir um pedido conta como ler as notificações dele
    public static function marcarDoTicket(int $uid, int $ticketId): void
    {
        Database::q(
            'UPDATE notificacoes SET lida_em = ? WHERE utilizador_id = ? AND ticket_id = ? AND lida_em IS NULL',
            [agora(), $uid, $ticketId]
        );
    }
}
