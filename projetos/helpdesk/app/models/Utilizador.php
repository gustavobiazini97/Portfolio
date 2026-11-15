<?php
// Contas (funcionários, técnicos, admins) e tokens de recuperação de palavra-passe.
declare(strict_types=1);

final class Utilizador
{
    // Campos que um admin pode alterar (whitelist contra mass assignment)
    private const EDITAVEIS_ADMIN = ['nome', 'email', 'papel', 'departamento', 'ativo'];

    public static function porId(int $id): ?array
    {
        return Database::linha('SELECT * FROM utilizadores WHERE id = ?', [$id]);
    }

    public static function porEmail(string $email): ?array
    {
        return Database::linha('SELECT * FROM utilizadores WHERE email = ?', [mb_strtolower(trim($email))]);
    }

    // Lista para o admin, com pesquisa por nome/email
    public static function todos(string $pesquisa = ''): array
    {
        if ($pesquisa !== '') {
            $like = '%' . $pesquisa . '%';
            return Database::linhas(
                'SELECT * FROM utilizadores WHERE nome LIKE ? OR email LIKE ? OR departamento LIKE ? ORDER BY ativo DESC, nome',
                [$like, $like, $like]
            );
        }
        return Database::linhas('SELECT * FROM utilizadores ORDER BY ativo DESC, nome');
    }

    // Técnicos e admins ativos (para atribuir pedidos e notificar)
    public static function equipaTi(): array
    {
        return Database::linhas("SELECT * FROM utilizadores WHERE papel IN ('tecnico','admin') AND ativo = 1 ORDER BY nome");
    }

    // Admins ativos (recebem os alertas de SLA ultrapassado)
    public static function admins(): array
    {
        return Database::linhas("SELECT * FROM utilizadores WHERE papel = 'admin' AND ativo = 1");
    }

    // Cria uma conta com palavra-passe temporária (obriga a trocar no primeiro login)
    public static function criar(array $d, string $passwordTemporaria): int
    {
        Database::q(
            'INSERT INTO utilizadores (nome, email, password_hash, papel, departamento, ativo, mudar_password, criado_em)
             VALUES (?, ?, ?, ?, ?, 1, 1, ?)',
            [
                $d['nome'],
                mb_strtolower($d['email']),
                password_hash($passwordTemporaria, PASSWORD_DEFAULT),
                $d['papel'],
                $d['departamento'] ?: null,
                agora(),
            ]
        );
        return Database::ultimoId();
    }

    // Atualiza só os campos permitidos
    public static function atualizar(int $id, array $d): void
    {
        $campos = array_intersect_key($d, array_flip(self::EDITAVEIS_ADMIN));
        if (!$campos) {
            return;
        }
        if (isset($campos['email'])) {
            $campos['email'] = mb_strtolower($campos['email']);
        }
        $sets = implode(', ', array_map(fn ($c) => "$c = ?", array_keys($campos)));
        Database::q("UPDATE utilizadores SET $sets WHERE id = ?", [...array_values($campos), $id]);
    }

    // Nova palavra-passe (hash bcrypt/argon do PHP); $temporaria = obriga a trocar no próximo login
    public static function definirPassword(int $id, string $password, bool $temporaria = false): void
    {
        Database::q(
            'UPDATE utilizadores SET password_hash = ?, mudar_password = ? WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), $temporaria ? 1 : 0, $id]
        );
    }

    // Se o hash foi feito com um algoritmo/custo antigo, refaz com o atual (no login, quando temos a password)
    public static function atualizarHashSePreciso(array $u, string $password): void
    {
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            Database::q('UPDATE utilizadores SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
        }
    }

    public static function registarLogin(int $id): void
    {
        Database::q('UPDATE utilizadores SET ultimo_login = ? WHERE id = ?', [agora(), $id]);
    }

    public static function preferenciaEmail(int $id, bool $quer): void
    {
        Database::q('UPDATE utilizadores SET notif_email = ? WHERE id = ?', [$quer ? 1 : 0, $id]);
    }

    public static function mudarNome(int $id, string $nome): void
    {
        Database::q('UPDATE utilizadores SET nome = ? WHERE id = ?', [$nome, $id]);
    }

    // ─── 2FA ───────────────────────────────────────────────────────────────

    // Guarda um segredo novo (ainda não ativo: só ativa depois de confirmar um código)
    public static function guardarSegredo2fa(int $id, string $segredo): void
    {
        Database::q('UPDATE utilizadores SET totp_segredo = ?, totp_ativo = 0, totp_ultimo = NULL WHERE id = ?', [$segredo, $id]);
    }

    public static function ativar2fa(int $id, int $passo): void
    {
        Database::q('UPDATE utilizadores SET totp_ativo = 1, totp_ultimo = ? WHERE id = ?', [$passo, $id]);
    }

    public static function desativar2fa(int $id): void
    {
        Database::q('UPDATE utilizadores SET totp_segredo = NULL, totp_ativo = 0, totp_ultimo = NULL WHERE id = ?', [$id]);
    }

    // Último código usado (impede usar o mesmo código duas vezes)
    public static function registarPasso2fa(int $id, int $passo): void
    {
        Database::q('UPDATE utilizadores SET totp_ultimo = ? WHERE id = ?', [$passo, $id]);
    }

    // ─── Recuperação de palavra-passe ─────────────────────────────────────

    // Cria um link de uso único válido 30 minutos. Na BD fica só o hash do token.
    public static function criarTokenReset(int $id): string
    {
        // Um pedido novo invalida os anteriores
        Database::q('UPDATE tokens_reset SET usado_em = ? WHERE utilizador_id = ? AND usado_em IS NULL', [agora(), $id]);
        $token = bin2hex(random_bytes(32));
        Database::q(
            'INSERT INTO tokens_reset (utilizador_id, token_hash, expira_em, criado_em) VALUES (?, ?, ?, ?)',
            [$id, hash('sha256', $token), date('Y-m-d H:i:s', time() + 1800), agora()]
        );
        return $token;
    }

    // Token válido (existe, não usado, não expirado) → linha do token; senão null
    public static function tokenResetValido(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        return Database::linha(
            'SELECT * FROM tokens_reset WHERE token_hash = ? AND usado_em IS NULL AND expira_em > ?',
            [hash('sha256', $token), agora()]
        );
    }

    public static function gastarTokenReset(int $tokenId): void
    {
        Database::q('UPDATE tokens_reset SET usado_em = ? WHERE id = ?', [agora(), $tokenId]);
    }
}
