<?php
// Prazos do SLA por prioridade (minutos de horário de trabalho) e a matriz impacto × urgência.
/** @var array $politicas */
$horas = (int) round((strtotime((string) config('horario.fim')) - strtotime((string) config('horario.inicio'))) / 3600);
?>
<div class="cab">
    <div>
        <h1>Administração</h1>
        <p class="sub">Prazos em minutos de horário de trabalho (<?= e((string) config('horario.inicio')) ?>–<?= e((string) config('horario.fim')) ?>: 1 dia útil = <?= $horas * 60 ?> min).</p>
    </div>
</div>
<?php require RAIZ . '/app/views/admin/_tabs.php'; ?>

<div class="perfil-grid">
    <form class="form cartao" method="post" action="<?= e(url('admin', 'guardarSla')) ?>">
        <?= csrf_campo() ?>
        <h2>Prazos por prioridade</h2>
        <div class="tabela-envolve">
        <table class="tabela tabela-sla">
            <thead><tr><th>Prioridade</th><th>1.ª resposta (min)</th><th>Resolução (min)</th></tr></thead>
            <tbody>
            <?php foreach (PRIORIDADES as $p => $nome): $pol = $politicas[$p] ?? ['resposta_min' => 60, 'resolucao_min' => 540]; ?>
                <tr>
                    <td data-label="Prioridade"><?= chip_prioridade($p) ?></td>
                    <td data-label="1.ª resposta"><input type="number" name="resposta_<?= $p ?>" min="5" max="32400" required value="<?= (int) $pol['resposta_min'] ?>"><small class="meta"><?= e(duracao((int) $pol['resposta_min'])) ?></small></td>
                    <td data-label="Resolução"><input type="number" name="resolucao_<?= $p ?>" min="5" max="32400" required value="<?= (int) $pol['resolucao_min'] ?>"><small class="meta"><?= e(duracao((int) $pol['resolucao_min'])) ?></small></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div class="form-acoes"><button class="botao" type="submit">Guardar prazos</button></div>
    </form>

    <section class="cartao">
        <h2>Como se calcula a prioridade</h2>
        <p class="ajuda">O funcionário responde a duas perguntas; a prioridade sai desta matriz (ITIL). O técnico pode corrigi-la com um motivo.</p>
        <div class="tabela-envolve">
        <table class="matriz">
            <thead><tr><th></th><?php foreach (URGENCIAS as $nome): ?><th><?= e($nome) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php foreach (array_reverse(IMPACTOS, true) as $i => $nomeI): ?>
                <tr><th><?= e($nomeI) ?></th>
                    <?php foreach (array_keys(URGENCIAS) as $u): $p = Sla::prioridade($i, $u); ?>
                        <td><span class="prio p<?= $p ?>"><b>P<?= $p ?></b></span></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p class="ajuda">Avisos automáticos: quando restam 20% do prazo (ao técnico) e quando o prazo passa (ao técnico e aos admins). O relógio da resolução para enquanto o pedido está “à espera”.</p>
    </section>
</div>
