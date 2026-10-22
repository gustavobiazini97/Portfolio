<?php
/* Medalhas (igual à app): por grupo, cada uma com o emblema de bronze/prata/ouro;
   as que faltam com cadeado e barra do progresso. Tocar numa abre o detalhe (folha preenchida pelo JS).
   Variáveis: $user, $pessoa, $proprio, $medalhas (obtidas, total, lista, destaque), $grupos (grupo → medalhas), $flash */
$niveis = ['bronze' => 'Bronze', 'prata' => 'Prata', 'ouro' => 'Ouro'];
?>
<div class="topo">
  <a class="btn-redondo vidro" href="<?= e(url('perfil', 'pessoa', ['id' => $pessoa->id])) ?>" aria-label="Voltar ao perfil">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
  </a>
  <h1 class="topo-meio"><?= $proprio ? 'Medalhas' : 'Medalhas de ' . e($pessoa->nome) ?></h1>
  <?php require __DIR__ . '/../layout/botao-tema.php'; ?>
</div>

<!-- Quantas já tem: número grande e barra -->
<section class="painel vidro medalhas-total">
  <p><b data-contar="<?= $medalhas['obtidas'] ?>"><?= $medalhas['obtidas'] ?></b> de <?= $medalhas['total'] ?></p>
  <progress class="barra vs-tu-cor" max="<?= $medalhas['total'] ?>" value="<?= $medalhas['obtidas'] ?>" aria-label="<?= $medalhas['obtidas'] ?> de <?= $medalhas['total'] ?> medalhas"></progress>
</section>

<?php foreach ($grupos as $grupo => $lista): ?>
  <section class="medalhas-grupo">
    <h2 class="estado-titulo"><?= e($grupo) ?></h2>
    <div class="medalhas-grelha">
      <?php foreach ($lista as $m): ?>
        <!-- data-* alimentam a folha do detalhe (texto sempre inserido como texto pelo JS) -->
        <button type="button" class="medalha<?= $m['obtida'] ? ' obtida' : '' ?>" data-medalha
                data-emoji="<?= e($m['emoji']) ?>" data-nome="<?= e($m['nome']) ?>" data-descricao="<?= e($m['descricao']) ?>"
                data-nivel="<?= e($m['nivel']) ?>" data-nivel-texto="<?= e($niveis[$m['nivel']] ?? 'Bronze') ?>"
                data-obtida="<?= $m['obtida'] ? '1' : '0' ?>" data-progresso="<?= e(medalha_progresso($m)) ?>" data-pct="<?= medalha_pct($m) ?>"
                data-quando="<?= e(medalha_quando($m)) ?>">
          <span class="emblema emblema-<?= e($m['nivel']) ?><?= $m['obtida'] ? '' : ' bloqueada' ?>">
            <span><?= e($m['emoji']) ?></span>
            <?php if (!$m['obtida']): ?><i class="cadeado" aria-hidden="true">🔒</i><?php endif; ?>
          </span>
          <span class="medalha-nome"><?= e($m['nome']) ?></span>
          <?php if (!$m['obtida']): ?>
            <progress class="barra barra-fina vs-par-cor" max="100" value="<?= medalha_pct($m) ?>" aria-label="<?= e(medalha_progresso($m)) ?>"></progress>
            <span class="medalha-progresso"><?= e(medalha_progresso($m)) ?></span>
          <?php endif; ?>
        </button>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>

<!-- Detalhe de uma medalha (o JS preenche ao tocar) -->
<dialog class="folha vidro folha-medalha" id="folha-medalha" aria-label="Medalha">
  <div class="folha-cima">
    <span></span>
    <button type="button" class="btn-redondo vidro" data-fechar aria-label="Fechar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
    </button>
  </div>
  <span class="emblema emblema-grande" id="md-emblema"><span id="md-emoji"></span></span>
  <h2 id="md-nome"></h2>
  <p class="pessoa-legenda" id="md-descricao"></p>
  <p class="md-nivel" id="md-obtida"></p>
  <!-- Dia em que foi conseguida (só nas obtidas) -->
  <p class="pessoa-legenda md-quando" id="md-quando"></p>
  <progress class="barra vs-par-cor" id="md-barra" max="100" value="0"></progress>
  <p class="pessoa-legenda" id="md-progresso"></p>
</dialog>
