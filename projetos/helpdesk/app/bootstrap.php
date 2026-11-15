<?php
// Arranque comum à web e aos scripts de linha de comandos (migrate, seed, cron):
// configuração, autoload das classes e tratamento de erros "fail-closed".
declare(strict_types=1);

// Pasta raiz do projeto (a que tem app/, config/, public/…)
define('RAIZ', dirname(__DIR__));

// Sem config.php não há nada a fazer: parar logo, sem revelar caminhos
$ficheiroConfig = RAIZ . '/config/config.php';
if (!is_file($ficheiroConfig)) {
    http_response_code(500);
    exit("Falta o ficheiro config/config.php (copiar de config/config.example.php).\n");
}
$GLOBALS['_config'] = require $ficheiroConfig;

require RAIZ . '/app/core/helpers.php';

date_default_timezone_set((string) config('timezone', 'Europe/Lisbon'));

// Autoload simples: procura a classe em core/, models/ e controllers/
spl_autoload_register(function (string $classe): void {
    // Só nomes de classe "limpos" (letras e números): nada de caminhos
    if (!preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $classe)) {
        return;
    }
    foreach (['core', 'models', 'controllers'] as $pasta) {
        $ficheiro = RAIZ . "/app/$pasta/$classe.php";
        if (is_file($ficheiro)) {
            require $ficheiro;
            return;
        }
    }
});

// Erros: nunca no ecrã em produção, sempre no log
error_reporting(E_ALL);
ini_set('display_errors', config('debug') ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', RAIZ . '/storage/logs/php-erros.log');

// Avisos e notices passam a exceções: um erro "pequeno" não deixa o código continuar a meio
set_error_handler(function (int $nivel, string $msg, string $ficheiro, int $linha): bool {
    if (!(error_reporting() & $nivel)) {
        return false;
    }
    throw new ErrorException($msg, 0, $nivel, $ficheiro, $linha);
});

// Qualquer exceção não apanhada: regista no log e mostra uma página genérica (fail-closed)
set_exception_handler(function (Throwable $e): void {
    error_log('[' . date('c') . '] ' . get_class($e) . ': ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
    // Deita fora o que estava a meio (meia página) antes de mostrar o erro
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (config('debug')) {
        echo '<pre>' . e((string) $e) . '</pre>';
        return;
    }
    // Página de erro sem dados internos
    $codigo = 500;
    require RAIZ . '/app/views/erros/erro.php';
});
