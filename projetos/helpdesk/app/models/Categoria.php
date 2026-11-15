<?php
// Categorias dos pedidos e o técnico que fica com eles por defeito.
declare(strict_types=1);

final class Categoria
{
    public static function porId(int $id): ?array
    {
        return Database::linha('SELECT * FROM categorias WHERE id = ?', [$id]);
    }

    // Categorias ativas (para o formulário de novo pedido e a FAQ)
    public static function ativas(): array
    {
        return Database::linhas('SELECT * FROM categorias WHERE ativa = 1 ORDER BY nome');
    }

    // Todas, com o nome do técnico padrão e o número de pedidos abertos (página do admin)
    public static function todasComDados(): array
    {
        return Database::linhas(
            "SELECT c.*, u.nome AS tecnico_nome,
                    (SELECT COUNT(*) FROM tickets t WHERE t.categoria_id = c.id AND t.estado IN ('novo','em_curso','aguarda_utilizador','aguarda_terceiros')) AS abertos
               FROM categorias c LEFT JOIN utilizadores u ON u.id = c.tecnico_padrao_id
              ORDER BY c.ativa DESC, c.nome"
        );
    }

    // Cria ou atualiza (id 0 = nova)
    public static function guardar(int $id, string $nome, string $descricao, ?int $tecnicoId, bool $ativa): int
    {
        if ($id > 0) {
            Database::q(
                'UPDATE categorias SET nome = ?, descricao = ?, tecnico_padrao_id = ?, ativa = ? WHERE id = ?',
                [$nome, $descricao ?: null, $tecnicoId, $ativa ? 1 : 0, $id]
            );
            return $id;
        }
        Database::q(
            'INSERT INTO categorias (nome, descricao, tecnico_padrao_id, ativa) VALUES (?, ?, ?, ?)',
            [$nome, $descricao ?: null, $tecnicoId, $ativa ? 1 : 0]
        );
        return Database::ultimoId();
    }
}
