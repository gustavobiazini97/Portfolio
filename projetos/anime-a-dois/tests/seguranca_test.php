<?php
// Testes de segurança com base de dados e servidor a sério (correm no GitHub Actions: anime-a-dois-testes.yml).
// Precisa de: config/config.php a apontar para uma base de dados VAZIA, migrate já corrido, e o site a correr em
// $URL (php -S 127.0.0.1:8099 -t public). Correr com: php tests/seguranca_test.php   (sai com 1 se algum falhar)
//
// Cobre: limite de tentativas de login (site e API), cabeçalhos de segurança, raiz da API sem lista de rotas,
// "juntar" a uma série de um amigo só por convite, só membros atualizam uma série, push só para serviços conhecidos.

require __DIR__ . '/../app/bootstrap.php';

use Illuminate\Database\Capsule\Manager as Capsule;

$URL = getenv('URL_TESTE') ?: 'http://127.0.0.1:8099';

$falhas = 0;
$verifica = function (string $nome, bool $ok, string $detalhe = '') use (&$falhas) {
    echo ($ok ? 'ok   ' : 'FALHA') . "  $nome" . ($ok || $detalhe === '' ? '' : "  ($detalhe)") . "\n";
    $falhas += $ok ? 0 : 1;
};

// ---------- Cliente HTTP mínimo (com cookies, para a sessão do site) ----------
$cookies = [];
$http = function (string $metodo, string $caminho, array $campos = [], array $cabecalhos = []) use ($URL, &$cookies): array {
    $ch = curl_init($URL . $caminho);
    $hdrs = $cabecalhos;
    if ($cookies) {
        $hdrs[] = 'Cookie: ' . implode('; ', array_map(fn ($k, $v) => "$k=$v", array_keys($cookies), $cookies));
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_HTTPHEADER     => $hdrs,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($campos) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, in_array('Content-Type: application/json', $cabecalhos, true) ? json_encode($campos) : http_build_query($campos));
    }
    $resposta = (string) curl_exec($ch);
    $tam = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $estado = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $cab = substr($resposta, 0, $tam);
    foreach (explode("\r\n", $cab) as $linha) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $linha, $m)) {
            $cookies[$m[1]] = $m[2];
        }
    }
    return ['estado' => $estado, 'cab' => strtolower($cab), 'corpo' => substr($resposta, $tam)];
};
// Abre uma página (por defeito o login) e devolve o token CSRF da sessão
$csrf = function (string $pagina = '/index.php?c=auth&a=login') use ($http): string {
    $r = $http('GET', $pagina);
    preg_match('/name="_csrf" value="([a-f0-9]+)"/', $r['corpo'], $m);
    return $m[1] ?? '';
};
// Lê a mensagem flash da página de login (depois de um redirect)
$flashLogin = function () use ($http): string {
    return html_entity_decode($http('GET', '/index.php?c=auth&a=login')['corpo']);
};

// ---------- Dados: casal (ana + bruno), amigo (carla), e mais um (duarte) ----------
$criar = fn (string $u, string $nome) => User::create(['nome' => $nome, 'username' => $u, 'password_hash' => password_hash('segredo123', PASSWORD_DEFAULT)]);
$ana = $criar('ana', 'Ana');
$bruno = $criar('bruno', 'Bruno');
$carla = $criar('carla', 'Carla');
$duarte = $criar('duarte', 'Duarte');
User::where('id', $ana->id)->update(['par_id' => $bruno->id]);
User::where('id', $bruno->id)->update(['par_id' => $ana->id]);
$ana->refresh();
$bruno->refresh();
Amizade::pedir($carla, 'ana');
Amizade::aceitar($ana, $carla);

// Série da ana (sem passar pelo AniList)
$serie = Serie::create(['slug' => 'teste', 'nome' => 'Teste', 'total_episodios' => 12, 'mal_id' => 999999, 'adicionada_por' => $ana->id]);
$serie2 = Serie::create(['slug' => 'teste-2', 'nome' => 'Teste 2', 'total_episodios' => 12, 'mal_id' => 999998, 'adicionada_por' => $ana->id]);
foreach ([$serie, $serie2] as $s) {
    Capsule::table('bibliotecas')->insert(['user_id' => $ana->id, 'serie_id' => $s->id, 'estado' => 'a_ver', 'desde' => date('Y-m-d H:i:s')]);
}

// ---------- 1. Juntar: amigo só por convite; par direto ----------
$r = $serie->juntarSe($carla, $ana);
$verifica('amigo a juntar-se → convite', $r === 'convite', $r);
$verifica('amigo ainda não é companheiro', !$serie->verCom($carla, $ana));
$verifica('série entrou na biblioteca do amigo', $serie->naBibliotecaDe($carla));
$verifica('convite pendente para a dona', Capsule::table('convites_serie')->where('serie_id', $serie->id)->where('de_id', $carla->id)->where('para_id', $ana->id)->exists());
$serie->aceitar($ana, $carla);
$verifica('depois de aceitar → veem juntos', $serie->verCom($carla, $ana) && $serie->verCom($ana, $carla));

$r = $serie2->juntarSe($bruno, $ana);
$verifica('par a juntar-se → juntos logo', $r === 'juntos' && $serie2->verCom($bruno, $ana), $r);

// Amigo que já tinha sido convidado: juntar = aceitar
$serie2->convidar($ana, $carla);
$r = $serie2->juntarSe($carla, $ana);
$verifica('amigo já convidado a juntar-se → juntos', $r === 'juntos' && $serie2->verCom($carla, $ana), $r);

// ---------- 2. Limite de login (modelo) ----------
for ($i = 0; $i < 5; $i++) {
    Limite::loginFalhou('duarte');
}
$verifica('5 falhas: ainda não bloqueia', !Limite::loginBloqueado('duarte'));
Limite::loginFalhou('duarte');
$verifica('6.ª falha: bloqueia o utilizador', Limite::loginBloqueado('duarte'));
$verifica('outro utilizador não fica bloqueado', !Limite::loginBloqueado('ana'));
Capsule::table('limites')->delete();   // começar o HTTP do zero

// ---------- 3. Cabeçalhos e raiz da API ----------
$r = $http('GET', '/index.php?c=auth&a=login');
$verifica('página: CSP', str_contains($r['cab'], "content-security-policy: default-src 'self'"));
$verifica('página: nosniff', str_contains($r['cab'], 'x-content-type-options: nosniff'));
$verifica('página: sem iframes', str_contains($r['cab'], 'x-frame-options: deny'));
$verifica('página: sem X-Powered-By', !str_contains($r['cab'], 'x-powered-by'));
$r = $http('GET', '/api.php');
$verifica('API: raiz sem lista de rotas', $r['estado'] === 200 && !str_contains($r['corpo'], 'rotas'));
$verifica('API: CSP', str_contains($r['cab'], "content-security-policy: default-src 'none'"));

// ---------- 4. Login pelo site: depois de 6 falhas fica bloqueado (nem a certa entra) ----------
for ($i = 1; $i <= 6; $i++) {
    $http('POST', '/index.php?c=auth&a=entrar', ['_csrf' => $csrf(), 'username' => 'bruno', 'password' => 'errada' . $i]);
}
$verifica('site: a 6.ª ainda diz "errados"', str_contains($flashLogin(), 'palavra-passe errados'));
$http('POST', '/index.php?c=auth&a=entrar', ['_csrf' => $csrf(), 'username' => 'bruno', 'password' => 'segredo123']);
$verifica('site: bloqueado, nem a certa entra', str_contains($flashLogin(), 'Demasiadas tentativas'));
$verifica('site: continua sem sessão', $http('GET', '/index.php?c=home')['estado'] === 302);
Capsule::table('limites')->delete();

// ---------- 5. Login pela API: 429 depois de 5 falhas ----------
$json = ['Content-Type: application/json', 'Accept: application/json'];
$estados = [];
for ($i = 1; $i <= 7; $i++) {
    $estados[] = $http('POST', '/api.php?rota=/auth/login', ['username' => 'carla', 'password' => 'x' . $i], $json)['estado'];
}
$verifica('API: 401 até à 6.ª, depois 429', $estados === [401, 401, 401, 401, 401, 401, 429], implode(',', $estados));
Capsule::table('limites')->delete();
$r = $http('POST', '/api.php?rota=/auth/login', ['username' => 'carla', 'password' => 'segredo123'], $json);
$verifica('API: login certo depois de limpar', $r['estado'] === 200);

// ---------- 6. Atualizar série: só quem a tem na biblioteca ----------
$cookies = [];
$http('POST', '/index.php?c=auth&a=entrar', ['_csrf' => $csrf(), 'username' => 'duarte', 'password' => 'segredo123']);
$token = $csrf('/index.php?c=perfil');   // depois do login a sessão é nova (o login já não abre: vai para o início)
$verifica('login do duarte (para os testes seguintes)', $token !== '');
$r = $http('POST', '/index.php?c=biblioteca&a=atualizar', [
    '_csrf' => $token, 'serie' => 'teste',
    'info' => json_encode(['mal_id' => 999999, 'nome' => 'Nome falso', 'episodios' => 3000]),
], ['Accept: application/json']);
$verifica('quem não tem a série não a atualiza (403)', $r['estado'] === 403, (string) $r['estado']);
$verifica('nome da série ficou igual', Serie::find($serie->id)->nome === 'Teste');

// ---------- 7. Push: só serviços conhecidos ----------
$erro = null;
try {
    Subscricao::registar($ana, ['endpoint' => 'https://interno.exemplo/roubar', 'keys' => ['p256dh' => 'x', 'auth' => 'y']]);
} catch (InvalidArgumentException $e) {
    $erro = $e->getMessage();
}
$verifica('push: endpoint desconhecido recusado', $erro !== null);
$s = Subscricao::registar($ana, ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['p256dh' => 'x', 'auth' => 'y']]);
$verifica('push: FCM aceite', $s->exists);

// ---------- 8. Mudar a palavra-passe no site termina os tokens da app ----------
Token::criar($duarte, 'telemovel');
$http('POST', '/index.php?c=perfil&a=guardarPassword', ['_csrf' => $token, 'atual' => 'segredo123', 'nova' => 'novasenha123', 'confirmar' => 'novasenha123'], ['Accept: application/json']);
$verifica('tokens da app apagados ao mudar a palavra-passe', Token::where('user_id', $duarte->id)->count() === 0);

echo $falhas === 0 ? "\nTudo certo.\n" : "\n$falhas falha(s).\n";
exit($falhas === 0 ? 0 : 1);
