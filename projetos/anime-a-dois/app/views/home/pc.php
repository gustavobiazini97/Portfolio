<?php
/* Blocos do Início no computador (o CSS só os mostra a partir de 1280 px; no telemóvel ficam escondidos).
   Usa as variáveis de home/index.php: $user, $parceiro, $ultimoPar, $pedidosAmigos, $capa (função da capa) e
   $pc = ['tu' => InicioPc::tu, 'par' => InicioPc::par ou null, 'amigos' => InicioPc::amigos].
   Cada bloco tem a sua área na grelha (.inicio em app.css): pc-tu, pc-marco, pc-par-col (espera, juntos, última), pc-amigos. */
$pcTu = $pc['tu'];
$pcPar = $pc['par'];

// Avatar pequeno (foto ou inicial) de uma pessoa
$pcAvatar = function (User $pcP, string $cor, string $extra = 'avatar-mini'): string {
    ob_start();
    $avatarUser = $pcP; $avatarCor = $cor; $avatarExtra = $extra;
    require __DIR__ . '/../layout/avatar.php';
    return ob_get_clean();
};
?>

<!-- ===== Esquerda: tu ===== -->
<aside class="pc-col pc-tu" aria-label="Os teus números">
  <h2 class="pc-col-titulo">Tu</h2>

  <section class="pc-bloco vidro">
    <h3 class="pc-titulo">Esta semana</h3>
    <p><span class="pc-num"><?= $pcTu['episodios'] ?></span> <span class="pc-suave"><?= $pcTu['episodios'] === 1 ? 'episódio' : 'episódios' ?> · <?= e($pcTu['horas']) ?></span></p>
  </section>

  <section class="pc-bloco vidro">
    <h3 class="pc-titulo">Dias seguidos</h3>
    <p><span class="pc-num"><?= $pcTu['seguidos'] ?></span> <span class="pc-suave">· recorde <?= $pcTu['recorde'] ?></span></p>
  </section>

  <?php if ($pcTu['medalha']): $pcMed = $pcTu['medalha']; ?>
    <!-- A medalha por obter mais perto; tocar abre todas -->
    <a class="pc-bloco vidro pc-link" href="<?= e(url('perfil', 'medalhas')) ?>">
      <h3 class="pc-titulo">Próxima medalha</h3>
      <p class="pc-forte"><?= e($pcMed['emoji'] . ' ' . $pcMed['nome']) ?></p>
      <progress class="barra vs-tu-cor pc-barra" max="100" value="<?= $pcMed['pct'] ?>"><?= $pcMed['pct'] ?>%</progress>
      <p class="pc-suave"><?= e($pcMed['descricao']) ?> · <?= $pcMed['atual'] ?>/<?= $pcMed['meta'] ?></p>
    </a>
  <?php endif; ?>

  <?php if ($pcTu['retro']): ?>
    <a class="pc-bloco vidro pc-link" href="<?= e(url('retrospetiva', 'ver', ['ano' => $pcTu['retro']['ano'], 'mes' => $pcTu['retro']['mes']])) ?>">
      <h3 class="pc-titulo">Retrospetiva</h3>
      <p class="pc-forte"><?= e(ucfirst($pcTu['retro']['nome'])) ?> →</p>
    </a>
  <?php endif; ?>
</aside>

<?php if ($pcPar): ?>
  <!-- ===== Meio: tu e o par ===== -->
  <?php if ($pcPar['marco']): ?>
    <!-- Marco do dia (só aparece no dia em que acontece) -->
    <section class="pc-bloco vidro pc-marco" aria-live="polite">
      <p class="pc-marco-texto">🎉 <?= e($pcPar['marco']) ?></p>
    </section>
  <?php endif; ?>

  <!-- Ao lado da série aberta: quem espera, números a dois e última vez juntos (uma coluna, sem esticar) -->
  <div class="pc-par-col">
  <?php if ($pcPar['esperas'] !== []): ?>
    <section class="pc-bloco vidro pc-espera">
      <h3 class="pc-titulo">Quem espera por quem</h3>
      <ul class="pc-lista">
        <?php foreach ($pcPar['esperas'] as $pcL): ?>
          <li>
            <!-- Tocar abre essa série aqui no Início -->
            <a class="pc-linha" href="<?= e(url('home', 'index', ['serie' => $pcL['serie']->slug])) ?>">
              <span class="pc-linha-txt<?= $pcL['dif'] < 0 ? ' pc-par' : ($pcL['dif'] > 0 ? ' pc-tu-cor' : '') ?>"><?= e($pcL['texto']) ?></span>
              <span class="pc-suave pc-linha-serie"><?= e($pcL['serie']->nomeCurto()) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <section class="pc-bloco vidro pc-juntos">
    <h3 class="pc-titulo">Tu e <?= e($parceiro->nome) ?></h3>
    <p><span class="pc-num"><?= $pcPar['juntos']['episodios'] ?></span> <span class="pc-suave">episódios vistos pelos dois · <?= e($pcPar['juntos']['horas']) ?></span></p>
    <p class="pc-suave">
      <?= e(plural($pcPar['juntos']['series'], 'série', 'séries') . ' juntos') ?><?= $pcPar['juntos']['desde'] ? e(' · desde ' . $pcPar['juntos']['desde']) : '' ?>
    </p>
  </section>

  <?php if ($pcPar['ultima']): $pcUlt = $pcPar['ultima']; ?>
    <!-- Última vez juntos: abre a série nesse episódio -->
    <a class="pc-bloco vidro pc-link pc-ultima" href="<?= e(url('serie', 'ver', ['serie' => $pcUlt['serie']->slug, 'ep' => $pcUlt['ate']])) ?>">
      <h3 class="pc-titulo">Última vez juntos</h3>
      <p class="pc-forte"><?= e($pcUlt['texto']) ?></p>
      <p class="pc-suave"><?= e($pcUlt['quando']) ?></p>
    </a>
  <?php else: ?>
    <section class="pc-bloco vidro pc-ultima">
      <h3 class="pc-titulo">Última vez juntos</h3>
      <p class="pc-suave">Quando os dois marcarem o mesmo episódio no mesmo dia, aparece aqui.</p>
    </section>
  <?php endif; ?>
  </div><!-- /.pc-par-col -->
<?php endif; ?>

<!-- ===== Direita: o último do par (por cima) e os amigos ===== -->
<aside class="pc-col pc-amigos" aria-label="Amigos">
  <?php if ($parceiro && $ultimoPar): ?>
    <!-- Último episódio do par, em qualquer série (no PC substitui o cartão largo do meio, que o CSS esconde).
         Abre a série nesse episódio se for tua; senão, o perfil do par. -->
    <?php $pcAbre = $ultimoPar->serie->naBibliotecaDe($user); ?>
    <a class="pc-bloco vidro pc-link pc-ultimo-par" href="<?= e($pcAbre ? url('serie', 'ver', ['serie' => $ultimoPar->serie->slug, 'ep' => $ultimoPar->numero]) : url('perfil', 'pessoa', ['id' => $parceiro->id])) ?>">
      <h3 class="pc-titulo"><?= e($parceiro->nome) ?> viu</h3>
      <span class="pc-ultimo-linha">
        <?= $pcAvatar($parceiro, 'par') ?>
        <span class="pc-ultimo-txt">
          <b><?= e($ultimoPar->serie->nomeCurto()) ?></b>
          <span class="pc-suave"><?= e('ep. ' . $ultimoPar->numero . ' · ' . LadoSerie::quando(strtotime($ultimoPar->pivot->visto_em))) ?></span>
        </span>
      </span>
    </a>
  <?php endif; ?>

  <h2 class="pc-col-titulo">Amigos</h2>

  <section class="pc-bloco vidro">
    <h3 class="pc-titulo">Os teus amigos estão a ver</h3>
    <?php if ($pc['amigos'] === []): ?>
      <p class="pc-suave">Nos últimos 7 dias os teus amigos não marcaram episódios.</p>
    <?php else: ?>
      <ul class="pc-amigos-lista">
        <?php foreach ($pc['amigos'] as $pcA): $pcQuem = $pcA['pessoas'][0]; ?>
          <li>
            <!-- Tocar abre o perfil do amigo (lá dá para adicionares a série ou veres com ele) -->
            <a class="pc-amigo<?= $pcA['tens'] ? ' pc-tens' : '' ?>" href="<?= e(url('perfil', 'pessoa', ['id' => $pcQuem['user']->id])) ?>">
              <span class="pc-amigo-capa">
                <?= $capa($pcA['serie'], 'capa') ?>
                <?php if ($pcA['tens']): ?><span class="capa-estado">✓ tens</span><?php endif; ?>
              </span>
              <span class="pc-amigo-txt">
                <b><?= e($pcA['serie']->nomeCurto()) ?></b>
                <?php foreach ($pcA['pessoas'] as $pcP): ?>
                  <span class="pc-amigo-pessoa"><?= $pcAvatar($pcP['user'], 'par', 'avatar-micro') ?> <?= e($pcP['user']->nome . ' · ep. ' . $pcP['numero'] . ' · ' . $pcP['quando']) ?></span>
                <?php endforeach; ?>
              </span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <?php if ($pedidosAmigos): ?>
    <a class="pc-bloco vidro pc-link" href="<?= e(url('amigos')) ?>">
      <h3 class="pc-titulo">Pedidos de amizade</h3>
      <p class="pc-forte"><?= e(plural($pedidosAmigos, 'pedido por responder', 'pedidos por responder')) ?> →</p>
    </a>
  <?php endif; ?>

  <a class="pc-bloco vidro pc-link pc-convidar" href="<?= e(url('amigos')) ?>">🔗 Convidar um amigo</a>
</aside>
