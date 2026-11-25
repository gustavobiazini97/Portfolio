<?php
// Testes do mascote do casal (app/models/Mascote.php), com base de dados a sério
// (correm no GitHub Actions, a seguir aos outros: anime-a-dois-testes.yml).
// Correr com: php tests/mascote_test.php
//
// Cobre: fases e humor (parte pura), espécie escolhida uma só vez e só depois de o ovo chocar,
// nome e cor validados, "vira as costas" (15+ episódios atrás), troca de par, conta sem par,
// e apagar a conta (a linha do mascote desaparece).

require __DIR__ . '/../app/bootstrap.php';

use Illuminate\Database\Capsule\Manager as Capsule;

// Precisa do site a correr em $URL (php -S 127.0.0.1:8099 -t public) para a parte da API
$URL = getenv('URL_TESTE') ?: 'http://127.0.0.1:8099';

$falhas = 0;
$verifica = function (string $nome, bool $ok, string $detalhe = '') use (&$falhas) {
    echo ($ok ? 'ok   ' : 'FALHA') . "  $nome" . ($ok || $detalhe === '' ? '' : "  ($detalhe)") . "\n";
    $falhas += $ok ? 0 : 1;
};

// Corre $f e diz se lançou InvalidArgumentException (o que a API converte em 422)
$lanca = function (callable $f): bool {
    try {
        $f();
    } catch (InvalidArgumentException $e) {
        return true;
    }
    return false;
};

// ---- PURA ----
// ---------- Partes sem base de dados ----------
$verifica('fase: 0 = ovo', Mascote::faseDe(0) === 'ovo');
$verifica('fase: 9 = ovo', Mascote::faseDe(9) === 'ovo');
$verifica('fase: 10 = cria', Mascote::faseDe(10) === 'cria');
$verifica('fase: 99 = cria', Mascote::faseDe(99) === 'cria');
$verifica('fase: 100 = jovem', Mascote::faseDe(100) === 'jovem');
$verifica('fase: 299 = jovem', Mascote::faseDe(299) === 'jovem');
$verifica('fase: 300 = adulta', Mascote::faseDe(300) === 'adulta');
$verifica('fase: 999 = adulta', Mascote::faseDe(999) === 'adulta');
$verifica('fase: 1000 = lendaria', Mascote::faseDe(1000) === 'lendaria');

$verifica('humor: 2 dias = feliz', Mascote::humorDe('2026-11-23', '2026-11-25') === 'feliz');
$verifica('humor: hoje = feliz', Mascote::humorDe('2026-11-25 10:00:00', '2026-11-25') === 'feliz');
$verifica('humor: 3 dias = aborrecida', Mascote::humorDe('2026-11-22', '2026-11-25') === 'aborrecida');
$verifica('humor: 6 dias = aborrecida', Mascote::humorDe('2026-11-19', '2026-11-25') === 'aborrecida');
$verifica('humor: 7 dias = a_dormir', Mascote::humorDe('2026-11-18', '2026-11-25') === 'a_dormir');
$verifica('humor: nunca viram juntos = feliz', Mascote::humorDe(null, '2026-11-25') === 'feliz');
// ---- FIM PURA ----

// ---------- Dados: casal (gil + hana), outra pessoa (ines) e uma conta sem par (sozinho) ----------
$criar = fn (string $u, string $nome) => User::create(['nome' => $nome, 'username' => $u, 'password_hash' => password_hash('segredo123', PASSWORD_DEFAULT)]);
$gil  = $criar('ms_gil', 'Gil');
$hana = $criar('ms_hana', 'Hana');
$ines = $criar('ms_ines', 'Inês');
$ze   = $criar('ms_ze', 'Zé');
$sozinho = $criar('ms_sozinho', 'Sozinho');
$parOf = function (User $a, ?User $b) {
    User::where('id', $a->id)->update(['par_id' => $b?->id]);
    $a->refresh();
};
$parOf($gil, $hana); $parOf($hana, $gil);
$parOf($ines, $ze);  $parOf($ze, $ines);

// Uma série de 60 episódios que o casal vê junto (series_juntos nos dois sentidos)
$serie = Serie::create(['slug' => 'ms-alfa', 'nome' => 'Alfa', 'total_episodios' => 60, 'mal_id' => 999901, 'adicionada_por' => $gil->id]);
for ($n = 1; $n <= 60; $n++) {
    Capsule::table('episodios')->insert(['serie_id' => $serie->id, 'numero' => $n, 'titulo' => "Ep $n"]);
}
Capsule::table('series_juntos')->insert([
    ['serie_id' => $serie->id, 'user_id' => $gil->id,  'com_id' => $hana->id],
    ['serie_id' => $serie->id, 'user_id' => $hana->id, 'com_id' => $gil->id],
]);
$id  = fn (int $n) => (int) Capsule::table('episodios')->where('serie_id', $serie->id)->where('numero', $n)->value('id');
$ver = fn (User $u, int $n, string $quando) => Capsule::table('vistos')->insertOrIgnore(['user_id' => $u->id, 'episodio_id' => $id($n), 'visto_em' => $quando]);
$limparVistos = fn () => Capsule::table('vistos')->whereIn('user_id', [$gil->id, $hana->id])->delete();
$hoje = date('Y-m-d H:i:s');

// ---------- Sem par ----------
$verifica('sem par: estado é null', Mascote::estado($sozinho) === null);
$verifica('sem par: guardar lança', $lanca(fn () => Mascote::guardar($sozinho, ['nome' => 'X'])));
$verifica('sem par: não cria linhas', Capsule::table('mascotes')->count() === 0);

// ---------- Ovo (9 episódios) ----------
foreach (range(1, 9) as $n) { $ver($gil, $n, $hoje); $ver($hana, $n, $hoje); }
$e = Mascote::estado($gil);
$verifica('ovo: fase ovo com 9 juntos', $e['fase'] === 'ovo' && $e['episodiosJuntos'] === 9, json_encode($e));
$verifica('ovo: ainda não pode escolher', $e['podeEscolher'] === false && $e['especie'] === null);
$verifica('ovo: escolher espécie lança', $lanca(fn () => Mascote::guardar($gil, ['especie' => 'gato'])));
$verifica('ovo: a espécie ficou por escolher', Mascote::estado($gil)['especie'] === null);

// ---------- Cria (12 episódios): escolher a espécie ----------
foreach (range(10, 12) as $n) { $ver($gil, $n, $hoje); $ver($hana, $n, $hoje); }
$e = Mascote::estado($gil);
$verifica('cria: fase cria com 12 juntos', $e['fase'] === 'cria' && $e['episodiosJuntos'] === 12, json_encode($e));
$verifica('cria: pode escolher', $e['podeEscolher'] === true);
$verifica('espécie inválida lança', $lanca(fn () => Mascote::guardar($gil, ['especie' => 'dragao'])));
$verifica('espécie vazia lança', $lanca(fn () => Mascote::guardar($gil, ['especie' => ''])));
Mascote::guardar($gil, ['especie' => 'raposa']);
$verifica('escolhe raposa', Mascote::estado($gil)['especie'] === 'raposa');
$verifica('a Hana vê a mesma espécie', Mascote::estado($hana)['especie'] === 'raposa');
$verifica('segunda escolha (da Hana) lança e fica a raposa', $lanca(fn () => Mascote::guardar($hana, ['especie' => 'gato'])) && Mascote::estado($gil)['especie'] === 'raposa');
$verifica('depois de escolher já não pode escolher', Mascote::estado($gil)['podeEscolher'] === false);
$verifica('só há uma linha de mascote para o casal', Capsule::table('mascotes')->count() === 1);

// ---------- Nome e cor ----------
Mascote::guardar($gil, ['nome' => '  Kuro  ']);
$verifica('nome: tira os espaços', Mascote::estado($gil)['nome'] === 'Kuro');
$verifica('nome: 21 letras lança', $lanca(fn () => Mascote::guardar($gil, ['nome' => str_repeat('a', 21)])));
Mascote::guardar($gil, ['nome' => str_repeat('a', 20)]);
$verifica('nome: 20 letras passa', mb_strlen(Mascote::estado($gil)['nome']) === 20);
$verifica('nome: vazio lança', $lanca(fn () => Mascote::guardar($gil, ['nome' => ''])));
$verifica('nome: só espaços lança', $lanca(fn () => Mascote::guardar($gil, ['nome' => '    '])));
$verifica('nome: caracteres de controlo lançam', $lanca(fn () => Mascote::guardar($gil, ['nome' => "a\x00b"])));
$verifica('nome: não texto lança', $lanca(fn () => Mascote::guardar($gil, ['nome' => ['x']])));
Mascote::guardar($gil, ['nome' => '<script>x</script>']);
$verifica('nome: HTML guarda-se como texto (quem desenha é que escapa)', Mascote::estado($gil)['nome'] === '<script>x</script>');
Mascote::guardar($gil, ['nome' => '🦊 Kuro']);
$verifica('nome: emoji passa', Mascote::estado($gil)['nome'] === '🦊 Kuro');
Mascote::guardar($gil, ['cor' => 5]);
$verifica('cor: 5 passa', Mascote::estado($gil)['cor'] === 5);
Mascote::guardar($gil, ['cor' => '2']);
$verifica('cor: "2" (texto) passa como 2', Mascote::estado($gil)['cor'] === 2);
$verifica('cor: 6 lança', $lanca(fn () => Mascote::guardar($gil, ['cor' => 6])));
$verifica('cor: -1 lança', $lanca(fn () => Mascote::guardar($gil, ['cor' => -1])));
$verifica('cor: "a" lança', $lanca(fn () => Mascote::guardar($gil, ['cor' => 'a'])));
$verifica('cor: uma inválida não deixa o nome gravado a meio', $lanca(fn () => Mascote::guardar($gil, ['nome' => 'Outro', 'cor' => 9])) && Mascote::estado($gil)['nome'] === '🦊 Kuro');

// ---------- Costas (15+ episódios atrás numa série juntos) ----------
$limparVistos();
foreach (range(1, 20) as $n) { $ver($gil, $n, $hoje); }
foreach (range(1, 4) as $n) { $ver($hana, $n, $hoje); }
$verifica('costas: a Hana (16 atrás) está de costas', Mascote::estado($hana)['deCostas'] === true);
$verifica('costas: o Gil (à frente) não', Mascote::estado($gil)['deCostas'] === false);
foreach (range(5, 6) as $n) { $ver($hana, $n, $hoje); }
$verifica('costas: 14 atrás já não', Mascote::estado($hana)['deCostas'] === false);
foreach (range(7, 20) as $n) { $ver($hana, $n, $hoje); }

// ---------- Humor com dados ----------
$limparVistos();
$antes = date('Y-m-d H:i:s', strtotime('-8 day'));
foreach (range(1, 12) as $n) { $ver($gil, $n, $antes); $ver($hana, $n, $antes); }
$verifica('humor: último juntos há 8 dias = a_dormir', Mascote::estado($gil)['humor'] === 'a_dormir');
$ver($gil, 13, $hoje); $ver($hana, 13, $hoje);
$verifica('humor: viram juntos hoje = feliz', Mascote::estado($gil)['humor'] === 'feliz');
$limparVistos();
$verifica('humor: nenhum episódio juntos = feliz', Mascote::estado($gil)['humor'] === 'feliz');

// ---------- API: GET e PUT /mascote ----------
// Casal ines + ze: 12 episódios juntos na mesma série (o ovo já chocou)
Capsule::table('series_juntos')->insert([
    ['serie_id' => $serie->id, 'user_id' => $ines->id, 'com_id' => $ze->id],
    ['serie_id' => $serie->id, 'user_id' => $ze->id,   'com_id' => $ines->id],
]);
foreach (range(1, 12) as $n) { $ver($ines, $n, $hoje); $ver($ze, $n, $hoje); }
$tokenInes = Token::criar($ines, 'teste');
$tokenSozinho = Token::criar($sozinho, 'teste');
$api = function (string $metodo, string $token, array $corpo = []) use ($URL): array {
    $ch = curl_init($URL . '/api.php?rota=/mascote');
    $cabecalhos = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token !== '') {
        $cabecalhos[] = 'X-Auth-Token: ' . $token;
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $cabecalhos,
        CURLOPT_POSTFIELDS     => $metodo === 'GET' ? null : json_encode($corpo),
    ]);
    $texto = (string) curl_exec($ch);
    $estado = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$estado, json_decode($texto, true) ?? [], $texto];
};
[$estado] = $api('GET', '');
$verifica('API: sem token → 401', $estado === 401, (string) $estado);
[$estado, $j, $texto] = $api('GET', $tokenInes);
$verifica('API: GET devolve a cria com 12 juntos', $estado === 200 && ($j['mascote']['fase'] ?? '') === 'cria' && ($j['mascote']['podeEscolher'] ?? false) === true, $texto);
$verifica('API: nunca expõe ids de utilizadores', !str_contains($texto, 'user_a') && !str_contains($texto, 'user_b') && !str_contains($texto, '"id"'));
[$estado, $j] = $api('GET', $tokenSozinho);
$verifica('API: sem par → 200 com mascote null', $estado === 200 && array_key_exists('mascote', $j) && $j['mascote'] === null, json_encode($j));
[$estado, $j] = $api('PUT', $tokenSozinho, ['nome' => 'X']);
$verifica('API: PUT sem par → 422 com mensagem', $estado === 422 && ($j['mensagem'] ?? '') !== '', (string) $estado);
[$estado, $j] = $api('PUT', $tokenInes, ['especie' => 'raposa', 'nome' => 'Kuro', 'cor' => 3]);
$verifica('API: PUT escolhe espécie, nome e cor', $estado === 200 && ($j['mascote']['especie'] ?? '') === 'raposa' && ($j['mascote']['nome'] ?? '') === 'Kuro' && ($j['mascote']['cor'] ?? -1) === 3, json_encode($j));
[$estado, $j] = $api('PUT', $tokenInes, ['especie' => 'gato']);
$verifica('API: segunda escolha de espécie → 422 com mensagem', $estado === 422 && ($j['mensagem'] ?? '') !== '', (string) $estado);
[$estado, $j] = $api('GET', $tokenInes);
$verifica('API: a espécie continua raposa', ($j['mascote']['especie'] ?? '') === 'raposa');
[$estado, $j] = $api('PUT', $tokenInes, ['nome' => '<img src=x>']);
$verifica('API: nome com HTML devolve-se tal e qual (quem desenha é que escapa)', $estado === 200 && ($j['mascote']['nome'] ?? '') === '<img src=x>', json_encode($j));
[$estado] = $api('PUT', $tokenInes, ['cor' => 9]);
$verifica('API: cor inválida → 422', $estado === 422, (string) $estado);

// ---------- Site: página, cartão no Início e guardar ----------
// A Inês (sem a ver) e o casal Gil/Hana: o Gil entra no site com a sessão de browser (cookies + CSRF)
$cookies = [];
$http = function (string $metodo, string $caminho, array $campos = []) use ($URL, &$cookies): array {
    $ch = curl_init($URL . $caminho);
    $hdrs = $cookies ? ['Cookie: ' . implode('; ', array_map(fn ($k, $v) => "$k=$v", array_keys($cookies), $cookies))] : [];
    $hdrs[] = 'Accept: application/json, text/html';
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_HTTPHEADER => $hdrs]);
    if ($campos) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($campos));
    }
    $resposta = (string) curl_exec($ch);
    $tam = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $estado = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $cabecalhos = substr($resposta, 0, $tam);
    curl_close($ch);
    foreach (explode("\r\n", $cabecalhos) as $l) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $l, $m)) {
            $cookies[$m[1]] = $m[2];
        }
    }
    return ['estado' => $estado, 'corpo' => substr($resposta, $tam), 'cabecalhos' => $cabecalhos];
};
$csrf = function () use ($http): string {
    preg_match('/name="_csrf" value="([a-f0-9]+)"/', $http('GET', '/index.php?c=auth&a=login')['corpo'], $m);
    return $m[1] ?? '';
};
$entrar = function (string $utilizador) use ($http, $csrf) {
    $http('POST', '/index.php?c=auth&a=entrar', ['_csrf' => $csrf(), 'username' => $utilizador, 'password' => 'segredo123']);
};
Capsule::table('limites')->delete();

// Sem sessão: a página manda para o login e o guardar não aceita nada
$r = $http('GET', '/index.php?c=mascote');
$verifica('site: sem login a página redireciona', $r['estado'] === 302 && stripos($r['cabecalhos'], 'c=auth') !== false, (string) $r['estado']);
$r = $http('POST', '/index.php?c=mascote&a=guardar', ['nome' => 'X']);
$verifica('site: guardar sem sessão nem CSRF é recusado', $r['estado'] !== 200 && !str_contains($r['corpo'], '"ok":true'), (string) $r['estado']);

// Com par (Gil): cartão no Início, página com o estado, nada de scripts inline
$entrar('ms_gil');
$r = $http('GET', '/index.php?c=home');
$verifica('site: Início com par tem o cartão do bicho', $r['estado'] === 200 && str_contains($r['corpo'], 'id="mascote-cartao"'), (string) $r['estado']);
$r = $http('GET', '/index.php?c=mascote');
$verifica('site: página do bicho (200) com data-estado e id mascote', $r['estado'] === 200 && str_contains($r['corpo'], 'data-estado=') && str_contains($r['corpo'], 'id="mascote"'), (string) $r['estado']);
$verifica('site: página sem scripts inline', !preg_match('/<script(?![^>]*\bsrc=)/i', $r['corpo']));
$verifica('site: página sem style inline', !preg_match('/<style\b/i', $r['corpo']));
$verifica('site: página sem o id nem o user_a do mascote', !str_contains($r['corpo'], 'user_a') && !str_contains($r['corpo'], 'user_b'));

// Guardar com CSRF: nome hostil fica escapado na página; sem CSRF é recusado
$token = $csrf();
$r = $http('POST', '/index.php?c=mascote&a=guardar', ['nome' => '<img src=x onerror=1>']);
$verifica('site: guardar sem _csrf é recusado', !str_contains($r['corpo'], '"ok":true'), $r['corpo']);
$r = $http('POST', '/index.php?c=mascote&a=guardar', ['_csrf' => $token, 'nome' => '<img src=x onerror=1>', 'cor' => '2']);
$j = json_decode($r['corpo'], true) ?: [];
$verifica('site: guardar com _csrf → ok', $r['estado'] === 200 && ($j['ok'] ?? false) === true && ($j['mascote']['nome'] ?? '') === '<img src=x onerror=1>', $r['corpo']);
$r = $http('GET', '/index.php?c=mascote');
$verifica('site: o nome vem escapado (&lt;img) e nunca como tag', str_contains($r['corpo'], '&lt;img') && !str_contains($r['corpo'], '<img src=x'));
$r = $http('POST', '/index.php?c=mascote&a=guardar', ['_csrf' => $token, 'nome' => str_repeat('a', 21)]);
$j = json_decode($r['corpo'], true) ?: [];
$verifica('site: nome com 21 caracteres → 422 com mensagem', $r['estado'] === 422 && ($j['mensagem'] ?? '') !== '', (string) $r['estado']);
$r = $http('POST', '/index.php?c=mascote&a=guardar', ['_csrf' => $token, 'especie' => 'dragao']);
$verifica('site: espécie inválida → 422', $r['estado'] === 422, (string) $r['estado']);
$r = $http('POST', '/index.php?c=mascote&a=guardar', ['_csrf' => $token, 'especie' => 'gato']);
$verifica('site: a espécie já escolhida (raposa) não muda → 422', $r['estado'] === 422 && Mascote::estado($gil)['especie'] === 'raposa', (string) $r['estado']);
$r = $http('POST', '/index.php?c=mascote&a=guardar', ['_csrf' => $token, 'user_a' => '999', 'id' => '1']);
$verifica('site: campos desconhecidos (user_a, id) são ignorados', Capsule::table('mascotes')->where('user_a', 999)->doesntExist());

// Sem par: sem cartão no Início, a página só avisa e o guardar recusa
$cookies = [];
$entrar('ms_sozinho');
$r = $http('GET', '/index.php?c=home');
$verifica('site: Início sem par não tem cartão do bicho', $r['estado'] === 200 && !str_contains($r['corpo'], 'mascote-cartao'), (string) $r['estado']);
$r = $http('GET', '/index.php?c=mascote');
$verifica('site: página sem par avisa e não tem data-estado', $r['estado'] === 200 && str_contains($r['corpo'], 'ainda não há mascote') && !str_contains($r['corpo'], 'data-estado'), (string) $r['estado']);
$r = $http('POST', '/index.php?c=mascote&a=guardar', ['_csrf' => $csrf(), 'nome' => 'X']);
$verifica('site: guardar sem par → 422', $r['estado'] === 422, (string) $r['estado']);
$cookies = [];

// ---------- Troca de par ----------
$idCasal = Mascote::obter($gil)->id;
$parOf($gil, $ines); $parOf($ines, $gil); $parOf($hana, null); $parOf($ze, null);
$novo = Mascote::obter($gil);
$verifica('troca de par: outro mascote, em ovo, sem espécie', $novo->id !== $idCasal && Mascote::estado($gil)['especie'] === null && Mascote::estado($gil)['fase'] === 'ovo');
$verifica('troca de par: o antigo continua guardado', Capsule::table('mascotes')->where('id', $idCasal)->exists());
$parOf($gil, $hana); $parOf($hana, $gil); $parOf($ines, null);
$verifica('voltar ao par antigo devolve o mesmo mascote', Mascote::obter($gil)->id === $idCasal && Mascote::estado($gil)['especie'] === 'raposa');

// ---------- Apagar a conta ----------
$total = Capsule::table('mascotes')->count();
User::where('id', $hana->id)->delete();
$verifica('apagar a conta apaga os mascotes dessa pessoa', Capsule::table('mascotes')->count() < $total, (string) Capsule::table('mascotes')->count());

echo $falhas ? "\n$falhas falha(s)\n" : "\nTudo ok\n";
exit($falhas ? 1 : 0);
