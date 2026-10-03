// Comportamento da app: botão de tema, pista de episódios (com seleção múltipla) e service worker.
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
  var porNumero = {};                                   // número do episódio → cartão
  cartoes.forEach(function (c) { porNumero[c.dataset.n] = c; });

  var btnMarcar = document.getElementById('btn-marcar');
  var btnModo = document.getElementById('btn-modo');
  var info = document.getElementById('acoes-info');
  var riscosTu = document.getElementById('riscos-tu');
  var pctTu = document.getElementById('pct-tu');
  var aviso = document.getElementById('aviso');

  var atual = null;          // cartão ao centro
  var multiplo = false;      // modo "Selecionar vários"
  var selecao = [];          // cartões escolhidos nesse modo
  var ocupado = false;       // evita dois pedidos ao mesmo tempo

  // ----- Painel do episódio ao centro -----
  function atualizarDetalhe(c) {
    document.getElementById('detalhe-titulo').textContent = 'Episódio ' + c.dataset.n;
    document.getElementById('detalhe-tipo').textContent = c.dataset.filler === '1' ? 'filler' : 'canónico';
    document.getElementById('detalhe-nome').textContent = c.dataset.titulo || '';
    var tu = c.dataset.tu === '1';
    document.getElementById('detalhe-tu').textContent = tu ? 'visto' : 'por ver';
    document.getElementById('detalhe-ponto-tu').classList.toggle('v', tu);
    var par = document.getElementById('detalhe-par');
    if (par) {
      var viu = c.dataset.par === '1';
      par.textContent = viu ? 'visto' : 'por ver';
      document.getElementById('detalhe-ponto-par').classList.toggle('v', viu);
    }
  }

  // ----- Texto e estado do botão principal -----
  function atualizarBotao() {
    if (multiplo) {
      var n = selecao.length;
      var todosVistos = n > 0 && selecao.every(function (c) { return c.dataset.tu === '1'; });
      btnMarcar.disabled = n === 0;
      btnMarcar.textContent = n === 0 ? 'Toca nos episódios a escolher'
        : todosVistos ? 'Desmarcar ' + n + (n === 1 ? ' episódio' : ' episódios')
        : 'Marcar ' + n + (n === 1 ? ' episódio' : ' episódios') + ' como vistos';
      btnMarcar.classList.toggle('btn-secundario', todosVistos);
      info.textContent = n === 0 ? '' : n + (n === 1 ? ' selecionado' : ' selecionados');
      return;
    }
    if (!atual) return;
    var visto = atual.dataset.tu === '1';
    btnMarcar.disabled = false;
    btnMarcar.textContent = visto ? 'Desmarcar episódio ' + atual.dataset.n : 'Marcar episódio ' + atual.dataset.n + ' como visto';
    btnMarcar.classList.toggle('btn-secundario', visto);
    info.textContent = '';
  }

  // Cartão ao centro passa a ser o ativo
  function ativar(c) {
    if (!c || c === atual) return;
    if (atual) atual.classList.remove('ativo');
    c.classList.add('ativo');
    atual = c;
    atualizarDetalhe(c);
    if (!multiplo) atualizarBotao();
  }

  // Centra um cartão na pista
  function centrar(c, suave) {
    var alvo = c.offsetLeft - (pista.clientWidth - c.offsetWidth) / 2;
    pista.scrollTo({ left: alvo, behavior: suave ? 'smooth' : 'auto' });
  }

  // O cartão mais perto do centro
  function maisAoCentro() {
    var meio = pista.scrollLeft + pista.clientWidth / 2;
    var melhor = null, menor = Infinity;
    cartoes.forEach(function (c) {
      var d = Math.abs(c.offsetLeft + c.offsetWidth / 2 - meio);
      if (d < menor) { menor = d; melhor = c; }
    });
    return melhor;
  }

  // ----- Modo "Selecionar vários" -----
  function definirModo(ligado) {
    multiplo = ligado;
    document.body.classList.toggle('modo-multiplo', ligado);
    btnModo.textContent = ligado ? 'Cancelar' : 'Selecionar vários';
    selecao.forEach(function (c) { c.classList.remove('selecionado'); });
    selecao = [];
    atualizarBotao();
  }
  btnModo.addEventListener('click', function () { definirModo(!multiplo); });

  // Tocar num cartão: no modo normal centra-o; no modo seleção junta-o ou tira-o
  cartoes.forEach(function (c) {
    c.addEventListener('click', function () {
      if (multiplo) {
        var i = selecao.indexOf(c);
        if (i === -1) selecao.push(c); else selecao.splice(i, 1);
        c.classList.toggle('selecionado', i === -1);
        atualizarBotao();
        return;
      }
      centrar(c, true);
      ativar(c);
    });
  });

  // Ao deslizar, o cartão do centro passa a ser o ativo
  var espera;
  pista.addEventListener('scroll', function () {
    clearTimeout(espera);
    espera = setTimeout(function () { ativar(maisAoCentro()); }, 90);
  }, { passive: true });

  // ----- Aviso curto -----
  var avisoTempo;
  function mostrarAviso(texto, erro) {
    aviso.textContent = texto;
    aviso.classList.toggle('erro', !!erro);
    aviso.hidden = false;
    requestAnimationFrame(function () { aviso.classList.add('mostrar'); });
    clearTimeout(avisoTempo);
    avisoTempo = setTimeout(function () {
      aviso.classList.remove('mostrar');
      setTimeout(function () { aviso.hidden = true; }, 300);
    }, 2200);
  }

  // ----- Aplicar no ecrã o que o servidor confirmou -----
  function aplicar(resposta) {
    resposta.numeros.forEach(function (n) {
      var c = porNumero[n];
      if (!c) return;
      c.dataset.tu = resposta.visto ? '1' : '0';
      c.querySelector('.ponto-tu').classList.toggle('v', resposta.visto);
      c.querySelector('.ep-estado-tu span').textContent = 'tu · ' + (resposta.visto ? 'visto' : 'por ver');
      var risco = riscosTu.children[n - 1];
      if (risco) risco.classList.toggle('v', resposta.visto);
    });
    pctTu.textContent = resposta.pct + '%';
    if (atual) atualizarDetalhe(atual);
  }

  // ----- Enviar: um episódio (modo normal) ou a seleção -----
  form.addEventListener('submit', function (ev) {
    if (!window.fetch) return;            // browser muito antigo: segue o formulário normal
    ev.preventDefault();
    if (ocupado) return;

    var dados = new FormData();
    dados.append('_csrf', form.querySelector('input[name="_csrf"]').value);
    dados.append('serie', form.dataset.serie);

    if (multiplo) {
      if (selecao.length === 0) return;
      var todosVistos = selecao.every(function (c) { return c.dataset.tu === '1'; });
      dados.append('acao', todosVistos ? 'desmarcar' : 'marcar');
      selecao.forEach(function (c) { dados.append('episodio_ids[]', c.dataset.id); });
    } else {
      if (!atual) return;
      dados.append('acao', atual.dataset.tu === '1' ? 'desmarcar' : 'marcar');
      dados.append('episodio_ids[]', atual.dataset.id);
    }

    ocupado = true;
    btnMarcar.disabled = true;
    fetch(form.action, { method: 'POST', body: dados, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (resposta) {
        if (!resposta.ok) { mostrarAviso(resposta.mensagem || 'Não foi possível guardar.', true); return; }
        aplicar(resposta);
        mostrarAviso(resposta.mensagem);
        if (multiplo) definirModo(false);
      })
      .catch(function () { mostrarAviso('Sem ligação. Tenta outra vez.', true); })
      .then(function () { ocupado = false; atualizarBotao(); });
  });

  // ----- Abertura: o cartão indicado pelo servidor -----
  var inicial = pista.querySelector('.ep[data-inicial]') || cartoes[0];
  centrar(inicial, false);
  ativar(inicial);
  atualizarBotao();
})();

// ---------- Botão "Instalar app" no hero ----------
(function () {
  var botao = document.getElementById('btn-instalar');
  if (!botao) return;

  // Já está a correr como app instalada: o botão não faz sentido
  if (window.matchMedia('(display-mode: standalone)').matches) return;

  var aviso = null;   // o evento do Chrome, guardado para usar no clique
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();          // em vez da mini-barra automática, usamos o nosso botão
    aviso = e;
    botao.hidden = false;
  });

  botao.addEventListener('click', function () {
    if (!aviso) return;
    aviso.prompt();              // abre a janela de instalação do Android
    aviso.userChoice.then(function () { aviso = null; botao.hidden = true; });   // só dá para usar uma vez
  });

  window.addEventListener('appinstalled', function () { botao.hidden = true; });
})();

// ---------- App instalável: regista o service worker ----------
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('sw.js').catch(function () { /* sem SW a app funciona na mesma */ });
  });
}
