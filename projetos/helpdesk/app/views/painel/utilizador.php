<?php
// Início do funcionário: procurar na FAQ primeiro, abrir pedido e acompanhar os seus pedidos.
/** @var array $contadores */
/** @var array $pedidos */
/** @var array $populares */
$aEspera = (int) ($contadores['a_espera'] ?? 0);
$porConfirmar = (int) ($contadores['por_confirmar'] ?? 0);
?>
<section class="boas-vindas">
    <h1>Olá, <?= e(explode(' ', $eu['nome'])[0]) ?>. Em que podemos ajudar?</h1>
    <!-- Pesquisa na FAQ: muitas dúvidas resolvem-se sem abrir pedido -->
    <form class="pesquisa-grande" method="get" action="index.php" role="search">
        <input type="hidden" name="c" value="faq">
        <input type="hidden" name="a" value="index">
        <?= icone('lupa') ?>
        <input type="search" name="q" placeholder="Ex.: impressora não imprime, esqueci a password do email…" aria-label="Procurar na FAQ" maxlength="150">
        <button class="botao" type="submit">Procurar</button>
    </form>
    <p class="ou">Não encontras? <a class="botao botao-sec" href="<?= e(url('ticket', 'novo')) ?>"><?= icone('mais') ?> Abrir pedido ao TI</a></p>
</section>

<?php if ($aEspera || $porConfirmar): ?>
    <div class="aviso-caixa">
        <?php if ($aEspera): ?><p><b><?= plural($aEspera, 'pedido está', 'pedidos estão') ?> à espera de uma resposta tua.</b> O TI não consegue avançar sem ela.</p><?php endif; ?>
        <?php if ($porConfirmar): ?><p><?= plural($porConfirmar, 'pedido foi resolvido', 'pedidos foram resolvidos') ?>: confirma se ficou tudo bem.</p><?php endif; ?>
    </div>
<?php endif; ?>

<section class="bloco">
    <div class="bloco-cab">
        <h2>Os meus pedidos</h2>
        <a class="botao-link" href="<?= e(url('ticket', 'lista', ['estado' => 'todos'])) ?>">Ver todos <?= icone('seta') ?></a>
    </div>
    <?php $linhas = $pedidos; $vistaTi = false; $vazio = 'Ainda não abriste nenhum pedido.'; require RAIZ . '/app/views/tickets/_tabela.php'; ?>
</section>

<?php if ($populares): ?>
<section class="bloco">
    <div class="bloco-cab">
        <h2>Perguntas mais vistas</h2>
        <a class="botao-link" href="<?= e(url('faq')) ?>">Toda a FAQ <?= icone('seta') ?></a>
    </div>
    <div class="faq-lista">
        <?php foreach ($populares as $a): ?>
            <a class="faq-item" href="<?= e(url('faq', 'ver', ['id' => $a['id']])) ?>">
                <span class="faq-titulo"><?= e($a['titulo']) ?></span>
                <span class="faq-resumo"><?= e($a['resumo']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
