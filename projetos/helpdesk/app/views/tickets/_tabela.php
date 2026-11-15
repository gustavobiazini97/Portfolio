<?php
// Tabela de pedidos (usada no painel e na lista). No telemóvel cada linha vira um cartão.
/** @var array $linhas */
/** @var bool $vistaTi */
?>
<?php if (!$linhas): ?>
    <p class="vazio"><?= e($vazio ?? 'Nada por aqui.') ?></p>
<?php else: ?>
<div class="tabela-envolve">
<table class="tabela">
    <thead>
    <tr>
        <th>Pedido</th>
        <th>Estado</th>
        <th>Prioridade</th>
        <?php if ($vistaTi): ?><th>Pedido por</th><th>Técnico</th><th>Prazo</th><?php endif; ?>
        <th>Atualizado</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($linhas as $t):
        $relogio = Sla::estado($t);
        // No TI interessa o relógio que ainda está a correr: 1.ª resposta se ainda ninguém respondeu, senão a resolução
        $mostrar = !$t['primeira_resposta_em'] && in_array($t['estado'], ESTADOS_ABERTOS, true) ? $relogio['resposta'] : $relogio['resolucao'];
    ?>
        <tr class="linha-<?= e($mostrar['tipo']) ?>">
            <td data-label="Pedido" class="col-pedido">
                <a class="link-pedido" href="<?= e(url('ticket', 'ver', ['id' => $t['id']])) ?>">
                    <span class="codigo"><?= e(codigo_ticket((int) $t['id'])) ?></span>
                    <span class="titulo"><?= e($t['titulo']) ?></span>
                </a>
                <span class="meta"><?= e($t['categoria_nome'] ?? 'Sem categoria') ?></span>
            </td>
            <td data-label="Estado"><?= chip_estado($t['estado'], $vistaTi) ?></td>
            <td data-label="Prioridade"><?= chip_prioridade((int) $t['prioridade']) ?></td>
            <?php if ($vistaTi): ?>
                <td data-label="Pedido por"><span><?= e($t['requerente_nome']) ?><?php if ($t['requerente_departamento']): ?><span class="meta"><?= e($t['requerente_departamento']) ?></span><?php endif; ?></span></td>
                <td data-label="Técnico"><?= $t['tecnico_nome'] ? e($t['tecnico_nome']) : '<span class="meta">Sem técnico</span>' ?></td>
                <td data-label="Prazo"><?= in_array($t['estado'], ESTADOS_ABERTOS, true) ? chip_sla($mostrar) : '<span class="meta">—</span>' ?></td>
            <?php endif; ?>
            <td data-label="Atualizado"><time datetime="<?= e($t['atualizado_em']) ?>" title="<?= e(data_pt($t['atualizado_em'])) ?>"><?= e(tempo_relativo($t['atualizado_em'])) ?></time></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
