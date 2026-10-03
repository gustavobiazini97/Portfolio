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

  // Frase por baixo dos avatares (igual à da view serie/ver.php)
  var nomePar = pista.dataset.parNome || '';
  function rotulo(tu, par) {
    if (!nomePar) return tu ? 'visto' : 'por ver';
    if (tu && par) return 'os dois viram';
    if (tu) return 'falta ' + nomePar;
    if (par) return nomePar + ' já viu';
    return 'por ver';
  }

  // ----- Painel do episódio ao centro -----
  function atualizarDetalhe(c) {
    document.getElementById('detalhe-titulo').textContent = 'Episódio ' + c.dataset.n;
    var tipo = document.getElementById('detalhe-tipo');
    tipo.textContent = c.dataset.filler === '1' ? 'filler' : 'canónico';
    tipo.classList.toggle('etiqueta-filler', c.dataset.filler === '1');   // etiqueta escura e sólida
    document.getElementById('detalhe-nome').textContent = c.dataset.titulo || '';
    var tu = c.dataset.tu === '1';
    document.getElementById('detalhe-tu').textContent = tu ? 'visto' : 'por ver';
    document.getElementById('detalhe-av-tu').classList.toggle('v', tu);
    var par = document.getElementById('detalhe-par');
    if (par) {
      var viu = c.dataset.par === '1';
      par.textContent = viu ? 'visto' : 'por ver';
      document.getElementById('detalhe-av-par').classList.toggle('v', viu);
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
    atualizarBotaoComent();
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
      c.classList.toggle('visto', resposta.visto);          // cor do cartão (sálvia / degradê se o par também viu)
      c.querySelector('.ep-av-tu').classList.toggle('v', resposta.visto);   // o teu avatar acende ou esbate
      c.querySelector('.ep-rotulo').textContent = rotulo(resposta.visto, c.dataset.par === '1');
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

  // ----- Comentários do episódio ao centro -----
  var btnComent = document.getElementById('btn-coment');
  var btnComentTxt = document.getElementById('btn-coment-txt');
  var folha = document.getElementById('folha-coment');
  var lista = document.getElementById('folha-lista');
  var vazio = document.getElementById('folha-vazio');
  var formComent = document.getElementById('folha-form');
  var texto = document.getElementById('folha-texto');
  var csrf = form.querySelector('input[name="_csrf"]').value;
  var aberto = null;   // cartão cujos comentários estão na folha

  // "Comentários" ou "Comentários · 3"
  function atualizarBotaoComent() {
    if (!btnComentTxt || !atual) return;
    var n = parseInt(atual.dataset.coment || '0', 10);
    btnComentTxt.textContent = n ? 'Comentários · ' + n : 'Comentários';
  }

  // Atualiza o número no cartão (balão) e no botão
  function definirContagem(cartao, n) {
    cartao.dataset.coment = n;
    var balao = cartao.querySelector('.ep-coment');
    balao.hidden = n === 0;
    balao.querySelector('span').textContent = n;
    if (cartao === atual) atualizarBotaoComent();
  }

  // Desenha a lista; o texto entra sempre com textContent (nunca como HTML)
  function desenhar(comentarios) {
    lista.innerHTML = '';
    vazio.hidden = comentarios.length > 0;
    comentarios.forEach(function (c) {
      var li = document.createElement('li'); li.className = 'coment';

      var av = document.createElement('span');
      av.className = 'avatar ' + (c.meu ? 'avatar-tu' : 'avatar-par');
      if (c.foto) { var img = document.createElement('img'); img.src = c.foto; img.alt = ''; av.appendChild(img); }
      else av.textContent = c.inicial;

      var corpo = document.createElement('div'); corpo.className = 'coment-corpo';
      var cima = document.createElement('div'); cima.className = 'coment-cima';
      var nome = document.createElement('b'); nome.textContent = c.meu ? 'Tu' : c.autor;
      var quando = document.createElement('span'); quando.textContent = c.quando;
      cima.appendChild(nome); cima.appendChild(quando);
      if (c.meu) {
        var apagar = document.createElement('button');
        apagar.type = 'button'; apagar.className = 'coment-apagar'; apagar.textContent = 'apagar';
        apagar.addEventListener('click', function () { enviar(folha.dataset.urlApagar, { id: c.id }); });
        cima.appendChild(apagar);
      }
      var p = document.createElement('p'); p.className = 'coment-texto'; p.textContent = c.texto;
      corpo.appendChild(cima); corpo.appendChild(p);

      li.appendChild(av); li.appendChild(corpo);
      lista.appendChild(li);
    });
    lista.scrollTop = lista.scrollHeight;   // o mais recente fica à vista
    if (aberto) definirContagem(aberto, comentarios.length);
  }

  // POST (comentar/apagar) → a resposta traz a lista atualizada
  function enviar(urlAcao, campos) {
    var dados = new FormData();
    dados.append('_csrf', csrf);
    Object.keys(campos).forEach(function (k) { dados.append(k, campos[k]); });
    return fetch(urlAcao, { method: 'POST', body: dados, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (resposta) {
        if (!resposta.ok) { mostrarAviso(resposta.mensagem || 'Não foi possível guardar.', true); return false; }
        desenhar(resposta.comentarios);
        return true;
      })
      .catch(function () { mostrarAviso('Sem ligação. Tenta outra vez.', true); return false; });
  }

  if (btnComent && folha) {
    btnComent.addEventListener('click', function () {
      if (!atual) return;
      aberto = atual;
      document.getElementById('folha-titulo').textContent = 'Episódio ' + atual.dataset.n;
      lista.innerHTML = ''; vazio.hidden = true;
      folha.showModal();
      fetch(btnComent.dataset.url + '&episodio=' + encodeURIComponent(atual.dataset.id), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (resposta) { if (resposta.ok) desenhar(resposta.comentarios); })
        .catch(function () { mostrarAviso('Sem ligação. Tenta outra vez.', true); });
    });

    formComent.addEventListener('submit', function (ev) {
      ev.preventDefault();
      if (!aberto || !texto.value.trim()) return;
      enviar(folha.dataset.urlComentar, { episodio_id: aberto.dataset.id, texto: texto.value })
        .then(function (ok) { if (ok) texto.value = ''; });
    });

    // Enter envia; Shift+Enter muda de linha (no teclado do telemóvel o botão é a forma principal)
    texto.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' && !ev.shiftKey) { ev.preventDefault(); formComent.requestSubmit(); }
    });

    document.getElementById('folha-fechar').addEventListener('click', function () { folha.close(); });
    folha.addEventListener('click', function (ev) { if (ev.target === folha) folha.close(); });   // tocar fora fecha
  }

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

// ---------- Foto de perfil: reduz no telemóvel e envia logo ----------
(function () {
  var input = document.getElementById('input-foto');
  var form = document.getElementById('form-foto');
  if (!input || !form || !window.fetch) return;   // sem fetch: fica o botão "Enviar foto" do <noscript>

  var rotulo = form.querySelector('.foto-escolher');
  var aviso = document.getElementById('aviso');

  function avisar(texto, erro) {
    aviso.textContent = texto;
    aviso.classList.toggle('erro', !!erro);
    aviso.hidden = false;
    requestAnimationFrame(function () { aviso.classList.add('mostrar'); });
    setTimeout(function () { aviso.classList.remove('mostrar'); }, 2400);
  }

  // Desenha a foto num canvas com no máximo 1024px de lado e devolve um JPEG (~150 KB em vez de 5–10 MB)
  function reduzir(ficheiro) {
    return new Promise(function (resolver, rejeitar) {
      var img = new Image();
      var url = URL.createObjectURL(ficheiro);
      img.onload = function () {
        var escala = Math.min(1, 1024 / Math.max(img.naturalWidth, img.naturalHeight));
        var canvas = document.createElement('canvas');
        canvas.width = Math.round(img.naturalWidth * escala);
        canvas.height = Math.round(img.naturalHeight * escala);
        canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);   // a rotação da câmara já vem aplicada
        URL.revokeObjectURL(url);
        canvas.toBlob(function (blob) { blob ? resolver(blob) : rejeitar(); }, 'image/jpeg', 0.9);
      };
      img.onerror = function () { URL.revokeObjectURL(url); rejeitar(); };
      img.src = url;
    });
  }

  input.addEventListener('change', function () {
    var ficheiro = input.files && input.files[0];
    if (!ficheiro) return;
    rotulo.classList.add('a-enviar');

    reduzir(ficheiro)
      .catch(function () { return ficheiro; })   // se o browser não conseguir reduzir, manda o original
      .then(function (blob) {
        var dados = new FormData();
        dados.append('_csrf', form.querySelector('input[name="_csrf"]').value);
        dados.append('foto', blob, 'foto.jpg');
        return fetch(form.action, { method: 'POST', body: dados, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
      })
      .then(function (r) { return r.json(); })
      .then(function (resposta) {
        if (!resposta.ok) { avisar(resposta.mensagem, true); return; }
        location.reload();   // mostra a foto nova (e o "Remover foto")
      })
      .catch(function () { avisar('Não foi possível enviar a foto.', true); })
      .then(function () { rotulo.classList.remove('a-enviar'); input.value = ''; });
  });
})();

// ---------- Convidar o par: partilha o link da app ----------
document.querySelectorAll('[data-convidar]').forEach(function (botao) {
  botao.addEventListener('click', function () {
    var link = botao.dataset.convidar;
    var texto = 'Cria a tua conta no Anime a Dois para vermos quem vai à frente 👀';
    if (navigator.share) {
      navigator.share({ title: 'Anime a Dois', text: texto, url: link }).catch(function () {});
    } else if (navigator.clipboard) {
      navigator.clipboard.writeText(link).then(function () { botao.textContent = 'Link copiado'; });
    } else {
      window.prompt('Copia o link:', link);
    }
  });
});

// ---------- Notificações neste telemóvel (perfil) ----------
(function () {
  var caixa = document.getElementById('notif-telemovel');
  if (!caixa) return;
  var estado = document.getElementById('notif-estado');
  var btnAtivar = document.getElementById('btn-notif-ativar');
  var btnTestar = document.getElementById('btn-notif-testar');
  var csrf = document.querySelector('input[name="_csrf"]').value;

  // Sem suporte (browser antigo, ou iPhone fora da app instalada)
  if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
    estado.textContent = 'Este browser não suporta notificações. Usa a app instalada no Chrome.';
    btnAtivar.disabled = true;
    return;
  }

  // A chave VAPID vem em base64url; o browser quer bytes
  function chaveEmBytes(b64) {
    var pad = '='.repeat((4 - b64.length % 4) % 4);
    var bin = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
    var bytes = new Uint8Array(bin.length);
    for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
    return bytes;
  }

  function post(url, campos) {
    var dados = new FormData();
    dados.append('_csrf', csrf);
    Object.keys(campos).forEach(function (k) { dados.append(k, campos[k]); });
    return fetch(url, { method: 'POST', body: dados, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  var subAtual = null;
  function mostrar(sub) {
    subAtual = sub;
    btnAtivar.textContent = sub ? 'Desligar neste telemóvel' : 'Ativar neste telemóvel';
    if (Notification.permission === 'denied') {
      estado.textContent = 'Bloqueaste as notificações. Ativa-as no Chrome: ⋮ → Definições → Notificações.';
    } else if (sub) {
      estado.textContent = 'Notificações ativas neste telemóvel.';
    }
  }

  navigator.serviceWorker.ready
    .then(function (reg) { return reg.pushManager.getSubscription(); })
    .then(mostrar)
    .catch(function () {});

  btnAtivar.addEventListener('click', function () {
    btnAtivar.disabled = true;
    navigator.serviceWorker.ready.then(function (reg) {
      // Já ativo → desligar
      if (subAtual) {
        var endpoint = subAtual.endpoint;
        return subAtual.unsubscribe()
          .then(function () { return post(caixa.dataset.urlDesubscrever, { endpoint: endpoint }); })
          .then(function (r) { estado.textContent = r.mensagem; mostrar(null); });
      }
      // Pede a permissão do Android e subscreve
      return Notification.requestPermission().then(function (perm) {
        if (perm !== 'granted') { mostrar(null); return; }
        return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: chaveEmBytes(caixa.dataset.vapid) })
          .then(function (sub) {
            return post(caixa.dataset.urlSubscrever, { subscricao: JSON.stringify(sub) })
              .then(function (r) { mostrar(sub); estado.textContent = r.mensagem; });
          });
      });
    })
      .catch(function () { estado.textContent = 'Não foi possível ativar. Tenta outra vez.'; })
      .then(function () { btnAtivar.disabled = false; });
  });

  btnTestar.addEventListener('click', function () {
    btnTestar.disabled = true;
    post(caixa.dataset.urlTestar, {})
      .then(function (r) { estado.textContent = r.mensagem; })
      .catch(function () { estado.textContent = 'Sem ligação. Tenta outra vez.'; })
      .then(function () { btnTestar.disabled = false; });
  });
})();

// ---------- App instalável: regista o service worker ----------
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('sw.js').catch(function () { /* sem SW a app funciona na mesma */ });
  });
}
