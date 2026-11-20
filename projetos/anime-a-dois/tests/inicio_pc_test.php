<?php
// Testes dos blocos do Início no computador (app/models/InicioPc.php e home/pc.php), com base de dados
// e servidor a sério (correm no GitHub Actions, a seguir aos outros: anime-a-dois-testes.yml).
// Precisa do site a correr em $URL (php -S 127.0.0.1:8099 -t public). Correr com: php tests/inicio_pc_test.php
//
// Cobre: dias seguidos, próxima medalha, esta semana; quem espera por quem, números a dois, última vez juntos,
// marcos do dia; o que os amigos estão a ver (sem o par e sem quem tem "só juntos"); o Início a abrir com os blocos.

require __DIR__ . '/../app/bootstrap.php';

use Illuminate\Database\Capsule\Manager as Capsule;

$URL = getenv('URL_TESTE') ?: 'http://127.0.0.1:8099';

$falhas = 0;
$verifica = function (string $nome, bool $ok, string $detalhe = '') use (&$falhas) {
    echo ($ok ? 'ok   ' : 'FALHA') . "  $nome" . ($ok || $detalhe === '' ? '' : "  ($detalhe)") . "\n";
    $falhas += $ok ? 0 : 1;
};

// ---------- Partes sem base de dados ----------
$hoje = date('Y-m-d');
$d = fn (int $n) => date('Y-m-d', strtotime("-$n day"));
$verifica('seguidos: hoje, ontem, anteontem = 3', InicioPc::sequenciaAtual([$hoje, $d(1), $d(2), $d(5)]) === 3);
$verifica('seguidos: sem hoje, conta até ontem', InicioPc::sequenciaAtual([$d(1), $d(2)]) === 2);
$verifica('seguidos: último há 2 dias = 0', InicioPc::sequenciaAtual([$d(2), $d(3)]) === 0);

$med = InicioPc::proximaMedalha([
    ['emoji' => 'a', 'nome' => 'A', 'descricao' => '', 'meta' => 10, 'atual' => 10, 'obtida' => true],
    ['emoji' => 'b', 'nome' => 'B', 'descricao' => '', 'meta' => 100, 'atual' => 80, 'obtida' => false],
    ['emoji' => 'c', 'nome' => 'C', 'descricao' => '', 'meta' => 10, 'atual' => 5, 'obtida' => false],
]);
$verifica('próxima medalha = a mais perto (B, 80%)', ($med['nome'] ?? '') === 'B' && $med['pct'] === 80);

// Marcos: 50.º episódio dos dois hoje
$linha = fn (string $ta, string $tb, int $serie = 1, int $n = 1) => ['serie' => $serie, 'numero' => $n, 'min' => 23, 'total' => 999, 'ta' => $ta, 'tb' => $tb];
$antigas = array_map(fn ($i) => $linha("2026-01-01 20:00:00", "2026-01-01 20:05:00", 1, $i), range(1, 49));
$par = new User(['nome' => 'Par']);
$verifica('marco: 50 juntos hoje', InicioPc::marco(array_merge($antigas, [$linha("$hoje 21:00:00", "2026-01-01 20:00:00", 1, 50)]), collect(), $par) === 'Hoje fazem 50 episódios vistos juntos');
$verifica('marco: nada nesse dia', InicioPc::marco($antigas, collect(), $par, '2026-06-15') === null);
$verifica('marco: aniversário', InicioPc::marco([$linha('2025-06-15 20:00:00', '2025-06-15 20:00:00')], collect(), $par, '2026-06-15') === 'Fazem hoje 1 ano a ver anime com Par');

// ---------- Dados: casal (gil + hana), amiga (ines), amigo com "só juntos" (joao) ----------
$criar = fn (string $u, string $nome) => User::create(['nome' => $nome, 'username' => $u, 'password_hash' => password_hash('segredo123', PASSWORD_DEFAULT)]);
$gil  = $criar('pc_gil', 'Gil');
$hana = $criar('pc_hana', 'Hana');
$ines = $criar('pc_ines', 'Inês');
$joao = $criar('pc_joao', 'João');
User::where('id', $gil->id)->update(['par_id' => $hana->id]);
User::where('id', $hana->id)->update(['par_id' => $gil->id]);
User::where('id', $joao->id)->update(['so_juntos' => 1]);
$gil->refresh(); $hana->refresh(); $joao->refresh();
foreach ([$ines, $joao] as $a) {
    Amizade::pedir($a, 'pc_gil');
    Amizade::aceitar($gil, $a);
}

// Duas séries do casal (Alfa e Beta) e uma só da Inês e do João (Gama)
$novaSerie = function (string $slug, string $nome, int $total, int $mal, User $dono) {
    $s = Serie::create(['slug' => $slug, 'nome' => $nome, 'total_episodios' => $total, 'mal_id' => $mal, 'adicionada_por' => $dono->id]);
    for ($n = 1; $n <= $total; $n++) {
        Capsule::table('episodios')->insert(['serie_id' => $s->id, 'numero' => $n, 'titulo' => "Ep $n"]);
    }
    Capsule::table('bibliotecas')->insert(['user_id' => $dono->id, 'serie_id' => $s->id, 'estado' => 'a_ver', 'desde' => date('Y-m-d H:i:s')]);
    return $s;
};
$alfa = $novaSerie('pc-alfa', 'Alfa', 20, 999801, $gil);
$beta = $novaSerie('pc-beta', 'Beta', 10, 999802, $gil);
$gama = $novaSerie('pc-gama', 'Gama', 12, 999803, $ines);
$alfa->juntarSe($hana, $gil);   // par: juntos logo
$beta->juntarSe($hana, $gil);
$verifica('casal vê Alfa e Beta juntos', $alfa->verCom($gil, $hana) && $beta->verCom($gil, $hana));

$id  = fn (Serie $s, int $n) => (int) Capsule::table('episodios')->where('serie_id', $s->id)->where('numero', $n)->value('id');
$ver = fn (User $u, Serie $s, int $n, string $quando) => Capsule::table('vistos')->insert(['user_id' => $u->id, 'episodio_id' => $id($s, $n), 'visto_em' => $quando]);

$ontem = $d(1);
// Alfa: Gil vê 1–8, Hana 1–3 (os 3 primeiros no mesmo dia, ontem); Beta: Hana vai à frente (1–4), Gil 1–2
foreach (range(1, 8) as $n) { $ver($gil, $alfa, $n, $n <= 3 ? "$ontem 21:0$n:00" : "$hoje 10:0" . ($n - 3) . ':00'); }
foreach (range(1, 3) as $n) { $ver($hana, $alfa, $n, "$ontem 21:1$n:00"); }
foreach (range(1, 4) as $n) { $ver($hana, $beta, $n, "2026-02-0$n 20:00:00"); }
foreach (range(1, 2) as $n) { $ver($gil, $beta, $n, "2026-03-0$n 20:00:00"); }
// Amigos: Inês vê Gama 1–5 hoje; João (só juntos) também vê Gama
foreach (range(1, 5) as $n) { $ver($ines, $gama, $n, "$hoje 09:0$n:00"); }
$ver($joao, $gama, 1, "$hoje 09:30:00");

// ---------- Tu ----------
$tu = InicioPc::tu($gil);
$verifica('seguidos do Gil = 2 (ontem e hoje)', $tu['seguidos'] === 2, (string) $tu['seguidos']);
$verifica('retrospetiva do mês atual', ($tu['retro']['mes'] ?? 0) === (int) date('n'));
$verifica('há uma próxima medalha', $tu['medalha'] !== null);

// ---------- Par ----------
$p = InicioPc::par($gil, $hana);
$textos = array_column($p['esperas'], 'texto');
$verifica('esperas: Alfa, Gil 5 à frente', in_array('Vais 5 episódios à frente', $textos, true), implode(' | ', $textos));
$verifica('esperas: Beta, Hana à frente → "Hana espera por ti · 2 eps."', in_array('Hana espera por ti · 2 eps.', $textos, true), implode(' | ', $textos));
$verifica('esperas: mais distância primeiro', ($p['esperas'][0]['serie']->slug ?? '') === 'pc-alfa');
$verifica('juntos: 5 episódios (3 Alfa + 2 Beta)', $p['juntos']['episodios'] === 5, (string) $p['juntos']['episodios']);
$verifica('juntos: 2 séries', $p['juntos']['series'] === 2);
$verifica('juntos: desde março de 2026', $p['juntos']['desde'] === 'março de 2026', (string) $p['juntos']['desde']);
$verifica('última vez juntos: Alfa 1–3', ($p['ultima']['texto'] ?? '') === 'Alfa 1–3', $p['ultima']['texto'] ?? 'null');
$verifica('última vez: ontem', str_starts_with($p['ultima']['quando'] ?? '', 'ontem'), $p['ultima']['quando'] ?? '');

// ---------- Amigos ----------
$am = InicioPc::amigos($gil);
$verifica('amigos: Gama aparece', ($am[0]['serie']->slug ?? '') === 'pc-gama');
$quem = array_map(fn ($x) => $x['user']->username, $am[0]['pessoas'] ?? []);
$verifica('amigos: só a Inês (o João tem "só juntos")', $quem === ['pc_ines'], implode(',', $quem));
$verifica('amigos: último episódio da Inês = 5', ($am[0]['pessoas'][0]['numero'] ?? 0) === 5);
$verifica('amigos: Gama não está na biblioteca do Gil', ($am[0]['tens'] ?? true) === false);
$verifica('amigos: o par não conta', array_filter($am, fn ($x) => in_array($x['serie']->slug, ['pc-alfa', 'pc-beta'], true)) === []);

// ---------- O Início abre com os blocos ----------
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
    foreach (explode("\r\n", substr($resposta, 0, $tam)) as $l) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $l, $m)) {
            $cookies[$m[1]] = $m[2];
        }
    }
    return ['estado' => $estado, 'corpo' => substr($resposta, $tam)];
};
Capsule::table('limites')->delete();
preg_match('/name="_csrf" value="([a-f0-9]+)"/', $http('GET', '/index.php?c=auth&a=login')['corpo'], $m);
$http('POST', '/index.php?c=auth&a=entrar', ['_csrf' => $m[1] ?? '', 'username' => 'pc_gil', 'password' => 'segredo123']);
$r = $http('GET', '/index.php?c=home');
$verifica('Início abre (200)', $r['estado'] === 200, (string) $r['estado']);
$verifica('tem as colunas tu e amigos', str_contains($r['corpo'], 'pc-tu') && str_contains($r['corpo'], 'pc-amigos'));
$verifica('tem os blocos do par (com-par)', str_contains($r['corpo'], 'inicio com-par') && str_contains($r['corpo'], 'Quem espera por quem'));
$verifica('última vez juntos na página', str_contains($r['corpo'], 'Alfa 1–3'));
$verifica('último da Hana no topo da direita', str_contains($r['corpo'], 'pc-ultimo-par') && str_contains($r['corpo'], 'Hana viu'));

// Conta sem par (Inês): sem blocos do par, mas com as colunas
$cookies = [];
preg_match('/name="_csrf" value="([a-f0-9]+)"/', $http('GET', '/index.php?c=auth&a=login')['corpo'], $m);
$http('POST', '/index.php?c=auth&a=entrar', ['_csrf' => $m[1] ?? '', 'username' => 'pc_ines', 'password' => 'segredo123']);
$r = $http('GET', '/index.php?c=home');
$verifica('Início sem par abre (200)', $r['estado'] === 200, (string) $r['estado']);
$verifica('sem par: sem blocos do par', !str_contains($r['corpo'], 'com-par') && str_contains($r['corpo'], 'pc-tu'));

echo $falhas ? "\n$falhas falha(s)\n" : "\nTudo ok\n";
exit($falhas ? 1 : 0);
