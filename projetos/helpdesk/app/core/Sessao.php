<?php
// Sessão segura: cookie HttpOnly/Secure/SameSite, ID novo no login, tempo de inatividade e tempo absoluto.
declare(strict_types=1);

final class Sessao
{
    // Arranca a sessão em cada pedido web
    public static function iniciar(): void
    {
        // Só cookies (nunca o ID no URL) e IDs inventados pelo cliente são rejeitados
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');

        session_name('helpdesk_sessao');
        session_set_cookie_params([
            'lifetime' => 0,               // morre ao fechar o browser
            'path'     => '/',
            'httponly' => true,            // inacessível ao JavaScript
            'secure'   => https_ativo(),   // só por HTTPS quando o site é HTTPS
            'samesite' => 'Lax',           // não vai em pedidos POST de outros sites
        ]);
        session_start();

        // Tempo limite: parado demasiado tempo ou sessão demasiado antiga → termina
        if (isset($_SESSION['uid'])) {
            $agora = time();
            $parado = $agora - (int) ($_SESSION['ultimo_ato'] ?? 0);
            $idade = $agora - (int) ($_SESSION['login_em'] ?? 0);
            if ($parado > (int) config('sessao.inatividade_min', 30) * 60
                || $idade > (int) config('sessao.absoluta_horas', 10) * 3600) {
                self::terminar();
                flash('aviso', 'A sessão expirou. Entra outra vez.');
            } elseif (!(($_GET['c'] ?? '') === 'notificacao' && ($_GET['a'] ?? '') === 'contar')) {
                // A contagem automática do sino (a cada minuto) não conta como atividade,
                // senão a sessão nunca expirava com a página aberta
                $_SESSION['ultimo_ato'] = $agora;
            }
        }

        // Token CSRF da sessão, criado uma vez e usado em todos os formulários
        $_SESSION['_csrf'] ??= bin2hex(random_bytes(32));
    }

    // Login concluído: ID de sessão novo (contra session fixation) e relógios a zero
    public static function entrar(int $uid): void
    {
        session_regenerate_id(true);
        unset($_SESSION['2fa_uid'], $_SESSION['2fa_em']);
        $_SESSION['uid'] = $uid;
        $_SESSION['login_em'] = time();
        $_SESSION['ultimo_ato'] = time();
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    // Passo intermédio do login com 2FA: palavra-passe certa, falta o código
    public static function pendente2fa(int $uid): void
    {
        session_regenerate_id(true);
        $_SESSION['2fa_uid'] = $uid;
        $_SESSION['2fa_em'] = time();
    }

    // Logout: apaga os dados, o cookie e a sessão no servidor; abre uma sessão limpa para as mensagens
    public static function terminar(): void
    {
        $_SESSION = [];
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => $p['path'],
            'secure'   => $p['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_destroy();
        session_start();
        session_regenerate_id(true);
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
}
