<?php
// Estatísticas do período: volume, SLA, tempos, satisfação, técnicos e FAQ.
// As barras são <progress> (sem estilos inline: a CSP não os deixa).
/** @var int $dias */
/** @var array $e */
$pct = fn (?int $v) => $v === null ? '—' : $v . '%';
$min = fn (?int $v) => $v === null ? '—' : duracao($v);
$maxCat = max(1, ...array_map(fn ($r) => (int) $r['total'], $e['por_categoria'] ?: [['total' => 1]]));
$maxPrio = max(1, ...array_map(fn ($r) => (int) $r['total'], $e['por_prioridade'] ?: [['total' => 1]]));
?>
<div class="cab">
    <div>
        <h1>Administração</h1>
        <p class="sub">Últimos <?= $dias ?> dias.</p>
    </div>
    <nav class="segmentos cab-acoes" aria-label="Período">
        <?php foreach ([7, 30, 90, 365] as $d): ?>
            <a class="<?= $d === $dias ? 'ativo' : '' ?>" href="<?= e(url('admin', 'estatisticas', ['dias' => $d])) ?>"><?= $d === 365 ? '1 ano' : $d . ' dias' ?></a>
        <?php endforeach; ?>
    </nav>
</div>
<?php require RAIZ . '/app/views/admin/_tabs.php'; ?>

<div class="stats">
    <div class="stat"><span class="stat-n"><?= $e['criados'] ?></span><span class="stat-l">Pedidos abertos no período</span></div>
    <div class="stat"><span class="stat-n"><?= $e['resolvidos'] ?></span><span class="stat-l">Resolvidos</span></div>
    <div class="stat"><span class="stat-n"><?= $e['abertos'] ?></span><span class="stat-l">Em aberto agora</span></div>
    <div class="stat"><span class="stat-n"><?= $e['desvios'] ?></span><span class="stat-l">Evitados pela FAQ</span></div>
</div>

<div class="stats">
    <div class="stat"><span class="stat-n"><?= $pct($e['resposta_pct']) ?></span><span class="stat-l">1.ª resposta a tempo</span></div>
    <div class="stat"><span class="stat-n"><?= $min($e['resposta_media']) ?></span><span class="stat-l">Tempo médio até responder</span></div>
    <div class="stat"><span class="stat-n"><?= $pct($e['resolucao_pct']) ?></span><span class="stat-l">Resolvidos a tempo</span></div>
    <div class="stat"><span class="stat-n"><?= $min($e['resolucao_media']) ?></span><span class="stat-l">Tempo médio a resolver</span></div>
</div>

<div class="perfil-grid">
    <section class="cartao">
        <h2>Por categoria</h2>
        <?php if (!$e['por_categoria']): ?><p class="vazio">Sem dados.</p><?php endif; ?>
        <ul class="barras">
            <?php foreach ($e['por_categoria'] as $r): ?>
                <li><span><?= e($r['nome']) ?></span><progress max="<?= $maxCat ?>" value="<?= (int) $r['total'] ?>"></progress><b><?= (int) $r['total'] ?></b></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="cartao">
        <h2>Por prioridade</h2>
        <?php if (!$e['por_prioridade']): ?><p class="vazio">Sem dados.</p><?php endif; ?>
        <ul class="barras">
            <?php foreach ($e['por_prioridade'] as $r): ?>
                <li><span><?= chip_prioridade((int) $r['prioridade']) ?></span><progress class="p<?= (int) $r['prioridade'] ?>" max="<?= $maxPrio ?>" value="<?= (int) $r['total'] ?>"></progress><b><?= (int) $r['total'] ?></b></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="cartao">
        <h2>Técnicos</h2>
        <?php if (!$e['por_tecnico']): ?><p class="vazio">Ainda ninguém resolveu pedidos neste período.</p><?php else: ?>
        <div class="tabela-envolve">
        <table class="tabela">
            <thead><tr><th>Técnico</th><th>Resolvidos</th><th>A tempo</th><th>Avaliação</th></tr></thead>
            <tbody>
            <?php foreach ($e['por_tecnico'] as $r): ?>
                <tr>
                    <td data-label="Técnico"><?= e($r['nome']) ?></td>
                    <td data-label="Resolvidos"><?= (int) $r['resolvidos'] ?></td>
                    <td data-label="A tempo"><?= (int) round((int) $r['a_tempo'] / max(1, (int) $r['resolvidos']) * 100) ?>%</td>
                    <td data-label="Avaliação"><?= $r['avaliacao'] !== null ? number_format((float) $r['avaliacao'], 1, ',', '') . '/5' : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
        <p class="ajuda">Satisfação geral: <b><?= $e['avaliacao_media'] !== null ? number_format($e['avaliacao_media'], 1, ',', '') . '/5' : '—' ?></b> (<?= plural($e['avaliacao_total'], 'avaliação', 'avaliações') ?>).</p>
    </section>

    <section class="cartao">
        <h2>FAQ que mais ajuda</h2>
        <?php if (!$e['faq_top']): ?><p class="vazio">Ainda não há artigos.</p><?php else: ?>
        <div class="tabela-envolve">
        <table class="tabela">
            <thead><tr><th>Artigo</th><th>Vistas</th><th>Útil</th><th>Evitou</th></tr></thead>
            <tbody>
            <?php foreach ($e['faq_top'] as $f): ?>
                <tr>
                    <td data-label="Artigo"><a href="<?= e(url('faq', 'ver', ['id' => $f['id']])) ?>"><?= e($f['titulo']) ?></a></td>
                    <td data-label="Vistas"><?= (int) $f['visualizacoes'] ?></td>
                    <td data-label="Útil"><span class="nowrap"><?= (int) $f['util_sim'] ?> / <?= (int) $f['util_nao'] ?></span></td>
                    <td data-label="Evitou"><?= (int) $f['desvios'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
        <p class="ajuda">Artigos com muitos “não útil” precisam de ser revistos.</p>
    </section>
</div>
