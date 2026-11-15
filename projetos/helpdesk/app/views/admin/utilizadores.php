<?php
// Lista de contas.
/** @var array $lista */
/** @var string $q */
?>
<div class="cab">
    <div>
        <h1>Administração</h1>
        <p class="sub"><?= plural(count($lista), 'conta', 'contas') ?></p>
    </div>
    <div class="cab-acoes"><a class="botao" href="<?= e(url('admin', 'utilizador')) ?>"><?= icone('mais') ?> Nova conta</a></div>
</div>
<?php require RAIZ . '/app/views/admin/_tabs.php'; ?>

<form class="filtros" method="get" action="index.php" role="search">
    <input type="hidden" name="c" value="admin">
    <input type="hidden" name="a" value="utilizadores">
    <label class="filtro-pesquisa">
        <span class="sr">Pesquisar</span><?= icone('lupa') ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Nome, email ou departamento" maxlength="100">
    </label>
    <button class="botao botao-sec" type="submit">Procurar</button>
</form>

<div class="tabela-envolve">
<table class="tabela">
    <thead><tr><th>Nome</th><th>Papel</th><th>Departamento</th><th>2FA</th><th>Último login</th></tr></thead>
    <tbody>
    <?php foreach ($lista as $u): ?>
        <tr class="<?= (int) $u['ativo'] ? '' : 'inativa' ?>">
            <td data-label="Nome">
                <a class="link-pedido" href="<?= e(url('admin', 'utilizador', ['id' => $u['id']])) ?>"><span class="titulo"><?= e($u['nome']) ?></span></a>
                <span class="meta"><?= e($u['email']) ?><?= (int) $u['ativo'] ? '' : ' · desativada' ?></span>
            </td>
            <td data-label="Papel"><?= e(PAPEIS[$u['papel']]) ?></td>
            <td data-label="Departamento"><?= e($u['departamento'] ?? '—') ?></td>
            <td data-label="2FA"><?= (int) $u['totp_ativo'] ? '<span class="chip estado-resolvido">Sim</span>' : '<span class="meta">Não</span>' ?></td>
            <td data-label="Último login"><?= e($u['ultimo_login'] ? tempo_relativo($u['ultimo_login']) : 'nunca') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
