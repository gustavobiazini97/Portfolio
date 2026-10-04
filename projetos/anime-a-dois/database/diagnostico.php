<?php
// Diagnóstico da ligação ao Jikan a partir do servidor (o deploy corre-o e mostra o resultado no GitHub).
// Uso (na pasta da app): php database/diagnostico.php
// Não mexe em nada: só faz pedidos de leitura e mostra tempos e códigos HTTP.

require __DIR__ . '/../app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit('Corre este script no terminal: php database/diagnostico.php');
}

$base = rtrim(Database::config()['jikan_url'] ?? 'https://api.jikan.moe/v4', '/');

// Um pedido simples com curl: código HTTP, tempo e erro (se houver)
function testar(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_USERAGENT      => 'AnimeADois/1.0 (diagnostico)',
    ]);
    $inicio = microtime(true);
    $corpo  = curl_exec($ch);
    $tempo  = round(microtime(true) - $inicio, 2);
    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro   = curl_error($ch);
    curl_close($ch);
    $n = is_string($corpo) ? count(json_decode($corpo, true)['data'] ?? []) : 0;
    return "HTTP $codigo em {$tempo}s" . ($erro ? " · erro: $erro" : '') . " · itens: $n";
}

echo 'Jikan pesquisa: ' . testar($base . '/anime?q=dragon%20ball&limit=12&sfw=true') . "\n";
echo 'Jikan anime:    ' . testar($base . '/anime/52991') . "\n";
echo 'max_execution_time (CLI): ' . ini_get('max_execution_time') . "\n";
echo 'Capas no seed:  ' . Serie::whereNotNull('capa')->count() . ' de ' . Serie::count() . " séries\n";
