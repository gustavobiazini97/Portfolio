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
// Paleta de cores da pessoa com sessão (0 = original); usada no <html data-paleta> para as cores aparecerem sem piscar
function paleta_atual(): int
{
    static $paleta = null;
    if ($paleta === null) {
        $id = $_SESSION['user_id'] ?? null;
        try {
            $paleta = $id === null ? 0 : (int) User::where('id', $id)->value('paleta');
        } catch (Throwable $e) {
            $paleta = 0;   // base de dados ainda sem a coluna (antes do migrate): cores originais
        }
    }
    return $paleta;
}

function url(string $c, string $a = 'index', array $params = []): string
{
    return 'index.php?' . http_build_query(['c' => $c, 'a' => $a] + $params);
}

// Endereço completo da app (para partilhar), ex.: https://animeadois.alwaysdata.net/
function url_absoluto(): string
{
    $https = !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $pasta = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $pasta . '/';
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

// Corre uma tarefa DEPOIS de a resposta chegar ao telemóvel (notificações, buscar episódios novos).
// No alojamento (PHP-FPM) o fastcgi_finish_request() entrega a página primeiro; uma falha aqui
// fica só no log e nunca estraga o pedido.
function depois(callable $tarefa): void
{
    register_shutdown_function(function () use ($tarefa) {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        try {
            $tarefa();
        } catch (Throwable $e) {
            error_log('Tarefa em segundo plano: ' . $e->getMessage());
        }
    });
}

// Dia de um registo, à portuguesa: "hoje, 22:10", "ontem, 22:10", "sábado, 21:00", "17 out", "17 out 2025"
// (igual ao da app: diaDoRegisto em widgets/comum.dart)
function dia_do_registo(?string $data): string
{
    if ($data === null || $data === '') {
        return '';
    }
    $quando = strtotime($data);
    $dias   = (int) floor((strtotime('today') - strtotime(date('Y-m-d', $quando))) / 86400);
    $hora   = date('H:i', $quando);
    $semana = ['segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado', 'domingo'];
    $meses  = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

    if ($dias <= 0) return 'hoje, ' . $hora;
    if ($dias === 1) return 'ontem, ' . $hora;
    if ($dias < 7) return $semana[(int) date('N', $quando) - 1] . ', ' . $hora;
    $dataCurta = (int) date('j', $quando) . ' ' . $meses[(int) date('n', $quando) - 1];
    return date('Y', $quando) === date('Y') ? $dataCurta : $dataCurta . ' ' . date('Y', $quando);
}

// "Ep. 14 · ontem, 22:10" a partir de Serie::ultimoDe() ({numero, vistoEm}); '' se não há registo
function texto_ultimo_registo(?array $ultimo): string
{
    return $ultimo === null ? '' : 'Ep. ' . $ultimo['numero'] . ' · ' . dia_do_registo($ultimo['vistoEm']);
}

// Progresso de uma medalha: "99 de 100" (as de tempo em horas: "12 de 24 h")
function medalha_progresso(array $m): string
{
    return str_starts_with($m['id'], 'horas')
        ? intdiv($m['atual'], 60) . ' de ' . intdiv($m['meta'], 60) . ' h'
        : $m['atual'] . ' de ' . $m['meta'];
}

// Dia em que uma medalha foi conseguida: "Conseguida a 14 out 2026" / "Conseguida hoje" / "Conseguida ontem" /
// "Conseguida antes de 22 out 2026" (quando não deu para saber o dia); '' se não tem data ou está por obter.
// (A app faz o mesmo em Medalha.quandoTexto, em lib/ecras/medalhas.dart.)
function medalha_quando(array $m): string
{
    if (empty($m['obtida']) || empty($m['obtidaEm'])) {
        return '';
    }
    $quando = strtotime($m['obtidaEm']);
    $meses  = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    $data   = (int) date('j', $quando) . ' ' . $meses[(int) date('n', $quando) - 1] . ' ' . date('Y', $quando);

    if (!empty($m['obtidaAprox'])) {
        return 'Conseguida antes de ' . $data;
    }
    $dias = (int) round((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', $quando))) / 86400);
    if ($dias <= 0) {
        return 'Conseguida hoje';
    }
    if ($dias === 1) {
        return 'Conseguida ontem';
    }
    return 'Conseguida a ' . $data;
}

// Percentagem de uma medalha (0–100)
function medalha_pct(array $m): int
{
    return $m['meta'] > 0 ? (int) min(100, floor($m['atual'] * 100 / $m['meta'])) : 0;
}

// Cabeçalhos de segurança de todas as respostas (páginas e API). Chamado logo no início de index.php e api.php.
//   CSP: só scripts e estilos do próprio site (+ Google Fonts); imagens das capas só dos sites de anime;
//        o browser só fala com o próprio site e com as APIs de anime do config.php; a app não abre dentro de iframes.
//   O resto: sem adivinhar tipos (nosniff), sem iframes, referrer curto, sem câmara/micro/localização, HTTPS forçado (HSTS).
function cabecalhos_seguranca(bool $api = false): void
{
    header_remove('X-Powered-By');                       // não anunciar a versão do PHP
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');

    // HSTS só faz sentido (e só é aceite) por HTTPS
    $https = !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    if ($https) {
        header('Strict-Transport-Security: max-age=31536000');
    }

    if ($api) {
        // JSON: não há nada para carregar nem para mostrar
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
        return;
    }

    // Origens das APIs de anime (o browser pede-lhes os dados das séries)
    $apis = [];
    foreach ((Database::config()['apis'] ?? []) as $url) {
        $p = parse_url((string) $url);
        if (isset($p['scheme'], $p['host'])) {
            $apis[] = $p['scheme'] . '://' . $p['host'];
        }
    }
    // Capas: os mesmos domínios aceites em DadosAnime::capa (e subdomínios)
    $capas = [];
    foreach (DadosAnime::DOMINIOS_CAPA as $d) {
        $capas[] = 'https://' . $d;
        $capas[] = 'https://*.' . $d;
    }

    header('Content-Security-Policy: ' . implode('; ', [
        "default-src 'self'",
        "script-src 'self'",
        "style-src 'self' https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com",
        "img-src 'self' data: blob: " . implode(' ', $capas),
        "connect-src 'self' " . implode(' ', array_unique($apis)),
        "worker-src 'self'",
        "manifest-src 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
    ]));
}
