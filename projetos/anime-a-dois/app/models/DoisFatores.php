<?php
// Autenticação de dois fatores (TOTP, RFC 6238) para o backoffice: o código de 6 dígitos
// das apps Google Authenticator, Microsoft Authenticator, etc. Sem bibliotecas externas.
//
// Na base de dados (users): totp_segredo (base32) e totp_passo (último intervalo de 30 s aceite,
// para o mesmo código não servir duas vezes). Na sessão: $_SESSION['_2fa'] = id da conta + validade.
// Perdeste o telemóvel? No SSH do servidor: php database/repor-2fa.php <utilizador>

class DoisFatores
{
    const DIGITOS   = 6;
    const PERIODO   = 30;     // segundos de cada código
    const VALIDADE  = 1800;   // 30 min sem mexer no backoffice → pede o código outra vez
    const TENTATIVAS = 5;     // códigos errados em 15 min antes de bloquear
    const JANELA    = 900;

    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    // ---------- TOTP ----------

    // Segredo novo: 20 bytes aleatórios (160 bits, o recomendado) em base32 → 32 caracteres
    public static function novoSegredo(): string
    {
        return self::base32(random_bytes(20));
    }

    // Código de 6 dígitos para um intervalo de 30 s (HOTP com HMAC-SHA1, como as apps esperam)
    public static function codigo(string $segredo, int $passo): string
    {
        $chave = self::deBase32($segredo);
        $hash  = hash_hmac('sha1', pack('N2', $passo >> 32, $passo & 0xFFFFFFFF), $chave, true);
        $pos   = ord($hash[19]) & 0x0F;
        $num   = (unpack('N', substr($hash, $pos, 4))[1] & 0x7FFFFFFF) % (10 ** self::DIGITOS);
        return str_pad((string) $num, self::DIGITOS, '0', STR_PAD_LEFT);
    }

    // Intervalo de 30 s atual
    public static function passoAtual(?int $agora = null): int
    {
        return intdiv($agora ?? time(), self::PERIODO);
    }

    // Verifica um código; aceita o intervalo anterior e o seguinte (relógio do telemóvel desacertado).
    // Devolve o intervalo que bateu, ou null. Intervalos <= $ultimo já foram usados: recusados (anti-repetição).
    public static function verificar(string $segredo, string $codigo, ?int $ultimo = null, ?int $agora = null): ?int
    {
        $codigo = preg_replace('/\D/', '', $codigo);   // aceita "123 456"
        if (strlen($codigo) !== self::DIGITOS) {
            return null;
        }
        $passo = self::passoAtual($agora);
        foreach ([$passo - 1, $passo, $passo + 1] as $p) {
            if (($ultimo === null || $p > $ultimo) && hash_equals(self::codigo($segredo, $p), $codigo)) {
                return $p;
            }
        }
        return null;
    }

    // Link otpauth:// (no telemóvel, tocar nele abre a app de autenticação já preenchida)
    public static function link(string $segredo, string $conta, string $emissor = 'Anime a Dois'): string
    {
        return 'otpauth://totp/' . rawurlencode($emissor . ':' . $conta)
            . '?secret=' . $segredo . '&issuer=' . rawurlencode($emissor)
            . '&algorithm=SHA1&digits=' . self::DIGITOS . '&period=' . self::PERIODO;
    }

    // Segredo em grupos de 4 (mais fácil de escrever à mão)
    public static function formatar(string $segredo): string
    {
        return trim(chunk_split($segredo, 4, ' '));
    }

    // ---------- Sessão do backoffice ----------

    // Esta sessão já passou o código para esta conta, e há menos de 30 min? (renova a validade)
    public static function sessaoValida(User $user): bool
    {
        $s = $_SESSION['_2fa'] ?? null;
        if (!is_array($s) || ($s['id'] ?? null) !== $user->id || ($s['ate'] ?? 0) < time()) {
            unset($_SESSION['_2fa']);
            return false;
        }
        $_SESSION['_2fa']['ate'] = time() + self::VALIDADE;
        return true;
    }

    // Código certo: marca a sessão (com ID de sessão novo, como num login)
    public static function marcarSessao(User $user): void
    {
        session_regenerate_id(true);
        $_SESSION['_2fa'] = ['id' => $user->id, 'ate' => time() + self::VALIDADE];
    }

    // Limite de tentativas por conta (partilhado com a tabela limites do login)
    public static function bloqueado(User $user): bool
    {
        return Limite::bloqueado('2fa:' . $user->id);
    }

    public static function falhou(User $user): void
    {
        Limite::registar('2fa:' . $user->id, self::TENTATIVAS, self::JANELA);
    }

    // ---------- Base32 (RFC 4648, sem "=") ----------

    private static function base32(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $b) {
            $bits .= str_pad(decbin(ord($b)), 8, '0', STR_PAD_LEFT);
        }
        $saida = '';
        foreach (str_split($bits, 5) as $grupo) {
            $saida .= self::BASE32[bindec(str_pad($grupo, 5, '0'))];
        }
        return $saida;
    }

    private static function deBase32(string $texto): string
    {
        $texto = strtoupper(preg_replace('/[\s=]/', '', $texto));
        $bits = '';
        foreach (str_split($texto) as $c) {
            $i = strpos(self::BASE32, $c);
            if ($i === false) {
                throw new InvalidArgumentException('Segredo 2FA inválido.');
            }
            $bits .= str_pad(decbin($i), 5, '0', STR_PAD_LEFT);
        }
        $saida = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $saida .= chr(bindec($byte));
            }
        }
        return $saida;
    }
}
