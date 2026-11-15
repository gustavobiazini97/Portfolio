<?php
// FAQ: pesquisa e lista de artigos (o TI vê também os rascunhos).
/** @var string $q */
/** @var int $categoria */
/** @var array $categorias */
/** @var array $artigos */
$eTiFaq = in_array($eu['papel'], ['tecnico', 'admin'], true);
?>
<div class="cab">
    <div>
        <h1>Perguntas frequentes</h1>
        <p class="sub">Soluções para os problemas mais comuns. Experimenta aqui antes de abrir um pedido.</p>
    </div>
    <?php if ($eTiFaq): ?>
        <div class="cab-acoes"><a class="botao" href="<?= e(url('faq', 'novo')) ?>"><?= icone('mais') ?> Novo artigo</a></div>
    <?php endif; ?>
</div>

<form class="filtros" method="get" action="index.php" role="search" data-auto-submeter>
    <input type="hidden" name="c" value="faq">
    <input type="hidden" name="a" value="index">
    <label class="filtro-pesquisa">
        <span class="sr">Pesquisar</span>
        <?= icone('lupa') ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Ex.: VPN, impressora, Outlook…" maxlength="150">
    </label>
    <label><span class="sr">Categoria</span>
        <select name="categoria">
            <option value="">Todas as categorias</option>
            <?php foreach ($categorias as $c): ?>
                <option value="<?= (int) $c['id'] ?>"<?= $categoria === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['nome']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button class="botao botao-sec" type="submit">Procurar</button>
</form>

<?php if (!$artigos): ?>
    <div class="vazio">
        <p>Nenhum artigo encontrado<?= $q !== '' ? ' para “' . e($q) . '”' : '' ?>.</p>
        <a class="botao" href="<?= e(url('ticket', 'novo')) ?>">Abrir pedido ao TI</a>
    </div>
<?php else: ?>
    <div class="faq-lista">
        <?php foreach ($artigos as $a): ?>
            <a class="faq-item" href="<?= e(url('faq', 'ver', ['id' => $a['id']])) ?>">
                <span class="faq-titulo">
                    <?= e($a['titulo']) ?>
                    <?php if (!(int) $a['publicado']): ?><span class="etiqueta etiqueta-interna">Rascunho</span><?php endif; ?>
                </span>
                <span class="faq-resumo"><?= e($a['resumo']) ?></span>
                <span class="meta"><?= e($a['categoria_nome'] ?? 'Geral') ?><?php if ($eTiFaq): ?> · <?= (int) $a['visualizacoes'] ?> visualizações · <?= (int) $a['util_sim'] ?> úteis<?php endif; ?></span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
