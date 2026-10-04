<?php
/* Página da série.
   Variáveis: $serie, $user, $parceiro (ou null), $fillers, $meu, $dele, $episodios, $inicial,
              $sincronizar (o browser vai buscar fillers ou episódios novos), $flash */
$nomePar   = $parceiro ? $parceiro->nome : null;

// Avatares (foto ou inicial) gerados uma vez e repetidos em todos os cartões
$avatarHtml = function ($pessoa, string $cor): string {
    ob_start();
    $avatarUser = $pessoa; $avatarCor = $cor; $avatarExtra = 'avatar-mini';
    require __DIR__ . '/../layout/avatar.php';
    return ob_get_clean();
};
$avTu  = $avatarHtml($user, 'tu');
$avPar = $parceiro ? $avatarHtml($parceiro, 'par') : '';

// Frase curta por baixo dos avatares (o js/app.js tem a mesma lógica)
$rotulo = function (bool $tu, bool $par) use ($nomePar): string {
    if ($nomePar === null) return $tu ? 'visto' : 'por ver';
    if ($tu && $par)       return 'os dois viram';
    if ($tu)               return 'falta ' . $nomePar;
    if ($par)              return $nomePar . ' já viu';
    return 'por ver';
};
$epInicial = $episodios[$inicial - 1] ?? $episodios[0];   // estado inicial do painel e do botão (funciona sem JS)
$temRecaps = in_array(true, array_column($episodios, 'recap'), true);   // a legenda só fala em recap se houver
?>
<div class="topo">
  <a class="btn-redondo vidro" href="<?= e(url('home', 'index', ['serie' => $serie->slug])) ?>" aria-label="Voltar ao início">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
  </a>
  <h1 class="topo-meio"><?= e($serie->nome) ?></h1>
  <?php require __DIR__ . '/../layout/botao-tema.php'; ?>
</div>

<?php require __DIR__ . '/../layout/flash.php'; ?>

<!-- Mapa: um risco por episódio, uma linha por pessoa.
     As duas linhas partilham a MESMA grelha (nome | barra | %), por isso as barras têm sempre
     o mesmo comprimento, seja qual for o tamanho dos nomes (o JS atualiza a linha "Tu" ao marcar). -->
<section class="mapa vidro" aria-label="Episódios vistos por cada um">
  <div class="mapa-info">
    <span><?= e($serie->total_episodios . ' episódios') ?></span>
    <span class="mapa-legenda"><i class="leg-visto"></i>visto <i class="leg-filler"></i>filler<?= $temRecaps ? '/recap' : '' ?></span>
  </div>

  <div class="mapa-grade">
    <span class="mapa-quem"><?= $avTu ?><span class="mapa-nome">Tu</span></span>
    <div class="riscos riscos-tu" id="riscos-tu" aria-hidden="true">
      <?php foreach ($episodios as $ep): ?><i class="<?= ($ep['tu'] ? 'v' : '') . ($ep['filler'] || $ep['recap'] ? ' f' : '') ?>"></i><?php endforeach; ?>
    </div>
    <span class="mapa-pct pct-tu" id="pct-tu"><?= $meu['pct'] ?>%</span>

    <?php if ($parceiro): ?>
      <span class="mapa-quem"><?= $avPar ?><span class="mapa-nome"><?= e($nomePar) ?></span></span>
      <div class="riscos riscos-par" aria-hidden="true">
        <?php foreach ($episodios as $ep): ?><i class="<?= ($ep['par'] ? 'v' : '') . ($ep['filler'] || $ep['recap'] ? ' f' : '') ?>"></i><?php endforeach; ?>
      </div>
      <span class="mapa-pct pct-par"><?= $dele['pct'] ?>%</span>
    <?php endif; ?>

    <!-- Régua por baixo das barras (só na coluna do meio) -->
    <div class="mapa-escala" aria-hidden="true">
      <span>1</span><span><?= intdiv($serie->total_episodios, 2) ?></span><span><?= $serie->total_episodios ?></span>
    </div>
  </div>
</section>

<!-- Pista: desliza para os lados; o cartão do centro é o selecionado
     (em "Selecionar vários", tocar num cartão junta-o à seleção) -->
<div class="pista" aria-label="Episódios de <?= e($serie->nome) ?>" data-par-nome="<?= e($nomePar ?? '') ?>">
  <?php foreach ($episodios as $ep): ?>
    <button type="button"
            class="ep vidro<?= $ep['tu'] ? ' visto' : '' ?><?= $ep['par'] ? ' par-viu' : '' ?><?= $ep['numero'] === $inicial ? ' ativo' : '' ?>"
            data-id="<?= $ep['id'] ?>" data-n="<?= $ep['numero'] ?>"
            data-tu="<?= $ep['tu'] ? '1' : '0' ?>" data-par="<?= $ep['par'] ? '1' : '0' ?>"
            data-filler="<?= $ep['filler'] ? '1' : '0' ?>" data-recap="<?= $ep['recap'] ? '1' : '0' ?>" data-titulo="<?= e($ep['titulo'] ?? '') ?>"
            data-coment="<?= $ep['coment'] ?>"
            <?= $ep['numero'] === $inicial ? 'data-inicial' : '' ?>
            aria-label="Episódio <?= $ep['numero'] ?>">
      <span class="ep-cima">
        <?php if ($ep['filler']): ?>
          <span class="etiqueta-filler">filler</span>
        <?php elseif ($ep['recap']): ?>
          <span class="etiqueta-filler">recap</span>
        <?php else: ?>
          <span>episódio</span>
        <?php endif; ?>
        <!-- Balão com o número de comentários (só se houver) -->
        <span class="ep-coment"<?= $ep['coment'] ? '' : ' hidden' ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 5h14v10H9l-4 4z"/></svg><span><?= $ep['coment'] ?></span></span>
        <!-- Visto de seleção: só aparece no modo "Selecionar vários" -->
        <span class="ep-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg></span>
      </span>
      <span class="ep-meio">
        <span class="ep-num"><?= $ep['numero'] ?></span>
        <span class="ep-titulo"><?= e($ep['titulo'] ?? '') ?></span>
      </span>
      <!-- Quem viu: avatares a cores = viu, esbatidos = ainda não -->
      <span class="ep-quem">
        <span class="ep-avs">
          <span class="ep-av ep-av-tu<?= $ep['tu'] ? ' v' : '' ?>"><?= $avTu ?></span>
          <?php if ($parceiro): ?><span class="ep-av ep-av-par<?= $ep['par'] ? ' v' : '' ?>"><?= $avPar ?></span><?php endif; ?>
        </span>
        <span class="ep-rotulo"><?= e($rotulo($ep['tu'], $ep['par'])) ?></span>
      </span>
    </button>
  <?php endforeach; ?>
</div>

<!-- Painel do episódio ao centro (o JS mantém-no atualizado) -->
<section class="detalhe vidro" id="detalhe" aria-live="polite">
  <div class="detalhe-cima">
    <h2 id="detalhe-titulo">Episódio <?= $epInicial['numero'] ?></h2>
    <?php $tipoInicial = $epInicial['filler'] ? 'filler' : ($epInicial['recap'] ? 'recap' : 'canónico'); ?>
    <span class="chip<?= $tipoInicial !== 'canónico' ? ' etiqueta-filler' : '' ?>" id="detalhe-tipo"><?= $tipoInicial ?></span>
  </div>
  <p class="detalhe-nome" id="detalhe-nome"><?= e($epInicial['titulo'] ?? '') ?></p>
  <div class="detalhe-estados">
    <span class="detalhe-pessoa">
      <span class="ep-av<?= $epInicial['tu'] ? ' v' : '' ?>" id="detalhe-av-tu"><?= $avTu ?></span>
      <span>Tu<br><b id="detalhe-tu"><?= $epInicial['tu'] ? 'visto' : 'por ver' ?></b></span>
    </span>
    <?php if ($parceiro): ?>
      <span class="detalhe-pessoa">
        <span class="ep-av<?= $epInicial['par'] ? ' v' : '' ?>" id="detalhe-av-par"><?= $avPar ?></span>
        <span><?= e($nomePar) ?><br><b id="detalhe-par"><?= $epInicial['par'] ? 'visto' : 'por ver' ?></b></span>
      </span>
    <?php endif; ?>
  </div>
</section>

<!-- Ações: um episódio (o do centro) ou vários (modo seleção) -->
<form id="form-marcar" class="acoes" method="post" action="<?= e(url('serie', 'marcar')) ?>" data-serie="<?= e($serie->slug) ?>">
  <?= csrf_campo() ?>
  <input type="hidden" name="serie" value="<?= e($serie->slug) ?>">
  <input type="hidden" name="acao" value="alternar">
  <input type="hidden" name="episodio_ids[]" value="<?= $epInicial['id'] ?>">

  <div class="acoes-linha">
    <button type="button" class="btn-texto vidro" id="btn-modo">Selecionar vários</button>
    <span class="acoes-info" id="acoes-info"></span>
    <!-- Abre a folha de comentários do episódio ao centro -->
    <button type="button" class="btn-texto vidro btn-coment" id="btn-coment"
            data-url="<?= e(url('serie', 'comentarios')) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 5h14v10H9l-4 4z"/></svg>
      <span id="btn-coment-txt">Comentários</span>
    </button>
  </div>
  <button type="submit" class="btn<?= $epInicial['tu'] ? ' btn-secundario' : '' ?>" id="btn-marcar">
    <?= $epInicial['tu'] ? 'Desmarcar episódio ' . $epInicial['numero'] : 'Marcar episódio ' . $epInicial['numero'] . ' como visto' ?>
  </button>
</form>

<?php if ($sincronizar): ?>
  <!-- Faltam fillers ou há episódios novos: o js/app.js vai buscá-los às APIs e manda-os ao servidor -->
  <span hidden id="sincronizar" data-serie="<?= e($serie->slug) ?>" data-mal="<?= (int) $serie->mal_id ?>"
        data-em-emissao="<?= $serie->em_emissao ? '1' : '0' ?>" data-url="<?= e(url('biblioteca', 'atualizar')) ?>"></span>
<?php endif; ?>

<!-- Aviso curto depois de marcar (substitui o recarregar da página) -->
<p class="aviso vidro" id="aviso" role="status" aria-live="polite" hidden></p>

<!-- Folha de comentários (abre por cima, a partir de baixo) -->
<dialog class="folha vidro" id="folha-coment" aria-labelledby="folha-titulo"
        data-url-comentar="<?= e(url('serie', 'comentar')) ?>" data-url-apagar="<?= e(url('serie', 'apagarComentario')) ?>">
  <div class="folha-cima">
    <h2 id="folha-titulo">Comentários</h2>
    <button type="button" class="btn-redondo vidro" id="folha-fechar" aria-label="Fechar comentários">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
    </button>
  </div>

  <!-- Lista preenchida pelo js/app.js (texto sempre inserido como texto, nunca como HTML) -->
  <ul class="folha-lista" id="folha-lista"></ul>
  <p class="folha-vazio" id="folha-vazio" hidden>Ainda ninguém comentou este episódio. Começa tu.</p>

  <form class="folha-form" id="folha-form">
    <textarea name="texto" id="folha-texto" rows="2" maxlength="500" placeholder="O que achaste deste episódio?" required></textarea>
    <button type="submit" class="btn folha-enviar" aria-label="Enviar comentário">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h13M13 6l6 6-6 6"/></svg>
    </button>
  </form>
</dialog>
