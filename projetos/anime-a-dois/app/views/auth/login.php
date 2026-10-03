<?php /* Entrar. Variáveis: $registoAberto, $antigo, $flash */ ?>
<div class="topo">
  <span></span>
  <?php require __DIR__ . '/../layout/botao-tema.php'; ?>
</div>

<section class="auth">
  <!-- Logótipo, nome e frase, ao centro -->
  <div class="auth-cabeca">
    <div class="logo logo-grande" aria-hidden="true"><i></i><i></i></div>
    <h1 class="auth-titulo">anime a dois</h1>
    <p class="auth-sub">o que cada um já viu, lado a lado</p>
  </div>

  <?php require __DIR__ . '/../layout/flash.php'; ?>

  <!-- Formulário num painel de vidro, encostado ao fundo do ecrã -->
  <form class="form vidro" method="post" action="<?= e(url('auth', 'entrar')) ?>">
    <?= csrf_campo() ?>

    <label class="campo">
      <span>utilizador</span>
      <input type="text" name="username" value="<?= e($antigo['username'] ?? '') ?>"
             autocomplete="username" autocapitalize="none" spellcheck="false" required>
    </label>

    <label class="campo">
      <span>palavra-passe</span>
      <input type="password" name="password" autocomplete="current-password" required>
    </label>

    <button class="btn" type="submit">Entrar</button>
  </form>

  <?php if ($registoAberto): ?>
    <!-- Só aparece enquanto ainda não existem as duas contas -->
    <p class="auth-rodape">Ainda não tens conta? <a href="<?= e(url('auth', 'registo')) ?>">Criar conta</a></p>
  <?php endif; ?>
</section>
