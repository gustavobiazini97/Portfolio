<?php
/* Dashboard.
   Variáveis: $user, $parceiro (ou null), $series, $serie, $ultimoPar (Episodio ou null), $meu, $dele, $resumo, $flash */
$comecou = $meu['vistos'] > 0 || $dele['vistos'] > 0;
?>
<div class="topo">
  <a class="marca" href="<?= e(url('home')) ?>">
    <img class="logo" src="img/logo.svg" alt="" width="34" height="34">
    anime a dois
  </a>
  <div class="topo-acoes">
    <?php require __DIR__ . '/../layout/botao-tema.php'; ?>
    <!-- O teu avatar abre o perfil (foto, nome, palavra-passe, sair) -->
    <a class="avatar-link" href="<?= e(url('perfil')) ?>" aria-label="Abrir o teu perfil">
      <?php $avatarUser = $user; $avatarCor = 'tu'; $avatarExtra = 'avatar-topo'; require __DIR__ . '/../layout/avatar.php'; ?>
    </a>
  </div>
</div>

<?php require __DIR__ . '/../layout/flash.php'; ?>

<!-- Separadores: a série ativa ganha a cor dela (ver [data-serie] no CSS) -->
<nav class="separadores vidro" aria-label="Séries">
  <?php foreach ($series as $s): ?>
    <a class="separador" href="<?= e(url('home', 'index', ['serie' => $s->slug])) ?>"
       <?= $s->id === $serie->id ? 'aria-current="page"' : '' ?>><?= e($s->nomeCurto()) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($parceiro && $ultimoPar): ?>
  <!-- Último episódio que o par viu (em qualquer série): abre a série nesse episódio -->
  <a class="painel vidro ultimo painel-link" href="<?= e(url('serie', 'ver', ['serie' => $ultimoPar->serie->slug, 'ep' => $ultimoPar->numero])) ?>">
    <?php $avatarUser = $parceiro; $avatarCor = 'par'; $avatarExtra = ''; require __DIR__ . '/../layout/avatar.php'; ?>
    <div class="ultimo-texto">
      <p><?= e($parceiro->nome . ' viu ' . tempo_relativo($ultimoPar->pivot->visto_em)) ?></p>
      <p><?= e($ultimoPar->serie->nomeCurto() . ' · episódio ' . $ultimoPar->numero) ?></p>
    </div>
    <span class="ultimo-num"><?= $ultimoPar->numero ?></span>
  </a>
<?php elseif ($parceiro): ?>
  <section class="painel vidro vazio">
    <p><?= e($parceiro->nome) ?> ainda não marcou nenhum episódio</p>
    <p>O último que marcar aparece aqui.</p>
  </section>
<?php else: ?>
  <section class="painel vidro vazio">
    <p>À espera do teu par</p>
    <p>Envia-lhe o link: quando criar a conta, aparece aqui e o registo fecha.</p>
    <!-- Partilha o link da app (menu do Android); sem partilha nativa, copia o link -->
    <button type="button" class="btn-texto vidro btn-convite" data-convidar="<?= e(url_absoluto()) ?>">
      Enviar convite
    </button>
  </section>
<?php endif; ?>

<?php if ($comecou): ?>
  <!-- Vs da série aberta: uma barra fina por pessoa; o cartão todo abre a série -->
  <a class="painel vidro painel-link" href="<?= e(url('serie', 'ver', ['serie' => $serie->slug])) ?>">
    <div class="painel-titulo">
      <h2><?= $parceiro ? e('Tu e ' . $parceiro->nome . ' em ' . $serie->nomeCurto()) : e('O teu progresso em ' . $serie->nomeCurto()) ?></h2>
      <span><?= $serie->total_episodios ?> ep. <svg class="painel-seta" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></span>
    </div>

    <div class="vs-linhas">
      <div class="vs-linha vs-tu">
        <div class="vs-rotulo"><span>Tu</span><span><b><?= $meu['posicao'] ?></b> · <?= $meu['pct'] ?>%</span></div>
        <progress class="barra" max="100" value="<?= $meu['pct'] ?>" aria-label="Progresso: <?= $meu['pct'] ?>%"></progress>
      </div>
      <?php if ($parceiro): ?>
        <div class="vs-linha vs-par">
          <div class="vs-rotulo"><span><?= e($parceiro->nome) ?></span><span><b><?= $dele['posicao'] ?></b> · <?= $dele['pct'] ?>%</span></div>
          <progress class="barra" max="100" value="<?= $dele['pct'] ?>" aria-label="Progresso: <?= $dele['pct'] ?>%"></progress>
        </div>
      <?php endif; ?>
    </div>

    <p class="vs-resumo"><?= e($resumo) ?></p>
  </a>
<?php else: ?>
  <section class="painel vidro vazio">
    <p>Ainda nenhum dos dois começou <?= e($serie->nome) ?></p>
    <p>Marca o primeiro episódio e o placar aparece aqui.</p>
  </section>
<?php endif; ?>

<a class="btn acao-fundo" href="<?= e(url('serie', 'ver', ['serie' => $serie->slug])) ?>">
  Ver episódios de <?= e($serie->nomeCurto()) ?>
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
</a>
