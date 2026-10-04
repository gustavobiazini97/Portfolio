<?php
// "Manter sessão iniciada": cookie persistente (seletor + validador) que reabre a sessão quando a do PHP já expirou.
// Na base de dados fica só o hash do validador; roubar a tabela não dá acesso a ninguém.

use Illuminate\Database\Capsule\Manager as Capsule;

class SessaoLonga
{
    private const COOKIE = 'lembrar';
    private const DIAS   = 90;

    // Cria o cookie para esta pessoa (chamar depois de iniciar sessão)
    public static function criar(User $user): void
    {
        $seletor   = bin2hex(random_bytes(12));      // 24 caracteres
        $validador = bin2hex(random_bytes(32));
        $expira    = time() + self::DIAS * 86400;

        Capsule::table('sessoes_longas')->insert([
            'user_id'        => $user->id,
            'seletor'        => $seletor,
            'validador_hash' => hash('sha256', $validador),
            'expira_em'      => date('Y-m-d H:i:s', $expira),
        ]);
        self::definirCookie($seletor . ':' . $validador, $expira);
    }

    // Sem sessão mas com cookie válido: inicia a sessão (chamado uma vez por pedido, em public/index.php)
    public static function restaurar(): void
    {
        if (isset($_SESSION['user_id']) || empty($_COOKIE[self::COOKIE])) {
            return;
        }
        [$seletor, $validador] = array_pad(explode(':', (string) $_COOKIE[self::COOKIE], 2), 2, '');
        if (!preg_match('/^[a-f0-9]{24}$/', $seletor) || !preg_match('/^[a-f0-9]{64}$/', $validador)) {
            self::limparCookie();
            return;
        }

        try {
            $linha = Capsule::table('sessoes_longas')->where('seletor', $seletor)->where('expira_em', '>', date('Y-m-d H:i:s'))->first();
            if ($linha === null || !hash_equals($linha->validador_hash, hash('sha256', $validador))) {
                self::limparCookie();   // cookie velho, apagado ou adulterado
                return;
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $linha->user_id;

            // Cada uso renova os 90 dias (deslizante)
            $expira = time() + self::DIAS * 86400;
            Capsule::table('sessoes_longas')->where('id', $linha->id)->update(['expira_em' => date('Y-m-d H:i:s', $expira)]);
            self::definirCookie($seletor . ':' . $validador, $expira);
        } catch (Throwable $e) {
            // Tabela ainda por criar (antes do migrate) ou base de dados em baixo: segue sem sessão longa
        }
    }

    // Termina a sessão longa deste telemóvel (terminar sessão)
    public static function terminar(): void
    {
        if (!empty($_COOKIE[self::COOKIE])) {
            $seletor = explode(':', (string) $_COOKIE[self::COOKIE], 2)[0];
            try {
                Capsule::table('sessoes_longas')->where('seletor', $seletor)->delete();
            } catch (Throwable $e) {
                // sem tabela: nada a apagar
            }
        }
        self::limparCookie();
    }

    // Apaga todas as sessões longas de uma pessoa (mudou a palavra-passe: os outros telemóveis têm de entrar outra vez)
    public static function terminarTodas(User $user): void
    {
        Capsule::table('sessoes_longas')->where('user_id', $user->id)->delete();
        self::limparCookie();
    }

    private static function limparCookie(): void
    {
        self::definirCookie('', time() - 3600);
        unset($_COOKIE[self::COOKIE]);
    }

    private static function definirCookie(string $valor, int $expira): void
    {
        $https = !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        setcookie(self::COOKIE, $valor, [
            'expires'  => $expira,
            'path'     => '/',
            'secure'   => $https,
            'httponly' => true,       // o JavaScript não lê o cookie
            'samesite' => 'Lax',
        ]);
    }
}
