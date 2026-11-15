<?php
// Cabeçalhos HTTP de segurança, enviados em todas as respostas.
declare(strict_types=1);

final class Seguranca
{
    public static function cabecalhos(): void
    {
        // CSP: só scripts, estilos e imagens do próprio site. Sem inline, sem CDNs.
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; "
            . "connect-src 'self'; font-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
        header('X-Content-Type-Options: nosniff');          // o browser não adivinha tipos de ficheiro
        header('X-Frame-Options: DENY');                     // não pode ser metido num iframe (clickjacking)
        header('Referrer-Policy: same-origin');              // o URL interno não vai para outros sites
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        // HSTS: só faz sentido (e só é aceite) em HTTPS
        if (https_ativo()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        // Páginas com dados pessoais não ficam em caches partilhadas
        header('Cache-Control: no-store, private');
        header_remove('X-Powered-By');
    }
}
