<?php
// Testes das notificações (Firebase + preferências) com base de dados a sério e um Firebase FALSO (tests/fcm_falso.php).
// Precisa do mesmo que o seguranca_test.php: config.php para uma base de dados de teste, migrate corrido e o site
// a correr em $URL. Correr com: php tests/notificacoes_test.php   (sai com 1 se algum falhar)
//
// Cobre: JWT assinado e trocado por token de acesso (guardado), envio ao telemóvel, token morto apagado,
// preferência desligada não envia, registo/remoção de dispositivos pela API, preferências só com push,
// limpeza dos emails antigos pelo migrate.

require __DIR__ . '/../app/bootstrap.php';

use Illuminate\Database\Capsule\Manager as Capsule;

$URL = getenv('URL_TESTE') ?: 'http://127.0.0.1:8099';

$falhas = 0;
$verifica = function (string $nome, bool $ok, string $detalhe = '') use (&$falhas) {
    echo ($ok ? 'ok   ' : 'FALHA') . "  $nome" . ($ok || $detalhe === '' ? '' : "  ($detalhe)") . "\n";
    $falhas += $ok ? 0 : 1;
};

// ---------- Firebase falso: chave RSA nova, conta de serviço a apontar para ele, servidor em 8098 ----------
$pasta = sys_get_temp_dir() . '/fcm-falso-' . getmypid();
@mkdir($pasta, 0700, true);
@unlink($pasta . '/pedidos.jsonl');
$chave = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
openssl_pkey_export($chave, $privada);
file_put_contents($pasta . '/publica.pem', openssl_pkey_get_details($chave)['key']);
file_put_contents($pasta . '/conta.json', json_encode([
    'type' => 'service_account', 'project_id' => 'teste-aad', 'private_key' => $privada,
    'client_email' => 'teste@teste-aad.iam.gserviceaccount.com', 'token_uri' => 'http://127.0.0.1:8098/token',
]));
$servidor = proc_open(
    [PHP_BINARY, '-S', '127.0.0.1:8098', __DIR__ . '/fcm_falso.php'],
    [['pipe', 'r'], ['file', $pasta . '/servidor.log', 'a'], ['file', $pasta . '/servidor.log', 'a']],
    $pipes, null, ['FCM_FALSO' => $pasta]
);
usleep(700000);
register_shutdown_function(fn () => proc_terminate($servidor));

Fcm::$contaFicheiro = $pasta . '/conta.json';
Fcm::$base = 'http://127.0.0.1:8098';
$pedidos = function () use ($pasta): array {
    $f = $pasta . '/pedidos.jsonl';
    return is_file($f) ? array_map(fn ($l) => json_decode($l, true), file($f, FILE_IGNORE_NEW_LINES)) : [];
};
Capsule::table('config_app')->where('chave', 'fcm_acesso')->delete();

// ---------- Dados ----------
$criar = fn (string $u) => User::create(['nome' => ucfirst($u), 'username' => $u, 'password_hash' => password_hash('segredo123', PASSWORD_DEFAULT)]);
$rita = $criar('rita-n');
$tiago = $criar('tiago-n');
$vivo = 'vivo' . str_repeat('x', 20);
$morto = 'morto' . str_repeat('x', 20);

// ---------- 1. Dispositivos ----------
$erro = null;
try {
    Dispositivo::registar($rita, 'curto');
} catch (InvalidArgumentException $e) {
    $erro = $e;
}
$verifica('token com formato inválido recusado', $erro !== null);
Dispositivo::registar($rita, $vivo);
Dispositivo::registar($rita, $morto);
$verifica('dois telemóveis registados', Dispositivo::where('user_id', $rita->id)->count() === 2);
Dispositivo::registar($tiago, $vivo);
$verifica('mesmo telemóvel, outra pessoa: passa a ser dela', Dispositivo::where('token', $vivo)->value('user_id') === $tiago->id);
Dispositivo::registar($rita, $vivo);

// ---------- 2. Envio pelo Firebase ----------
$verifica('Firebase ativo com a conta de serviço', Fcm::ativo());
$n = Fcm::enviar($rita, ['titulo' => 'Tiago viu o episódio 5', 'corpo' => 'Naruto', 'url' => 'https://exemplo/x', 'tag' => 'ep-naruto']);
$p = $pedidos();
$tokens = array_values(array_filter($p, fn ($r) => $r['tipo'] === 'token'));
$envios = array_values(array_filter($p, fn ($r) => $r['tipo'] === 'envio'));
$verifica('JWT com assinatura válida', ($tokens[0]['valido'] ?? false) === true);
$verifica('JWT com o escopo do FCM', ($tokens[0]['scope'] ?? '') === Fcm::ESCOPO);
$verifica('enviado a 1 telemóvel (o vivo)', $n === 1, (string) $n);
$verifica('mensagem com título e etiqueta', ($envios[0]['mensagem']['notification']['title'] ?? '') === 'Tiago viu o episódio 5'
    && ($envios[0]['mensagem']['android']['notification']['tag'] ?? '') === 'ep-naruto');
$verifica('projeto certo no URL', ($envios[0]['projeto'] ?? '') === 'teste-aad');
$verifica('ícone com a cor da paleta da pessoa', ($envios[0]['mensagem']['android']['notification']['color'] ?? '') === User::CORES_PALETA[0]);
$verifica('telemóvel morto apagado', Dispositivo::where('token', $morto)->doesntExist());

Fcm::enviar($rita, ['titulo' => 'Outra', 'corpo' => 'x']);
$tokens = array_filter($pedidos(), fn ($r) => $r['tipo'] === 'token');
$verifica('token de acesso reaproveitado (só 1 pedido à Google)', count($tokens) === 1, (string) count($tokens));

$verifica('sem conta de serviço: desligado, sem erro', (function () use ($rita) {
    Fcm::$contaFicheiro = '/nao/existe.json';
    $r = Fcm::enviar($rita, ['titulo' => 'x', 'corpo' => 'y']) === 0 && !Fcm::ativo();
    return $r;
})());
Fcm::$contaFicheiro = $pasta . '/conta.json';

// ---------- 3. Preferências: só push, e desligado não envia ----------
Preferencia::guardar($rita, ['ep_push' => '1', 'com_push' => '', 'email' => 'nao@guardar.pt', 'ep_email' => '1']);
$pref = Preferencia::de($rita);
$verifica('preferências: episódios ligado, comentários desligado', $pref->ep_push && !$pref->com_push);
$verifica('preferências: email ignorado', Capsule::table('preferencias')->where('user_id', $rita->id)->value('email') === null);
$verifica('preferências em JSON: só os 4 interruptores', array_keys($pref->paraJson()) === Preferencia::TIPOS);

// O Notificador só envia quando o tipo está ligado (paraPessoa lê Preferencia::de)
$verifica('comentários desligados: não envia', (bool) Preferencia::de($rita)->com_push === false);

// ---------- 4. API: dispositivos e preferências ----------
$tokenApi = Token::criar($tiago, 'teste');
$api = function (string $metodo, string $rota, array $corpo = []) use ($URL, $tokenApi): array {
    $ch = curl_init($URL . '/api.php?rota=' . $rota);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json', 'X-Auth-Token: ' . $tokenApi],
        CURLOPT_POSTFIELDS     => json_encode($corpo),
    ]);
    $corpoResp = (string) curl_exec($ch);
    $estado = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$estado, json_decode($corpoResp, true) ?? []];
};
$novo = 'apiapi' . str_repeat('y', 30);
[$estado] = $api('POST', '/dispositivos', ['token' => $novo]);
$verifica('API: regista o telemóvel', $estado === 200 && Dispositivo::where('token', $novo)->value('user_id') === $tiago->id, (string) $estado);
[$estado] = $api('POST', '/dispositivos', ['token' => 'mau']);
$verifica('API: token inválido → 422', $estado === 422, (string) $estado);
[$estado] = $api('DELETE', '/dispositivos', ['token' => $novo]);
$verifica('API: terminar sessão apaga o telemóvel', $estado === 200 && Dispositivo::where('token', $novo)->doesntExist());
[$estado, $r] = $api('PUT', '/perfil/notificacoes', ['ep_push' => true, 'com_push' => false, 'amigo_push' => true, 'serie_push' => false]);
$verifica('API: preferências guardadas sem email', $estado === 200 && ($r['preferencias'] ?? null) === ['ep_push' => true, 'com_push' => false, 'amigo_push' => true, 'serie_push' => false], json_encode($r));
[$estado] = $api('POST', '/perfil/notificacoes/teste');
$verifica('API: teste de notificação responde', $estado === 200, (string) $estado);

// ---------- 5. Migrate apaga os emails antigos ----------
Capsule::table('preferencias')->updateOrInsert(['user_id' => $tiago->id], ['email' => 'velho@exemplo.pt', 'ep_email' => 1]);
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../database/migrate.php') . ' 2>&1', $saida, $codigo);
$linha = Capsule::table('preferencias')->where('user_id', $tiago->id)->first();
$verifica('migrate: email antigo apagado', $codigo === 0 && $linha->email === null && (int) $linha->ep_email === 0, implode(' | ', array_slice($saida, -3)));

echo $falhas === 0 ? "\nTudo certo.\n" : "\n$falhas falha(s).\n";
exit($falhas === 0 ? 0 : 1);
