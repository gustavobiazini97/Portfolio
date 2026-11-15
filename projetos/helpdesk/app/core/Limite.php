<?php
// Rate limiting na base de dados: conta acontecimentos por chave numa janela de tempo.
// Usado no login (por email e por IP), na recuperação de palavra-passe e na criação de pedidos.
declare(strict_types=1);

final class Limite
{
    // Regista mais um acontecimento para a chave (ex.: "login:ana@empresa.pt")
    public static function registar(string $chave): void
    {
        Database::q('INSERT INTO limites (chave, criado_em) VALUES (?, ?)', [self::chave($chave), agora()]);
    }

    // Quantos acontecimentos houve nos últimos $minutos
    public static function contar(string $chave, int $minutos): int
    {
        return (int) Database::valor(
            'SELECT COUNT(*) FROM limites WHERE chave = ? AND criado_em >= ?',
            [self::chave($chave), date('Y-m-d H:i:s', time() - $minutos * 60)]
        );
    }

    // Passou o máximo permitido na janela?
    public static function excedido(string $chave, int $maximo, int $minutos): bool
    {
        return self::contar($chave, $minutos) >= $maximo;
    }

    // Data do último acontecimento (ou null)
    public static function ultimo(string $chave): ?string
    {
        $v = Database::valor('SELECT MAX(criado_em) FROM limites WHERE chave = ?', [self::chave($chave)]);
        return $v ? (string) $v : null;
    }

    // Apaga o histórico da chave (ex.: login certo limpa as falhas)
    public static function limpar(string $chave): void
    {
        Database::q('DELETE FROM limites WHERE chave = ?', [self::chave($chave)]);
    }

    // Limpeza periódica (cron): nada com mais de 2 dias interessa
    public static function limparAntigos(): void
    {
        Database::q('DELETE FROM limites WHERE criado_em < ?', [date('Y-m-d H:i:s', time() - 2 * 86400)]);
    }

    // As chaves guardam-se em hash: o email não fica em texto na tabela e o tamanho é fixo
    private static function chave(string $chave): string
    {
        return hash('sha256', mb_strtolower($chave));
    }
}
