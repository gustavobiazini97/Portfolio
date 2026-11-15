<?php
// Topo comum: <head>, barra de cima (logo, navegação, sino, conta) e mensagens flash.
// Sem sessão (login, recuperar), mostra só a versão simples centrada.
/** @var ?array $eu */
/** @var string $tituloPagina */
$appNome = (string) config('app_nome', 'Helpdesk TI');
$c = $_GET['c'] ?? 'painel';
$autenticado = $eu !== null && !isset($soCaixa);
$eTiTopo = $autenticado && in_array($eu['papel'], ['tecnico', 'admin'], true);
$naoLidas = $autenticado ? Notificacao::naoLidas((int) $eu['id']) : 0;
// Iniciais para o botão da conta: "Gustavo Biazini" → "GB"
$iniciais = '';
if ($autenticado) {
    $partes = preg_split('/\s+/u', trim($eu['nome'])) ?: [''];
    $iniciais = mb_strtoupper(mb_substr($partes[0], 0, 1) . (count($partes) > 1 ? mb_substr(end($partes), 0, 1) : ''));
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(($tituloPagina !== '' ? $tituloPagina . ' · ' : '') . $appNome) ?></title>
    <link rel="icon" href="<?= e(asset('img/logo.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <!-- Tema aplicado antes de desenhar a página (sem "piscar" do claro para o escuro) -->
    <script src="<?= e(asset('js/tema.js')) ?>"></script>
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="<?= $autenticado ? 'app' : 'auth' ?>" data-csrf="<?= e($_SESSION['_csrf'] ?? '') ?>">
<a class="saltar" href="#conteudo">Saltar para o conteúdo</a>

<?php if ($autenticado): ?>
<header class="topo">
    <div class="topo-in">
        <a class="marca" href="<?= e(url('painel')) ?>">
            <img src="<?= e(asset('img/logo.svg')) ?>" alt="" width="28" height="28">
            <span><?= e($appNome) ?></span>
        </a>

        <!-- Navegação principal (no telemóvel passa a barra fixa em baixo) -->
        <nav class="nav" aria-label="Principal">
            <a class="nav-link<?= $c === 'painel' ? ' ativo' : '' ?>" href="<?= e(url('painel')) ?>"><?= icone('painel') ?><span>Painel</span></a>
            <a class="nav-link<?= $c === 'ticket' ? ' ativo' : '' ?>" href="<?= e(url('ticket', 'lista')) ?>"><?= icone('ticket') ?><span>Pedidos</span></a>
            <a class="nav-link<?= $c === 'faq' ? ' ativo' : '' ?>" href="<?= e(url('faq')) ?>"><?= icone('faq') ?><span>FAQ</span></a>
            <?php if ($eu['papel'] === 'admin'): ?>
                <a class="nav-link<?= $c === 'admin' ? ' ativo' : '' ?>" href="<?= e(url('admin', 'estatisticas')) ?>"><?= icone('admin') ?><span>Admin</span></a>
            <?php endif; ?>
            <a class="nav-link nav-novo" href="<?= e(url('ticket', 'novo')) ?>"><?= icone('mais') ?><span>Novo pedido</span></a>
        </nav>

        <div class="topo-acoes">
            <button class="icone-btn" type="button" data-tema-alternar aria-label="Mudar entre tema claro e escuro">
                <?= icone('lua', 'ic so-claro') ?><?= icone('sol', 'ic so-escuro') ?>
            </button>
            <a class="icone-btn sino" href="<?= e(url('notificacao')) ?>" aria-label="Notificações<?= $naoLidas ? " ($naoLidas por ler)" : '' ?>">
                <?= icone('sino') ?>
                <span class="badge" data-sino-badge<?= $naoLidas ? '' : ' hidden' ?>><?= $naoLidas > 99 ? '99+' : $naoLidas ?></span>
            </a>
            <details class="conta">
                <summary class="avatar" aria-label="Menu da conta"><?= e($iniciais) ?></summary>
                <div class="conta-menu">
                    <p class="conta-nome"><?= e($eu['nome']) ?><small><?= e(PAPEIS[$eu['papel']]) ?></small></p>
                    <a href="<?= e(url('perfil')) ?>"><?= icone('pessoa') ?> O meu perfil</a>
                    <?php if ($eu['papel'] === 'admin'): ?>
                        <a href="<?= e(url('admin', 'utilizadores')) ?>"><?= icone('pessoa') ?> Contas</a>
                        <a href="<?= e(url('admin', 'categorias')) ?>"><?= icone('pasta') ?> Categorias</a>
                        <a href="<?= e(url('admin', 'sla')) ?>"><?= icone('relogio') ?> Prazos (SLA)</a>
                        <a href="<?= e(url('admin', 'auditoria')) ?>"><?= icone('cadeado') ?> Auditoria</a>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('auth', 'sair')) ?>">
                        <?= csrf_campo() ?>
                        <button type="submit"><?= icone('sair') ?> Terminar sessão</button>
                    </form>
                </div>
            </details>
        </div>
    </div>
</header>
<?php endif; ?>

<main id="conteudo" class="<?= $autenticado ? 'conteudo' : 'auth-caixa' ?>">
<?php if (!$autenticado): ?>
    <a class="marca marca-auth" href="<?= e(url('auth', 'login')) ?>">
        <img src="<?= e(asset('img/logo.svg')) ?>" alt="" width="40" height="40">
        <span><?= e($appNome) ?><small><?= e((string) config('empresa', '')) ?></small></span>
    </a>
<?php endif; ?>

<?php $mensagens = flashes(); if ($mensagens): ?>
    <div class="flashes" role="status">
        <?php foreach ($mensagens as $m): ?>
            <div class="flash flash-<?= e($m['tipo']) ?>"><?= e($m['msg']) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
