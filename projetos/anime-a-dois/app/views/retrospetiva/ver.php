<?php
/* Retrospetiva (stories, igual à app): ecrãs que avançam sozinhos — tocar à direita = seguinte,
   à esquerda = anterior, manter carregado = pausa — e um cartão final que se partilha como imagem.
   O JS (stories em app.js) trata da navegação e desenha a imagem a partir de data-cartao.
   Variáveis: $user, $r (Resumo::calcular()), $anual (true = o ano inteiro) */

$periodo = $r['periodo'];
$total   = $r['episodios'];
$quando  = $anual ? 'este ano' : 'este mês';
$doPeriodo = $anual ? 'do ano' : 'do mês';
$grandePeriodo = $anual ? (string) $periodo['ano'] : Resumo::MESES[$periodo['mes']];
$maiuscula = fn (string $t) => mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1);

// Um slide de texto: emoji, frase pequena, número/palavra gigante (e por baixo) e uma linha
$slide = function (string $emoji, string $pequeno, string $grande, string $texto, string $subGrande = ''): string {
    return '<section class="story" hidden>'
        . '<p class="story-emoji" aria-hidden="true">' . e($emoji) . '</p>'
        . '<p class="story-pequeno">' . e($pequeno) . '</p>'
        . '<p class="story-grande">' . e($grande) . '</p>'
        . ($subGrande !== '' ? '<p class="story-sub">' . e($subGrande) . '</p>' : '')
        . '<p class="story-texto">' . e($texto) . '</p>'
        . '</section>';
};

// Dados do cartão final (o JS desenha-o numa imagem para partilhar)
$linhas = [['Episódios', (string) $total], ['Tempo', $r['horas']]];
if ($r['top'] !== []) $linhas[] = ['Série favorita', $r['top'][0]['serie']->nomeCurto()];
$linhas[] = ['Maior sequência', $r['sequencia'] . ' dias'];
if ($r['melhorDia']) $linhas[] = ['Recorde num dia', $r['melhorDia']['episodios'] . ' ep.'];
if ($r['par']) $linhas[] = ['Vs ' . $r['par']['user']->nome, $total . ' × ' . $r['par']['episodios']];
$cartao = ['titulo' => $anual ? 'O meu ' . $periodo['ano'] : 'O meu ' . $periodo['nome'], 'linhas' => $linhas];
?>
<div class="stories" id="stories" data-sair="<?= e(url('retrospetiva')) ?>">
  <!-- Barrinhas de progresso (uma por slide) e fechar -->
  <div class="stories-topo">
    <div class="stories-barras" id="stories-barras"></div>
    <a class="stories-fechar" href="<?= e(url('retrospetiva')) ?>" aria-label="Fechar">✕</a>
  </div>

  <div class="stories-palco" id="stories-palco">
    <?php if ($total === 0): ?>
      <?= $slide('🫥', $periodo['nome'], 'Nada por aqui', 'Ainda não marcaste episódios ' . $quando . '. Bora a isso!') ?>
    <?php else: ?>
      <?php // 1. Abertura ?>
      <?= $slide('📼', 'A tua retrospetiva', $maiuscula($grandePeriodo), $anual ? 'Um ano inteiro de anime. Vamos a isto?' : 'O que viste em ' . $periodo['nome'] . '.') ?>

      <?php // 2. Episódios e horas (e comparação com o período anterior)
      $var = $r['anterior']['variacao'];
      $comparacao = $var === null ? '' : ' · ' . ($var >= 0 ? '+' : '') . $var . '% do que ' . ($anual ? 'no ano anterior' : 'no mês anterior'); ?>
      <?= $slide('🎬', 'Viste', (string) $total, $r['horas'] . ' de anime' . $comparacao, $total === 1 ? 'episódio' : 'episódios') ?>

      <?php if ($r['top'] !== []): $s = $r['top'][0]['serie']; ?>
        <!-- 3. A série do período (capa) e as seguintes -->
        <section class="story" hidden>
          <p class="story-pequeno">A tua série <?= e($doPeriodo) ?></p>
          <?php if ($s->capa): ?>
            <img class="story-capa" src="<?= e($s->capa) ?>" alt="" referrerpolicy="no-referrer">
          <?php else: ?>
            <span class="story-capa capa-sem" data-acento="<?= e((string) $s->acento) ?>"><?= e(mb_strtoupper(mb_substr($s->nomeCurto(), 0, 2))) ?></span>
          <?php endif; ?>
          <p class="story-medio"><?= e($s->nomeCurto()) ?></p>
          <p class="story-texto"><?= e(plural($r['top'][0]['episodios'], 'episódio', 'episódios')) ?></p>
          <?php foreach (array_slice($r['top'], 1) as $t): ?>
            <p class="story-texto story-outra"><?= e($t['serie']->nomeCurto() . ' · ' . $t['episodios'] . ' ep.') ?></p>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>

      <?php if ($anual && $r['porMes'] !== null): ?>
        <!-- 4. (Ano) episódios por mês: as alturas vêm de data-valor (o JS aplica-as) -->
        <section class="story" hidden>
          <p class="story-pequeno">Mês a mês</p>
          <div class="story-meses" data-maximo="<?= max(1, max(array_column($r['porMes'], 'episodios'))) ?>">
            <?php foreach ($r['porMes'] as $m): ?>
              <div class="story-mes<?= $r['melhorMes'] && $r['melhorMes']['mes'] === $m['mes'] ? ' melhor' : '' ?>">
                <i data-valor="<?= (int) $m['episodios'] ?>"></i>
                <span><?= e(mb_substr($m['nome'], 0, 1)) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if ($r['melhorMes']): ?>
            <p class="story-texto">O teu mês: <?= e($r['melhorMes']['nome']) ?> (<?= (int) $r['melhorMes']['episodios'] ?> ep.)</p>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php if ($r['melhorDia']): ?>
        <!-- 5. Melhor dia -->
        <?= $slide($r['melhorDia']['episodios'] >= 10 ? '🍿' : '📆', 'O teu dia de maratona', (string) $r['melhorDia']['episodios'], $maiuscula($r['melhorDia']['texto']), 'episódios num dia') ?>
      <?php endif; ?>

      <?php if ($r['momento'] !== null): ?>
        <!-- 6. Hora preferida -->
        <?= $slide(['madrugada' => '🦉', 'manhã' => '☀️', 'tarde' => '🌤️'][$r['momento']] ?? '🌙', 'Gostas de ver anime',
                   $r['momento'] === 'madrugada' ? 'de madrugada' : 'à ' . $r['momento'],
                   'A tua hora: ' . $r['hora'] . 'h' . ($r['diaSemana'] ? ' · o teu dia: ' . $r['diaSemana'] : '')) ?>
      <?php endif; ?>

      <?php // 7. Constância ?>
      <?= $slide($r['sequencia'] >= 7 ? '🔥' : '📅', 'A tua maior sequência', (string) $r['sequencia'],
                 'Viste anime em ' . plural($r['diasAtivos'], 'dia', 'dias') . ' ' . $quando . '.', $r['sequencia'] === 1 ? 'dia seguido' : 'dias seguidos') ?>

      <?php if ($r['par']): $doPar = (int) $r['par']['episodios']; ?>
        <!-- 8. Tu vs o par -->
        <section class="story" hidden>
          <p class="story-pequeno">Tu vs <?= e($r['par']['user']->nome) ?></p>
          <div class="story-vs">
            <div><b class="cor-tu"><?= $total ?></b><span>tu</span></div>
            <div><b class="cor-par"><?= $doPar ?></b><span><?= e($r['par']['user']->nome) ?></span></div>
          </div>
          <p class="story-texto"><?= $total === $doPar ? 'Empate técnico!' : ($total > $doPar ? 'Foste tu quem viu mais 🏆' : e($r['par']['user']->nome) . ' viu mais desta vez 👀') ?></p>
        </section>
      <?php endif; ?>

      <?php // 9. Extras: séries acabadas, fillers, comentários
      $extras = [];
      if ($r['acabadas'] !== []) $extras[] = implode(', ', array_map(fn ($s) => $s->nomeCurto(), $r['acabadas']));
      $extras[] = $r['fillers'] . ' fillers vistos';
      $extras[] = $r['comentarios'] . ' comentários escritos'; ?>
      <?= $slide($r['acabadas'] !== [] ? '🏁' : '✨', 'E ainda…',
                 $r['acabadas'] !== [] ? plural(count($r['acabadas']), 'série acabada', 'séries acabadas') : 'mais umas coisas',
                 implode(' · ', $extras)) ?>

      <!-- 10. Cartão final (partilhável): o JS desenha a imagem a partir de data-cartao -->
      <section class="story story-final" hidden>
        <div class="retro-cartao" id="retro-cartao" data-cartao="<?= e(json_encode($cartao, JSON_UNESCAPED_UNICODE)) ?>">
          <p class="retro-cartao-marca">anime a dois</p>
          <p class="retro-cartao-titulo"><?= e($cartao['titulo']) ?></p>
          <?php foreach ($linhas as [$a, $b]): ?>
            <p class="retro-cartao-linha"><span><?= e($a) ?></span><b><?= e($b) ?></b></p>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn retro-partilhar" id="retro-partilhar">Partilhar imagem</button>
      </section>
    <?php endif; ?>
  </div>
</div>
