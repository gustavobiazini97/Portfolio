<?php
/* Início.
   Variáveis: $user, $parceiro (o par, ou null), $parVs (o par só se a série aberta for dos dois),
              $biblioteca (a tua: ['serie','estado','conjunta','tu','par']), $doPar (só do par, mesmo formato),
              $convites (['serie','recebido']), $serie (aberta, ou null se a tua biblioteca estiver vazia),
              $meuEstado, $conjunta, $ligados (par + amigos), $enviadosSerie, $ultimoPar (Episodio ou null), $meu, $dele, $resumo,
              $podeRemover, $jaCa (mal_id → biblioteca|convite|par), $novidades, $pedidosAmigos, $esperaPar, $flash */
$comecou = $serie && ($meu['vistos'] > 0 || $dele['vistos'] > 0);   // $dele já vem a zeros se a série for só tua
$nomePar = $parceiro ? $parceiro->nome : null;

// Capa de uma série: a imagem do MyAnimeList ou, sem ela, as iniciais sobre a cor da série
$capa = function (Serie $s, string $classe): string {
    if ($s->capa) {
        return '<img class="' . e($classe) . '" src="' . e($s->capa) . '" alt="" loading="lazy" referrerpolicy="no-referrer">';
    }
    $iniciais = mb_strtoupper(mb_substr($s->nomeCurto(), 0, 2));
    return '<span class="' . e($classe) . ' capa-sem" data-serie="' . e($s->slug) . '" data-acento="' . e((string) $s->acento) . '">' . e($iniciais) . '</span>';
};

// Selo no canto da capa: o estado (em pausa / acabado) e, nas da tua fila, se é dos dois
$selo = fn (array $item) => match ($item['estado']) {
    'acabado' => '✓ acabado',
    'pausa'   => 'em pausa',
    default   => '',
};
?>
<div class="topo">
  <a class="marca" href="<?= e(url('home')) ?>">
    <img class="logo" src="img/logo.svg" alt="" width="34" height="34">
    anime a dois
  </a>
  <div class="topo-acoes">
    <?php if ($serie): ?>
      <!-- Estatísticas da série aberta -->
      <a class="btn-redondo vidro" href="<?= e(url('estatisticas', 'index', ['serie' => $serie->slug])) ?>" aria-label="Ver estatísticas">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 20V12M12 20V5M19 20v-5"/></svg>
      </a>
    <?php endif; ?>
    <!-- Amigos (bolinha se há pedidos por responder) -->
    <a class="btn-redondo vidro" href="<?= e(url('amigos')) ?>" aria-label="Amigos<?= $pedidosAmigos ? ' (' . $pedidosAmigos . ' pedidos)' : '' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 19c.4-3 2.6-4.7 5.5-4.7s5.1 1.7 5.5 4.7"/><path d="M16 5.3a3 3 0 0 1 0 5.4M17.5 14.6c1.8.5 2.8 2 3 4.4"/></svg>
      <?php if ($pedidosAmigos): ?><span class="ponto-aviso" aria-hidden="true"></span><?php endif; ?>
    </a>
    <?php require __DIR__ . '/../layout/botao-tema.php'; ?>
    <!-- O teu avatar abre o perfil (foto, nome, palavra-passe, sair) -->
    <a class="avatar-link" href="<?= e(url('perfil')) ?>" aria-label="Abrir o teu perfil">
      <?php $avatarUser = $user; $avatarCor = 'tu'; $avatarExtra = 'avatar-topo'; require __DIR__ . '/../layout/avatar.php'; ?>
    </a>
  </div>
</div>

<?php require __DIR__ . '/../layout/flash.php'; ?>

<?php if ($parceiro && $ultimoPar): ?>
  <!-- Último episódio que o par viu (em qualquer série). Se a série também está na tua biblioteca,
       o cartão abre-a nesse episódio; se é só dele, é só para espreitar. -->
  <?php $abre = $ultimoPar->serie->naBibliotecaDe($user); ?>
  <div class="painel vidro ultimo<?= $abre ? ' painel-link' : '' ?>">
    <!-- A foto abre o perfil do par -->
    <a class="avatar-link" href="<?= e(url('perfil', 'pessoa', ['id' => $parceiro->id])) ?>" aria-label="Ver o perfil de <?= e($parceiro->nome) ?>">
      <?php $avatarUser = $parceiro; $avatarCor = 'par'; $avatarExtra = ''; require __DIR__ . '/../layout/avatar.php'; ?>
    </a>
    <!-- O resto do cartão abre o episódio (se a série for tua); display: contents mantém o layout -->
    <<?= $abre ? 'a' : 'div' ?> class="ultimo-link"<?= $abre ? ' href="' . e(url('serie', 'ver', ['serie' => $ultimoPar->serie->slug, 'ep' => $ultimoPar->numero])) . '"' : '' ?>>
      <div class="ultimo-texto">
        <p><?= e($parceiro->nome . ' viu ' . tempo_relativo($ultimoPar->pivot->visto_em)) ?></p>
        <p><?= e($ultimoPar->serie->nomeCurto() . ' · episódio ' . $ultimoPar->numero) ?></p>
      </div>
      <span class="ultimo-num"><?= $ultimoPar->numero ?></span>
    </<?= $abre ? 'a' : 'div' ?>>
  </div>
<?php elseif ($parceiro): ?>
  <section class="painel vidro vazio">
    <p><?= e($parceiro->nome) ?> ainda não marcou nenhum episódio</p>
    <p>O último que marcar aparece aqui.</p>
  </section>
<?php elseif (!$esperaPar): ?>
  <!-- Conta de amigo: não tem par; os amigos estão na página Amigos -->
  <section class="painel vidro vazio">
    <p>Aqui é tudo teu</p>
    <p>Adiciona séries com o + e vê o que os teus amigos andam a ver na página Amigos.</p>
  </section>
<?php else: ?>
  <section class="painel vidro vazio">
    <p>À espera do teu par</p>
    <p>Envia-lhe o link: quando criar a conta, aparece aqui e o registo fecha.</p>
    <!-- Partilha o link da app (menu do Android); sem partilha nativa, copia o link -->
    <button type="button" class="btn-texto vidro btn-convite" data-convidar="<?= e(url_absoluto()) ?>">
      Enviar convite
    </button>
  </section>
<?php endif; ?>

<!-- A tua biblioteca: fila de capas que desliza para os lados. As séries dos dois têm duas barrinhas
     (tu e o par); as só tuas, uma. A ver → em pausa → acabado; tocar numa capa abre-a aqui em baixo. -->
<section class="biblioteca" aria-labelledby="biblioteca-titulo">
  <div class="secao-cima">
    <h2 id="biblioteca-titulo">A tua biblioteca</h2>
    <button type="button" class="btn-redondo vidro btn-adicionar" id="btn-adicionar" aria-label="Adicionar série">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
    </button>
  </div>

  <?php if ($biblioteca === []): ?>
    <div class="painel vidro vazio">
      <p>A tua biblioteca está vazia</p>
      <p>Toca no + e procura o próximo anime.</p>
    </div>
  <?php else: ?>
    <?php
    // Séries com id do MyAnimeList ainda sem capa (as do seed, na primeira visita): o browser vai buscá-las
    $semCapa = [];
    foreach (array_merge($biblioteca, $doPar) as $item) {
        if ($item['serie']->capa === null && $item['serie']->mal_id !== null) {
            $semCapa[] = ['serie' => $item['serie']->slug, 'mal' => (int) $item['serie']->mal_id];
        }
    }
    ?>
    <div class="fila" id="fila" data-sem-capa="<?= e(json_encode($semCapa)) ?>" data-url="<?= e(url('biblioteca', 'atualizar')) ?>">
      <?php foreach ($biblioteca as $item): $s = $item['serie']; ?>
        <!-- data-serie/data-acento dão a cor da série só a este cartão (aro da capa e fundo sem imagem) -->
        <a class="capa-item capa-<?= e($item['estado']) ?>" href="<?= e(url('home', 'index', ['serie' => $s->slug])) ?>"
           data-serie="<?= e($s->slug) ?>" data-acento="<?= e((string) $s->acento) ?>"
           <?= $serie && $s->id === $serie->id ? 'aria-current="page"' : '' ?>>
          <span class="capa-moldura">
            <?= $capa($s, 'capa') ?>
            <?php if ($item['conjunta'] && $item['companheiro']): ?>
              <!-- Vista com alguém: os dois avatares pequenos no canto -->
              <span class="capa-dois" title="Vês esta série com <?= e($item['companheiro']->nome) ?>">
                <?php $avatarUser = $user; $avatarCor = 'tu'; $avatarExtra = 'avatar-micro'; require __DIR__ . '/../layout/avatar.php'; ?>
                <?php $avatarUser = $item['companheiro']; $avatarCor = 'par'; $avatarExtra = 'avatar-micro'; require __DIR__ . '/../layout/avatar.php'; ?>
              </span>
            <?php endif; ?>
            <?php if ($selo($item) !== ''): ?>
              <span class="capa-estado"><?= e($selo($item)) ?></span>
            <?php endif; ?>
          </span>
          <span class="capa-nome"><?= e($s->nomeCurto()) ?></span>
          <span class="capa-barras">
            <progress class="barra barra-fina vs-tu-cor" max="100" value="<?= $item['tu'] ?>" aria-label="Tu: <?= $item['tu'] ?>%"></progress>
            <?php if ($item['conjunta'] && $item['companheiro']): ?>
              <progress class="barra barra-fina vs-par-cor" max="100" value="<?= $item['par'] ?>" aria-label="<?= e($item['companheiro']->nome) ?>: <?= $item['par'] ?>%"></progress>
            <?php endif; ?>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php if ($convites !== []): ?>
  <!-- Quero ver contigo: convites do par ou de amigos para ver uma série juntos; aceitar põe-na na tua biblioteca -->
  <section class="painel vidro propostas" id="convites">
    <div class="painel-titulo">
      <h2>Quero ver contigo</h2>
      <span><?= plural(count($convites), 'convite', 'convites') ?></span>
    </div>
    <ul class="propostas-lista">
      <?php foreach ($convites as $c): $p = $c['serie']; ?>
        <li class="proposta">
          <?= $capa($p, 'proposta-capa') ?>
          <div class="proposta-texto">
            <p class="proposta-nome"><?= e($p->nome) ?></p>
            <p class="proposta-info">
              <?= e(implode(' · ', array_filter([
                  $c['recebido'] ? $c['de']->nome . ' convidou-te' : 'convidaste ' . $c['para']->nome,
                  plural((int) $p->total_episodios, 'ep.', 'ep.'),
                  $p->tipo,
                  $p->anos,
              ]))) ?>
            </p>
            <div class="proposta-acoes">
              <?php if ($c['recebido']): ?>
                <form method="post" action="<?= e(url('biblioteca', 'aceitar')) ?>">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="serie" value="<?= e($p->slug) ?>">
                  <input type="hidden" name="de" value="<?= (int) $c['de']->id ?>">
                  <button type="submit" class="btn-pequeno">Bora ver</button>
                </form>
                <form method="post" action="<?= e(url('biblioteca', 'recusar')) ?>" data-confirmar="Recusar <?= e($p->nomeCurto()) ?>?">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="serie" value="<?= e($p->slug) ?>">
                  <input type="hidden" name="outro" value="<?= (int) $c['de']->id ?>">
                  <button type="submit" class="link-suave">Agora não</button>
                </form>
              <?php else: ?>
                <span class="proposta-espera">à espera de <?= e($c['para']->nome) ?></span>
                <form method="post" action="<?= e(url('biblioteca', 'recusar')) ?>" data-confirmar="Cancelar o convite de <?= e($p->nomeCurto()) ?>?">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="serie" value="<?= e($p->slug) ?>">
                  <input type="hidden" name="outro" value="<?= (int) $c['para']->id ?>">
                  <button type="submit" class="link-suave">Cancelar</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<?php if ($serie): ?>
  <?php if ($comecou): ?>
    <!-- Vs da série aberta (ou só o teu progresso, se a série for só tua); o cartão todo abre a série -->
    <a class="painel vidro painel-link" href="<?= e(url('serie', 'ver', ['serie' => $serie->slug])) ?>">
      <div class="painel-titulo">
        <h2><?= $parVs ? e('Tu e ' . $parVs->nome . ' em ' . $serie->nomeCurto()) : e('O teu progresso em ' . $serie->nomeCurto()) ?></h2>
        <span><?= $serie->total_episodios ?> ep. <svg class="painel-seta" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></span>
      </div>

      <div class="vs-linhas">
        <div class="vs-linha vs-tu">
          <div class="vs-rotulo"><span>Tu</span><span><b><?= $meu['posicao'] ?></b> · <?= $meu['pct'] ?>%</span></div>
          <progress class="barra" max="100" value="<?= $meu['pct'] ?>" aria-label="Progresso: <?= $meu['pct'] ?>%"></progress>
        </div>
        <?php if ($parVs): ?>
          <div class="vs-linha vs-par">
            <div class="vs-rotulo"><span><?= e($parVs->nome) ?></span><span><b><?= $dele['posicao'] ?></b> · <?= $dele['pct'] ?>%</span></div>
            <progress class="barra" max="100" value="<?= $dele['pct'] ?>" aria-label="Progresso: <?= $dele['pct'] ?>%"></progress>
          </div>
        <?php endif; ?>
      </div>

      <p class="vs-resumo"><?= e($resumo) ?></p>
    </a>
  <?php else: ?>
    <section class="painel vidro vazio">
      <p><?= $parVs ? 'Ainda nenhum dos dois começou ' : 'Ainda não começaste ' ?><?= e($serie->nomeCurto()) ?></p>
      <p>Marca o primeiro episódio e o placar aparece aqui.</p>
    </section>
  <?php endif; ?>

  <!-- O TEU estado desta série (o do par é dele): os acabados vão para o fim da tua fila -->
  <form class="estados vidro" method="post" action="<?= e(url('biblioteca', 'estado')) ?>" aria-label="Estado de <?= e($serie->nomeCurto()) ?>">
    <?= csrf_campo() ?>
    <input type="hidden" name="serie" value="<?= e($serie->slug) ?>">
    <?php foreach (Serie::ESTADOS as $valor => $texto): ?>
      <button type="submit" name="estado" value="<?= e($valor) ?>" class="estado"
              aria-pressed="<?= $meuEstado === $valor ? 'true' : 'false' ?>"><?= e($texto) ?></button>
    <?php endforeach; ?>
  </form>

  <?php if ($ligados->isNotEmpty() || $conjunta || $podeRemover): ?>
    <!-- Ações da série: ver com alguém (par ou amigo), deixar de ver juntos e tirar (se ainda não marcaste episódios) -->
    <div class="serie-acoes">
      <?php if ($conjunta): ?>
        <span class="pessoa-legenda">Vês com <?= e($parVs->nome) ?></span>
        <form method="post" action="<?= e(url('biblioteca', 'separar')) ?>" data-confirmar="Deixar de ver <?= e($serie->nomeCurto()) ?> com <?= e($parVs->nome) ?>? Cada um fica com o seu progresso.">
          <?= csrf_campo() ?>
          <input type="hidden" name="serie" value="<?= e($serie->slug) ?>">
          <button type="submit" class="link-suave">Deixar de ver juntos</button>
        </form>
      <?php elseif ($ligados->isNotEmpty()): ?>
        <?php foreach ($enviadosSerie as $env): ?>
          <span class="proposta-espera">Convite enviado · à espera de <?= e($env['para']->nome) ?></span>
        <?php endforeach; ?>
        <?php if ($ligados->count() === 1): ?>
          <!-- Só há uma pessoa a quem convidar: botão direto -->
          <form method="post" action="<?= e(url('biblioteca', 'convidar')) ?>">
            <?= csrf_campo() ?>
            <input type="hidden" name="serie" value="<?= e($serie->slug) ?>">
            <input type="hidden" name="com" value="<?= (int) $ligados->first()->id ?>">
            <button type="submit" class="btn-pequeno secundario">Ver com <?= e($ligados->first()->nome) ?></button>
          </form>
        <?php else: ?>
          <button type="button" class="btn-pequeno secundario" data-abrir="folha-convidar">Ver com…</button>
        <?php endif; ?>
      <?php endif; ?>
      <?php if ($podeRemover): ?>
        <form method="post" action="<?= e(url('biblioteca', 'remover')) ?>" data-confirmar="Tirar <?= e($serie->nomeCurto()) ?> da tua biblioteca?">
          <?= csrf_campo() ?>
          <input type="hidden" name="serie" value="<?= e($serie->slug) ?>">
          <button type="submit" class="link-suave">Tirar da biblioteca</button>
        </form>
      <?php endif; ?>
    </div>

    <?php if (!$conjunta && $ligados->count() > 1): ?>
      <!-- Escolher com quem ver esta série: uma linha por pessoa (o par primeiro, depois os amigos) -->
      <dialog class="folha vidro folha-par" id="folha-convidar" aria-label="Ver <?= e($serie->nomeCurto()) ?> com…">
        <div class="folha-cima">
          <h2>Ver <?= e($serie->nomeCurto()) ?> com…</h2>
          <button type="button" class="btn-redondo vidro" data-fechar aria-label="Fechar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
          </button>
        </div>
        <ul class="pessoa-lista">
          <?php foreach ($ligados as $l): ?>
            <li>
              <form class="amigo-linha" method="post" action="<?= e(url('biblioteca', 'convidar')) ?>">
                <?= csrf_campo() ?>
                <input type="hidden" name="serie" value="<?= e($serie->slug) ?>">
                <input type="hidden" name="com" value="<?= (int) $l->id ?>">
                <?php $avatarUser = $l; $avatarCor = 'par'; $avatarExtra = 'avatar-mini'; require __DIR__ . '/../layout/avatar.php'; ?>
                <span class="amigo-nome"><?= e($l->nome) ?></span>
                <button type="submit" class="btn-texto vidro">Convidar</button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
        <p class="pessoa-legenda par-nota">A pessoa recebe o convite e, se aceitar, passam a ver a série juntos.</p>
      </dialog>
    <?php endif; ?>
  <?php endif; ?>

  <a class="btn" href="<?= e(url('serie', 'ver', ['serie' => $serie->slug])) ?>">
    Ver episódios de <?= e($serie->nomeCurto()) ?>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
  </a>
<?php endif; ?>

<?php if ($doPar !== []): ?>
  <!-- A <par> está a ver: as séries só dele. Tocar abre uma folha com o progresso e o botão de adicionar à tua biblioteca -->
  <section class="biblioteca biblioteca-par" aria-labelledby="par-titulo">
    <div class="secao-cima">
      <h2 id="par-titulo"><?= e($nomePar) ?> está a ver</h2>
    </div>
    <div class="fila fila-par">
      <?php foreach ($doPar as $item): $s = $item['serie']; ?>
        <button type="button" class="capa-item capa-<?= e($item['estado']) ?>" data-abrir="juntar-<?= (int) $parceiro->id ?>-<?= e($s->slug) ?>" data-serie="<?= e($s->slug) ?>" data-acento="<?= e((string) $s->acento) ?>">
          <span class="capa-moldura">
            <?= $capa($s, 'capa') ?>
            <?php if ($selo($item) !== ''): ?>
              <span class="capa-estado"><?= e($selo($item)) ?></span>
            <?php endif; ?>
          </span>
          <span class="capa-nome"><?= e($s->nomeCurto()) ?></span>
          <span class="capa-barras">
            <progress class="barra barra-fina vs-par-cor" max="100" value="<?= $item['tu'] ?>" aria-label="<?= e($nomePar) ?>: <?= $item['tu'] ?>%"></progress>
          </span>
        </button>
      <?php endforeach; ?>
    </div>
  </section>

  <?php foreach ($doPar as $item): $dono = $parceiro; require __DIR__ . '/../layout/folha-juntar.php'; endforeach; ?>
<?php endif; ?>

<!-- Folha "Adicionar série": pesquisa no MyAnimeList (o js/app.js preenche os resultados).
     O telemóvel fala diretamente com as APIs de anime (AniList, Kitsu, Jikan) e manda os dados ao adicionar. -->
<dialog class="folha vidro folha-adicionar" id="folha-adicionar" aria-labelledby="adicionar-titulo"
        data-ja-ca="<?= e(json_encode((object) $jaCa)) ?>"
        data-url-adicionar="<?= e(url('biblioteca', 'adicionar')) ?>"
        data-ligados="<?= e(json_encode($ligados->map(fn ($l) => ['id' => $l->id, 'nome' => $l->nome])->values())) ?>">
  <div class="folha-cima">
    <h2 id="adicionar-titulo">Adicionar série</h2>
    <button type="button" class="btn-redondo vidro" id="adicionar-fechar" aria-label="Fechar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
    </button>
  </div>

  <?= csrf_campo() ?>
  <label class="pesquisa">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4 4"/></svg>
    <input type="search" id="pesquisa-texto" placeholder="Procura um anime (ex.: Frieren)" autocomplete="off" enterkeyhint="search" maxlength="80">
  </label>
  <p class="pesquisa-estado" id="pesquisa-estado" aria-live="polite">Os dados vêm do AniList e do Kitsu.</p>

  <!-- Resultados: o texto entra sempre com textContent, nunca como HTML -->
  <ul class="resultados" id="resultados"></ul>
</dialog>

<?php if ($novidades !== []): ?>
  <!-- Novidades desde a última vez: abre sozinho (js/app.js) e, ao fechar, ficam vistas -->
  <dialog class="folha vidro folha-novidades" id="folha-novidades" aria-labelledby="novidades-titulo">
    <div class="folha-cima">
      <h2 id="novidades-titulo">O que há de novo ✨</h2>
    </div>
    <div class="novidades-lista">
      <?php foreach ($novidades as $n): ?>
        <section class="novidade">
          <div class="novidade-cima">
            <h3><?= e($n['titulo']) ?></h3>
            <span class="chip"><?= e(Novidade::dataCurta($n['data'] ?? null)) ?></span>
          </div>
          <ul>
            <?php foreach ($n['itens'] ?? [] as $item): ?>
              <li><?= e($item) ?></li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endforeach; ?>
    </div>
    <!-- Sem JavaScript: formulário normal; com JavaScript fecha logo e grava em segundo plano -->
    <form method="post" action="<?= e(url('home', 'novidadesVistas')) ?>" id="form-novidades">
      <?= csrf_campo() ?>
      <button type="submit" class="btn">Fixe, bora!</button>
    </form>
  </dialog>
<?php endif; ?>

<!-- Aviso curto (adicionar, mudar estado) -->
<p class="aviso vidro" id="aviso" role="status" aria-live="polite" hidden></p>
