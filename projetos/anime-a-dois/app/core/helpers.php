<?php
// Funções pequenas usadas nas views.

// Escapa texto para HTML (usar SEMPRE ao imprimir dados nas views).
// O illuminate/support já define um e() equivalente; este só entra se ele não existir.
if (!function_exists('e')) {
    function e(?string $texto): string
    {
        return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// URL interno no formato do mini-MVC: index.php?c=controller&a=acao&...
function url(string $c, string $a = 'index', array $params = []): string
{
    return 'index.php?' . http_build_query(['c' => $c, 'a' => $a] + $params);
}

// Caminho de um ficheiro de public/ com a versão no fim (?v=data de modificação):
// cada deploy muda o endereço, por isso nem o browser nem o service worker servem uma cópia antiga
function asset(string $caminho): string
{
    $ficheiro = __DIR__ . '/../../public/' . $caminho;
    return $caminho . (is_file($ficheiro) ? '?v=' . filemtime($ficheiro) : '');
}

// "1 episódio" / "15 episódios"
function plural(int $n, string $um, string $varios): string
{
    return $n . ' ' . ($n === 1 ? $um : $varios);
}

// Data em linguagem de conversa: "agora mesmo", "há 5 min", "há 2 horas", "ontem", "há 3 dias", "12/09"
function tempo_relativo(?string $data): string
{
    if ($data === null) {
        return '';
    }
    $quando = strtotime($data);
    $seg = time() - $quando;

    if ($seg < 60)     return 'agora mesmo';
    if ($seg < 3600)   return 'há ' . intdiv($seg, 60) . ' min';
    if ($seg < 86400)  return 'há ' . plural(intdiv($seg, 3600), 'hora', 'horas');
    if ($seg < 172800) return 'ontem';
    if ($seg < 604800) return 'há ' . intdiv($seg, 86400) . ' dias';
    return date('d/m', $quando);
}

// Campo escondido com o token CSRF, para pôr dentro de todos os <form method="post">
function csrf_campo(): string
{
    return '<input type="hidden" name="_csrf" value="' . e($_SESSION['_csrf'] ?? '') . '">';
}
