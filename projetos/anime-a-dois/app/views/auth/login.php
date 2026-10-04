<?php /* Entrar. Variáveis: $registoAberto, $antigo, $flash */ ?>
<div class="topo">
  <span></span>
  <?php require __DIR__ . '/../layout/botao-tema.php'; ?>
</div>

<section class="auth">
  <!-- Logótipo, nome e frase, ao centro -->
  <div class="auth-cabeca">
    <img class="logo logo-grande" src="img/logo.svg" alt="" width="96" height="96">
    <h1 class="auth-titulo">anime a dois</h1>
    <p class="auth-sub">o que cada um já viu, lado a lado</p>
    <?php require __DIR__ . '/../layout/botao-instalar.php'; ?>
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

  <!-- Atalhos para as secções de baixo (a app é privada: só se cria conta com um convite) -->
  <p class="auth-rodape">
    <a href="#convite">Tenho um convite</a> · <a href="#apresentacao">O que é isto?</a>
    <?php if ($registoAberto): ?> · <a href="<?= e(url('auth', 'registo')) ?>">Criar conta</a><?php endif; ?>
  </p>
</section>

<!-- Apresentação: o que a app faz, com capturas reais (dados de teste) -->
<section class="apresentacao" id="apresentacao">
  <h2 class="apresentacao-titulo">Ver anime a dois, ou com amigos</h2>
  <p class="apresentacao-texto">Uma app privada para saberem onde cada um vai, sem spoilers e sem folhas de cálculo. Instala-se no telemóvel como uma app normal.</p>

  <!-- Carrossel horizontal (scroll-snap, sem JavaScript) -->
  <ul class="apresentacao-carrossel">
    <?php foreach ([
      ['inicio',       'O placar',        'Vês o teu progresso lado a lado com o de quem vê contigo, e o último episódio de cada um.'],
      ['serie',        'Episódio a episódio', 'Marcas o que viste e vês logo quem já viu, com fillers e recaps assinalados.'],
      ['estatisticas', 'Estatísticas',    'Ritmo semanal, arcos, horas vistas e quando acabas ao teu ritmo.'],
      ['amigos',       'Amigos',          'Convida com um link ou pelo utilizador. Cada série pode ser vista com pessoas diferentes.'],
      ['perfil',       'Perfis',          'Espreita o que os amigos estão a ver e junta as séries à tua biblioteca com um toque.'],
    ] as [$img, $titulo, $texto]): ?>
      <li class="apresentacao-cartao">
        <!-- Moldura de telemóvel à volta da captura -->
        <span class="telemovel"><img src="img/apresentacao/<?= e($img) ?>.jpg" alt="Captura do ecrã: <?= e($titulo) ?>" width="390" height="800" loading="lazy"></span>
        <h3><?= e($titulo) ?></h3>
        <p><?= e($texto) ?></p>
      </li>
    <?php endforeach; ?>
  </ul>

  <ul class="apresentacao-pontos">
    <li>Séries do MyAnimeList: pesquisa e adiciona qualquer anime.</li>
    <li>Avisos no telemóvel quando alguém marca um episódio ou comenta.</li>
    <li>Cada um tem a sua biblioteca e o seu progresso, mesmo nas séries que vêem juntos.</li>
    <li>Feita para um grupo pequeno de amigos: só se entra por convite.</li>
  </ul>
</section>

<!-- Criar conta: só com o link do convite que um amigo enviou (o código sozinho também serve) -->
<section class="convite-caixa" id="convite">
  <form class="form vidro" method="get" action="<?= e(url('auth', 'registo')) ?>">
    <input type="hidden" name="c" value="auth">
    <input type="hidden" name="a" value="registo">
    <h2 class="convite-titulo">Tenho um convite</h2>
    <label class="campo">
      <span>link do convite</span>
      <input type="text" name="convite" autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="cola aqui o link que te enviaram" required>
      <small>o convite é de uso único e dura 7 dias · pede um novo a quem te convidou</small>
    </label>
    <button class="btn" type="submit">Criar conta</button>
  </form>
</section>
