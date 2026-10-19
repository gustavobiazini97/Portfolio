<?php
/* Perfil (igual ao da app): o teu, o do teu par ou o de um amigo.
   Variáveis: $user (tu), $pessoa, $proprio (é o teu), $ehPar, $ultimo (Episodio ou null), $soJuntos (ele só mostra o que vê contigo),
              $numeros (episodios, horas, series, acabadas, seriesJuntos, episodiosJuntos),
              $medalhas (obtidas, total, lista, destaque — null com "só juntos"),
              $biblioteca (['serie','estado','tu','companheiros','naTua','vesCom','ultimo','teuPct','teuUltimo']), $flash */

$cor = $proprio ? 'tu' : 'par';   // as cores da pessoa: sálvia para ti, alperce para os outros

// Capa da folha "adicionar" (imagem ou iniciais sobre a cor da série)
$capa = function (Serie $s, string $classe): string {
    if ($s->capa) {
        return '<img class="' . e($classe) . '" src="' . e($s->capa) . '" alt="" loading="lazy" referrerpolicy="no-referrer">';
    }
    return '<span class="' . e($classe) . ' capa-sem" data-acento="' . e((string) $s->acento) . '">' . e(mb_strtoupper(mb_substr($s->nomeCurto(), 0, 2))) . '</span>';
};

// Capa pequena da lista (imagem ou iniciais sobre a cor da série)
$capaMini = function (Serie $s): string {
    if ($s->capa) {
        return '<img class="pessoa-capa" src="' . e($s->capa) . '" alt="" loading="lazy" referrerpolicy="no-referrer">';
    }
    return '<span class="pessoa-capa capa-sem" data-acento="' . e((string) $s->acento) . '">' . e(mb_strtoupper(mb_substr($s->nomeCurto(), 0, 2))) . '</span>';
};

// Secções da biblioteca, pela ordem da app
$estados = ['a_ver' => 'A ver', 'pausa' => 'Em pausa', 'acabado' => 'Acabado'];
?>
<div class="topo">
  <a class="btn-redondo vidro" href="<?= e(url('home')) ?>" aria-label="Voltar ao início">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
  </a>
  <h1 class="topo-meio"><?= $proprio ? 'Perfil' : e($pessoa->nome) ?></h1>
  <?php if ($proprio): ?>
    <!-- ⚙️ Definições: foto, nome, palavra-passe, cores, notificações, sair -->
    <a class="btn-redondo vidro" href="<?= e(url('perfil')) ?>" aria-label="Definições">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>
    </a>
  <?php else: ?>
    <?php require __DIR__ . '/../layout/botao-tema.php'; ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../layout/flash.php'; ?>

<div class="perfil-blocos">
<!-- Cabeçalho: foto, nome e o último episódio -->
<section class="pessoa-cabeca cascata">
  <?php $avatarUser = $pessoa; $avatarCor = $cor; $avatarExtra = 'avatar-xl'; require __DIR__ . '/../layout/avatar.php'; ?>
  <p class="pessoa-nome"><?= e($pessoa->nome) ?></p>
  <p class="pessoa-legenda"><?= $ehPar ? 'o teu par' : '@' . e($pessoa->username) ?></p>
</section>

<?php if ($ultimo): ?>
  <section class="painel vidro cascata">
    <p><?= $proprio ? 'Viste' : 'Viu' ?> o episódio <?= (int) $ultimo->numero ?> de <?= e($ultimo->serie->nomeCurto()) ?> · <?= e(tempo_relativo($ultimo->pivot->visto_em)) ?></p>
  </section>
<?php endif; ?>
<?php if ($soJuntos): ?>
  <p class="pessoa-legenda cascata"><?= e($pessoa->nome) ?> só mostra as séries que vê contigo.</p>
<?php endif; ?>

<!-- Números: sobem a contar (o JS anima de 0 até data-contar) -->
<section class="painel vidro numeros-faixa numeros-<?= $cor ?> cascata">
  <?php foreach ([[$numeros['episodios'], 'episódios'], [$numeros['horas'], 'horas'], [$numeros['series'], 'séries'], [$numeros['acabadas'], 'acabadas']] as [$valor, $legenda]): ?>
    <div class="numero">
      <b data-contar="<?= (int) $valor ?>"><?= (int) $valor ?></b>
      <span><?= e($legenda) ?></span>
    </div>
  <?php endforeach; ?>
</section>

<?php if (!$proprio && $numeros['seriesJuntos'] > 0): ?>
  <!-- Tu e ele: episódios vistos pelos dois e séries juntos -->
  <section class="painel vidro juntos-bloco cascata">
    <span class="juntos-coracao" aria-hidden="true">❤</span>
    <div>
      <p class="juntos-titulo">Tu e <?= e($pessoa->nome) ?></p>
      <p class="pessoa-legenda"><?= e(plural($numeros['episodiosJuntos'], 'episódio visto', 'episódios vistos')) ?> pelos dois ·
        <?= e(plural($numeros['seriesJuntos'], 'série', 'séries')) ?> juntos</p>
    </div>
  </section>
<?php endif; ?>

<?php if ($medalhas !== null): ?>
  <!-- Medalhas: as 3 melhores obtidas e "Ver todas" -->
  <section class="medalhas-destaque cascata">
    <div class="secao-cima">
      <h2>Medalhas · <?= $medalhas['obtidas'] ?>/<?= $medalhas['total'] ?></h2>
      <a class="link-suave" href="<?= e(url('perfil', 'medalhas', $proprio ? [] : ['id' => $pessoa->id])) ?>">Ver todas</a>
    </div>
    <?php if ($medalhas['destaque'] === []): ?>
      <p class="pessoa-legenda"><?= $proprio ? 'Ainda não tens medalhas.' : e($pessoa->nome) . ' ainda não tem medalhas.' ?></p>
    <?php else: ?>
      <div class="medalhas-tres">
        <?php foreach ($medalhas['destaque'] as $m): ?>
          <a class="medalha-cartao vidro" href="<?= e(url('perfil', 'medalhas', $proprio ? [] : ['id' => $pessoa->id])) ?>">
            <span class="emblema emblema-<?= e($m['nivel']) ?>"><span><?= e($m['emoji']) ?></span></span>
            <span class="medalha-nome"><?= e($m['nome']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php if ($proprio): ?>
  <!-- Atalho para a retrospetiva (mês a mês e o ano) -->
  <a class="painel vidro painel-link atalho cascata" href="<?= e(url('retrospetiva')) ?>">
    <span class="atalho-emoji" aria-hidden="true">📅</span>
    <span>Retrospetiva</span>
    <svg class="painel-seta" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
  </a>
<?php endif; ?>

<!-- A biblioteca, por estado: a barra dele com o último episódio e o dia; se também a tens, a tua por baixo -->
<section class="cascata">
  <div class="secao-cima"><h2><?= $proprio ? 'A tua biblioteca' : 'A biblioteca de ' . e($pessoa->nome) ?></h2></div>
  <?php if ($biblioteca === []): ?>
    <p class="folha-vazio"><?= $soJuntos ? e($pessoa->nome) . ' só mostra aos amigos as séries que vê com eles.' : 'Nada para mostrar por agora.' ?></p>
  <?php endif; ?>
  <?php foreach ($estados as $estado => $titulo):
      $doEstado = array_values(array_filter($biblioteca, fn ($i) => $i['estado'] === $estado));
      if ($doEstado === []) continue; ?>
    <p class="estado-titulo"><?= e($titulo) ?> · <?= count($doEstado) ?></p>
    <ul class="pessoa-lista">
      <?php foreach ($doEstado as $item): $s = $item['serie']; ?>
        <li class="pessoa-serie-cartao vidro">
          <?php if ($item['naTua']): ?>
            <a class="pessoa-serie" href="<?= e(url('serie', 'ver', ['serie' => $s->slug])) ?>">
          <?php else: ?>
            <div class="pessoa-serie">
          <?php endif; ?>
            <?= $capaMini($s) ?>
            <span class="pessoa-serie-texto">
              <span class="pessoa-serie-nome"><?= e($s->nomeCurto()) ?></span>
              <!-- A barra dele (ou a tua, no teu perfil), com o último episódio e o dia por baixo -->
              <span class="linha-pct"><span><?= $proprio ? 'Tu' : e($pessoa->nome) ?></span><b class="<?= $proprio ? 'cor-tu' : 'cor-par' ?>"><?= (int) $item['tu'] ?>%</b></span>
              <progress class="barra barra-fina <?= $proprio ? 'vs-tu-cor' : 'vs-par-cor' ?>" max="100" value="<?= (int) $item['tu'] ?>" aria-label="<?= $proprio ? 'Tu' : e($pessoa->nome) ?>: <?= (int) $item['tu'] ?>%"></progress>
              <?php if ($item['ultimo']): ?><span class="registo"><?= e(texto_ultimo_registo($item['ultimo'])) ?></span><?php endif; ?>
              <?php if ($item['teuPct'] !== null): ?>
                <span class="linha-pct"><span>Tu</span><b class="cor-tu"><?= (int) $item['teuPct'] ?>%</b></span>
                <progress class="barra barra-fina vs-tu-cor" max="100" value="<?= (int) $item['teuPct'] ?>" aria-label="Tu: <?= (int) $item['teuPct'] ?>%"></progress>
                <?php if ($item['teuUltimo']): ?><span class="registo"><?= e(texto_ultimo_registo($item['teuUltimo'])) ?></span><?php endif; ?>
              <?php endif; ?>
            </span>
          <?php if ($item['naTua']): ?></a><?php else: ?></div><?php endif; ?>

          <?php if (!$proprio): ?>
            <?php if ($item['vesCom']): ?>
              <p class="pessoa-etiqueta cor-tu">❤ Vês com <?= e($pessoa->nome) ?></p>
            <?php elseif ($item['naTua']): ?>
              <p class="pessoa-etiqueta">Também está na tua biblioteca</p>
            <?php else: ?>
              <!-- Ainda não a tens: abre a folha para te juntares (com ele ou só para ti) -->
              <button type="button" class="btn btn-secundario pessoa-juntar" data-abrir="juntar-<?= (int) $pessoa->id ?>-<?= e($s->slug) ?>">Ver com <?= e($pessoa->nome) ?>…</button>
            <?php endif; ?>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endforeach; ?>
  <?php if (!$proprio): foreach ($biblioteca as $item): if ($item['naTua']) continue; $dono = $pessoa; require __DIR__ . '/../layout/folha-juntar.php'; endforeach; endif; ?>
</section>

<?php if (!$proprio && !$ehPar): ?>
  <!-- Só os amigos se podem desfazer (o par não) -->
  <form class="acao-fundo" method="post" action="<?= e(url('amigos', 'remover')) ?>" data-confirmar="Tirar <?= e($pessoa->nome) ?> dos amigos?">
    <?= csrf_campo() ?>
    <input type="hidden" name="id" value="<?= $pessoa->id ?>">
    <button type="submit" class="btn btn-secundario">Tirar dos amigos</button>
  </form>
<?php endif; ?>
</div>
