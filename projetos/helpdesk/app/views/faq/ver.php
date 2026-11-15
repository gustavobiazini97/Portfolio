<?php
// Um artigo da FAQ, com "isto foi útil?" e, vindo do formulário de pedido, "resolveu, não preciso de pedido".
/** @var array $a */
/** @var array $relacionados */
/** @var bool $votou */
/** @var bool $deNovoPedido */
$eTiFaq = in_array($eu['papel'], ['tecnico', 'admin'], true);
?>
<div class="cab">
    <div>
        <p class="codigo-grande"><?= e($a['categoria_nome'] ?? 'Geral') ?></p>
        <h1><?= e($a['titulo']) ?></h1>
        <p class="sub"><?= e($a['resumo']) ?></p>
    </div>
    <div class="cab-acoes">
        <a class="botao botao-sec" href="<?= e(url('faq')) ?>">Voltar à FAQ</a>
        <?php if ($eTiFaq): ?><a class="botao botao-sec" href="<?= e(url('faq', 'editar', ['id' => $a['id']])) ?>">Editar</a><?php endif; ?>
    </div>
</div>

<?php if (!(int) $a['publicado']): ?>
    <p class="aviso-caixa">Rascunho: só a equipa de TI vê este artigo.</p>
<?php endif; ?>

<div class="artigo-grid">
    <article class="cartao artigo">
        <?= formatar_artigo($a['conteudo']) ?>
        <p class="meta artigo-meta">Atualizado <?= e(data_pt($a['atualizado_em'], false)) ?><?= $a['autor_nome'] ? ' · ' . e($a['autor_nome']) : '' ?></p>
    </article>

    <aside class="lateral">
        <?php if ($deNovoPedido && (int) $a['publicado']): ?>
            <!-- Veio das sugestões do formulário de pedido -->
            <section class="cartao destaque">
                <h2>Resolveu o teu problema?</h2>
                <form method="post" action="<?= e(url('faq', 'resolveu')) ?>">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                    <button class="botao botao-largo" type="submit"><?= icone('visto') ?> Sim, já não preciso de pedido</button>
                </form>
                <a class="botao botao-sec botao-largo" href="<?= e(url('ticket', 'novo')) ?>">Não, quero abrir o pedido</a>
            </section>
        <?php elseif (!$votou): ?>
            <section class="cartao">
                <h2>Isto foi útil?</h2>
                <form method="post" action="<?= e(url('faq', 'votar')) ?>" class="junto">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                    <button class="botao botao-sec" type="submit" name="util" value="1"><?= icone('polegar') ?> Sim</button>
                    <button class="botao botao-sec" type="submit" name="util" value="0">Não</button>
                </form>
                <p class="ajuda">Se não resolveu, <a href="<?= e(url('ticket', 'novo')) ?>">abre um pedido</a>.</p>
            </section>
        <?php else: ?>
            <section class="cartao"><p>Obrigado pela resposta! Se não resolveu, <a href="<?= e(url('ticket', 'novo')) ?>">abre um pedido</a>.</p></section>
        <?php endif; ?>

        <?php if ($relacionados): ?>
            <section class="cartao">
                <h2>Relacionados</h2>
                <ul class="lista-simples">
                    <?php foreach ($relacionados as $r): ?>
                        <li><a href="<?= e(url('faq', 'ver', ['id' => $r['id']])) ?>"><?= e($r['titulo']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if ($eTiFaq): ?>
            <section class="cartao">
                <h2>Números</h2>
                <dl class="detalhes">
                    <dt>Visualizações</dt><dd><?= (int) $a['visualizacoes'] ?></dd>
                    <dt>Útil / não útil</dt><dd><?= (int) $a['util_sim'] ?> / <?= (int) $a['util_nao'] ?></dd>
                </dl>
            </section>
        <?php endif; ?>
    </aside>
</div>
