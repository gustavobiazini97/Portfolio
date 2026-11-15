<?php
// Lista de notificações (as não lidas em destaque).
/** @var array $lista */
$haNaoLidas = (bool) array_filter($lista, fn ($n) => $n['lida_em'] === null);
?>
<div class="cab">
    <div>
        <h1>Notificações</h1>
        <p class="sub">As últimas 50. Também chegam por email, se o tiveres ligado no perfil.</p>
    </div>
    <?php if ($haNaoLidas): ?>
        <form class="cab-acoes" method="post" action="<?= e(url('notificacao', 'lerTodas')) ?>">
            <?= csrf_campo() ?>
            <button class="botao botao-sec" type="submit"><?= icone('visto') ?> Marcar todas como lidas</button>
        </form>
    <?php endif; ?>
</div>

<?php if (!$lista): ?>
    <p class="vazio">Sem notificações.</p>
<?php else: ?>
    <ul class="notificacoes">
        <?php foreach ($lista as $n): ?>
            <li class="<?= $n['lida_em'] === null ? 'nao-lida' : '' ?>">
                <a href="<?= e(url('notificacao', 'abrir', ['id' => $n['id']])) ?>">
                    <span class="ponto" aria-hidden="true"></span>
                    <span class="notif-texto"><?= e($n['mensagem']) ?></span>
                    <time datetime="<?= e($n['criado_em']) ?>" title="<?= e(data_pt($n['criado_em'])) ?>"><?= e(tempo_relativo($n['criado_em'])) ?></time>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
