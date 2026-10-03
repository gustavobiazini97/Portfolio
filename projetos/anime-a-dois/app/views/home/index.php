<?php
/* Dashboard.
   Variáveis: $user, $parceiro (ou null), $series, $serie, $ultimoPar (Episodio ou null), $meu, $dele, $resumo, $flash */
$comecou = $meu['vistos'] > 0 || $dele['vistos'] > 0;
?>
<div class="topo">
  <a class="marca" href="<?= e(url('home')) ?>">
    <span class="logo" aria-hidden="true"><i></i><i></i></span>
    anime a dois
  </a>
  <div class="topo-acoes">
    <?php require __DIR__ . '/../layout/botao-tema.php'; ?>
    <form method="post" action="<?= e(url('auth', 'sair')) ?>">
      <?= csrf_campo() ?>
      <button type="submit" class="btn-redondo vidro" aria-label="Terminar sessão">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 16l-4-4 4-4M6 12h10"/></svg>
      </button>
    </form>
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
  <!-- Último episódio que o par viu (em qualquer série) -->
  <section class="painel vidro ultimo">
    <span class="avatar avatar-par" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($parceiro->nome, 0, 1))) ?></span>
    <div class="ultimo-texto">
      <p><?= e($parceiro->nome . ' viu ' . tempo_relativo($ultimoPar->pivot->visto_em)) ?></p>
      <p><?= e($ultimoPar->serie->nomeCurto() . ' · episódio ' . $ultimoPar->numero) ?></p>
    </div>
    <span class="ultimo-num"><?= $ultimoPar->numero ?></span>
  </section>
<?php elseif ($parceiro): ?>
  <section class="painel vidro vazio">
    <p><?= e($parceiro->nome) ?> ainda não marcou nenhum episódio</p>
    <p>O último que marcar aparece aqui.</p>
  </section>
<?php else: ?>
  <section class="painel vidro vazio">
    <p>À espera do teu par</p>
    <p>Quando a segunda conta for criada, aparece aqui e o registo fecha.</p>
  </section>
<?php endif; ?>

<?php if ($comecou): ?>
  <!-- Vs da série aberta: uma barra fina por pessoa -->
  <section class="painel vidro">
    <div class="painel-titulo">
      <h2><?= $parceiro ? e('Tu e ' . $parceiro->nome . ' em ' . $serie->nomeCurto()) : e('O teu progresso em ' . $serie->nomeCurto()) ?></h2>
      <span><?= $serie->total_episodios ?> ep.</span>
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
  </section>
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
