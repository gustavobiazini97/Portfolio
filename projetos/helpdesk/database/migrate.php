<?php
// Cria as tabelas que faltarem (schema.sql) e os prazos do SLA por defeito.
// Pode correr-se as vezes que se quiser: não apaga nem altera dados.
//   php database/migrate.php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../app/bootstrap.php';

// Lê o schema, tira os comentários "-- …" e separa as instruções pelo ";" do fim
$sql = (string) file_get_contents(__DIR__ . '/schema.sql');
$sql = preg_replace('/--[^\n]*/', '', $sql) ?? '';
$instrucoes = array_filter(array_map('trim', explode(';', $sql)));

foreach ($instrucoes as $i) {
    Database::pdo()->exec($i);
    if (preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $i, $m)) {
        echo "  ✓ {$m[1]}\n";
    }
}

// Prazos por defeito (minutos de horário de trabalho; 1 dia útil = 540 min com 09:00–18:00)
//   P1 Crítica: responder em 30 min, resolver em 4 h
//   P2 Alta:    responder em 1 h,   resolver em 1 dia útil
//   P3 Média:   responder em 4 h,   resolver em 3 dias úteis
//   P4 Baixa:   responder em 1 dia, resolver em 5 dias úteis
if ((int) Database::valor('SELECT COUNT(*) FROM sla_politicas') === 0) {
    foreach ([1 => [30, 240], 2 => [60, 540], 3 => [240, 1620], 4 => [540, 2700]] as $p => [$resp, $res]) {
        SlaPolitica::guardar($p, $resp, $res);
    }
    echo "  ✓ prazos do SLA por defeito\n";
}

echo "Base de dados pronta.\n";
