<?php
// Ponto de entrada único (front controller). Todos os pedidos passam por aqui:
// index.php?c=<controller>&a=<ação>

// Cookie de sessão: inacessível ao JavaScript e não enviado por outros sites.
// HTTPS direto ou atrás do proxy do alojamento (X-Forwarded-Proto) → cookie só por HTTPS
$https = !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => $https,
]);
session_start();

require __DIR__ . '/../app/bootstrap.php';

// Token CSRF da sessão, criado uma vez e usado em todos os formulários
$_SESSION['_csrf'] ??= bin2hex(random_bytes(32));

// Rotas permitidas: controller => [ações]. O que não estiver aqui dá 404.
$rotas = [
    'auth'  => ['login', 'entrar', 'registo', 'registar', 'sair'],
    'home'  => ['index'],
    'serie' => ['ver', 'marcar'],
    'perfil' => ['index', 'foto', 'enviarFoto', 'removerFoto', 'guardarNome', 'guardarPassword'],
];

// Por defeito: dashboard (que manda para o login se não houver sessão)
$c = $_GET['c'] ?? 'home';
$a = $_GET['a'] ?? 'index';

if (!isset($rotas[$c]) || !in_array($a, $rotas[$c], true)) {
    http_response_code(404);
    exit('Página não encontrada.');
}

// auth → AuthController, home → HomeController
$classe = ucfirst($c) . 'Controller';
(new $classe())->$a();
