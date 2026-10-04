<?php /* Criar conta. Variáveis: $primeiro (User ou null), $convite (link de amigo válido ou null), $antigo, $flash */ ?>
<div class="topo">
  <a class="btn-redondo vidro" href="<?= e(url('auth', 'login')) ?>" aria-label="Voltar a entrar">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
  </a>
  <?php require __DIR__ . '/../layout/botao-tema.php'; ?>
</div>

<section class="auth">
  <div class="auth-cabeca">
    <img class="logo logo-grande" src="img/logo.svg" alt="" width="96" height="96">
    <h1 class="auth-titulo">criar conta</h1>
    <?php if ($convite): ?>
      <!-- Convite de um amigo: a conta nasce ligada a ele, com a biblioteca vazia -->
      <p class="auth-sub">convite de <strong><?= e($convite->de->nome) ?></strong> · vão ficar amigos na app</p>
    <?php elseif ($primeiro): ?>
      <!-- Segunda conta: fica ligada à primeira e o registo fecha a seguir -->
      <p class="auth-sub">conta 2 de 2 · vais ficar ligado(a) a <strong><?= e($primeiro->nome) ?></strong></p>
    <?php else: ?>
      <p class="auth-sub">conta 1 de 2 · a segunda fica para o teu par</p>
    <?php endif; ?>
    <?php require __DIR__ . '/../layout/botao-instalar.php'; ?>
  </div>

  <?php require __DIR__ . '/../layout/flash.php'; ?>

  <form class="form vidro" method="post" action="<?= e(url('auth', 'registar')) ?>">
    <?= csrf_campo() ?>
    <?php if ($convite): ?><input type="hidden" name="convite" value="<?= e($convite->codigo) ?>"><?php endif; ?>

    <label class="campo">
      <span>nome</span>
      <input type="text" name="nome" value="<?= e($antigo['nome'] ?? '') ?>" maxlength="40" autocomplete="nickname" placeholder="como vais aparecer na app" required>
    </label>

    <label class="campo">
      <span>utilizador</span>
      <input type="text" name="username" value="<?= e($antigo['username'] ?? '') ?>"
             maxlength="30" pattern="[A-Za-z0-9._\-]{3,30}" autocomplete="username"
             autocapitalize="none" spellcheck="false" placeholder="para entrar: letras, números, . - _" required>
    </label>

    <label class="campo">
      <span>palavra-passe</span>
      <input type="password" name="password" minlength="8" autocomplete="new-password" placeholder="pelo menos 8 caracteres" required>
    </label>

    <label class="campo">
      <span>repetir palavra-passe</span>
      <input type="password" name="password_confirmar" minlength="8" autocomplete="new-password" required>
    </label>

    <button class="btn" type="submit">Criar conta</button>
  </form>

  <p class="auth-rodape">Já tens conta? <a href="<?= e(url('auth', 'login')) ?>">Entrar</a></p>
</section>
