<?php /* Login. Variáveis: $registoAberto, $antigo */ ?>
<section class="auth">
  <!-- Logótipo: as duas bolas são "tu" e "o teu par" -->
  <div class="auth-marca" aria-hidden="true"><i class="bola tu"></i><i class="bola par"></i></div>
  <h1 class="auth-titulo"><?= e($appNome) ?></h1>
  <p class="auth-sub">Os episódios que vocês os dois já viram, lado a lado.</p>

  <form class="cartao form" method="post" action="<?= e(url('auth', 'entrar')) ?>">
    <?= csrf_campo() ?>

    <label class="campo">
      <span>Utilizador</span>
      <input type="text" name="username" value="<?= e($antigo['username'] ?? '') ?>"
             autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
    </label>

    <label class="campo">
      <span>Palavra-passe</span>
      <input type="password" name="password" autocomplete="current-password" required>
    </label>

    <button class="btn" type="submit">Entrar</button>
  </form>

  <?php if ($registoAberto): ?>
    <!-- Só aparece enquanto ainda não existem as duas contas -->
    <p class="auth-rodape">Ainda não tens conta? <a href="<?= e(url('auth', 'registo')) ?>">Criar conta</a></p>
  <?php endif; ?>
</section>
