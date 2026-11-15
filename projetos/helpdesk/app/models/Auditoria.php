<?php
// Registo de auditoria: logins (certos e falhados), acessos negados, mudanças de permissões e contas.
declare(strict_types=1);

final class Auditoria
{
    public static function registar(string $acao, string $detalhe = '', ?int $uid = null): void
    {
        try {
            Database::q(
                'INSERT INTO auditoria (utilizador_id, acao, detalhe, ip, criado_em) VALUES (?, ?, ?, ?, ?)',
                [$uid ?? ($_SESSION['uid'] ?? null), $acao, mb_substr($detalhe, 0, 255), ip_cliente(), agora()]
            );
        } catch (Throwable $e) {
            // A auditoria nunca pode deitar abaixo o pedido; fica pelo menos no log do PHP
            error_log('Auditoria falhou: ' . $e->getMessage());
        }
    }

    // Últimos registos (página do admin), com o nome de quem fez
    public static function ultimos(int $limite = 200, string $acao = ''): array
    {
        $sql = 'SELECT a.*, u.nome, u.email FROM auditoria a LEFT JOIN utilizadores u ON u.id = a.utilizador_id';
        $params = [];
        if ($acao !== '') {
            $sql .= ' WHERE a.acao = ?';
            $params[] = $acao;
        }
        return Database::linhas($sql . ' ORDER BY a.id DESC LIMIT ' . max(1, min(1000, $limite)), $params);
    }

    // Ações distintas (para o filtro)
    public static function acoes(): array
    {
        return array_column(Database::linhas('SELECT DISTINCT acao FROM auditoria ORDER BY acao'), 'acao');
    }
}
