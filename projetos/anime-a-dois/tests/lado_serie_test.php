<?php
// Testes das colunas laterais da página da série (app/models/LadoSerie.php e serie/lados.php), com base de dados
// e servidor a sério (correm no GitHub Actions, a seguir aos de segurança: anime-a-dois-testes.yml).
// Precisa do site a correr em $URL (php -S 127.0.0.1:8099 -t public). Correr com: php tests/lado_serie_test.php
//
// Cobre: sessões de registos (agrupadas por pessoa, 3 h de pausa), textos dos episódios, últimos comentários,
// distância entre os dois, arco atual e a página da série a abrir com as colunas.

require __DIR__ . '/../app/bootstrap.php';

use Illuminate\Database\Capsule\Manager as Capsule;

$URL = getenv('URL_TESTE') ?: 'http://127.0.0.1:8099';

$falhas = 0;
$verifica = function (string $nome, bool $ok, string $detalhe = '') use (&$falhas) {
    echo ($ok ? 'ok   ' : 'FALHA') . "  $nome" . ($ok || $detalhe === '' ? '' : "  ($detalhe)") . "\n";
    $falhas += $ok ? 0 : 1;
};

// ---------- Dados: duas pessoas que vêem uma série juntas (nomes próprios, para não chocar com os outros testes) ----------
$criar = fn (string $u, string $nome) => User::create(['nome' => $nome, 'username' => $u, 'password_hash' => password_hash('segredo123', PASSWORD_DEFAULT)]);
$eva  = $criar('lado_eva', 'Eva');
$filo = $criar('lado_filo', 'Filo');
Amizade::pedir($filo, 'lado_eva');   // amigos (só amigos ligados podem ver juntos)
Amizade::aceitar($eva, $filo);

$serie = Serie::create(['slug' => 'lado-teste', 'nome' => 'Lado Teste', 'total_episodios' => 20, 'mal_id' => 999901, 'adicionada_por' => $eva->id]);
for ($n = 1; $n <= 20; $n++) {
    Capsule::table('episodios')->insert(['serie_id' => $serie->id, 'numero' => $n, 'titulo' => "Ep $n", 'filler' => $n >= 18 ? 1 : 0]);
}
Capsule::table('bibliotecas')->insert(['user_id' => $eva->id, 'serie_id' => $serie->id, 'estado' => 'a_ver', 'desde' => date('Y-m-d H:i:s')]);
$r = $serie->juntarSe($filo, $eva);   // convite (não são par) …
$serie->aceitar($eva, $filo);         // … aceite: vêem juntos
$verifica('eva e filo vêem juntos', $serie->verCom($eva, $filo));

$idDe = fn (int $n) => (int) Capsule::table('episodios')->where('serie_id', $serie->id)->where('numero', $n)->value('id');
$ver  = fn (User $u, int $n, string $quando) => Capsule::table('vistos')->insert(['user_id' => $u->id, 'episodio_id' => $idDe($n), 'visto_em' => $quando]);

// Eva: ontem à noite 1–4 seguidos (uma sessão) e hoje de manhã 5 e 7 (outra sessão, com buraco)
$ontem = date('Y-m-d', strtotime('-1 day'));
$hoje  = date('Y-m-d');
foreach (['21:10', '21:30', '21:50', '22:10'] as $i => $h) { $ver($eva, $i + 1, "$ontem $h:00"); }
$ver($eva, 5, "$hoje 00:05:00");   // menos de 3 h depois da última de ontem → mesma sessão
$ver($eva, 7, "$hoje 09:00:00");   // mais de 3 h depois → sessão nova
// Filo: um episódio, à mesma hora que a eva (vêem juntos; não pode partir a sessão dela)
$ver($filo, 2, "$ontem 21:31:00");

$sessoes = LadoSerie::registos($serie, $eva, $serie->companheirosDe($eva));
$verifica('3 sessões (2 da eva, 1 do filo)', count($sessoes) === 3, (string) count($sessoes));
$verifica('mais recente primeiro: eva viu o 7', ($sessoes[0]['texto'] ?? '') === 'Viste o ep. 7', $sessoes[0]['texto'] ?? '');
$verifica('quando "hoje, 09:00"', ($sessoes[0]['quando'] ?? '') === 'hoje, 09:00', $sessoes[0]['quando'] ?? '');
$sessaoLonga = array_values(array_filter($sessoes, fn ($s) => $s['cor'] === 'tu' && $s['quantos'] > 1))[0] ?? null;
$verifica('sessão de ontem junta 1–5 (passou da meia-noite)', $sessaoLonga !== null && $sessaoLonga['texto'] === 'Viste os eps. 1–5', $sessaoLonga['texto'] ?? '');
$doFilo = array_values(array_filter($sessoes, fn ($s) => $s['cor'] === 'par'))[0] ?? null;
$verifica('filo: "Filo viu o ep. 2"', $doFilo !== null && $doFilo['texto'] === 'Filo viu o ep. 2', $doFilo['texto'] ?? '');

// Com buracos: 8, 10 e 12 logo a seguir ao 7 (menos de 3 h) → a sessão do 7 passa a ter 4 episódios
foreach ([8, 10, 12] as $i => $n) { $ver($eva, $n, "$hoje 1" . $i . ":00:00"); }
$s = LadoSerie::registos($serie, $eva, $serie->companheirosDe($eva))[0];
$verifica('com buracos: "4 episódios (7–12)"', $s['texto'] === 'Viste 4 episódios (7–12)', $s['texto']);

// ---------- Comentários ----------
Comentario::escrever($filo, Episodio::find($idDe(2)), 'Que episódio!');
Comentario::escrever($eva, Episodio::find($idDe(4)), 'Gostei <b>muito</b>');
$com = LadoSerie::comentarios($serie, $eva, $serie->companheirosDe($eva));
$verifica('2 comentários', count($com) === 2, (string) count($com));
$verifica('comentário com o número do episódio', in_array(2, array_column($com, 'numero'), true) && in_array(4, array_column($com, 'numero'), true));

// ---------- Distância e arco ----------
$meu = $serie->progressoDe($eva);
$deles = [$serie->progressoDe($filo)];
$frases = LadoSerie::distancias($meu, $serie->companheirosDe($eva), $deles);
$verifica('distância: eva 10 à frente', ($frases[0] ?? '') === 'Estás 10 episódios à frente de Filo', $frases[0] ?? '');
$frases = LadoSerie::distancias($deles[0], collect([$eva]), [$meu]);
$verifica('distância do outro lado', ($frases[0] ?? '') === 'Eva está 10 episódios à tua frente', $frases[0] ?? '');
$arcos = [['nome' => 'Arco A', 'de' => 1, 'ate' => 12], ['nome' => 'Arco B', 'de' => 13, 'ate' => 20]];
$verifica('arco do próximo episódio (13 → B)', (LadoSerie::arcoAtual($arcos, $meu, 20)['nome'] ?? '') === 'Arco B');
$verifica('sem arcos → null', LadoSerie::arcoAtual([], $meu, 20) === null);

// ---------- Datas curtas ----------
$verifica('outro ano leva o ano', LadoSerie::quando(strtotime('2020-10-12 20:00:00')) === '12 out 2020');

// ---------- A página da série abre com as colunas ----------
$cookies = [];
$http = function (string $metodo, string $caminho, array $campos = []) use ($URL, &$cookies): array {
    $ch = curl_init($URL . $caminho);
    $hdrs = $cookies ? ['Cookie: ' . implode('; ', array_map(fn ($k, $v) => "$k=$v", array_keys($cookies), $cookies))] : [];
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_HTTPHEADER => $hdrs]);
    if ($campos) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($campos));
    }
    $resposta = (string) curl_exec($ch);
    $tam = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $estado = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    foreach (explode("\r\n", substr($resposta, 0, $tam)) as $linha) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $linha, $m)) {
            $cookies[$m[1]] = $m[2];
        }
    }
    return ['estado' => $estado, 'corpo' => substr($resposta, $tam)];
};
Capsule::table('limites')->delete();   // os testes de segurança podem ter deixado o IP bloqueado
preg_match('/name="_csrf" value="([a-f0-9]+)"/', $http('GET', '/index.php?c=auth&a=login')['corpo'], $m);
$http('POST', '/index.php?c=auth&a=entrar', ['_csrf' => $m[1] ?? '', 'username' => 'lado_eva', 'password' => 'segredo123']);
$r = $http('GET', '/index.php?c=serie&a=ver&serie=lado-teste');
$verifica('página da série abre (200)', $r['estado'] === 200, (string) $r['estado']);
$verifica('tem as duas colunas', str_contains($r['corpo'], 'lado-esq') && str_contains($r['corpo'], 'lado-dir'));
$verifica('registos na página', str_contains($r['corpo'], 'Viste 4 episódios (7–12)'));
$verifica('comentário escapado (sem HTML)', str_contains($r['corpo'], 'Gostei &lt;b&gt;muito&lt;/b&gt;'));
$verifica('fillers por ver (3 de 3)', (bool) preg_match('/Fillers por ver<\/dt><dd>3 <small>\/ 3/', $r['corpo']));
$verifica('setas da pista', str_contains($r['corpo'], 'id="pista-ant"'));

echo $falhas ? "\n$falhas falha(s)\n" : "\nTudo ok\n";
exit($falhas ? 1 : 0);
