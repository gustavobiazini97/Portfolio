<?php
// Funções pequenas usadas em todo o lado (controllers, models e views).
declare(strict_types=1);

// ─── Configuração ──────────────────────────────────────────────────────────

// Lê um valor do config.php com "pontos": config('email.smtp.host')
function config(string $chave, mixed $padrao = null): mixed
{
    $valor = $GLOBALS['_config'] ?? [];
    foreach (explode('.', $chave) as $parte) {
        if (!is_array($valor) || !array_key_exists($parte, $valor)) {
            return $padrao;
        }
        $valor = $valor[$parte];
    }
    return $valor;
}

// ─── Saída segura ──────────────────────────────────────────────────────────

// Escapa texto para HTML. Usar SEMPRE ao imprimir dados nas views.
function e(mixed $texto): string
{
    return htmlspecialchars((string) ($texto ?? ''), ENT_QUOTES, 'UTF-8');
}

// Texto livre (descrições, comentários) com as quebras de linha mantidas — escapado primeiro
function texto_multilinha(?string $texto): string
{
    return nl2br(e($texto), false);
}

// Primeiras N letras de um texto, sem cortar a meio de um carácter UTF-8
function excerto(?string $texto, int $max = 140): string
{
    $limpo = trim(preg_replace('/\s+/u', ' ', (string) $texto) ?? '');
    return mb_strlen($limpo) > $max ? mb_substr($limpo, 0, $max - 1) . '…' : $limpo;
}

// ─── URLs e redirecionamentos ──────────────────────────────────────────────

// URL interno no formato do mini-MVC: index.php?c=controller&a=acao&...
function url(string $c, string $a = 'index', array $params = []): string
{
    return 'index.php?' . http_build_query(['c' => $c, 'a' => $a] + $params);
}

// URL absoluto (para os emails), a partir do url_base do config
function url_absoluto(string $c, string $a = 'index', array $params = []): string
{
    return rtrim((string) config('url_base', ''), '/') . '/' . url($c, $a, $params);
}

// Redireciona SEMPRE para dentro da app (nunca para um endereço vindo do utilizador)
function redirecionar(string $c, string $a = 'index', array $params = []): never
{
    header('Location: ' . url($c, $a, $params), true, 303);
    exit;
}

// Ficheiro de public/ com a versão (?v=data de modificação) para o browser não usar cópias antigas
function asset(string $caminho): string
{
    $ficheiro = RAIZ . '/public/' . $caminho;
    return $caminho . (is_file($ficheiro) ? '?v=' . filemtime($ficheiro) : '');
}

// ─── Pedido HTTP ───────────────────────────────────────────────────────────

// A ligação é HTTPS? (direta ou atrás do proxy do alojamento)
function https_ativo(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

// IP de quem fez o pedido (só o REMOTE_ADDR: o X-Forwarded-For pode ser falsificado)
function ip_cliente(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'), 0, 45);
}

// Valor de um campo POST como texto aparado (nunca array)
function post(string $campo, string $padrao = ''): string
{
    $v = $_POST[$campo] ?? $padrao;
    return is_string($v) ? trim($v) : $padrao;
}

// Valor de um campo GET como texto aparado (nunca array)
function get(string $campo, string $padrao = ''): string
{
    $v = $_GET[$campo] ?? $padrao;
    return is_string($v) ? trim($v) : $padrao;
}

// Inteiro de GET/POST (0 se não for número)
function inteiro(mixed $v): int
{
    return is_string($v) && ctype_digit($v) ? (int) $v : (is_int($v) ? $v : 0);
}

// ─── CSRF e mensagens flash ────────────────────────────────────────────────

// Campo escondido com o token CSRF — dentro de TODOS os <form method="post">
function csrf_campo(): string
{
    return '<input type="hidden" name="_csrf" value="' . e($_SESSION['_csrf'] ?? '') . '">';
}

// O token enviado (campo do formulário ou cabeçalho X-CSRF-Token do fetch) é o da sessão?
function csrf_valido(): bool
{
    $enviado = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($enviado) && isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $enviado);
}

// Guarda uma mensagem para mostrar na próxima página (tipo: ok, erro, aviso)
function flash(string $tipo, string $mensagem): void
{
    $_SESSION['_flash'][] = ['tipo' => $tipo, 'msg' => $mensagem];
}

// Devolve e apaga as mensagens guardadas
function flashes(): array
{
    $lista = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $lista;
}

// Valores antigos de um formulário que falhou a validação (para não obrigar a escrever tudo de novo)
function antigo(string $campo, string $padrao = ''): string
{
    return (string) ($_SESSION['_antigo'][$campo] ?? $padrao);
}

// ─── Datas ─────────────────────────────────────────────────────────────────

// Agora, no formato da base de dados (as datas vêm sempre do PHP, nunca do NOW() do MySQL)
function agora(): string
{
    return date('Y-m-d H:i:s');
}

// "15/11/2026 14:30"
function data_pt(?string $data, bool $comHora = true): string
{
    if (!$data) {
        return '—';
    }
    return date($comHora ? 'd/m/Y H:i' : 'd/m/Y', strtotime($data));
}

// "agora mesmo", "há 5 min", "há 2 horas", "ontem", "há 3 dias", "12/09"
function tempo_relativo(?string $data): string
{
    if (!$data) {
        return '';
    }
    $seg = time() - strtotime($data);
    if ($seg < 60)     return 'agora mesmo';
    if ($seg < 3600)   return 'há ' . intdiv($seg, 60) . ' min';
    if ($seg < 86400)  return 'há ' . plural(intdiv($seg, 3600), 'hora', 'horas');
    if ($seg < 172800) return 'ontem';
    if ($seg < 604800) return 'há ' . intdiv($seg, 86400) . ' dias';
    return date('d/m', strtotime($data));
}

// Minutos em texto curto: 45 → "45 min", 150 → "2 h 30 min", 1620 → "27 h"
function duracao(int $minutos): string
{
    $minutos = max(0, $minutos);
    if ($minutos < 60) {
        return $minutos . ' min';
    }
    $h = intdiv($minutos, 60);
    $m = $minutos % 60;
    return $h . ' h' . ($m ? ' ' . $m . ' min' : '');
}

// "1 pedido" / "3 pedidos"
function plural(int $n, string $um, string $varios): string
{
    return $n . ' ' . ($n === 1 ? $um : $varios);
}

// ─── Rótulos do domínio ────────────────────────────────────────────────────

// Código visível do pedido: 42 → "TI-00042"
function codigo_ticket(int $id): string
{
    return 'TI-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
}

// Estados do pedido e o nome mostrado
const ESTADOS = [
    'novo'               => 'Novo',
    'em_curso'           => 'Em curso',
    'aguarda_utilizador' => 'À espera de ti',
    'aguarda_terceiros'  => 'À espera de terceiros',
    'resolvido'          => 'Resolvido',
    'fechado'            => 'Fechado',
];

// O mesmo estado visto pelo técnico (o "ti" do funcionário é o "utilizador" para o técnico)
const ESTADOS_TECNICO = [
    'aguarda_utilizador' => 'À espera do utilizador',
];

// Estados em que o relógio do SLA para (o atraso não é culpa do TI)
const ESTADOS_PAUSA = ['aguarda_utilizador', 'aguarda_terceiros'];

// Estados "em aberto" (contam para as filas)
const ESTADOS_ABERTOS = ['novo', 'em_curso', 'aguarda_utilizador', 'aguarda_terceiros'];

// Prioridades P1–P4
const PRIORIDADES = [
    1 => 'Crítica',
    2 => 'Alta',
    3 => 'Média',
    4 => 'Baixa',
];

// Impacto: quantas pessoas o problema afeta (pergunta feita ao funcionário)
const IMPACTOS = [
    1 => 'Só a mim',
    2 => 'À minha equipa ou departamento',
    3 => 'À empresa toda ou a clientes',
];

// Urgência: quanto o problema atrapalha agora
const URGENCIAS = [
    1 => 'Posso continuar a trabalhar',
    2 => 'Atrapalha, mas vou trabalhando',
    3 => 'Não consigo trabalhar',
];

// Papéis das contas
const PAPEIS = [
    'utilizador' => 'Funcionário',
    'tecnico'    => 'Técnico de TI',
    'admin'      => 'Administrador',
];

// Nome do estado, conforme quem está a ver
function rotulo_estado(string $estado, bool $vistaTecnico = false): string
{
    if ($vistaTecnico && isset(ESTADOS_TECNICO[$estado])) {
        return ESTADOS_TECNICO[$estado];
    }
    return ESTADOS[$estado] ?? $estado;
}

// ─── Ícones (SVG em linha, sem pedidos extra nem CDN) ──────────────────────

function icone(string $nome, string $classe = 'ic'): string
{
    // Traços de 24×24 no estilo "outline"
    $caminhos = [
        'painel'   => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'ticket'   => '<path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4z"/><path d="M14 5v12" stroke-dasharray="2 2"/>',
        'mais'     => '<path d="M12 5v14M5 12h14"/>',
        'faq'      => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.3-1 .9-1 1.7v.5"/><path d="M12 17h.01"/>',
        'sino'     => '<path d="M6 8a6 6 0 1 1 12 0c0 7 3 8 3 8H3s3-1 3-8"/><path d="M10 20a2 2 0 0 0 4 0"/>',
        'admin'    => '<path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
        'pessoa'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'sair'     => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 17l-5-5 5-5M5 12h11"/>',
        'lua'      => '<path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z"/>',
        'sol'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'clip'     => '<path d="M21 11.5l-8.6 8.6a5 5 0 0 1-7-7l8.5-8.6a3.3 3.3 0 0 1 4.7 4.7l-8.5 8.6a1.7 1.7 0 0 1-2.4-2.4l7.9-7.9"/>',
        'relogio'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'pausa'    => '<circle cx="12" cy="12" r="9"/><path d="M10 9v6M14 9v6"/>',
        'visto'    => '<path d="M5 12l5 5L20 7"/>',
        'lupa'     => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
        'cadeado'  => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'grafico'  => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'pasta'    => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'menu'     => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'polegar'  => '<path d="M7 11v9H4v-9zM7 11l4-7a2 2 0 0 1 3 2l-1 4h5a2 2 0 0 1 2 2.3l-1.2 6A2 2 0 0 1 16.8 20H7"/>',
        'nota'     => '<path d="M4 4h16v12l-4 4H4z"/><path d="M16 20v-4h4"/>',
        'seta'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    ];
    $path = $caminhos[$nome] ?? '';
    return '<svg class="' . e($classe) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}

// ─── Validação ─────────────────────────────────────────────────────────────

// Regras da palavra-passe. Devolve a mensagem de erro ou null se estiver boa.
// (O bcrypt só usa os primeiros 72 bytes, por isso o máximo é 72.)
function erro_password(string $password, string $confirmacao): ?string
{
    if (mb_strlen($password) < 10) {
        return 'A palavra-passe precisa de pelo menos 10 caracteres.';
    }
    if (strlen($password) > 72) {
        return 'A palavra-passe é demasiado longa (máximo 72 caracteres).';
    }
    if ($password !== $confirmacao) {
        return 'As duas palavras-passe não são iguais.';
    }
    return null;
}

// ─── Pedaços de HTML repetidos nas views ──────────────────────────────────

// Etiqueta do estado: <span class="chip estado-em_curso">Em curso</span>
function chip_estado(string $estado, bool $vistaTecnico = false): string
{
    return '<span class="chip estado-' . e($estado) . '">' . e(rotulo_estado($estado, $vistaTecnico)) . '</span>';
}

// Etiqueta da prioridade: "P1 Crítica"
function chip_prioridade(int $p): string
{
    return '<span class="prio p' . $p . '"><b>P' . $p . '</b> ' . e(PRIORIDADES[$p] ?? '') . '</span>';
}

// Etiqueta de um relógio do SLA (resultado de Sla::estado)
function chip_sla(array $s): string
{
    $icone = match ($s['tipo']) {
        'pausado' => 'pausa',
        'cumprido' => 'visto',
        default => 'relogio',
    };
    return '<span class="sla sla-' . e($s['tipo']) . '">' . icone($icone) . e($s['texto']) . '</span>';
}

// Texto de um artigo da FAQ com formatação mínima e segura (escapa primeiro, formata depois):
//   linha em branco → parágrafo novo · "- item" → lista · "1. passo" → lista numerada
//   **negrito** · `código`
function formatar_artigo(string $texto): string
{
    $html = '';
    $blocos = preg_split("/\R\s*\R/u", trim(str_replace("\r\n", "\n", $texto))) ?: [];
    foreach ($blocos as $bloco) {
        $linhas = array_values(array_filter(array_map('trim', explode("\n", $bloco)), fn ($l) => $l !== ''));
        if (!$linhas) {
            continue;
        }
        $todas = fn (string $re) => count(array_filter($linhas, fn ($l) => preg_match($re, $l))) === count($linhas);
        if ($todas('/^[-*]\s+/u')) {
            $itens = array_map(fn ($l) => '<li>' . formatar_linha(preg_replace('/^[-*]\s+/u', '', $l)) . '</li>', $linhas);
            $html .= '<ul>' . implode('', $itens) . '</ul>';
        } elseif ($todas('/^\d+[.)]\s+/u')) {
            $itens = array_map(fn ($l) => '<li>' . formatar_linha(preg_replace('/^\d+[.)]\s+/u', '', $l)) . '</li>', $linhas);
            $html .= '<ol>' . implode('', $itens) . '</ol>';
        } else {
            $html .= '<p>' . implode('<br>', array_map('formatar_linha', $linhas)) . '</p>';
        }
    }
    return $html;
}

// Negrito e código dentro de uma linha (o texto é escapado antes)
function formatar_linha(string $linha): string
{
    $s = e($linha);
    $s = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $s) ?? $s;
    return preg_replace('/`(.+?)`/u', '<code>$1</code>', $s) ?? $s;
}
