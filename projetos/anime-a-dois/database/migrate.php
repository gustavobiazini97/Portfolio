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

// Colunas que chegaram depois da primeira versão. O CREATE TABLE IF NOT EXISTS não mexe em
// tabelas que já existem, por isso cada coluna em falta é acrescentada aqui (uma vez só).
// A definição tem de ser igual à do schema.sql.
$colunas = [
    ['series', 'mal_id',          'INT UNSIGNED NULL UNIQUE'],
    ['series', 'capa',            'VARCHAR(255) NULL'],
    ['series', 'tipo',            'VARCHAR(20) NULL'],
    ['series', 'minutos_ep',      'TINYINT UNSIGNED NULL'],
    ['series', 'em_emissao',      'TINYINT(1) NOT NULL DEFAULT 0'],
    ['series', 'estado',          "VARCHAR(10) NOT NULL DEFAULT 'a_ver'"],
    ['series', 'acento',          'TINYINT UNSIGNED NULL'],
    ['series', 'adicionada_por',  'INT UNSIGNED NULL'],
    ['series', 'adicionada_em',   'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'],
    ['series', 'sincronizada_em', 'DATETIME NULL'],
    ['episodios', 'recap',        'TINYINT(1) NOT NULL DEFAULT 0'],
    ['preferencias', 'serie_push',  'TINYINT(1) NOT NULL DEFAULT 1'],
    ['preferencias', 'serie_email', 'TINYINT(1) NOT NULL DEFAULT 0'],
];

$schema = Capsule::schema();
foreach ($colunas as [$tabela, $coluna, $definicao]) {
    if (!$schema->hasColumn($tabela, $coluna)) {
        Capsule::connection()->statement("ALTER TABLE `$tabela` ADD COLUMN `$coluna` $definicao");
        echo "Coluna nova: $tabela.$coluna\n";
    }
}

echo "Estrutura atualizada.\n";
