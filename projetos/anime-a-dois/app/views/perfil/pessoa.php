<?php /* Perfil do teu par ou de um amigo.
   Variáveis: $user (tu), $pessoa (o par ou um amigo), $ehPar, $ultimo (Episodio ou null), $biblioteca (['serie','estado','conjunta','tu','par']), $flash */

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
?>
<div class="topo">
  <a class="btn-redondo vidro" href="<?= e(url('home')) ?>" aria-label="Voltar ao início">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
  </a>
  <h1 class="topo-meio"><?= e($pessoa->nome) ?></h1>
  <?php require __DIR__ . '/../layout/botao-tema.php'; ?>
</div>

<?php require __DIR__ . '/../layout/flash.php'; ?>

<!-- Foto e último episódio visto -->
<section class="painel vidro pessoa-cabeca">
  <?php $avatarUser = $pessoa; $avatarCor = 'par'; $avatarExtra = 'avatar-xl'; require __DIR__ . '/../layout/avatar.php'; ?>
  <?php if ($ultimo): ?>
    <p class="pessoa-legenda">Último episódio visto</p>
    <p class="pessoa-ultimo"><?= e($ultimo->serie->nomeCurto() . ' · episódio ' . $ultimo->numero) ?></p>
    <p class="pessoa-legenda"><?= e(tempo_relativo($ultimo->pivot->visto_em)) ?></p>
  <?php else: ?>
    <p class="pessoa-legenda"><?= e($pessoa->nome) ?> ainda não marcou nenhum episódio.</p>
  <?php endif; ?>
</section>

<!-- A biblioteca dela: as que também tens abrem; as outras abrem uma folha para as adicionares -->
<section class="painel vidro">
  <div class="painel-titulo"><h2>Biblioteca de <?= e($pessoa->nome) ?></h2></div>
  <?php if ($biblioteca === []): ?>
    <p class="folha-vazio">Ainda não tem séries.</p>
  <?php else: ?>
    <ul class="pessoa-lista">
      <?php foreach ($biblioteca as $item): $s = $item['serie']; $abre = $s->naBibliotecaDe($user); ?>
        <li>
          <<?= $abre ? 'a href="' . e(url('home', 'index', ['serie' => $s->slug])) . '"' : 'button type="button" data-abrir="juntar-' . (int) $pessoa->id . '-' . e($s->slug) . '"' ?> class="pessoa-serie">
            <?= $capaMini($s) ?>
            <span class="pessoa-serie-texto">
              <span class="pessoa-serie-nome"><?= e($s->nomeCurto()) ?></span>
              <span class="pessoa-legenda"><?= e(Serie::estadoTexto($item['estado'])) ?> · <?= $item['tu'] ?>%<?= $item['conjunta'] && $item['companheiro'] ? ' · vê com ' . ($item['companheiro']->id === $user->id ? 'ti' : 'outra pessoa') : '' ?><?= $abre ? '' : ' · toca para adicionar' ?></span>
              <progress class="barra barra-fina vs-par-cor" max="100" value="<?= $item['tu'] ?>" aria-label="<?= e($pessoa->nome) ?>: <?= $item['tu'] ?>%"></progress>
            </span>
          </<?= $abre ? 'a' : 'button' ?>>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php foreach ($biblioteca as $item): if ($item['serie']->naBibliotecaDe($user)) continue; $dono = $pessoa; require __DIR__ . '/../layout/folha-juntar.php'; endforeach; ?>
  <?php endif; ?>
</section>

<?php if (!$ehPar): ?>
  <!-- Só os amigos se podem desfazer (o par não) -->
  <form class="acao-fundo" method="post" action="<?= e(url('amigos', 'remover')) ?>" data-confirmar="Tirar <?= e($pessoa->nome) ?> dos amigos?">
    <?= csrf_campo() ?>
    <input type="hidden" name="id" value="<?= $pessoa->id ?>">
    <button type="submit" class="btn btn-secundario">Tirar dos amigos</button>
  </form>
<?php endif; ?>
