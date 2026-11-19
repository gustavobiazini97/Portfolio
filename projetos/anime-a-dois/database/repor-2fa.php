<?php
// Repõe o 2FA do backoffice de uma conta (perdeu o telemóvel e a chave).
// Uso, no SSH do servidor (na pasta da app):  php database/repor-2fa.php <utilizador>
// Na próxima entrada no backoffice, a conta volta a configurar o código com uma chave nova.

require __DIR__ . '/../app/bootstrap.php';

// Só na linha de comandos: quem tem acesso ao servidor já é o dono da app
if (PHP_SAPI !== 'cli') {
    exit('Corre este script no terminal: php database/repor-2fa.php <utilizador>');
}

$username = $argv[1] ?? '';
$user = $username !== '' ? User::where('username', $username)->first() : null;
if ($user === null) {
    fwrite(STDERR, "Indica um utilizador que exista: php database/repor-2fa.php <utilizador>\n");
    exit(1);
}

$user->totp_segredo = null;
$user->totp_passo = null;
$user->save();
Limite::limpar('2fa:' . $user->id);   // tira também um bloqueio por códigos errados

echo "2FA reposto para {$user->username}. Na próxima entrada no backoffice configura-se outra vez.\n";
