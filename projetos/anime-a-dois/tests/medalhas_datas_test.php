<?php
// Testes do dia em que cada medalha foi conseguida (Conquistas::diaNosVistos — a parte sem base de dados).
// Correr com: php tests/medalhas_datas_test.php   (sai com código 1 se algum falhar)

require __DIR__ . '/../app/models/Conquistas.php';

// Episódios de teste: [quando, série, filler, minutos]
$l = fn (string $em, int $serie = 1, bool $filler = false, int $minutos = 24) =>
    ['em' => $em, 'serie' => $serie, 'filler' => $filler, 'minutos' => $minutos];

$linhas = [
    $l('2026-01-01 21:00:00'),                // ano novo (quinta)
    $l('2026-01-02 02:30:00', 2, true),       // madrugada, filler
    $l('2026-01-03 06:10:00', 3),             // cedo (sábado)
    $l('2026-01-03 21:00:00', 4),
    $l('2026-01-03 21:30:00', 5),             // 5.ª série diferente em janeiro
    $l('2026-01-04 22:00:00'),                // domingo
    $l('2026-12-24 20:00:00', 1, true),       // natal, filler
];

$falhas = 0;
$verifica = function (string $nome, ?string $obtido, ?string $esperado) use (&$falhas) {
    $ok = $obtido === $esperado;
    echo ($ok ? 'ok   ' : 'FALHA') . "  $nome" . ($ok ? '' : "  (deu " . var_export($obtido, true) . ", esperava " . var_export($esperado, true) . ')') . "\n";
    $falhas += $ok ? 0 : 1;
};

$d = fn (string $grupo, int $meta, ?string $criada = null) => Conquistas::diaNosVistos($grupo, $meta, $linhas, $criada);

$verifica('1.º episódio',            $d('ep', 1), '2026-01-01 21:00:00');
$verifica('5.º episódio',            $d('ep', 5), '2026-01-03 21:30:00');
$verifica('episódios que não chegam', $d('ep', 100), null);
$verifica('2.º filler',              $d('fillers', 2), '2026-12-24 20:00:00');
$verifica('horas (meta 48 min)',     $d('horas', 48), '2026-01-02 02:30:00');
$verifica('madrugada',               $d('madrugada', 1), '2026-01-02 02:30:00');
$verifica('cedo',                    $d('cedo', 1), '2026-01-03 06:10:00');
$verifica('fim de semana (3.º)',     $d('fim-semana', 3), '2026-01-03 21:30:00');
$verifica('ano novo',                $d('ano-novo', 1), '2026-01-01 21:00:00');
$verifica('natal',                   $d('natal', 1), '2026-12-24 20:00:00');
$verifica('maratona de 3 num dia',   $d('maratona', 3), '2026-01-03 21:30:00');
$verifica('4 dias seguidos',         $d('seguidos', 4), '2026-01-04 22:00:00');
$verifica('5 dias seguidos (não)',   $d('seguidos', 5), null);
$verifica('explorador (5 séries)',   $d('explorador', 5), '2026-01-03 21:30:00');
$verifica('aniversário da conta',    $d('aniversario', 1, '2025-12-24 10:00:00'), '2026-12-24 20:00:00');
$verifica('aniversário no 1.º ano (não conta)', $d('aniversario', 1, '2026-12-24 10:00:00'), null);

exit($falhas > 0 ? 1 : 0);
