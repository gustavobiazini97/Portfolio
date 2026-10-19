<?php
/* Retrospetivas: escolher o mês ou o ano (só aparecem os períodos com episódios).
   Variáveis: $user, $periodos (meses: [ano, mes, nome, episodios]; anos: [ano, episodios]), $flash */
?>
<div class="topo">
  <a class="btn-redondo vidro" href="<?= e(url('perfil', 'pessoa', ['id' => $user->id])) ?>" aria-label="Voltar ao perfil">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
  </a>
  <h1 class="topo-meio">Retrospetivas</h1>
  <?php require __DIR__ . '/../layout/botao-tema.php'; ?>
</div>

<?php if ($periodos['meses'] === []): ?>
  <section class="painel vidro vazio">
    <p>📼</p>
    <p>Marca episódios e a tua primeira retrospetiva aparece aqui.</p>
  </section>
<?php else: ?>
  <?php if ($periodos['anos'] !== []): ?>
    <h2 class="estado-titulo">Anos</h2>
    <ul class="retro-lista">
      <?php foreach ($periodos['anos'] as $a): ?>
        <li class="cascata">
          <a class="retro-item vidro retro-ano" href="<?= e(url('retrospetiva', 'ver', ['ano' => $a['ano']])) ?>">
            <span class="retro-nome"><?= (int) $a['ano'] ?></span>
            <span class="pessoa-legenda"><?= e(plural($a['episodios'], 'episódio', 'episódios')) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <h2 class="estado-titulo">Meses</h2>
  <ul class="retro-lista">
    <?php foreach ($periodos['meses'] as $m): ?>
      <li class="cascata">
        <a class="retro-item vidro" href="<?= e(url('retrospetiva', 'ver', ['ano' => $m['ano'], 'mes' => $m['mes']])) ?>">
          <span class="retro-nome"><?= e(mb_strtoupper(mb_substr($m['nome'], 0, 1)) . mb_substr($m['nome'], 1)) ?></span>
          <span class="pessoa-legenda"><?= e(plural($m['episodios'], 'episódio', 'episódios')) ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
