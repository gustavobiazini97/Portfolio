<?php
/* Estatísticas de uma série.
   Variáveis: $user, $companheiros (quem vê a série contigo; vazio se sozinho), $series, $serie, $est (ver Estatisticas::calcular) */
?>
<div class="topo">
  <a class="btn-redondo vidro" href="<?= e(url('home', 'index', ['serie' => $serie->slug])) ?>" aria-label="Voltar ao início">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
  </a>
  <h1 class="topo-meio">Estatísticas</h1>
  <?php require __DIR__ . '/../layout/botao-tema.php'; ?>
</div>

<!-- Separadores das séries (os mesmos da Home) -->
<nav class="separadores vidro" aria-label="Séries">
  <?php foreach ($series as $s): ?>
    <a class="separador" href="<?= e(url('estatisticas', 'index', ['serie' => $s->slug])) ?>"
       <?= $s->id === $serie->id ? 'aria-current="page"' : '' ?>><?= e($s->nomeCurto()) ?></a>
  <?php endforeach; ?>
</nav>

<!-- Um cartão por pessoa: episódios, horas e fillers -->
<section class="est-pessoas">
  <?php foreach ($est['pessoas'] as $p): ?>
    <div class="est-pessoa vidro est-<?= e($p['cor']) ?>">
      <div class="est-quem">
        <?php $avatarUser = $p['user']; $avatarCor = $p['cor']; $avatarExtra = 'avatar-mini'; require __DIR__ . '/../layout/avatar.php'; ?>
        <span><?= $p['cor'] === 'tu' ? 'Tu' : e($p['user']->nome) ?></span>
      </div>
      <p class="est-numero"><?= $p['vistos'] ?></p>
      <p class="est-legenda">episódios · <?= e($p['horas']) ?></p>
      <p class="est-legenda"><?= e($p['fillers']) ?></p>
    </div>
  <?php endforeach; ?>
</section>

<!-- Ritmo: episódios por semana; a altura de cada barra é uma classe nivel-0 a nivel-10 -->
<section class="painel vidro est-bloco">
  <div class="painel-titulo">
    <h2>Ritmo</h2>
    <span>episódios por semana</span>
  </div>
  <div class="ritmo" role="img" aria-label="Episódios por semana nas últimas <?= count($est['semanas']) ?> semanas">
    <?php foreach ($est['semanas'] as $s): ?>
      <div class="ritmo-semana">
        <div class="ritmo-barras">
          <span class="ritmo-barra ritmo-tu nivel-<?= $s['nivelTu'] ?>" title="Tu: <?= $s['tu'] ?>"></span>
          <?php foreach ($s['com'] as $i => $n): ?>
            <span class="ritmo-barra ritmo-par nivel-<?= $s['nivelCom'][$i] ?>" title="<?= e($companheiros[$i]->nome . ': ' . $n) ?>"></span>
          <?php endforeach; ?>
        </div>
        <span class="ritmo-rotulo"><?= e($s['rotulo']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="est-nota"><?= e($est['previsao'] ?? 'Marca uns episódios esta semana e a previsão aparece aqui.') ?></p>
</section>

<?php if ($est['arcos'] !== []): ?>
  <!-- Arcos: duas barras finas por arco e um selo (✓ os dois acabaram, ½ só um) -->
  <section class="painel vidro est-bloco">
    <div class="painel-titulo">
      <h2>Arcos</h2>
      <span class="legenda-pontos">
        <span><i class="ponto ponto-tu"></i>Tu</span>
        <?php foreach ($companheiros as $c): ?><span><i class="ponto ponto-par"></i><?= e($c->nome) ?></span><?php endforeach; ?>
      </span>
    </div>
    <ul class="arcos">
      <?php foreach ($est['arcos'] as $a): ?>
        <li class="arco <?= $a['filler'] ? 'arco-filler' : '' ?>">
          <span class="arco-texto">
            <span class="arco-nome"><?= e($a['nome']) ?></span>
            <span class="arco-eps"><?= $a['de'] ?>–<?= $a['ate'] ?></span>
          </span>
          <span class="arco-barras">
            <progress class="barra barra-fina vs-tu-cor" max="100" value="<?= $a['pctTu'] ?>" aria-label="Tu: <?= $a['pctTu'] ?>%"></progress>
            <?php foreach ($a['pctCom'] as $i => $pct): ?>
              <progress class="barra barra-fina vs-par-cor" max="100" value="<?= $pct ?>" aria-label="<?= e($companheiros[$i]->nome) ?>: <?= $pct ?>%"></progress>
            <?php endforeach; ?>
          </span>
          <span class="selo selo-<?= e($a['selo'] ?: 'nada') ?>"
                aria-label="<?= $a['selo'] === 'os-dois' ? 'Acabado pelos dois' : ($a['selo'] === '' ? 'Por acabar' : 'Acabado por um') ?>">
            <?= $a['selo'] === 'os-dois' ? '✓' : ($a['selo'] !== '' ? '½' : '') ?>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<!-- Curiosidades rápidas -->
<section class="est-pessoas">
  <div class="est-curio vidro">
    <p class="est-curio-num"><?= $est['comentarios'] ?></p>
    <p class="est-legenda"><?= $est['comentarios'] === 1 ? 'comentário' : 'comentários' ?> <?= $companheiros->isEmpty() ? 'teus' : ($companheiros->count() === 1 ? 'entre os dois' : 'entre todos') ?></p>
  </div>
  <div class="est-curio vidro">
    <p class="est-curio-num"><?= $est['recorde']['n'] ?? 0 ?></p>
    <p class="est-legenda">
      <?php if ($est['recorde'] === null): ?>
        episódios máximos num dia
      <?php else: ?>
        máximo num dia (<?= $est['recorde']['quem'] === 'tu' ? 'tu' : e($est['recorde']['quem']) ?>)
      <?php endif; ?>
    </p>
  </div>
</section>
