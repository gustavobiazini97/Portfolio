<?php
/* Backoffice. Variáveis: $user, $q (pesquisa), $n (números), $top (séries mais vistas), $contas, $flash */
// Cartão de número: valor grande e legenda
$cartao = function (int $valor, string $legenda): void { ?>
  <div class="admin-num"><b><?= $valor ?></b><span><?= e($legenda) ?></span></div>
<?php };
?>
<div class="topo">
  <a class="btn-redondo vidro" href="<?= e(url('perfil')) ?>" aria-label="Voltar ao perfil">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
  </a>
  <h1 class="topo-meio">Backoffice</h1>
  <?php require __DIR__ . '/../layout/botao-tema.php'; ?>
</div>

<?php require __DIR__ . '/../layout/flash.php'; ?>

<!-- Números da app -->
<section class="painel vidro">
  <div class="painel-titulo"><h2>Utilização</h2></div>
  <div class="admin-nums">
    <?php $cartao($n['contas'], 'contas'); $cartao($n['novas7'], 'novas (7 dias)'); ?>
    <?php $cartao($n['ativas1'], 'ativas hoje'); $cartao($n['ativas7'], 'ativas (7 dias)'); $cartao($n['ativas30'], 'ativas (30 dias)'); ?>
    <?php $cartao($n['series'], 'séries'); $cartao($n['emBiblioteca'], 'séries em bibliotecas'); ?>
    <?php $cartao($n['vistos'], 'episódios vistos'); $cartao($n['comentarios'], 'comentários'); ?>
    <?php $cartao($n['amizades'], 'amizades'); $cartao($n['juntos'], 'séries vistas em conjunto'); ?>
    <?php $cartao($n['comPush'], 'contas com avisos no telemóvel'); $cartao($n['telemoveis'], 'telemóveis ligados'); ?>
  </div>
  <?php if ($top !== []): ?>
    <p class="pessoa-legenda admin-top">Mais vistas: <?= e(implode(' · ', array_map(fn ($t) => $t->nome . ' (' . $t->pessoas . ')', $top))) ?></p>
  <?php endif; ?>
</section>

<!-- Contas -->
<section class="painel vidro">
  <div class="painel-titulo"><h2>Contas</h2><a class="btn-texto vidro" href="<?= e(url('admin', 'novo')) ?>">Nova conta</a></div>
  <form class="admin-pesquisa" method="get" action="<?= e(url('admin')) ?>">
    <input type="hidden" name="c" value="admin">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Procurar por nome ou utilizador" aria-label="Procurar contas">
  </form>
  <?php if ($contas === []): ?>
    <p class="folha-vazio">Nenhuma conta encontrada.</p>
  <?php else: ?>
    <ul class="pessoa-lista">
      <?php foreach ($contas as $c): ?>
        <li class="admin-conta">
          <div class="admin-conta-texto">
            <span class="pessoa-serie-nome"><?= e($c->nome) ?><?= (int) $c->admin === 1 ? ' · admin' : '' ?></span>
            <span class="pessoa-legenda">@<?= e($c->username) ?> · criada <?= e(date('d/m/Y', strtotime($c->criado_em))) ?></span>
            <span class="pessoa-legenda"><?= $c->ultimo_acesso ? 'visto ' . e(tempo_relativo($c->ultimo_acesso)) : 'ainda sem acessos' ?> · <?= (int) $c->n_series ?> séries · <?= (int) $c->n_vistos ?> eps</span>
          </div>
          <div class="admin-conta-acoes">
            <a class="btn-texto vidro" href="<?= e(url('admin', 'editar', ['id' => $c->id])) ?>">Editar</a>
            <?php if ((int) $c->admin !== 1): ?>
              <form method="post" action="<?= e(url('admin', 'apagar')) ?>" data-confirmar="Apagar a conta de <?= e($c->nome) ?>? Perde tudo (progresso, comentários, ligações). Não dá para desfazer.">
                <?= csrf_campo() ?>
                <input type="hidden" name="id" value="<?= (int) $c->id ?>">
                <button type="submit" class="btn-texto vidro admin-apagar">Apagar</button>
              </form>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
