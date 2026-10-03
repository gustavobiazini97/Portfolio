<?php /* Criar conta. Variáveis: $primeiro (User ou null), $antigo */ ?>
<section class="auth">
  <div class="auth-marca" aria-hidden="true"><i class="bola tu"></i><i class="bola par"></i></div>
  <h1 class="auth-titulo">Criar conta</h1>

  <?php if ($primeiro): ?>
    <!-- Segunda conta: fica ligada à primeira e o registo fecha a seguir -->
    <p class="auth-sub">Conta 2 de 2 · vais ficar ligado(a) a <strong><?= e($primeiro->nome) ?></strong>.</p>
  <?php else: ?>
    <p class="auth-sub">Conta 1 de 2 · a segunda fica para a tua pessoa.</p>
  <?php endif; ?>

  <form class="cartao form" method="post" action="<?= e(url('auth', 'registar')) ?>">
    <?= csrf_campo() ?>

    <label class="campo">
      <span>Nome</span>
      <input type="text" name="nome" value="<?= e($antigo['nome'] ?? '') ?>"
             maxlength="40" autocomplete="nickname" required autofocus>
      <small>É assim que vais aparecer na app.</small>
    </label>

    <label class="campo">
      <span>Utilizador</span>
      <input type="text" name="username" value="<?= e($antigo['username'] ?? '') ?>"
             maxlength="30" pattern="[A-Za-z0-9._\-]{3,30}" autocomplete="username"
             autocapitalize="none" spellcheck="false" required>
      <small>Para entrar. Letras, números, ponto, hífen ou _.</small>
    </label>

    <label class="campo">
      <span>Palavra-passe</span>
      <input type="password" name="password" minlength="8" autocomplete="new-password" required>
      <small>Pelo menos 8 caracteres.</small>
    </label>

    <label class="campo">
      <span>Repetir palavra-passe</span>
      <input type="password" name="password_confirmar" minlength="8" autocomplete="new-password" required>
    </label>

    <button class="btn" type="submit">Criar conta</button>
  </form>

  <p class="auth-rodape">Já tens conta? <a href="<?= e(url('auth', 'login')) ?>">Entrar</a></p>
</section>
