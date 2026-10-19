<?php
/* Sininho (🔔): o que aconteceu à tua volta nos últimos 30 dias (igual ao da app).
   Variáveis: $user, $lista (Notificacoes::lista já filtrada), $vistoAntes (timestamp ou null: o que for mais recente é "novo"),
              $filtros (nome → bool), $flash */

// Para onde leva cada notificação: episódio/comentário → a série nesse episódio (se a vês com a pessoa);
// episódio de uma série que não vês com ela → o perfil dela; convite → Início; pedido de amizade → Amigos
$destino = function (array $n): string {
    if ($n['tipo'] === 'ep' && !$n['juntos']) {
        return url('perfil', 'pessoa', ['id' => $n['quem']->id]);
    }
    if ($n['serie'] !== null) {
        return url('serie', 'ver', ['serie' => $n['serie'], 'ep' => $n['episodio']]);
    }
    return $n['tipo'] === 'amigo' ? url('amigos') : url('home');
};
$emoji = ['ep' => '▶️', 'com' => '💬', 'convite' => '🎬', 'amigo' => '👋'];
?>
<div class="topo">
  <a class="btn-redondo vidro" href="<?= e(url('home')) ?>" aria-label="Voltar ao início">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
  </a>
  <h1 class="topo-meio">Notificações</h1>
  <!-- O que mostrar (abre a folha dos filtros) -->
  <button type="button" class="btn-redondo vidro" data-abrir="folha-filtros" aria-label="O que mostrar">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h10M18 7h2M4 17h4M12 17h8"/><circle cx="16" cy="7" r="2"/><circle cx="10" cy="17" r="2"/></svg>
  </button>
</div>

<?php require __DIR__ . '/../layout/flash.php'; ?>

<?php if ($lista === []): ?>
  <section class="painel vidro vazio">
    <p>🔔</p>
    <p>Nada de novo nos últimos <?= Notificacoes::DIAS ?> dias.</p>
  </section>
<?php else: ?>
  <!-- As novas (depois da última vez que abriste a lista) têm bolinha e o tom do par -->
  <ul class="sino-lista">
    <?php foreach ($lista as $n): $nova = $vistoAntes === null || strtotime($n['ts']) > $vistoAntes; ?>
      <li class="cascata">
        <a class="sino-item vidro<?= $nova ? ' sino-nova' : '' ?>" href="<?= e($destino($n)) ?>">
          <?php $avatarUser = $n['quem']; $avatarCor = 'par'; $avatarExtra = ''; require __DIR__ . '/../layout/avatar.php'; ?>
          <span class="sino-texto">
            <span class="sino-titulo"><?= e($n['texto']) ?></span>
            <?php if ($n['detalhe'] !== ''): ?><span class="sino-detalhe"><?= e($n['detalhe']) ?></span><?php endif; ?>
            <span class="sino-quando"><?= $emoji[$n['tipo']] ?? '🔔' ?>  <?= e(dia_do_registo($n['ts'])) ?></span>
          </span>
          <?php if ($nova): ?><span class="sino-ponto" aria-label="Nova"></span><?php endif; ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<!-- Folha "O que mostrar": interruptores guardados neste browser -->
<dialog class="folha vidro" id="folha-filtros" aria-label="O que mostrar">
  <div class="folha-cima">
    <h2>O que mostrar</h2>
    <button type="button" class="btn-redondo vidro" data-fechar aria-label="Fechar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
    </button>
  </div>
  <form method="post" action="<?= e(url('notificacoes', 'filtrar')) ?>" class="sino-filtros">
    <?= csrf_campo() ?>
    <?php foreach (Notificacoes::FILTROS as $nome => [$rotulo]): ?>
      <label class="linha-interruptor">
        <span><?= e($rotulo) ?></span>
        <span class="interruptor"><input type="checkbox" name="<?= e($nome) ?>" value="1" <?= $filtros[$nome] ? 'checked' : '' ?>><i></i></span>
      </label>
    <?php endforeach; ?>
    <button type="submit" class="btn">Guardar</button>
  </form>
</dialog>
