<?php
/* Avatar redondo: a foto, se houver; senão a inicial na cor da pessoa.
   Variáveis: $avatarUser (User), $avatarCor ('tu' ou 'par'), $avatarExtra (classes extra, opcional) */
$fotoUrl = $avatarUser->fotoUrl();
?>
<span class="avatar avatar-<?= e($avatarCor) ?> <?= e($avatarExtra ?? '') ?>" aria-hidden="true">
  <?php if ($fotoUrl): ?>
    <img src="<?= e($fotoUrl) ?>" alt="" loading="lazy">
  <?php else: ?>
    <?= e($avatarUser->inicial()) ?>
  <?php endif; ?>
</span>
