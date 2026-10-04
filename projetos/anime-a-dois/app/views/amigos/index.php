<?php /* Amigos.
   Variáveis: $user, $amigos, $recebidos, $enviados (coleções de User), $link (convite em circulação ou null), $validade (dias), $flash */ ?>
<div class="topo">
  <a class="btn-redondo vidro" href="<?= e(url('home')) ?>" aria-label="Voltar ao início">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
  </a>
  <h1 class="topo-meio">Amigos</h1>
  <?php require __DIR__ . '/../layout/botao-tema.php'; ?>
</div>

<?php require __DIR__ . '/../layout/flash.php'; ?>

<?php if ($recebidos->isNotEmpty()): ?>
  <!-- Pedidos que recebeste -->
  <section class="painel vidro" id="pedidos">
    <div class="painel-titulo"><h2>Pedidos</h2></div>
    <ul class="pessoa-lista">
      <?php foreach ($recebidos as $p): ?>
        <li class="amigo-linha">
          <?php $avatarUser = $p; $avatarCor = 'par'; $avatarExtra = 'avatar-mini'; require __DIR__ . '/../layout/avatar.php'; ?>
          <span class="amigo-nome"><?= e($p->nome) ?> <span class="pessoa-legenda">quer ser teu amigo</span></span>
          <form method="post" action="<?= e(url('amigos', 'aceitar')) ?>"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $p->id ?>"><button class="btn-texto vidro" type="submit">Aceitar</button></form>
          <form method="post" action="<?= e(url('amigos', 'recusar')) ?>"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $p->id ?>"><button class="btn-texto vidro" type="submit">Agora não</button></form>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<!-- Lista de amigos: tocar abre o perfil -->
<section class="painel vidro">
  <div class="painel-titulo"><h2>Os teus amigos</h2><span><?= $amigos->count() ?></span></div>
  <?php if ($amigos->isEmpty()): ?>
    <p class="folha-vazio">Ainda não tens amigos aqui. Envia um link de convite ou adiciona alguém que já tenha conta.</p>
  <?php else: ?>
    <ul class="pessoa-lista">
      <?php foreach ($amigos as $a): $ult = $a->ultimoVisto(); ?>
        <li class="amigo-linha">
          <a class="amigo-link" href="<?= e(url('perfil', 'pessoa', ['id' => $a->id])) ?>">
            <?php $avatarUser = $a; $avatarCor = 'par'; $avatarExtra = 'avatar-mini'; require __DIR__ . '/../layout/avatar.php'; ?>
            <span class="amigo-nome">
              <?= e($a->nome) ?>
              <span class="pessoa-legenda"><?= $ult ? e($ult->serie->nomeCurto() . ' · ep. ' . $ult->numero . ' · ' . tempo_relativo($ult->pivot->visto_em)) : 'ainda sem episódios' ?></span>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<!-- Para quem ainda não tem conta: link de uso único -->
<section class="form vidro amigos-bloco">
  <div class="painel-titulo"><h2>Convidar com link</h2></div>
  <p class="atualizacao-texto">Para quem ainda não tem conta. O link serve uma pessoa só e dura <?= (int) $validade ?> dias; a conta nasce já ligada a ti, com a biblioteca vazia.</p>
  <?php if ($link): ?>
    <button type="button" class="btn" data-convidar="<?= e($link) ?>" data-texto="Entra no Anime a Dois e vemos o que cada um anda a ver 👀">Enviar link</button>
  <?php endif; ?>
  <form method="post" action="<?= e(url('amigos', 'criarConvite')) ?>">
    <?= csrf_campo() ?>
    <button type="submit" class="btn-texto vidro"><?= $link ? 'Gerar link novo' : 'Gerar link' ?></button>
  </form>
</section>

<!-- Para quem já tem conta: utilizador exato (não há pesquisa por parte do nome) -->
<form class="form vidro amigos-bloco" method="post" action="<?= e(url('amigos', 'pedir')) ?>">
  <?= csrf_campo() ?>
  <div class="painel-titulo"><h2>Adicionar quem já tem conta</h2></div>
  <label class="campo">
    <span>utilizador</span>
    <input type="text" name="username" maxlength="30" autocapitalize="none" autocomplete="off" spellcheck="false" required>
    <small>o utilizador completo (o que a pessoa usa para entrar)</small>
  </label>
  <button type="submit" class="btn btn-secundario">Enviar pedido</button>
</form>

<?php if ($enviados->isNotEmpty()): ?>
  <section class="painel vidro">
    <div class="painel-titulo"><h2>À espera de resposta</h2></div>
    <ul class="pessoa-lista">
      <?php foreach ($enviados as $p): ?>
        <li class="amigo-linha">
          <?php $avatarUser = $p; $avatarCor = 'par'; $avatarExtra = 'avatar-mini'; require __DIR__ . '/../layout/avatar.php'; ?>
          <span class="amigo-nome"><?= e($p->nome) ?></span>
          <form method="post" action="<?= e(url('amigos', 'recusar')) ?>"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $p->id ?>"><button class="btn-texto vidro" type="submit">Cancelar</button></form>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>
