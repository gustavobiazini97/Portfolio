<?php
// Servidor FALSO do Firebase para os testes (tests/notificacoes_test.php arranca-o com php -S).
// Imita as duas partes que a app usa:
//   POST /token                                  → verifica a assinatura do JWT e devolve um token de acesso
//   POST /v1/projects/{id}/messages:send          → aceita (200) ou, para o token "morto", responde 404 UNREGISTERED
// Regista o que recebeu em $PASTA/pedidos.jsonl para o teste confirmar. $PASTA vem da variável FCM_FALSO.

$pasta = getenv('FCM_FALSO') ?: sys_get_temp_dir() . '/fcm-falso';
$registar = function (array $linha) use ($pasta) {
    file_put_contents($pasta . '/pedidos.jsonl', json_encode($linha) . "\n", FILE_APPEND);
};
$responder = function (int $estado, array $corpo) {
    http_response_code($estado);
    header('Content-Type: application/json');
    echo json_encode($corpo);
    exit;
};

$caminho = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// ---------- Token de acesso ----------
if ($caminho === '/token') {
    $jwt = $_POST['assertion'] ?? '';
    [$cab, $dados, $assin] = array_pad(explode('.', $jwt), 3, '');
    $b64 = fn (string $s) => base64_decode(strtr($s, '-_', '+/'));
    $publica = file_get_contents($pasta . '/publica.pem');
    $valido = openssl_verify("$cab.$dados", $b64($assin), $publica, OPENSSL_ALGO_SHA256) === 1;
    $claims = json_decode($b64($dados), true) ?: [];
    $registar(['tipo' => 'token', 'valido' => $valido, 'scope' => $claims['scope'] ?? null, 'grant' => $_POST['grant_type'] ?? null]);
    if (!$valido) {
        $responder(400, ['error' => 'invalid_grant']);
    }
    $responder(200, ['access_token' => 'acesso-falso', 'expires_in' => 3600, 'token_type' => 'Bearer']);
}

// ---------- Envio ----------
if (preg_match('#^/v1/projects/([^/]+)/messages:send$#', $caminho, $m)) {
    if (($_SERVER['HTTP_AUTHORIZATION'] ?? '') !== 'Bearer acesso-falso') {
        $responder(401, ['error' => ['status' => 'UNAUTHENTICATED']]);
    }
    $corpo = json_decode((string) file_get_contents('php://input'), true) ?: [];
    $token = $corpo['message']['token'] ?? '';
    $registar(['tipo' => 'envio', 'projeto' => $m[1], 'token' => $token, 'mensagem' => $corpo['message'] ?? null]);
    if (str_starts_with($token, 'recusa')) {
        // Mensagem recusada por um campo (não pelo telemóvel): o telemóvel tem de ficar registado
        $responder(400, ['error' => ['status' => 'INVALID_ARGUMENT', 'message' => "Invalid value at 'message.android.notification.color'"]]);
    }
    if ($token === 'morto' . str_repeat('x', 20)) {
        $responder(404, ['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]]);
    }
    $responder(200, ['name' => 'projects/' . $m[1] . '/messages/1']);
}

$responder(404, ['error' => 'rota desconhecida']);
