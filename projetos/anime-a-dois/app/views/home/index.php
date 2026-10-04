<?php
/* Início.
   Variáveis: $user, $parceiro (ou null), $biblioteca (lista de ['serie','tu','par']), $propostas,
              $serie (aberta, ou null se a biblioteca estiver vazia), $ultimoPar (Episodio ou null),
              $meu, $dele, $resumo, $podeRemover, $flash */
$comecou = $serie && ($meu['vistos'] > 0 || $dele['vistos'] > 0);
$nomePar = $parceiro ? $parceiro->nome : null;

// Capa de uma série: a imagem do MyAnimeList ou, sem ela, as iniciais sobre a cor da série
$capa = function (Serie $s, string $classe): string {
    if ($s->capa) {
        return '<img class="' . e($classe) . '" src="' . e($s->capa) . '" alt="" loading="lazy" referrerpolicy="no-referrer">';
    }
    $iniciais = mb_strtoupper(mb_substr($s->nomeCurto(), 0, 2));
    return '<span class="' . e($classe) . ' capa-sem" data-serie="' . e($s->slug) . '" data-acento="' . e((string) $s->acento) . '">' . e($iniciais) . '</span>';
};
?>
<div class="topo">
  <a class="marca" href="<?= e(url('home')) ?>">
    <img class="logo" src="img/logo.svg" alt="" width="34" height="34">
    anime a dois
  </a>
  <div class="topo-acoes">
    <?php if ($serie): ?>
      <!-- Estatísticas da série aberta -->
      <a class="btn-redondo vidro" href="<?= e(url('estatisticas', 'index', ['serie' => $serie->slug])) ?>" aria-label="Ver estatísticas">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 20V12M12 20V5M19 20v-5"/></svg>
      </a>
    <?php endif; ?>
    <?php require __DIR__ . '/../layout/botao-tema.php'; ?>
    <!-- O teu avatar abre o perfil (foto, nome, palavra-passe, sair) -->
    <a class="avatar-link" href="<?= e(url('perfil')) ?>" aria-label="Abrir o teu perfil">
      <?php $avatarUser = $user; $avatarCor = 'tu'; $avatarExtra = 'avatar-topo'; require __DIR__ . '/../layout/avatar.php'; ?>
    </a>
  </div>
</div>

<?php require __DIR__ . '/../layout/flash.php'; ?>

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

<!-- Biblioteca: fila de capas que desliza para os lados, com as duas barrinhas por baixo.
     A ver → em pausa → acabado; tocar numa capa abre-a aqui em baixo (Vs e estado). -->
<section class="biblioteca" aria-labelledby="biblioteca-titulo">
  <div class="secao-cima">
    <h2 id="biblioteca-titulo">Biblioteca</h2>
    <button type="button" class="btn-redondo vidro btn-adicionar" id="btn-adicionar" aria-label="Adicionar série">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
    </button>
  </div>

  <?php if ($biblioteca === []): ?>
    <div class="painel vidro vazio">
      <p>A biblioteca está vazia</p>
      <p>Toca no + e procura o próximo anime.</p>
    </div>
  <?php else: ?>
    <div class="fila" id="fila">
      <?php foreach ($biblioteca as $item): $s = $item['serie']; ?>
        <!-- data-serie/data-acento dão a cor da série só a este cartão (aro da capa e fundo sem imagem) -->
        <a class="capa-item capa-<?= e($s->estado) ?>" href="<?= e(url('home', 'index', ['serie' => $s->slug])) ?>"
           data-serie="<?= e($s->slug) ?>" data-acento="<?= e((string) $s->acento) ?>"
           <?= $serie && $s->id === $serie->id ? 'aria-current="page"' : '' ?>>
          <span class="capa-moldura">
            <?= $capa($s, 'capa') ?>
            <?php if ($s->estado !== 'a_ver'): ?>
              <span class="capa-estado"><?= $s->estado === 'acabado' ? '✓ acabado' : 'em pausa' ?></span>
            <?php endif; ?>
          </span>
          <span class="capa-nome"><?= e($s->nomeCurto()) ?></span>
          <span class="capa-barras">
            <progress class="barra barra-fina vs-tu-cor" max="100" value="<?= $item['tu'] ?>" aria-label="Tu: <?= $item['tu'] ?>%"></progress>
            <?php if ($parceiro): ?>
              <progress class="barra barra-fina vs-par-cor" max="100" value="<?= $item['par'] ?>" aria-label="<?= e($nomePar) ?>: <?= $item['par'] ?>%"></progress>
            <?php endif; ?>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php if (count($propostas) > 0): ?>
  <!-- Quero ver contigo: séries que um propõe ao outro; aceitar passa-as para "a ver" -->
  <section class="painel vidro propostas" id="propostas">
    <div class="painel-titulo">
      <h2>Quero ver contigo</h2>
      <span><?= plural(count($propostas), 'proposta', 'propostas') ?></span>
    </div>
    <ul class="propostas-lista">
      <?php foreach ($propostas as $p): $minha = (int) $p->adicionada_por === $user->id; ?>
        <li class="proposta">
          <?= $capa($p, 'proposta-capa') ?>
          <div class="proposta-texto">
            <p class="proposta-nome"><?= e($p->nome) ?></p>
            <p class="proposta-info">
              <?= e(implode(' · ', array_filter([
                  $minha ? 'propuseste tu' : ($p->autor ? $p->autor->nome . ' propôs' : null),
                  plural((int) $p->total_episodios, 'ep.', 'ep.'),
                  $p->tipo,
                  $p->anos,
              ]))) ?>
            </p>
            <div class="proposta-acoes">
              <?php if ($minha): ?>
                <span class="proposta-espera">à espera de <?= e($nomePar ?? 'o teu par') ?></span>
                <form method="post" action="<?= e(url('biblioteca', 'remover')) ?>" data-confirmar="Retirar a proposta de <?= e($p->nomeCurto()) ?>?">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="serie" value="<?= e($p->slug) ?>">
                  <button type="submit" class="link-suave">Cancelar</button>
                </form>
              <?php else: ?>
                <form method="post" action="<?= e(url('biblioteca', 'aceitar')) ?>">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="serie" value="<?= e($p->slug) ?>">
                  <button type="submit" class="btn-pequeno">Bora ver</button>
                </form>
                <form method="post" action="<?= e(url('biblioteca', 'remover')) ?>" data-confirmar="Recusar <?= e($p->nomeCurto()) ?>?">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="serie" value="<?= e($p->slug) ?>">
                  <button type="submit" class="link-suave">Agora não</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<?php if ($serie): ?>
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
      <p>Ainda nenhum dos dois começou <?= e($serie->nomeCurto()) ?></p>
      <p>Marca o primeiro episódio e o placar aparece aqui.</p>
    </section>
  <?php endif; ?>

  <!-- Estado da série aberta: os acabados vão para o fim da fila -->
  <form class="estados vidro" method="post" action="<?= e(url('biblioteca', 'estado')) ?>" aria-label="Estado de <?= e($serie->nomeCurto()) ?>">
    <?= csrf_campo() ?>
    <input type="hidden" name="serie" value="<?= e($serie->slug) ?>">
    <?php foreach (Serie::ESTADOS as $valor => $texto): ?>
      <button type="submit" name="estado" value="<?= e($valor) ?>" class="estado"
              aria-pressed="<?= $serie->estado === $valor ? 'true' : 'false' ?>"><?= e($texto) ?></button>
    <?php endforeach; ?>
  </form>

  <?php if ($podeRemover): ?>
    <!-- Só aparece enquanto ninguém marcou episódios desta série: nada se perde -->
    <form class="remover" method="post" action="<?= e(url('biblioteca', 'remover')) ?>" data-confirmar="Tirar <?= e($serie->nomeCurto()) ?> da biblioteca?">
      <?= csrf_campo() ?>
      <input type="hidden" name="serie" value="<?= e($serie->slug) ?>">
      <button type="submit" class="link-suave">Tirar da biblioteca</button>
    </form>
  <?php endif; ?>

  <a class="btn acao-fundo" href="<?= e(url('serie', 'ver', ['serie' => $serie->slug])) ?>">
    Ver episódios de <?= e($serie->nomeCurto()) ?>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
  </a>
<?php endif; ?>

<!-- Folha "Adicionar série": pesquisa no MyAnimeList (o js/app.js preenche os resultados) -->
<dialog class="folha vidro folha-adicionar" id="folha-adicionar" aria-labelledby="adicionar-titulo"
        data-url-pesquisar="<?= e(url('biblioteca', 'pesquisar')) ?>"
        data-url-adicionar="<?= e(url('biblioteca', 'adicionar')) ?>"
        data-par-nome="<?= e($nomePar ?? '') ?>">
  <div class="folha-cima">
    <h2 id="adicionar-titulo">Adicionar série</h2>
    <button type="button" class="btn-redondo vidro" id="adicionar-fechar" aria-label="Fechar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
    </button>
  </div>

  <?= csrf_campo() ?>
  <label class="pesquisa">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4 4"/></svg>
    <input type="search" id="pesquisa-texto" placeholder="Procura um anime (ex.: Frieren)" autocomplete="off" enterkeyhint="search" maxlength="80">
  </label>
  <p class="pesquisa-estado" id="pesquisa-estado" aria-live="polite">Os dados (episódios, fillers e capas) vêm do MyAnimeList.</p>

  <!-- Resultados: o texto entra sempre com textContent, nunca como HTML -->
  <ul class="resultados" id="resultados"></ul>
</dialog>

<!-- Aviso curto (adicionar, mudar estado) -->
<p class="aviso vidro" id="aviso" role="status" aria-live="polite" hidden></p>
