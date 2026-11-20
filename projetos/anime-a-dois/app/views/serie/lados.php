<?php
/* Colunas laterais da página da série (o CSS só as mostra em ecrãs largos; no telemóvel ficam escondidas).
   Usa as variáveis de serie/ver.php: $serie, $user, $companheiros, $meu, $episodios, $fillers, $lado, $avatarHtml, $avCom
   e $lado = estado, previsao, arco, distancias, registos, comentarios (ver SerieController::ver e app/models/LadoSerie.php).
   Tocar num registo ou comentário salta para esse episódio (js/app.js: [data-ir-ep]). */

// Números da série para ti
$minEp      = (int) ($serie->minutos_ep ?: Resumo::MINUTOS_EP);
$porVer     = max(0, (int) $serie->total_episodios - $meu['vistos']);
$fillersFal = count(array_filter($episodios, fn ($ep) => $ep['filler'] && !$ep['tu']));   // fillers que ainda não viste
// 7,5 h com uma casa decimal até 10 h; depois horas redondas (como nas Estatísticas)
$horas = function (int $eps) use ($minEp): string {
    $h = $eps * $minEp / 60;
    return ($h < 10 ? str_replace('.', ',', (string) round($h, 1)) : (string) round($h)) . ' h';
};
// Linha por baixo do nome: ano(s), tipo e episódios (só o que a série tiver)
$sub = implode(' · ', array_filter([$serie->anos, $serie->tipo, plural((int) $serie->total_episodios, 'episódio', 'episódios')]));
?>

<!-- ===== Esquerda: a série ===== -->
<aside class="lado lado-esq" aria-label="Sobre <?= e($serie->nome) ?>">
  <section class="lado-bloco vidro lado-serie">
    <?php if ($serie->capa): ?>
      <img class="lado-capa" src="<?= e($serie->capa) ?>" alt="" referrerpolicy="no-referrer" loading="lazy">
    <?php endif; ?>
    <h2 class="lado-nome"><?= e($serie->nome) ?></h2>
    <p class="lado-sub"><?= e($sub) ?></p>
    <div class="lado-chips">
      <span class="chip"><?= e($lado['estado']) ?></span>
      <?php if ($serie->em_emissao): ?><span class="chip">em emissão</span><?php endif; ?>
    </div>
    <?php if ($companheiros->count()): ?>
      <!-- Com quem vês esta série -->
      <p class="lado-com">
        <span class="ep-avs"><?php foreach ($avCom as $av): ?><span class="ep-av v"><?= $av ?></span><?php endforeach; ?></span>
        <span>Vês com <?= e(count($nomes) === 1 ? $nomes[0] : implode(', ', array_slice($nomes, 0, -1)) . ' e ' . end($nomes)) ?></span>
      </p>
    <?php endif; ?>
  </section>

  <section class="lado-bloco vidro">
    <h3 class="lado-titulo">Os teus números</h3>
    <dl class="lado-numeros">
      <div><dt>Vistos</dt><dd><?= $meu['vistos'] ?> <small>/ <?= (int) $serie->total_episodios ?></small></dd></div>
      <div><dt>Por ver</dt><dd><?= $porVer ?></dd></div>
      <div><dt>Horas vistas</dt><dd><?= e($horas($meu['vistos'])) ?></dd></div>
      <div><dt>Faltam</dt><dd><?= e($horas($porVer)) ?></dd></div>
      <?php if ($fillers > 0): ?>
        <div><dt>Fillers por ver</dt><dd><?= $fillersFal ?> <small>/ <?= (int) $fillers ?></small></dd></div>
      <?php endif; ?>
    </dl>
    <?php if ($lado['previsao']): ?><p class="lado-nota"><?= e($lado['previsao']) ?></p><?php endif; ?>
  </section>

  <?php if ($lado['arco']): $arco = $lado['arco']; ?>
    <!-- Arco do teu próximo episódio -->
    <section class="lado-bloco vidro">
      <h3 class="lado-titulo">Arco em que vais</h3>
      <p class="lado-arco"><?= e($arco['nome']) ?></p>
      <p class="lado-sub">Episódios <?= $arco['de'] ?>–<?= $arco['ate'] ?></p>
      <progress class="barra vs-tu-cor lado-barra" max="100" value="<?= (int) $arco['pctTu'] ?>"><?= (int) $arco['pctTu'] ?>%</progress>
      <p class="lado-sub"><?= (int) $arco['pctTu'] ?>% visto<?php foreach ($companheiros as $i => $c): ?> · <?= e($c->nome) ?> <?= (int) $arco['pctCom'][$i] ?>%<?php endforeach; ?></p>
    </section>
  <?php endif; ?>
</aside>

<!-- ===== Direita: o que tem acontecido ===== -->
<aside class="lado lado-dir" aria-label="Atividade em <?= e($serie->nome) ?>">
  <?php if ($lado['distancias']): ?>
    <section class="lado-bloco vidro">
      <?php foreach ($lado['distancias'] as $frase): ?><p class="lado-distancia"><?= e($frase) ?></p><?php endforeach; ?>
    </section>
  <?php endif; ?>

  <section class="lado-bloco vidro">
    <h3 class="lado-titulo">Últimos registos</h3>
    <?php if ($lado['registos'] === []): ?>
      <p class="lado-vazio">Ainda ninguém marcou episódios desta série.</p>
    <?php else: ?>
      <ul class="lado-lista">
        <?php foreach ($lado['registos'] as $r): ?>
          <li>
            <!-- Salta para o último episódio desta sessão -->
            <button type="button" class="lado-item" data-ir-ep="<?= $r['ate'] ?>">
              <?= $avatarHtml($r['quem'], $r['cor']) ?>
              <span class="lado-item-txt"><?= e($r['texto']) ?><small><?= e($r['quando']) ?></small></span>
            </button>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="lado-bloco vidro">
    <h3 class="lado-titulo">Últimos comentários</h3>
    <?php if ($lado['comentarios'] === []): ?>
      <p class="lado-vazio">Ainda não há comentários nesta série.</p>
    <?php else: ?>
      <ul class="lado-lista">
        <?php foreach ($lado['comentarios'] as $c): ?>
          <li>
            <!-- Salta para o episódio e abre os comentários dele -->
            <button type="button" class="lado-item" data-ir-ep="<?= $c['numero'] ?>" data-abrir-coment>
              <?= $avatarHtml($c['quem'], $c['cor']) ?>
              <span class="lado-item-txt">
                <b><?= e($c['cor'] === 'tu' ? 'Tu' : $c['quem']->nome) ?> · ep. <?= $c['numero'] ?></b>
                <span class="lado-coment"><?= e($c['texto']) ?></span>
                <small><?= e($c['quando']) ?></small>
              </span>
            </button>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</aside>
