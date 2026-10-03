<?php /* Perfil. Variáveis: $user, $antigo, $flash */ ?>
<div class="topo">
  <a class="btn-redondo vidro" href="<?= e(url('home')) ?>" aria-label="Voltar ao início">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
  </a>
  <h1 class="topo-meio">Perfil</h1>
  <?php require __DIR__ . '/../layout/botao-tema.php'; ?>
</div>

<?php require __DIR__ . '/../layout/flash.php'; ?>

<!-- Foto: tocar no avatar abre a galeria/câmara; o JS reduz a foto e envia logo -->
<section class="painel vidro perfil-foto">
  <form id="form-foto" method="post" action="<?= e(url('perfil', 'enviarFoto')) ?>" enctype="multipart/form-data">
    <?= csrf_campo() ?>
    <label class="foto-escolher" aria-label="Escolher foto de perfil">
      <?php $avatarUser = $user; $avatarCor = 'tu'; $avatarExtra = 'avatar-xl'; require __DIR__ . '/../layout/avatar.php'; ?>
      <span class="foto-camara" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg>
      </span>
      <input type="file" name="foto" accept="image/*" id="input-foto" class="escondido">
    </label>
    <!-- Sem JavaScript: botão normal para enviar -->
    <noscript><button type="submit" class="btn-texto vidro">Enviar foto</button></noscript>
  </form>

  <p class="perfil-nome"><?= e($user->nome) ?></p>
  <p class="perfil-user">@<?= e($user->username) ?></p>

  <?php if ($user->fotoUrl()): ?>
    <form method="post" action="<?= e(url('perfil', 'removerFoto')) ?>">
      <?= csrf_campo() ?>
      <button type="submit" class="link-suave">Remover foto</button>
    </form>
  <?php endif; ?>
</section>

<!-- Nome que aparece na app e nome de utilizador para entrar -->
<form class="form vidro" method="post" action="<?= e(url('perfil', 'guardarNome')) ?>">
  <?= csrf_campo() ?>
  <label class="campo">
    <span>nome</span>
    <input type="text" name="nome" value="<?= e($antigo['nome'] ?? $user->nome) ?>" maxlength="40" autocomplete="nickname" required>
    <small>é assim que apareces para o teu par</small>
  </label>
  <label class="campo">
    <span>utilizador</span>
    <input type="text" name="username" value="<?= e($antigo['username'] ?? $user->username) ?>"
           maxlength="30" pattern="[A-Za-z0-9._\-]{3,30}" autocomplete="username"
           autocapitalize="none" spellcheck="false" required>
    <small>para entrar · letras, números, ponto, hífen ou _</small>
  </label>
  <button type="submit" class="btn btn-secundario">Guardar</button>
</form>

<!-- Palavra-passe (fechado por defeito, para o ecrã não ficar cheio) -->
<details class="form vidro dobravel">
  <summary>Mudar palavra-passe</summary>
  <form method="post" action="<?= e(url('perfil', 'guardarPassword')) ?>" class="dobravel-corpo">
    <?= csrf_campo() ?>
    <label class="campo">
      <span>palavra-passe atual</span>
      <input type="password" name="atual" autocomplete="current-password" required>
    </label>
    <label class="campo">
      <span>nova palavra-passe</span>
      <input type="password" name="nova" minlength="8" autocomplete="new-password" required>
      <small>pelo menos 8 caracteres</small>
    </label>
    <label class="campo">
      <span>repetir a nova</span>
      <input type="password" name="confirmar" minlength="8" autocomplete="new-password" required>
    </label>
    <button type="submit" class="btn btn-secundario">Alterar palavra-passe</button>
  </form>
</details>

<form class="acao-fundo" method="post" action="<?= e(url('auth', 'sair')) ?>">
  <?= csrf_campo() ?>
  <button type="submit" class="btn">Terminar sessão</button>
</form>

<!-- Aviso curto (usado pelo envio da foto) -->
<p class="aviso vidro" id="aviso" role="status" aria-live="polite" hidden></p>
