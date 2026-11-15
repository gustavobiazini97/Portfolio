<?php
// Categorias: cada uma pode ter um técnico que recebe os pedidos automaticamente.
/** @var array $lista */
/** @var array $equipa */
$opcoesTecnico = function (?int $atual) use ($equipa): string {
    $html = '<option value="">Ninguém (fila geral)</option>';
    foreach ($equipa as $t) {
        $html .= '<option value="' . (int) $t['id'] . '"' . ((int) $atual === (int) $t['id'] ? ' selected' : '') . '>' . e($t['nome']) . '</option>';
    }
    return $html;
};
?>
<div class="cab">
    <div>
        <h1>Administração</h1>
        <p class="sub">Categorias dos pedidos e quem fica com eles por defeito.</p>
    </div>
</div>
<?php require RAIZ . '/app/views/admin/_tabs.php'; ?>

<div class="lista-cartoes">
    <?php foreach ($lista as $c): ?>
        <form class="cartao form form-categoria<?= (int) $c['ativa'] ? '' : ' inativa' ?>" method="post" action="<?= e(url('admin', 'guardarCategoria')) ?>">
            <?= csrf_campo() ?>
            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <div class="linha-campos">
                <div class="campo"><label>Nome<input name="nome" required maxlength="80" value="<?= e($c['nome']) ?>"></label></div>
                <div class="campo"><label>Técnico por defeito<select name="tecnico_id"><?= $opcoesTecnico($c['tecnico_padrao_id'] !== null ? (int) $c['tecnico_padrao_id'] : null) ?></select></label></div>
            </div>
            <div class="campo"><label>Descrição<input name="descricao" maxlength="255" value="<?= e($c['descricao'] ?? '') ?>"></label></div>
            <div class="linha-campos">
                <label class="interruptor"><input type="checkbox" name="ativa" value="1"<?= (int) $c['ativa'] ? ' checked' : '' ?>><span>Ativa</span></label>
                <span class="meta"><?= plural((int) $c['abertos'], 'pedido aberto', 'pedidos abertos') ?></span>
                <button class="botao botao-sec" type="submit">Guardar</button>
            </div>
        </form>
    <?php endforeach; ?>

    <form class="cartao form form-categoria nova" method="post" action="<?= e(url('admin', 'guardarCategoria')) ?>">
        <?= csrf_campo() ?>
        <input type="hidden" name="id" value="0">
        <h2>Nova categoria</h2>
        <div class="linha-campos">
            <div class="campo"><label>Nome<input name="nome" required maxlength="80"></label></div>
            <div class="campo"><label>Técnico por defeito<select name="tecnico_id"><?= $opcoesTecnico(null) ?></select></label></div>
        </div>
        <div class="campo"><label>Descrição<input name="descricao" maxlength="255"></label></div>
        <div class="form-acoes"><button class="botao" type="submit">Adicionar</button></div>
    </form>
</div>
