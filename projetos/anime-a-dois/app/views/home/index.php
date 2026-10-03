<?php /* Dashboard (versão inicial). Variáveis: $user, $parceiro (User ou null) */ ?>
<section class="ola">
  <p class="ola-pre">Olá,</p>
  <h1 class="ola-nome"><?= e($user->nome) ?></h1>
</section>

<?php if ($parceiro): ?>
  <!-- As duas contas existem: mostra o par -->
  <article class="cartao par-cartao">
    <div class="duo" aria-hidden="true">
      <span class="avatar tu"><?= e(mb_substr($user->nome, 0, 1)) ?></span>
      <span class="avatar par"><?= e(mb_substr($parceiro->nome, 0, 1)) ?></span>
    </div>
    <div>
      <p class="par-titulo">Ligado(a) a <?= e($parceiro->nome) ?></p>
      <p class="par-sub">Em breve: o último episódio que <?= e($parceiro->nome) ?> viu e o vosso Vs.</p>
    </div>
  </article>
<?php else: ?>
  <!-- Só existe esta conta: falta a segunda pessoa criar a dela -->
  <article class="cartao par-cartao espera">
    <div class="duo" aria-hidden="true">
      <span class="avatar tu"><?= e(mb_substr($user->nome, 0, 1)) ?></span>
      <span class="avatar vazio">?</span>
    </div>
    <div>
      <p class="par-titulo">À espera do teu par</p>
      <p class="par-sub">Quando a segunda conta for criada, aparece aqui e o registo fecha.</p>
    </div>
  </article>
<?php endif; ?>
