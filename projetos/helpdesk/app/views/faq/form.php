<?php
// Criar/editar artigo da FAQ (só TI).
/** @var array $a */
/** @var array $categorias */
$id = (int) $a['id'];
?>
<div class="cab">
    <div>
        <h1><?= $id ? 'Editar artigo' : 'Novo artigo' ?></h1>
        <p class="sub">Escreve como se estivesses a explicar a um colega ao telefone: passos curtos e claros.</p>
    </div>
</div>

<form class="form cartao" method="post" action="<?= e(url('faq', 'guardar')) ?>">
    <?= csrf_campo() ?>
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="campo">
        <label for="titulo">Título (a pergunta, como as pessoas a fazem)</label>
        <input id="titulo" name="titulo" required minlength="5" maxlength="150" value="<?= e($a['titulo']) ?>" placeholder="Ex.: A impressora diz que está offline">
    </div>
    <div class="campo">
        <label for="resumo">Resumo (uma linha, aparece nas sugestões)</label>
        <input id="resumo" name="resumo" required minlength="10" maxlength="255" value="<?= e($a['resumo']) ?>">
    </div>
    <div class="campo">
        <label for="conteudo">Conteúdo</label>
        <textarea id="conteudo" name="conteudo" rows="14" required minlength="20" maxlength="20000"><?= e($a['conteudo']) ?></textarea>
        <p class="ajuda">Linha em branco = parágrafo novo · <code>- item</code> = lista · <code>1. passo</code> = passos numerados · <code>**negrito**</code></p>
    </div>
    <div class="linha-campos">
        <div class="campo">
            <label for="categoria_id">Categoria</label>
            <select id="categoria_id" name="categoria_id">
                <option value="">Geral</option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"<?= (int) ($a['categoria_id'] ?? 0) === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="palavras_chave">Palavras-chave</label>
            <input id="palavras_chave" name="palavras_chave" maxlength="255" value="<?= e($a['palavras_chave'] ?? '') ?>" placeholder="net, internet, wifi, rede">
        </div>
    </div>
    <label class="interruptor">
        <input type="checkbox" name="publicado" value="1"<?= (int) $a['publicado'] ? ' checked' : '' ?>>
        <span>Publicado (desmarcado = rascunho, só o TI vê)</span>
    </label>

    <div class="form-acoes">
        <?php if ($id): ?>
            <button class="botao botao-perigo" type="submit" form="apagar-artigo">Apagar</button>
        <?php endif; ?>
        <a class="botao botao-sec" href="<?= e($id ? url('faq', 'ver', ['id' => $id]) : url('faq')) ?>">Cancelar</a>
        <button class="botao" type="submit">Guardar</button>
    </div>
</form>

<?php if ($id): ?>
    <!-- Formulário separado (não se podem aninhar forms); o botão "Apagar" de cima submete este -->
    <form id="apagar-artigo" method="post" action="<?= e(url('faq', 'apagar')) ?>" data-confirmar="Apagar este artigo? Não dá para desfazer.">
        <?= csrf_campo() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
    </form>
<?php endif; ?>
