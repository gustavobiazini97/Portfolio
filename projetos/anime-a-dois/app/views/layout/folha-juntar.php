<?php
/* Folha de uma série de outra pessoa (o par, na fila "está a ver"; um amigo, no perfil dele):
   mostra o progresso dela e deixa-te adicionar a série à tua biblioteca.
   Variáveis: $item (['serie','estado','conjunta',...], da biblioteca dessa pessoa), $dono (User: quem a tem),
              $capa (função: Serie + classe → HTML da capa) */
$s    = $item['serie'];
$prog = $s->progressoDe($dono);
?>
<dialog class="folha vidro folha-par" id="juntar-<?= (int) $dono->id ?>-<?= e($s->slug) ?>" aria-label="<?= e($s->nomeCurto()) ?>">
  <div class="folha-cima">
    <h2><?= e($s->nomeCurto()) ?></h2>
    <button type="button" class="btn-redondo vidro" data-fechar aria-label="Fechar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
    </button>
  </div>
  <div class="par-detalhe">
    <span class="capa-moldura par-detalhe-capa"><?= $capa($s, 'capa') ?></span>
    <div class="par-detalhe-texto">
      <p class="pessoa-legenda"><?= e(Serie::estadoTexto($item['estado'])) ?> · <?= e(plural((int) $s->total_episodios, 'episódio', 'episódios')) ?></p>
      <p><?= e($dono->nome) ?>: <?= $prog['vistos'] > 0 ? 'vai no episódio ' . $prog['posicao'] . ' (' . $prog['pct'] . '%)' : 'ainda não começou' ?></p>
      <progress class="barra vs-par-cor" max="100" value="<?= $prog['pct'] ?>" aria-label="<?= e($dono->nome) ?>: <?= $prog['pct'] ?>%"></progress>
    </div>
  </div>

  <!-- Só para mim: cada um vê a sua, sem avisos -->
  <form method="post" action="<?= e(url('biblioteca', 'juntar')) ?>">
    <?= csrf_campo() ?>
    <input type="hidden" name="serie" value="<?= e($s->slug) ?>">
    <button type="submit" class="btn">Adicionar só para mim</button>
  </form>
  <!-- Ver com ele: vêem a série juntos (o Vs, os comentários e os avisos); ele é avisado. Uma série pode ser vista com várias pessoas. -->
    <form method="post" action="<?= e(url('biblioteca', 'juntar')) ?>">
      <?= csrf_campo() ?>
      <input type="hidden" name="serie" value="<?= e($s->slug) ?>">
      <input type="hidden" name="com" value="<?= (int) $dono->id ?>">
      <button type="submit" class="btn btn-secundario">Ver com <?= e($dono->nome) ?></button>
    </form>
    <p class="pessoa-legenda par-nota">Vêem juntos: o teu progresso começa do zero e <?= e($dono->nome) ?> recebe um aviso.</p>
</dialog>
