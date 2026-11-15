<?php
// Tarefas em segundo plano: emails da fila, alertas de SLA, fecho automático e limpezas.
// Agendar para correr a cada minuto:
//   Linux/alojamento (crontab):  * * * * * php /caminho/helpdesk/cron/processar.php > /dev/null 2>&1
//   Windows (WAMP): Agendador de Tarefas → php.exe C:\wamp64\www\helpdesk\cron\processar.php (a cada 1 minuto)
// Sem cron, a app corre isto sozinha no fim dos pedidos (no máximo 1× por minuto), mas o cron é mais fiável.
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../app/bootstrap.php';

// Um só processo de cada vez (se o anterior ainda estiver a enviar emails, este sai)
$lock = fopen(RAIZ . '/storage/logs/.cron.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}

$r = Tarefas::correr();
touch(RAIZ . '/storage/logs/.ultima-tarefa');
echo date('Y-m-d H:i:s') . " · alertas SLA: {$r['sla']} · fechados: {$r['fechados']} · emails: {$r['emails']}\n";

flock($lock, LOCK_UN);
