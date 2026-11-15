<?php
// Novo pedido. Enquanto se escreve o título, aparecem artigos da FAQ que podem resolver já o problema.
// A prioridade não é escolhida diretamente: vem de duas perguntas simples (impacto e urgência).
/** @var array $categorias */
$maxMb = (int) config('anexos.max_mb', 5);
$maxN = (int) config('anexos.max_por_envio', 3);
?>
<div class="cab">
    <div>
        <h1>Novo pedido ao TI</h1>
        <p class="sub">Quanto melhor descreveres, mais depressa se resolve.</p>
    </div>
</div>

<form class="form cartao form-pedido" method="post" action="<?= e(url('ticket', 'criar')) ?>" enctype="multipart/form-data">
    <?= csrf_campo() ?>

    <div class="campo">
        <label for="titulo">Resumo do problema</label>
        <input id="titulo" name="titulo" required minlength="5" maxlength="150" value="<?= e(antigo('titulo')) ?>"
               placeholder="Ex.: A impressora do armazém não imprime" data-sugerir-faq autocomplete="off" autofocus>
    </div>

    <!-- Sugestões da FAQ (preenchidas pelo app.js) -->
    <aside class="sugestoes" data-sugestoes hidden aria-live="polite">
        <p class="sugestoes-titulo"><?= icone('faq') ?> Isto pode resolver já:</p>
        <ul data-sugestoes-lista></ul>
    </aside>

    <div class="campo">
        <label for="descricao">O que se passa?</label>
        <textarea id="descricao" name="descricao" required minlength="10" maxlength="5000" rows="6"
                  placeholder="O que estavas a fazer, que mensagem apareceu, desde quando acontece, em que PC/posto…"><?= e(antigo('descricao')) ?></textarea>
    </div>

    <div class="campo">
        <label for="categoria_id">Categoria</label>
        <select id="categoria_id" name="categoria_id">
            <option value="">Não sei / outra</option>
            <?php foreach ($categorias as $c): ?>
                <option value="<?= (int) $c['id'] ?>"<?= antigo('categoria_id') === (string) $c['id'] ? ' selected' : '' ?>><?= e($c['nome']) ?><?= $c['descricao'] ? ' — ' . e($c['descricao']) : '' ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <fieldset class="campo">
        <legend>A quem afeta?</legend>
        <div class="opcoes">
            <?php foreach (IMPACTOS as $k => $nome): ?>
                <label class="opcao">
                    <input type="radio" name="impacto" value="<?= $k ?>" required<?= antigo('impacto', '1') === (string) $k ? ' checked' : '' ?>>
                    <span><?= e($nome) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <fieldset class="campo">
        <legend>Quanto te atrapalha agora?</legend>
        <div class="opcoes">
            <?php foreach (URGENCIAS as $k => $nome): ?>
                <label class="opcao">
                    <input type="radio" name="urgencia" value="<?= $k ?>" required<?= antigo('urgencia', '2') === (string) $k ? ' checked' : '' ?>>
                    <span><?= e($nome) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <!-- Pré-visualização da prioridade (calculada no browser só para informar; o servidor recalcula) -->
        <p class="prioridade-previa" data-prioridade-previa></p>
    </fieldset>

    <div class="campo">
        <label for="anexos">Anexos <span class="opcional">(opcional)</span></label>
        <input id="anexos" name="anexos[]" type="file" multiple data-max="<?= $maxN ?>"
               accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.log,.csv,.docx,.xlsx,.zip">
        <p class="ajuda">Uma fotografia do ecrã ajuda muito. Até <?= $maxN ?> ficheiros de <?= $maxMb ?> MB (imagens, PDF, texto, Word, Excel, ZIP).</p>
    </div>

    <div class="form-acoes">
        <a class="botao botao-sec" href="<?= e(url('painel')) ?>">Cancelar</a>
        <button class="botao" type="submit">Enviar pedido</button>
    </div>
</form>
