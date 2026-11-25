<?php
/* O contentor do bicho (usado na página grande e no cartão do Início) e os ficheiros que o desenham.
   Variáveis: $mascote (Mascote::estado, nunca null aqui), $modo ('ecra' ou 'cartao').
   O estado vai em data-estado (JSON escapado com e(): o nome nunca vira HTML) e o token CSRF em data-csrf.
   Os scripts são todos ficheiros do próprio site (a CSP não deixa scripts inline nem de outras origens). */
$ehCartao = ($modo ?? 'ecra') === 'cartao';
?>
<link rel="stylesheet" href="<?= e(asset('mascote/mascote.css')) ?>">
<section class="painel vidro mascote-painel<?= $ehCartao ? ' mascote-painel-cartao' : '' ?>">
  <?php if ($ehCartao): ?>
    <!-- No cartão o bicho é pequeno; a página grande tem a escolha da espécie e mais espaço -->
    <a class="mascote-abrir" href="<?= e(url('mascote')) ?>">Abrir ›</a>
  <?php endif; ?>
  <div id="<?= $ehCartao ? 'mascote-cartao' : 'mascote' ?>"
       class="mascote-caixa<?= $ehCartao ? ' mascote-caixa-cartao' : '' ?>"
       data-mascote
       data-modo="<?= $ehCartao ? 'cartao' : 'ecra' ?>"
       data-estado="<?= e(json_encode($mascote, JSON_UNESCAPED_UNICODE)) ?>"
       data-csrf="<?= e($_SESSION['_csrf'] ?? '') ?>"
       data-url="<?= e(url('mascote', 'guardar')) ?>"></div>
  <p class="mascote-aviso" role="status" hidden></p>
</section>

<!-- Ordem: three.js, estado e ferramentas, corpos, motor e, por fim, a ponte do site -->
<script src="<?= e(asset('mascote/three.min.js')) ?>" defer></script>
<script src="<?= e(asset('mascote/mascote-estado.js')) ?>" defer></script>
<script src="<?= e(asset('mascote/mascote-ovo.js')) ?>" defer></script>
<script src="<?= e(asset('mascote/mascote-raposa.js')) ?>" defer></script>
<script src="<?= e(asset('mascote/mascote-gato.js')) ?>" defer></script>
<script src="<?= e(asset('mascote/mascote-motor.js')) ?>" defer></script>
<script src="<?= e(asset('js/mascote-site.js')) ?>" defer></script>
