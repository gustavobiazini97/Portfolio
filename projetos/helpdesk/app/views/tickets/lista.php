<?php
// Lista de pedidos com filtros (o TI tem também técnico e SLA).
/** @var array $filtros */
/** @var array $resultado */
/** @var array $categorias */
/** @var array $equipa */
$vistaTi = in_array($eu['papel'], ['tecnico', 'admin'], true);
$sel = fn (string $campo, string $valor) => ($filtros[$campo] ?? '') === $valor ? ' selected' : '';
?>
<div class="cab">
    <div>
        <h1><?= $vistaTi ? 'Pedidos' : 'Os meus pedidos' ?></h1>
        <p class="sub"><?= plural($resultado['total'], 'pedido', 'pedidos') ?></p>
    </div>
    <div class="cab-acoes">
        <a class="botao" href="<?= e(url('ticket', 'novo')) ?>"><?= icone('mais') ?> Novo pedido</a>
    </div>
</div>

<!-- Filtros (GET: o URL pode ser guardado nos favoritos) -->
<form class="filtros" method="get" action="index.php" data-auto-submeter>
    <input type="hidden" name="c" value="ticket">
    <input type="hidden" name="a" value="lista">
    <label class="filtro-pesquisa">
        <span class="sr">Pesquisar</span>
        <?= icone('lupa') ?>
        <input type="search" name="q" value="<?= e($filtros['q']) ?>" placeholder="TI-00042 ou palavras do título" maxlength="100">
    </label>
    <label><span class="sr">Estado</span>
        <select name="estado">
            <option value="abertos"<?= $sel('estado', 'abertos') ?>>Em aberto</option>
            <option value="todos"<?= $sel('estado', 'todos') ?>>Todos</option>
            <?php foreach (ESTADOS as $k => $nome): ?>
                <option value="<?= e($k) ?>"<?= $sel('estado', $k) ?>><?= e(rotulo_estado($k, $vistaTi)) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label><span class="sr">Prioridade</span>
        <select name="prioridade">
            <option value="">Qualquer prioridade</option>
            <?php foreach (PRIORIDADES as $k => $nome): ?>
                <option value="<?= $k ?>"<?= $sel('prioridade', (string) $k) ?>>P<?= $k ?> <?= e($nome) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label><span class="sr">Categoria</span>
        <select name="categoria">
            <option value="">Todas as categorias</option>
            <?php foreach ($categorias as $c): ?>
                <option value="<?= (int) $c['id'] ?>"<?= $sel('categoria', (string) $c['id']) ?>><?= e($c['nome']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php if ($vistaTi): ?>
        <label><span class="sr">Técnico</span>
            <select name="tecnico">
                <option value="">Qualquer técnico</option>
                <option value="meus"<?= $sel('tecnico', 'meus') ?>>Atribuídos a mim</option>
                <option value="nenhum"<?= $sel('tecnico', 'nenhum') ?>>Sem técnico</option>
                <?php foreach ($equipa as $tec): ?>
                    <option value="<?= (int) $tec['id'] ?>"<?= $sel('tecnico', (string) $tec['id']) ?>><?= e($tec['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><span class="sr">Prazo</span>
            <select name="sla">
                <option value="">Qualquer prazo</option>
                <option value="risco"<?= $sel('sla', 'risco') ?>>Quase a acabar</option>
                <option value="violado"<?= $sel('sla', 'violado') ?>>Ultrapassado</option>
            </select>
        </label>
    <?php endif; ?>
    <button class="botao botao-sec" type="submit">Filtrar</button>
</form>

<?php $linhas = $resultado['linhas']; $vazio = 'Nenhum pedido com estes filtros.'; require RAIZ . '/app/views/tickets/_tabela.php'; ?>

<?php if ($resultado['paginas'] > 1): ?>
    <nav class="paginacao" aria-label="Páginas">
        <?php for ($i = 1; $i <= $resultado['paginas']; $i++): ?>
            <a class="<?= $i === $resultado['pagina'] ? 'ativo' : '' ?>" href="<?= e(url('ticket', 'lista', array_filter($filtros) + ['p' => $i])) ?>"><?= $i ?></a>
        <?php endfor; ?>
    </nav>
<?php endif; ?>
