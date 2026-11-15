<?php
// Ligação única à base de dados (PDO). Todas as queries usam prepared statements.
declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    // Devolve a ligação, criada na primeira chamada
    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                config('db.host', '127.0.0.1'),
                (int) config('db.port', 3306),
                config('db.database', 'helpdesk')
            );
            self::$pdo = new PDO($dsn, (string) config('db.username', ''), (string) config('db.password', ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // qualquer erro SQL é exceção
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,                    // prepared statements reais
            ]);
        }
        return self::$pdo;
    }

    // Executa uma query com parâmetros e devolve o statement
    public static function q(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    // Uma linha (ou null)
    public static function linha(string $sql, array $params = []): ?array
    {
        $r = self::q($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    // Todas as linhas
    public static function linhas(string $sql, array $params = []): array
    {
        return self::q($sql, $params)->fetchAll();
    }

    // Um único valor (primeira coluna da primeira linha)
    public static function valor(string $sql, array $params = []): mixed
    {
        $v = self::q($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    // Id gerado pelo último INSERT
    public static function ultimoId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }

    // Corre várias operações numa transação: ou ficam todas, ou nenhuma
    public static function transacao(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $r = $fn();
            $pdo->commit();
            return $r;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
