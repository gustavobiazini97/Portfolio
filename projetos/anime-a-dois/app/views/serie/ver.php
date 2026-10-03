<?php
/* Página da série.
   Variáveis: $serie, $user, $parceiro (ou null), $fillers, $meu, $dele, $episodios, $inicial, $flash */
$nomePar = $parceiro ? $parceiro->nome : null;
?>
<div class="topo">
  <a class="btn-redondo vidro" href="<?= e(url('home', 'index', ['serie' => $serie->slug])) ?>" aria-label="Voltar ao início">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
  </a>
  <h1 class="topo-meio"><?= e($serie->nome) ?></h1>
  <?php require __DIR__ . '/../layout/botao-tema.php'; ?>
</div>

<?php require __DIR__ . '/../layout/flash.php'; ?>

<!-- Mapa: um risco por episódio, uma linha por pessoa -->
<section class="mapa vidro" aria-label="Episódios vistos por cada um">
  <div class="mapa-info">
    <span><?= e($serie->total_episodios . ' episódios · ' . $fillers . ' fillers') ?></span>
    <span>um risco por episódio</span>
  </div>

  <div class="mapa-linha mapa-tu">
    <span>Tu</span>
    <div class="riscos" aria-hidden="true">
      <?php foreach ($episodios as $ep): ?><i class="<?= ($ep['tu'] ? 'v' : '') . ($ep['filler'] ? ' f' : '') ?>"></i><?php endforeach; ?>
    </div>
    <span class="mapa-pct"><?= $meu['pct'] ?>%</span>
  </div>

  <?php if ($parceiro): ?>
    <div class="mapa-linha mapa-par">
      <span><?= e($nomePar) ?></span>
      <div class="riscos" aria-hidden="true">
        <?php foreach ($episodios as $ep): ?><i class="<?= ($ep['par'] ? 'v' : '') . ($ep['filler'] ? ' f' : '') ?>"></i><?php endforeach; ?>
      </div>
      <span class="mapa-pct"><?= $dele['pct'] ?>%</span>
    </div>
  <?php endif; ?>
</section>

<!-- Pista: desliza para os lados; o cartão do centro é o selecionado -->
<div class="pista" aria-label="Episódios de <?= e($serie->nome) ?>">
  <?php foreach ($episodios as $ep): ?>
    <button type="button"
            class="ep vidro<?= $ep['numero'] === $inicial ? ' ativo' : '' ?>"
            data-id="<?= $ep['id'] ?>" data-n="<?= $ep['numero'] ?>" data-tu="<?= $ep['tu'] ? '1' : '0' ?>"
            <?= $ep['numero'] === $inicial ? 'data-inicial' : '' ?>
            aria-label="Episódio <?= $ep['numero'] ?>">
      <span class="ep-cima"><span>episódio</span><span><?= $ep['filler'] ? 'filler' : '' ?></span></span>
      <span class="ep-num"><?= $ep['numero'] ?></span>
      <span class="ep-estados">
        <span class="ep-estado"><i class="ponto ponto-tu<?= $ep['tu'] ? ' v' : '' ?>"></i>tu · <?= $ep['tu'] ? 'visto' : 'por ver' ?></span>
        <?php if ($parceiro): ?>
          <span class="ep-estado"><i class="ponto ponto-par<?= $ep['par'] ? ' v' : '' ?>"></i><?= e(mb_strtolower($nomePar)) ?> · <?= $ep['par'] ? 'visto' : 'por ver' ?></span>
        <?php endif; ?>
      </span>
    </button>
  <?php endforeach; ?>
</div>

<?php
// Estado do episódio inicial, para o botão funcionar mesmo sem JavaScript
$epInicial = $episodios[$inicial - 1] ?? $episodios[0];
?>
<form id="form-marcar" class="acao-fundo" method="post" action="<?= e(url('serie', 'marcar')) ?>">
  <?= csrf_campo() ?>
  <input type="hidden" name="episodio_id" value="<?= $epInicial['id'] ?>">
  <button type="submit" class="btn<?= $epInicial['tu'] ? ' btn-secundario' : '' ?>">
    <?= $epInicial['tu'] ? 'Desmarcar episódio ' . $epInicial['numero'] : 'Marcar episódio ' . $epInicial['numero'] . ' como visto' ?>
  </button>
</form>
