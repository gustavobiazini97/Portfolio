<?php
// Testes das contas do SLA (horário de trabalho), da matriz de prioridade, do TOTP e da formatação da FAQ.
//   php tests/sla_totp_test.php
// Não precisa de base de dados (só do config.php para o horário).
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$falhas = 0;
$t = function (bool $cond, string $msg) use (&$falhas): void {
    echo ($cond ? '  ok   ' : '  FALHOU ') . $msg . "\n";
    $falhas += $cond ? 0 : 1;
};

// Horário de teste fixo (independente do config): seg–sex, 09:00–18:00, feriado a 1 de dezembro
$GLOBALS['_config']['horario'] = ['dias' => [1, 2, 3, 4, 5], 'inicio' => '09:00', 'fim' => '18:00', 'feriados' => ['12-01']];

$t(Sla::somar('2026-11-13 17:30:00', 60) === '2026-11-16 09:30:00', 'sexta 17:30 + 1 h = segunda 09:30');
$t(Sla::somar('2026-11-14 11:00:00', 30) === '2026-11-16 09:30:00', 'sábado: conta a partir de segunda 09:00');
$t(Sla::somar('2026-11-30 17:00:00', 120) === '2026-12-02 10:00:00', 'feriado (1 dez) saltado');
$t(Sla::somar('2026-11-16 07:00:00', 540) === '2026-11-16 18:00:00', 'antes da abertura começa às 09:00');
$t(Sla::minutosEntre('2026-11-13 17:00:00', '2026-11-16 10:00:00') === 120, 'sexta 17h → segunda 10h = 120 min úteis');
$t(Sla::minutosEntre('2026-11-16 10:00:00', '2026-11-16 09:00:00') === 0, 'intervalo ao contrário = 0');

$t(Sla::prioridade(3, 3) === 1, 'empresa toda + não consigo trabalhar = P1');
$t(Sla::prioridade(2, 3) === 2 && Sla::prioridade(3, 2) === 2, 'equipa + parado / empresa + atrapalha = P2');
$t(Sla::prioridade(1, 3) === 3 && Sla::prioridade(2, 2) === 3, 'só eu + parado / equipa + atrapalha = P3');
$t(Sla::prioridade(1, 1) === 4, 'só eu + posso continuar = P4');

// Vetores oficiais do RFC 6238 (SHA1, segredo "12345678901234567890"), últimos 6 dígitos
$r = new ReflectionClass(Totp::class);
$codigo = $r->getMethod('codigo');
$base32 = $r->getMethod('deBase32');
$chave = $base32->invoke(null, 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');
$t($chave === '12345678901234567890', 'Base32 → bytes');
$t($codigo->invoke(null, $chave, intdiv(59, 30)) === '287082', 'TOTP RFC 6238, T = 59');
$t($codigo->invoke(null, $chave, intdiv(1111111109, 30)) === '081804', 'TOTP RFC 6238, T = 1111111109');
$t($codigo->invoke(null, $chave, intdiv(2000000000, 30)) === '279037', 'TOTP RFC 6238, T = 2000000000');
$t(strlen(Totp::novoSegredo()) === 32, 'segredo novo com 32 caracteres');

$html = formatar_artigo("Olá **mundo** <script>\n\n- a\n- b `x`\n\n1. um\n2. dois");
$t(str_contains($html, '&lt;script&gt;') && !str_contains($html, '<script>'), 'FAQ: HTML escapado');
$t(str_contains($html, '<ul><li>a</li>') && str_contains($html, '<ol><li>um</li>'), 'FAQ: listas');

echo $falhas ? "\n$falhas falha(s).\n" : "\nTudo certo.\n";
exit($falhas ? 1 : 0);
