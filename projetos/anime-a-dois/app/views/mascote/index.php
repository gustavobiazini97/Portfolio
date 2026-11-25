<?php
/* O bicho do casal, em página grande.
   Variáveis: $user, $mascote (Mascote::estado: array, ou null se ainda não há par), $flash */
?>
<div class="topo">
  <a class="btn-redondo vidro" href="<?= e(url('home')) ?>" aria-label="Voltar ao início">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
  </a>
  <h1 class="topo-meio">O vosso bicho</h1>
</div>

<?php require __DIR__ . '/../layout/flash.php'; ?>

<?php if ($mascote === null): ?>
  <!-- Sem par não há bicho -->
  <section class="painel vidro vazio">
    <p>🥚</p>
    <p>Ainda não tens par, por isso ainda não há mascote.</p>
  </section>
<?php else: ?>
  <?php $modo = 'ecra'; require __DIR__ . '/cartao.php'; ?>
<?php endif; ?>
