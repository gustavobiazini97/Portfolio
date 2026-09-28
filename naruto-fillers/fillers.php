<?php
/**
 * Devolve em JSON os episódios filler de uma série, lidos ao vivo de
 * animefillerlist.com (com cache de 24h). Se o site não responder,
 * usa a cópia local em data/fillers.json.
 *
 *   fillers.php?show=naruto
 *   fillers.php?show=naruto-shippuden
 *   fillers.php?show=boruto-naruto-next-generations
 */

header('Content-Type: application/json; charset=utf-8');

const SHOWS = [
    'naruto'                         => ['name' => 'Naruto',           'total' => 220, 'years' => '2002–2007'],
    'naruto-shippuden'               => ['name' => 'Naruto Shippuden', 'total' => 500, 'years' => '2007–2017'],
    'boruto-naruto-next-generations' => ['name' => 'Boruto',           'total' => 293, 'years' => '2017–2023'],
];
const CACHE_TTL = 86400;

$show = $_GET['show'] ?? 'naruto';
if (!isset(SHOWS[$show])) {
    http_response_code(400);
    echo json_encode(['error' => 'Série desconhecida']);
    exit;
}

$cacheDir  = __DIR__ . '/cache';
$cacheFile = "$cacheDir/$show.json";

if (is_file($cacheFile) && time() - filemtime($cacheFile) < CACHE_TTL && !isset($_GET['refresh'])) {
    readfile($cacheFile);
    exit;
}

$episodes = scrape($show);

if ($episodes) {
    $out = json_encode(SHOWS[$show] + ['source' => 'live', 'episodes' => $episodes], JSON_UNESCAPED_UNICODE);
    if (is_dir($cacheDir) || @mkdir($cacheDir, 0775, true)) {
        @file_put_contents($cacheFile, $out);
    }
    echo $out;
    exit;
}

// Fallback: cache antiga ou cópia local.
if (is_file($cacheFile)) {
    readfile($cacheFile);
    exit;
}
$local = json_decode(file_get_contents(__DIR__ . '/data/fillers.json'), true);
echo json_encode($local['shows'][$show] + ['source' => 'offline'], JSON_UNESCAPED_UNICODE);


function scrape(string $show): array
{
    $url = "https://www.animefillerlist.com/shows/$show";
    $html = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (NarutoFillers)',
        ]);
        $html = curl_exec($ch);
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
            $html = null;
        }
        curl_close($ch);
    } else {
        $ctx  = stream_context_create(['http' => ['timeout' => 15, 'header' => "User-Agent: Mozilla/5.0 (NarutoFillers)\r\n"]]);
        $html = @file_get_contents($url, false, $ctx) ?: null;
    }
    if (!$html) {
        return [];
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
    libxml_clear_errors();
    $xp = new DOMXPath($dom);

    // Cada episódio é uma <tr> na tabela .EpisodeList; o tipo está em td.Type.
    $episodes = [];
    foreach ($xp->query("//table[contains(@class,'EpisodeList')]//tbody/tr") as $tr) {
        $cell = fn(string $cls) => trim($xp->evaluate("string(td[contains(@class,'$cls')])", $tr));
        if (strcasecmp($cell('Type'), 'Filler') !== 0) {
            continue;
        }
        $episodes[] = [
            'n'     => (int) $cell('Number'),
            'title' => $cell('Title') ?: null,
            'date'  => $cell('Date') ?: null,
        ];
    }
    return $episodes;
}
