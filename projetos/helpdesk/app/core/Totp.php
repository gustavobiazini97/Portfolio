<?php
// 2FA por TOTP (RFC 6238): os códigos de 6 dígitos do Google Authenticator, Microsoft Authenticator, Aegis…
// Implementado à mão (sem bibliotecas): HMAC-SHA1 sobre o "passo" de 30 segundos.
declare(strict_types=1);

final class Totp
{
    private const ALFABETO = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';   // Base32 (RFC 4648)
    private const PERIODO = 30;                                     // segundos por código
    private const DIGITOS = 6;

    // Segredo novo: 20 bytes aleatórios em Base32 (32 letras)
    public static function novoSegredo(): string
    {
        return self::base32(random_bytes(20));
    }

    // Link otpauth:// — no telemóvel abre direto a app autenticadora
    public static function uri(string $segredo, string $conta): string
    {
        $emissor = (string) config('app_nome', 'Helpdesk');
        return 'otpauth://totp/' . rawurlencode($emissor . ':' . $conta)
            . '?secret=' . $segredo . '&issuer=' . rawurlencode($emissor)
            . '&algorithm=SHA1&digits=' . self::DIGITOS . '&period=' . self::PERIODO;
    }

    // Segredo em grupos de 4 para escrever à mão: "ABCD EFGH …"
    public static function legivel(string $segredo): string
    {
        return trim(chunk_split($segredo, 4, ' '));
    }

    // Verifica um código. Aceita o passo anterior e o seguinte (relógios ligeiramente desacertados).
    // Devolve o passo usado (para impedir reutilizar o mesmo código) ou null se estiver errado.
    public static function verificar(string $segredo, string $codigo, ?int $ultimoPassoUsado = null): ?int
    {
        $codigo = preg_replace('/\s+/', '', $codigo) ?? '';
        if (!preg_match('/^\d{6}$/', $codigo)) {
            return null;
        }
        $chave = self::deBase32($segredo);
        $passoAtual = intdiv(time(), self::PERIODO);
        foreach ([0, -1, 1] as $desvio) {
            $passo = $passoAtual + $desvio;
            // Um código já usado não volta a servir (contra replay)
            if ($ultimoPassoUsado !== null && $passo <= $ultimoPassoUsado) {
                continue;
            }
            if (hash_equals(self::codigo($chave, $passo), $codigo)) {
                return $passo;
            }
        }
        return null;
    }

    // Código de 6 dígitos para um passo (HOTP com "dynamic truncation")
    private static function codigo(string $chave, int $passo): string
    {
        $hash = hash_hmac('sha1', pack('J', $passo), $chave, true);
        $offset = ord($hash[19]) & 0x0f;
        $valor = ((ord($hash[$offset]) & 0x7f) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);
        return str_pad((string) ($valor % (10 ** self::DIGITOS)), self::DIGITOS, '0', STR_PAD_LEFT);
    }

    // Bytes → Base32
    private static function base32(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $b) {
            $bits .= str_pad(decbin(ord($b)), 8, '0', STR_PAD_LEFT);
        }
        $saida = '';
        foreach (str_split($bits, 5) as $grupo) {
            $saida .= self::ALFABETO[bindec(str_pad($grupo, 5, '0'))];
        }
        return $saida;
    }

    // Base32 → bytes
    private static function deBase32(string $texto): string
    {
        $texto = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $texto) ?? '');
        $bits = '';
        foreach (str_split($texto) as $c) {
            $bits .= str_pad(decbin((int) strpos(self::ALFABETO, $c)), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($bits, 8) as $grupo) {
            if (strlen($grupo) === 8) {
                $bytes .= chr(bindec($grupo));
            }
        }
        return $bytes;
    }
}
