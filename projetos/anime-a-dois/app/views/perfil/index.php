<?php /* Perfil. Variáveis: $user, $pref (Preferencia), $vapidPublica, $telemoveis, $antigo, $flash */ ?>
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

<!-- Notificações: o que receber (linhas) e por onde (colunas) -->
<form class="form vidro notif" method="post" action="<?= e(url('perfil', 'guardarNotificacoes')) ?>">
  <?= csrf_campo() ?>
  <h2 class="notif-titulo">Notificações</h2>
  <p class="notif-sub">Quando o teu par marca episódios, comenta ou traz uma série nova.</p>

  <div class="notif-grade" role="group" aria-label="O que receber e por onde">
    <span></span><span class="notif-col">Telemóvel</span><span class="notif-col">Email</span>

    <span>Episódios marcados</span>
    <label class="interruptor"><input type="checkbox" name="ep_push" value="1" <?= $pref->ep_push ? 'checked' : '' ?>><i></i><span class="escondido">Episódios no telemóvel</span></label>
    <label class="interruptor"><input type="checkbox" name="ep_email" value="1" <?= $pref->ep_email ? 'checked' : '' ?>><i></i><span class="escondido">Episódios por email</span></label>

    <span>Comentários</span>
    <label class="interruptor"><input type="checkbox" name="com_push" value="1" <?= $pref->com_push ? 'checked' : '' ?>><i></i><span class="escondido">Comentários no telemóvel</span></label>
    <label class="interruptor"><input type="checkbox" name="com_email" value="1" <?= $pref->com_email ? 'checked' : '' ?>><i></i><span class="escondido">Comentários por email</span></label>

    <span>Séries novas e propostas</span>
    <label class="interruptor"><input type="checkbox" name="serie_push" value="1" <?= $pref->serie_push ? 'checked' : '' ?>><i></i><span class="escondido">Séries no telemóvel</span></label>
    <label class="interruptor"><input type="checkbox" name="serie_email" value="1" <?= $pref->serie_email ? 'checked' : '' ?>><i></i><span class="escondido">Séries por email</span></label>
  </div>

  <label class="campo">
    <span>email</span>
    <input type="email" name="email" value="<?= e($pref->email ?? '') ?>" autocomplete="email" placeholder="só se quiseres receber por email">
  </label>

  <button type="submit" class="btn btn-secundario">Guardar notificações</button>

  <!-- Este telemóvel: pedir permissão e subscrever (js/app.js) -->
  <div class="notif-telemovel" id="notif-telemovel"
       data-vapid="<?= e($vapidPublica) ?>"
       data-url-subscrever="<?= e(url('perfil', 'subscrever')) ?>"
       data-url-desubscrever="<?= e(url('perfil', 'desubscrever')) ?>"
       data-url-testar="<?= e(url('perfil', 'testarNotificacao')) ?>">
    <p class="notif-estado" id="notif-estado">
      <?= $telemoveis ? 'Notificações ativas em ' . plural($telemoveis, 'telemóvel', 'telemóveis') . '.' : 'Ainda não ativaste as notificações em nenhum telemóvel.' ?>
    </p>
    <div class="notif-botoes">
      <button type="button" class="btn-texto vidro" id="btn-notif-ativar">Ativar neste telemóvel</button>
      <button type="button" class="btn-texto vidro" id="btn-notif-testar">Enviar teste</button>
    </div>
  </div>
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

<!-- Atualizações: a app atualiza sozinha; o botão força a procura e recarrega com os ficheiros mais recentes -->
<section class="form vidro atualizacao" id="atualizacao">
  <div class="painel-titulo"><h2>Atualizações</h2><span>versão <?= (int) $versao ?></span></div>
  <p class="atualizacao-texto" id="atualizacao-estado">A app atualiza sozinha ao abrir. Se algo parecer desatualizado, toca aqui.</p>
  <button type="button" class="btn-texto vidro" id="btn-atualizar">Procurar atualizações</button>
</section>

<form class="acao-fundo" method="post" action="<?= e(url('auth', 'sair')) ?>">
  <?= csrf_campo() ?>
  <button type="submit" class="btn">Terminar sessão</button>
</form>

<?php if ($user->ehAdmin()): ?>
  <a class="btn btn-secundario" href="<?= e(url('admin')) ?>">Backoffice</a>
<?php endif; ?>

<!-- Apagar conta (fechado por defeito): pede a palavra-passe e apaga tudo da base de dados -->
<details class="form vidro dobravel zona-perigo">
  <summary>Apagar conta</summary>
  <form method="post" action="<?= e(url('perfil', 'apagarConta')) ?>" class="dobravel-corpo" data-confirmar="Apagar a tua conta de vez? Perdes o progresso, os comentários e as ligações. Não dá para desfazer.">
    <?= csrf_campo() ?>
    <p class="atualizacao-texto">Isto apaga o teu progresso, os teus comentários, a foto e as ligações a amigos e ao par. Não dá para desfazer.</p>
    <label class="campo">
      <span>palavra-passe</span>
      <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button type="submit" class="btn btn-perigo">Apagar a minha conta</button>
  </form>
</details>

<!-- Aviso curto (usado pelo envio da foto) -->
<p class="aviso vidro" id="aviso" role="status" aria-live="polite" hidden></p>
