<?php
// Registo de auditoria (últimos 300), com filtro por ação.
/** @var array $lista */
/** @var array $acoes */
/** @var string $acao */
?>
<div class="cab">
    <div>
        <h1>Administração</h1>
        <p class="sub">Logins, falhas, acessos negados e mudanças de permissões.</p>
    </div>
</div>
<?php require RAIZ . '/app/views/admin/_tabs.php'; ?>

<form class="filtros" method="get" action="index.php" data-auto-submeter>
    <input type="hidden" name="c" value="admin">
    <input type="hidden" name="a" value="auditoria">
    <label><span class="sr">Ação</span>
        <select name="acao">
            <option value="">Todas as ações</option>
            <?php foreach ($acoes as $ac): ?>
                <option value="<?= e($ac) ?>"<?= $ac === $acao ? ' selected' : '' ?>><?= e($ac) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button class="botao botao-sec" type="submit">Filtrar</button>
</form>

<div class="tabela-envolve">
<table class="tabela tabela-compacta">
    <thead><tr><th>Quando</th><th>Ação</th><th>Quem</th><th>Detalhe</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($lista as $r): ?>
        <tr class="<?= in_array($r['acao'], ['login_falhado', 'acesso_negado', 'csrf_invalido', '2fa_falhado', '2fa_bloqueado', 'bot_login'], true) ? 'linha-violado' : '' ?>">
            <td data-label="Quando"><?= e(data_pt($r['criado_em'])) ?></td>
            <td data-label="Ação"><code><?= e($r['acao']) ?></code></td>
            <td data-label="Quem"><?= e($r['nome'] ?? '—') ?></td>
            <td data-label="Detalhe"><?= e($r['detalhe'] ?? '') ?></td>
            <td data-label="IP"><?= e($r['ip']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
