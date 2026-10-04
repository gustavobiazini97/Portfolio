<?php /* Mensagem do pedido anterior (Post/Redirect/Get). Variável: $flash */ ?>
<?php if ($flash): ?>
  <p class="flash flash-<?= e($flash['tipo']) ?> vidro" role="status"><?= e($flash['mensagem']) ?></p>
<?php endif; ?>
