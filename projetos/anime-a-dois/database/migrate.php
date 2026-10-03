<?php
// Cria as tabelas na base de dados definida no config/config.php.
// Uso (na pasta da app): php database/migrate.php
// Pode correr-se sempre: o schema.sql só usa CREATE TABLE IF NOT EXISTS.

require __DIR__ . '/../app/bootstrap.php';

use Illuminate\Database\Capsule\Manager as Capsule;

// Só faz sentido na linha de comandos (no alojamento corre pelo deploy, via SSH)
if (PHP_SAPI !== 'cli') {
    exit('Corre este script no terminal: php database/migrate.php');
}

$sql = file_get_contents(__DIR__ . '/schema.sql');

// Tira os comentários "-- ..." (a linha toda ou o resto da linha) antes de dividir por ";"
$sql = preg_replace('/--.*$/m', '', $sql);

// Cada comando termina em ";"; ignora os pedaços vazios
$comandos = array_filter(array_map('trim', explode(';', $sql)));

foreach ($comandos as $comando) {
    Capsule::connection()->unprepared($comando);

    // Mostra o nome da tabela para se ver o progresso no log do deploy
    if (preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/i', $comando, $m)) {
        echo "Tabela ok: {$m[1]}\n";
    }
}

echo "Estrutura atualizada.\n";
