<?php
// Ponto de entrada da API REST (usada pela app Flutter). Responde sempre em JSON.
//
// Formato dos URLs:  api.php/<rota>            ex.: api.php/series/naruto
//   (com o .htaccess também funciona /api/<rota>; sem PATH_INFO: api.php?rota=/series/naruto)
// Autenticação:      Authorization: Bearer <token>   (o token vem do POST /auth/login)
// Documentação:      docs/API.md

// Avisos do PHP nunca podem aparecer no meio do JSON
ini_set('display_errors', '0');

require __DIR__ . '/../app/bootstrap.php';

// ---------- CORS ----------
// A app Android não precisa disto, mas assim a API também se pode testar a partir de uma página web.
// Não há cookies envolvidos (só o token no cabeçalho), por isso "*" é seguro.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, X-Auth-Token, Content-Type');

// Pedido de verificação do browser (preflight): basta responder que sim
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---------- Rotas ----------
// [método, padrão, classe, ação]. {nome} apanha um pedaço do URL e passa-o à ação.
$rotas = [
    // Conta
    ['GET',    '/auth/estado',                        AuthApi::class,        'estado'],
    ['POST',   '/auth/login',                         AuthApi::class,        'login'],
    ['POST',   '/auth/registo',                       AuthApi::class,        'registo'],
    ['POST',   '/auth/logout',                        AuthApi::class,        'logout'],
    ['GET',    '/eu',                                 AuthApi::class,        'eu'],

    // Início, séries da tua biblioteca, vistos e estatísticas
    ['GET',    '/inicio',                             SeriesApi::class,      'inicio'],
    ['GET',    '/series',                             SeriesApi::class,      'lista'],
    ['GET',    '/series/{slug}',                      SeriesApi::class,      'ver'],
    ['POST',   '/series/{slug}/vistos',               SeriesApi::class,      'marcar'],
    ['GET',    '/series/{slug}/estatisticas',         SeriesApi::class,      'estatisticas'],

    // Biblioteca: adicionar, estado, convites "Quero ver contigo", juntar, separar, tirar
    ['POST',   '/biblioteca',                         BibliotecaApi::class,  'adicionar'],
    ['PUT',    '/series/{slug}/estado',               BibliotecaApi::class,  'estado'],
    ['POST',   '/series/{slug}/convites',             BibliotecaApi::class,  'convidar'],
    ['POST',   '/series/{slug}/convites/aceitar',     BibliotecaApi::class,  'aceitar'],
    ['DELETE', '/series/{slug}/convites/{id}',        BibliotecaApi::class,  'recusar'],
    ['POST',   '/series/{slug}/juntar',               BibliotecaApi::class,  'juntar'],
    ['DELETE', '/series/{slug}/juntos/{id}',          BibliotecaApi::class,  'separar'],
    ['DELETE', '/series/{slug}',                      BibliotecaApi::class,  'remover'],

    // Comentários (só os teus e os de quem vê a série contigo)
    ['GET',    '/episodios/{id}/comentarios',         ComentariosApi::class, 'lista'],
    ['POST',   '/episodios/{id}/comentarios',         ComentariosApi::class, 'criar'],
    ['DELETE', '/comentarios/{id}',                   ComentariosApi::class, 'apagar'],

    // Amigos e perfis de outras pessoas
    ['GET',    '/amigos',                             AmigosApi::class,      'lista'],
    ['POST',   '/amigos/convite',                     AmigosApi::class,      'criarConvite'],
    ['POST',   '/amigos/pedidos',                     AmigosApi::class,      'pedir'],
    ['POST',   '/amigos/pedidos/{id}/aceitar',        AmigosApi::class,      'aceitar'],
    ['DELETE', '/amigos/pedidos/{id}',                AmigosApi::class,      'recusar'],
    ['DELETE', '/amigos/{id}',                        AmigosApi::class,      'remover'],
    ['GET',    '/pessoas/{id}',                       AmigosApi::class,      'pessoa'],

    // Perfil
    ['PUT',    '/perfil',                             PerfilApi::class,      'guardar'],
    ['PUT',    '/perfil/password',                    PerfilApi::class,      'password'],
    ['PUT',    '/perfil/paleta',                      PerfilApi::class,      'paleta'],
    ['PUT',    '/perfil/privacidade',                 PerfilApi::class,      'privacidade'],
    ['POST',   '/perfil/apagar',                      PerfilApi::class,      'apagarConta'],
    ['POST',   '/perfil/foto',                        PerfilApi::class,      'enviarFoto'],
    ['DELETE', '/perfil/foto',                        PerfilApi::class,      'removerFoto'],
    ['GET',    '/perfil/notificacoes',                PerfilApi::class,      'notificacoes'],
    ['PUT',    '/perfil/notificacoes',                PerfilApi::class,      'guardarNotificacoes'],
    ['GET',    '/utilizadores/{id}/foto',             PerfilApi::class,      'foto'],
];

// Rota pedida: PATH_INFO (api.php/series) ou ?rota= (alojamentos sem PATH_INFO)
$rota   = '/' . trim($_SERVER['PATH_INFO'] ?? ($_GET['rota'] ?? ''), '/');
$metodo = $_SERVER['REQUEST_METHOD'];

// Envia uma resposta de erro em JSON e termina
$falhar = function (string $mensagem, int $estado): never {
    http_response_code($estado);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'mensagem' => $mensagem], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

// Raiz: identificação da API (útil para ver se está no ar)
if ($rota === '/') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'     => true,
        'api'    => 'Anime a Dois',
        'versao' => 2,
        'rotas'  => array_map(fn ($r) => $r[0] . ' ' . $r[1], $rotas),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Procura a rota: o padrão vira uma expressão regular ({slug} → ([^/]+))
$metodoErrado = false;
foreach ($rotas as [$m, $padrao, $classe, $acao]) {
    $regex = '#^' . preg_replace('#\{\w+\}#', '([^/]+)', $padrao) . '$#';
    if (!preg_match($regex, $rota, $partes)) {
        continue;
    }
    if ($m !== $metodo) {
        $metodoErrado = true;    // o caminho existe, mas com outro método (ex.: GET em vez de POST)
        continue;
    }

    array_shift($partes);        // tira o URL inteiro; ficam só os {parâmetros}
    $partes = array_map('rawurldecode', $partes);

    try {
        (new $classe())->$acao(...$partes);
    } catch (InvalidArgumentException $e) {
        // Erros de validação dos Models: a mensagem já está pronta para o utilizador
        $falhar($e->getMessage(), 422);
    } catch (PDOException $e) {
        $falhar('Não foi possível ligar à base de dados. Tenta outra vez.', 500);
    } catch (Throwable $e) {
        error_log('[api] ' . $e);  // fica no log do servidor, nunca na resposta
        $falhar('Algo correu mal no servidor.', 500);
    }
    exit;
}

$metodoErrado
    ? $falhar('Método ' . $metodo . ' não é permitido nesta rota.', 405)
    : $falhar('Rota não encontrada: ' . $rota, 404);
