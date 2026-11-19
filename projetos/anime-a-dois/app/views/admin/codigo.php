<?php
/* Backoffice: código de 2 fatores. Variáveis: $configurar (primeira vez), $segredo e $link (só ao configurar), $flash */
?>
<div class="topo">
  <a class="btn-redondo vidro" href="<?= e(url('perfil')) ?>" aria-label="Voltar ao perfil">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
  </a>
  <h1 class="topo-meio">Código de acesso</h1>
  <?php require __DIR__ . '/../layout/botao-tema.php'; ?>
</div>

<?php require __DIR__ . '/../layout/flash.php'; ?>

<form class="form vidro" method="post" action="<?= e(url('admin', 'confirmarCodigo')) ?>">
  <?= csrf_campo() ?>

  <?php if ($configurar): ?>
    <!-- Primeira vez: pôr o segredo na app de autenticação do telemóvel -->
    <p class="pessoa-legenda">
      O backoffice pede um código da app de autenticação (Google Authenticator, Microsoft Authenticator…).
      Na app, adiciona uma conta com esta chave, do tipo <b>baseada no tempo</b>:
    </p>
    <p class="codigo-segredo" translate="no"><?= e(DoisFatores::formatar($segredo)) ?></p>
    <p class="pessoa-legenda">
      No telemóvel também podes tocar em <a href="<?= e($link) ?>">abrir na app de autenticação</a>.
      Guarda a chave no teu gestor de palavras-passe: é com ela que recuperas o acesso se perderes o telemóvel.
    </p>
  <?php else: ?>
    <p class="pessoa-legenda">Escreve o código de 6 dígitos que a app de autenticação mostra agora.</p>
  <?php endif; ?>

  <label class="campo">
    <span>código</span>
    <input type="text" name="codigo" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7"
           autocomplete="one-time-code" autofocus required>
  </label>

  <button class="btn" type="submit"><?= $configurar ? 'Ativar e entrar' : 'Entrar no backoffice' ?></button>
</form>
