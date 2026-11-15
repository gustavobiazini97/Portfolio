<?php
// Painel do TI: contadores das filas e os pedidos que precisam de atenção.
/** @var array $contadores */
/** @var array $meus */
/** @var array $semTecnico */
$n = fn (string $k) => (int) ($contadores[$k] ?? 0);
?>
<div class="cab">
    <div>
        <h1>Olá, <?= e(explode(' ', $eu['nome'])[0]) ?></h1>
        <p class="sub"><?= $n('abertos') ? plural($n('abertos'), 'pedido aberto', 'pedidos abertos') . ' no total.' : 'Não há pedidos abertos. Bom trabalho!' ?></p>
    </div>
</div>

<!-- Contadores: cada um abre a lista já filtrada -->
<div class="stats">
    <a class="stat" href="<?= e(url('ticket', 'lista', ['tecnico' => 'nenhum'])) ?>">
        <span class="stat-n"><?= $n('sem_tecnico') ?></span><span class="stat-l">Sem técnico</span>
    </a>
    <a class="stat" href="<?= e(url('ticket', 'lista', ['tecnico' => 'meus'])) ?>">
        <span class="stat-n"><?= $n('meus') ?></span><span class="stat-l">Atribuídos a mim</span>
    </a>
    <a class="stat stat-risco<?= $n('risco') ? ' com' : '' ?>" href="<?= e(url('ticket', 'lista', ['sla' => 'risco'])) ?>">
        <span class="stat-n"><?= $n('risco') ?></span><span class="stat-l">Prazo quase a acabar</span>
    </a>
    <a class="stat stat-violado<?= $n('violados') ? ' com' : '' ?>" href="<?= e(url('ticket', 'lista', ['sla' => 'violado'])) ?>">
        <span class="stat-n"><?= $n('violados') ?></span><span class="stat-l">Prazo ultrapassado</span>
    </a>
</div>

<section class="bloco">
    <div class="bloco-cab">
        <h2>Atribuídos a mim</h2>
        <a class="botao-link" href="<?= e(url('ticket', 'lista', ['tecnico' => 'meus'])) ?>">Ver todos <?= icone('seta') ?></a>
    </div>
    <?php $linhas = $meus; $vistaTi = true; $vazio = 'Não tens pedidos abertos.'; require RAIZ . '/app/views/tickets/_tabela.php'; ?>
</section>

<section class="bloco">
    <div class="bloco-cab">
        <h2>À espera de técnico</h2>
        <a class="botao-link" href="<?= e(url('ticket', 'lista', ['tecnico' => 'nenhum'])) ?>">Ver todos <?= icone('seta') ?></a>
    </div>
    <?php $linhas = $semTecnico; $vazio = 'A fila está vazia.'; require RAIZ . '/app/views/tickets/_tabela.php'; ?>
</section>
