<?php
// Separadores da área de administração.
$aAtual = $_GET['a'] ?? '';
$tabs = [
    'estatisticas' => 'Estatísticas',
    'utilizadores' => 'Contas',
    'categorias' => 'Categorias',
    'sla' => 'Prazos (SLA)',
    'auditoria' => 'Auditoria',
];
?>
<nav class="tabs" aria-label="Administração">
    <?php foreach ($tabs as $acao => $nome): ?>
        <a class="<?= $aAtual === $acao || ($acao === 'utilizadores' && $aAtual === 'utilizador') ? 'ativo' : '' ?>" href="<?= e(url('admin', $acao)) ?>"><?= e($nome) ?></a>
    <?php endforeach; ?>
</nav>
