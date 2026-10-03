// Comportamento da app: botão de tema e pista de episódios.
// A app funciona sem JavaScript (formulários normais); isto só torna tudo mais suave.

// ---------- Tema claro / escuro ----------
document.querySelectorAll('[data-alternar-tema]').forEach(function (botao) {
  botao.addEventListener('click', function () {
    var raiz = document.documentElement;
    var novo = raiz.getAttribute('data-tema') === 'escuro' ? 'claro' : 'escuro';
    raiz.setAttribute('data-tema', novo);
    try { localStorage.setItem('tema', novo); } catch (e) { /* sem armazenamento: só nesta página */ }

    // A barra do sistema acompanha o fundo
    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', novo === 'escuro' ? '#0E1116' : '#F5F3F8');
  });
});

// ---------- Pista de episódios (página da série) ----------
(function () {
  var pista = document.querySelector('.pista');
  var form = document.getElementById('form-marcar');
  if (!pista || !form) return;

  var cartoes = Array.prototype.slice.call(pista.querySelectorAll('.ep'));
  var campoId = form.querySelector('input[name="episodio_id"]');
  var botao = form.querySelector('button[type="submit"]');
  var atual = null;

  // Marca um cartão como ativo e prepara o botão "Marcar / Desmarcar"
  function ativar(cartao) {
    if (!cartao || cartao === atual) return;
    if (atual) atual.classList.remove('ativo');
    cartao.classList.add('ativo');
    atual = cartao;
    campoId.value = cartao.dataset.id;
    botao.textContent = cartao.dataset.tu === '1'
      ? 'Desmarcar episódio ' + cartao.dataset.n
      : 'Marcar episódio ' + cartao.dataset.n + ' como visto';
    botao.classList.toggle('btn-secundario', cartao.dataset.tu === '1');
  }

  // Centra um cartão na pista (sem animação na abertura da página)
  function centrar(cartao, suave) {
    var alvo = cartao.offsetLeft - (pista.clientWidth - cartao.offsetWidth) / 2;
    pista.scrollTo({ left: alvo, behavior: suave ? 'smooth' : 'auto' });
  }

  // O cartão mais perto do centro da pista
  function maisAoCentro() {
    var meio = pista.scrollLeft + pista.clientWidth / 2;
    var melhor = null, menor = Infinity;
    cartoes.forEach(function (c) {
      var d = Math.abs(c.offsetLeft + c.offsetWidth / 2 - meio);
      if (d < menor) { menor = d; melhor = c; }
    });
    return melhor;
  }

  // Tocar num cartão: centra-o e ativa-o
  cartoes.forEach(function (c) {
    c.addEventListener('click', function () { centrar(c, true); ativar(c); });
  });

  // Ao deslizar, o cartão que fica ao centro passa a ser o ativo
  var espera;
  pista.addEventListener('scroll', function () {
    clearTimeout(espera);
    espera = setTimeout(function () { ativar(maisAoCentro()); }, 90);
  }, { passive: true });

  // Abertura: o cartão indicado pelo servidor (o seguinte ao teu, ou o que acabaste de marcar)
  var inicial = pista.querySelector('.ep[data-inicial]') || cartoes[0];
  centrar(inicial, false);
  ativar(inicial);
})();
