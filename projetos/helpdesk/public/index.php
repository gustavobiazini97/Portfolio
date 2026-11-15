<?php
// Ponto de entrada único (front controller). Todos os pedidos passam por aqui:
// index.php?c=<controller>&a=<ação>
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

Seguranca::cabecalhos();
Sessao::iniciar();

// Rotas permitidas: controller => [ação => método HTTP]. O que não estiver aqui dá 404.
// Todas as ações POST passam pela verificação do token CSRF antes de chegarem ao controller.
$rotas = [
    'auth' => [
        'login' => 'GET', 'entrar' => 'POST', 'codigo' => 'GET', 'verificarCodigo' => 'POST', 'sair' => 'POST',
        'recuperar' => 'GET', 'pedirLink' => 'POST', 'redefinir' => 'GET', 'guardarRedefinicao' => 'POST',
    ],
    'painel' => ['index' => 'GET'],
    'ticket' => [
        'lista' => 'GET', 'novo' => 'GET', 'criar' => 'POST', 'ver' => 'GET', 'responder' => 'POST',
        'atribuir' => 'POST', 'ficar' => 'POST', 'prioridade' => 'POST', 'categoria' => 'POST',
        'confirmar' => 'POST', 'anexo' => 'GET',
    ],
    'faq' => [
        'index' => 'GET', 'ver' => 'GET', 'sugerir' => 'GET', 'votar' => 'POST', 'resolveu' => 'POST',
        'novo' => 'GET', 'editar' => 'GET', 'guardar' => 'POST', 'apagar' => 'POST',
    ],
    'notificacao' => ['index' => 'GET', 'contar' => 'GET', 'abrir' => 'GET', 'lerTodas' => 'POST'],
    'perfil' => [
        'index' => 'GET', 'guardar' => 'POST', 'password' => 'POST',
        'configurar2fa' => 'GET', 'ativar2fa' => 'POST', 'desativar2fa' => 'POST',
    ],
    'admin' => [
        'utilizadores' => 'GET', 'utilizador' => 'GET', 'guardarUtilizador' => 'POST', 'repor2fa' => 'POST', 'reporPassword' => 'POST',
        'categorias' => 'GET', 'guardarCategoria' => 'POST',
        'sla' => 'GET', 'guardarSla' => 'POST',
        'estatisticas' => 'GET', 'auditoria' => 'GET',
    ],
];

// Por defeito: painel (que manda para o login se não houver sessão)
$c = is_string($_GET['c'] ?? null) ? $_GET['c'] : 'painel';
$a = is_string($_GET['a'] ?? null) ? $_GET['a'] : 'index';
$_GET['c'] = $c;
$_GET['a'] = $a;

if (!isset($rotas[$c][$a])) {
    http_response_code(404);
    $codigo = 404;
    require RAIZ . '/app/views/erros/erro.php';
    exit;
}

// Método errado (ex.: GET numa ação que altera dados) → 405
if ($_SERVER['REQUEST_METHOD'] !== $rotas[$c][$a]) {
    http_response_code(405);
    header('Allow: ' . $rotas[$c][$a]);
    exit('Método não permitido.');
}

// CSRF em todos os POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_valido()) {
    Auditoria::registar('csrf_invalido', "$c/$a");
    http_response_code(419);
    $codigo = 419;
    require RAIZ . '/app/views/erros/erro.php';
    exit;
}

// Emails, alertas de SLA e fechos automáticos depois de a página ser entregue (no máximo 1× por minuto)
register_shutdown_function([Tarefas::class, 'noFimDoPedido']);

// auth → AuthController, ticket → TicketController…
$classe = ucfirst($c) . 'Controller';
(new $classe())->$a();
