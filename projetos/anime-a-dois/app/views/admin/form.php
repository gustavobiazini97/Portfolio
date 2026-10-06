<?php
/* Backoffice: criar ou editar uma conta. Variáveis: $conta (User, ou null para criar), $antigo, $flash */
$editar = $conta !== null;
?>
<div class="topo">
  <a class="btn-redondo vidro" href="<?= e(url('admin')) ?>" aria-label="Voltar ao backoffice">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
  </a>
  <h1 class="topo-meio"><?= $editar ? 'Editar conta' : 'Nova conta' ?></h1>
  <?php require __DIR__ . '/../layout/botao-tema.php'; ?>
</div>

<?php require __DIR__ . '/../layout/flash.php'; ?>

<form class="form vidro" method="post" action="<?= e(url('admin', $editar ? 'guardar' : 'criar')) ?>">
  <?= csrf_campo() ?>
  <?php if ($editar): ?><input type="hidden" name="id" value="<?= (int) $conta->id ?>"><?php endif; ?>
  <label class="campo">
    <span>nome</span>
    <input type="text" name="nome" value="<?= e($antigo['nome'] ?? ($editar ? $conta->nome : '')) ?>" maxlength="40" required>
  </label>
  <label class="campo">
    <span>utilizador</span>
    <input type="text" name="username" value="<?= e($antigo['username'] ?? ($editar ? $conta->username : '')) ?>"
           maxlength="30" pattern="[A-Za-z0-9._\-]{3,30}" autocapitalize="none" spellcheck="false" required>
  </label>
  <label class="campo">
    <span><?= $editar ? 'nova palavra-passe' : 'palavra-passe' ?></span>
    <input type="password" name="password" minlength="8" autocomplete="new-password" <?= $editar ? '' : 'required' ?>>
    <small><?= $editar ? 'deixa vazio para manter a atual · ' : '' ?>pelo menos 8 caracteres</small>
  </label>
  <?php if (!$editar): ?>
    <label class="campo">
      <span>repetir palavra-passe</span>
      <input type="password" name="password_confirmar" minlength="8" autocomplete="new-password" required>
    </label>
  <?php endif; ?>
  <button type="submit" class="btn"><?= $editar ? 'Guardar' : 'Criar conta' ?></button>
</form>
