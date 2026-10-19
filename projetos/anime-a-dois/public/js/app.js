// Comportamento da app: botão de tema, pista de episódios (com seleção múltipla), biblioteca
// (pesquisa e adicionar séries) e service worker.
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
  var nomes = [];            // nomes de quem vê a série contigo, na ordem de data-com
  try { nomes = JSON.parse(pista.dataset.companheiros || '[]'); } catch (e) { /* sem companheiros */ }
  function juntar(lista) { return lista.length === 1 ? lista[0] : (lista.length === 2 ? lista[0] + ' e ' + lista[1] : lista.length + ' pessoas'); }
  function flagsDe(c) { return nomes.length ? (c.dataset.com || '').split(',').map(function (x) { return x === '1'; }) : []; }
  function rotulo(tu, com) {
    if (!nomes.length) return tu ? 'visto' : 'por ver';
    var viram = [], faltam = [];
    com.forEach(function (viu, i) { (viu ? viram : faltam).push(nomes[i]); });
    if (tu && !faltam.length) return nomes.length === 1 ? 'os dois viram' : 'todos viram';
    if (tu) return 'falta ' + juntar(faltam);
    if (viram.length) return juntar(viram) + (viram.length === 1 ? ' já viu' : ' já viram');
    return 'por ver';
  }

  // ----- Painel do episódio ao centro -----
  function atualizarDetalhe(c) {
    document.getElementById('detalhe-titulo').textContent = 'Episódio ' + c.dataset.n;
    var tipo = document.getElementById('detalhe-tipo');
    var especial = c.dataset.filler === '1' ? 'filler' : (c.dataset.recap === '1' ? 'recap' : '');
    tipo.textContent = especial || 'canónico';
    tipo.classList.toggle('etiqueta-filler', especial !== '');   // filler e recap: etiqueta escura e sólida
    document.getElementById('detalhe-nome').textContent = c.dataset.titulo || '';
    var tu = c.dataset.tu === '1';
    document.getElementById('detalhe-tu').textContent = tu ? 'visto' : 'por ver';
    document.getElementById('detalhe-av-tu').classList.toggle('v', tu);
    flagsDe(c).forEach(function (viu, i) {
      document.getElementById('detalhe-c' + i).textContent = viu ? 'visto' : 'por ver';
      document.getElementById('detalhe-av-c' + i).classList.toggle('v', viu);
    });
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
      c.querySelector('.ep-rotulo').textContent = rotulo(resposta.visto, flagsDe(c));
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
        // Marcar com impacto: os cartões viram, partículas, vibração e ❤️ se os companheiros já tinham visto
        if (resposta.visto) festejarCartoes(resposta.numeros.map(function (n) { return porNumero[n]; }).filter(Boolean), atual);
        // Acabaste a série agora? Celebração com o cartão para partilhar
        if (resposta.acabou) celebrarSerie(form.dataset.celebrar, resposta.comigo || []);
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
    var texto = botao.dataset.texto || 'Cria a tua conta no Anime a Dois para vermos quem vai à frente 👀';
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
      .then(function (r) {
        // Página de erro em vez de JSON: diz o código, para se perceber se foi o servidor
        if ((r.headers.get('Content-Type') || '').indexOf('json') === -1) {
          var e = new Error('o servidor respondeu com erro ' + r.status); e.name = 'Servidor'; throw e;
        }
        return r.json();
      });
  }

  // Subscreve o push. Se o telemóvel tiver uma subscrição antiga (outra chave, outra instalação),
  // o browser recusa com InvalidStateError: apaga-se a antiga e tenta-se outra vez.
  function subscrever(reg) {
    var opcoes = { userVisibleOnly: true, applicationServerKey: chaveEmBytes(caixa.dataset.vapid) };
    return reg.pushManager.subscribe(opcoes).catch(function (e) {
      if (e.name !== 'InvalidStateError') throw e;
      return reg.pushManager.getSubscription()
        .then(function (antiga) { return antiga ? antiga.unsubscribe() : null; })
        .then(function () { return reg.pushManager.subscribe(opcoes); });
    });
  }

  // O motivo real da falha, em vez de um "tenta outra vez" que não diz nada
  function explicarErro(e) {
    var nome = (e && e.name) || '';
    var msg = (e && e.message) || '';
    if (nome === 'NotAllowedError') {
      return 'As notificações estão bloqueadas para esta app. Ativa-as em Definições → Apps → Chrome → Notificações (e no site, no Chrome: ⋮ → Definições → Notificações).';
    }
    if (nome === 'AbortError' || /push service/i.test(msg)) {
      return 'O serviço de notificações do telemóvel não respondeu. Confirma que estás no Chrome (não noutro browser), com a Google Play Store/Serviços Google a funcionar e sem poupança de bateria/dados para o Chrome. Erro: ' + (msg || nome);
    }
    if (nome === 'NotSupportedError') {
      return 'Este browser não suporta notificações. Abre a app no Chrome. Erro: ' + (msg || nome);
    }
    return 'Não foi possível ativar' + (nome || msg ? ' (' + [nome, msg].filter(Boolean).join(': ') + ')' : '') + '. Tenta outra vez.';
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
        return subscrever(reg)
          .then(function (sub) {
            return post(caixa.dataset.urlSubscrever, { subscricao: JSON.stringify(sub) })
              .then(function (r) { mostrar(r.ok ? sub : null); estado.textContent = r.mensagem; });
          });
      });
    })
      .catch(function (e) { estado.textContent = explicarErro(e); })
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

// ---------- Filas que deslizam (capas do Início, separadores das Estatísticas) ----------
// A série aberta fica à vista, mesmo que esteja lá para o fim da fila
document.querySelectorAll('#fila, .separadores').forEach(function (fila) {
  var atual = fila.querySelector('[aria-current="page"]');
  if (atual) fila.scrollLeft = atual.offsetLeft - fila.offsetLeft - (fila.clientWidth - atual.offsetWidth) / 2;
});

// ---------- Formulários que pedem confirmação (tirar série, recusar proposta) ----------
document.querySelectorAll('form[data-confirmar]').forEach(function (form) {
  form.addEventListener('submit', function (ev) {
    if (!window.confirm(form.dataset.confirmar)) ev.preventDefault();
  });
});

// ---------- APIs de anime, chamadas diretamente do browser ----------
// O servidor (alwaysdata) não consegue ligar a estas APIs, por isso é o telemóvel que vai buscar
// os dados e depois os entrega ao servidor (que os valida em app/models/DadosAnime.php).
//   AniList → pesquisa, capa, episódios, em emissão (GraphQL, rápido)
//   Kitsu   → títulos dos episódios
//   Jikan   → fillers e recaps (MyAnimeList). Em baixo desde ago/2026: tenta-se depressa e,
//             se falhar, fica 30 min sem tentar; a página da série volta a tentar mais tarde.
var Anime = (function () {
  var apis = {};
  try { apis = JSON.parse(document.body.dataset.apis || '{}'); } catch (e) { /* sem APIs configuradas */ }

  var ultimo = {};   // hora do último pedido a cada API (para não passar os limites de cada uma)

  function esperar(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

  // fetch com tempo máximo (sem resposta em `ms`, desiste)
  function comLimite(url, opcoes, ms) {
    var ctrl = window.AbortController ? new AbortController() : null;
    var t = ctrl ? setTimeout(function () { ctrl.abort(); }, ms) : null;
    if (ctrl) opcoes.signal = ctrl.signal;
    return fetch(url, opcoes).then(function (r) { clearTimeout(t); return r; }, function (e) { clearTimeout(t); throw e; });
  }

  // Pedido a uma API com intervalo mínimo entre pedidos e novas tentativas (429, 5xx, sem resposta)
  //   qual: 'anilist' | 'kitsu' | 'jikan'; cfg: { intervalo, ms, tentativas }
  function pedir(qual, url, opcoes, cfg, tentativa) {
    tentativa = tentativa || 1;
    return esperar(Math.max(0, (ultimo[qual] || 0) + cfg.intervalo - Date.now()))
      .then(function () {
        ultimo[qual] = Date.now();
        return comLimite(url, Object.assign({}, opcoes), cfg.ms);
      })
      .then(function (r) {
        if (r.status === 429 || r.status >= 500) { var e = new Error(qual + ' ' + r.status); e.repetir = true; throw e; }
        if (!r.ok) throw new Error('O serviço de anime respondeu com erro ' + r.status + '.');
        return r.json();
      })
      .catch(function (e) {
        var repetir = e.repetir || e.name === 'AbortError' || e instanceof TypeError;   // TypeError = sem rede
        if (repetir && tentativa < cfg.tentativas) {
          return esperar(800 * tentativa).then(function () { return pedir(qual, url, opcoes, cfg, tentativa + 1); });
        }
        if (repetir) throw new Error('O serviço de anime não está a responder. Tenta daqui a um bocadinho.');
        throw e;
      });
  }

  // ----- AniList (GraphQL) -----
  var CAMPOS = 'idMal title { english romaji } coverImage { extraLarge large } episodes status format ' +
               'seasonYear startDate { year } duration nextAiringEpisode { episode }';
  var TIPOS = { TV: 'TV', TV_SHORT: 'TV', MOVIE: 'Movie', OVA: 'OVA', ONA: 'ONA', SPECIAL: 'Special', MUSIC: 'Music' };

  function anilist(query, variaveis, tentativas) {
    return pedir('anilist', apis.anilist, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ query: query, variables: variaveis })
    }, { intervalo: 350, ms: 10000, tentativas: tentativas || 3 }).then(function (json) {
      if (json.errors && !json.data) throw new Error('A pesquisa falhou.');
      return json.data;
    });
  }

  // Uma série do AniList → os campos que a app usa (o servidor volta a validar)
  function resumo(m) {
    var nome = (m.title && (m.title.english || m.title.romaji)) || '';
    var original = m.title && m.title.romaji;
    var emissao = m.status === 'RELEASING' || m.status === 'NOT_YET_RELEASED';
    // Em emissão sem total anunciado: os que já saíram (o próximo a sair menos um)
    var eps = m.episodes || (m.nextAiringEpisode ? m.nextAiringEpisode.episode - 1 : null);
    return {
      mal_id: m.idMal,
      nome: nome,
      original: original && original !== nome ? original : null,
      capa: (m.coverImage && (m.coverImage.extraLarge || m.coverImage.large)) || null,
      tipo: TIPOS[m.format] || m.format || null,
      episodios: eps || null,
      ano: m.seasonYear || (m.startDate && m.startDate.year) || null,
      em_emissao: emissao,
      minutos: m.duration || null
    };
  }

  // ----- Kitsu: títulos dos episódios (páginas de 20) -----
  function kitsu(url) {
    return pedir('kitsu', url, { headers: { 'Accept': 'application/vnd.api+json' } }, { intervalo: 150, ms: 10000, tentativas: 3 });
  }
  function episodiosKitsu(malId, aoAvancar) {
    var base = apis.kitsu;
    return kitsu(base + '/mappings?filter[externalSite]=myanimelist/anime&filter[externalId]=' + malId + '&include=item')
      .then(function (json) {
        var item = (json.included || []).filter(function (x) { return x.type === 'anime'; })[0];
        if (!item) throw new Error('Sem episódios no Kitsu.');
        var lista = [];
        function pagina(url, n) {
          return kitsu(url).then(function (json) {
            (json.data || []).forEach(function (ep) {
              var a = ep.attributes || {};
              if (a.number) lista.push({ n: a.number, titulo: a.canonicalTitle || null, filler: false, recap: false });
            });
            if (aoAvancar) aoAvancar(lista.length);
            var seguinte = json.links && json.links.next;
            return seguinte && n < 200 ? pagina(seguinte, n + 1) : lista;
          });
        }
        return pagina(base + '/anime/' + item.id + '/episodes?page[limit]=20&page[offset]=0&sort=number&fields[episodes]=number,canonicalTitle', 1);
      });
  }

  // ----- Jikan: títulos + filler + recap (páginas de 100) -----
  var CHAVE_JIKAN = 'jikan-em-baixo-ate';
  function jikanEmBaixo() {
    try { return Date.now() < +(localStorage.getItem(CHAVE_JIKAN) || 0); } catch (e) { return false; }
  }
  function episodiosJikan(malId, aoAvancar) {
    if (!apis.jikan || jikanEmBaixo()) return Promise.reject(new Error('Jikan em baixo'));
    var lista = [];
    function pagina(p) {
      return pedir('jikan', apis.jikan + '/anime/' + malId + '/episodes?page=' + p, { headers: { 'Accept': 'application/json' } },
                   { intervalo: 400, ms: p === 1 ? 6000 : 12000, tentativas: p === 1 ? 1 : 3 })
        .then(function (json) {
          (json.data || []).forEach(function (ep) {
            lista.push({ n: ep.mal_id, titulo: ep.title || null, filler: !!ep.filler, recap: !!ep.recap });
          });
          if (aoAvancar) aoAvancar(lista.length);
          return json.pagination && json.pagination.has_next_page && p < 30 ? pagina(p + 1) : lista;
        });
    }
    return pagina(1).catch(function (e) {
      // Não respondeu: durante 30 min nem se tenta (poupa 6 s a cada adicionar)
      try { localStorage.setItem(CHAVE_JIKAN, String(Date.now() + 30 * 60000)); } catch (x) { /* só nesta página */ }
      throw e;
    });
  }

  return {
    // Pesquisa: até 12 séries com id do MyAnimeList, sem conteúdo adulto
    pesquisar: function (q) {
      return anilist('query ($q: String) { Page(perPage: 15) { media(search: $q, type: ANIME, isAdult: false, sort: SEARCH_MATCH) { ' + CAMPOS + ' } } }', { q: q }, 2)
        .then(function (data) {
          var vistos = {};
          return ((data.Page && data.Page.media) || []).map(resumo).filter(function (r) {
            if (!r.mal_id || vistos[r.mal_id]) return false;   // sem id do MyAnimeList não dá para guardar
            vistos[r.mal_id] = true;
            return true;
          }).slice(0, 12);
        });
    },
    // Uma série pelo id do MyAnimeList
    anime: function (malId) {
      return anilist('query ($id: Int) { Media(idMal: $id, type: ANIME) { ' + CAMPOS + ' } }', { id: +malId })
        .then(function (data) {
          if (!data.Media) throw new Error('Não encontrei essa série.');
          return resumo(data.Media);
        });
    },
    // Episódios: Jikan (com fillers) → Kitsu (só títulos) → nenhum. Devolve { fonte, lista }.
    // soComFillers: só interessa o Jikan (a série já tem títulos; faltam os fillers)
    episodios: function (malId, aoAvancar, soComFillers) {
      return episodiosJikan(malId, aoAvancar)
        .then(function (lista) { return { fonte: 'jikan', lista: lista }; })
        .catch(function (e) {
          if (soComFillers) throw e;
          return episodiosKitsu(malId, aoAvancar)
            .then(function (lista) { return { fonte: 'kitsu', lista: lista }; })
            .catch(function () { return { fonte: 'nenhuma', lista: [] }; });   // fica com o número de episódios, sem títulos
        });
    },
    comLimite: comLimite
  };
})();

// POST ao servidor com o token CSRF; lê JSON (uma página de erro em HTML vira uma mensagem clara)
function enviarAoServidor(url, campos, ms) {
  var dados = new FormData();
  var csrf = document.querySelector('input[name="_csrf"]');
  if (csrf) dados.append('_csrf', csrf.value);
  Object.keys(campos).forEach(function (k) { dados.append(k, campos[k]); });
  return Anime.comLimite(url, { method: 'POST', body: dados, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }, ms || 60000)
    .then(function (r) {
      if ((r.headers.get('Content-Type') || '').indexOf('json') === -1) throw new Error('O servidor respondeu com erro ' + r.status + '.');
      return r.json();
    })
    .then(function (resposta) {
      if (!resposta.ok) throw new Error(resposta.mensagem || 'Não foi possível guardar.');
      return resposta;
    });
}

// ---------- Folha "Adicionar série": pesquisa no MyAnimeList ----------
(function () {
  var folha = document.getElementById('folha-adicionar');
  var abrir = document.getElementById('btn-adicionar');
  if (!folha || !abrir || !window.fetch || !window.Promise) return;

  var input = document.getElementById('pesquisa-texto');
  var estado = document.getElementById('pesquisa-estado');
  var lista = document.getElementById('resultados');
  var ligados = [];       // o par e os amigos, a quem se pode convidar: [{id, nome}]
  try { ligados = JSON.parse(folha.dataset.ligados || '[]'); } catch (e) { /* sem convites */ }
  var textoInicial = estado.textContent;
  var jaCa = {};          // mal_id → 'biblioteca' | 'convite' (vem do servidor na página)
  try { jaCa = JSON.parse(folha.dataset.jaCa || '{}'); } catch (e) { /* sem marcas "já está" */ }

  var espera = null;      // temporizador do "parou de escrever"
  var pedido = 0;         // número do último pedido: respostas antigas são ignoradas
  var ocupado = false;    // a adicionar: bloqueia os outros botões

  function mostrarEstado(texto, erro) {
    estado.textContent = texto;
    estado.classList.toggle('erro', !!erro);
  }

  abrir.addEventListener('click', function () {
    folha.showModal();
    input.focus();
  });
  document.getElementById('adicionar-fechar').addEventListener('click', function () { if (!ocupado) folha.close(); });
  folha.addEventListener('click', function (ev) { if (ev.target === folha && !ocupado) folha.close(); });   // tocar fora fecha
  folha.addEventListener('cancel', function (ev) { if (ocupado) ev.preventDefault(); });                    // "voltar" a meio de adicionar

  // Pesquisa 350 ms depois de parar de escrever
  input.addEventListener('input', function () {
    clearTimeout(espera);
    var q = input.value.trim();
    if (q.length < 2) { pedido++; lista.innerHTML = ''; mostrarEstado(textoInicial); return; }
    espera = setTimeout(function () { pesquisar(q); }, 350);
  });
  input.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter') { ev.preventDefault(); clearTimeout(espera); if (input.value.trim().length >= 2) pesquisar(input.value.trim()); input.blur(); }
  });

  function pesquisar(q) {
    var meu = ++pedido;
    mostrarEstado('A procurar…');
    Anime.pesquisar(q)
      .then(function (resultados) {
        if (meu !== pedido) return;               // entretanto escreveu outra coisa
        desenhar(resultados);
        mostrarEstado(resultados.length ? '' : 'Nada encontrado. Experimenta o nome em inglês ou japonês.');
      })
      .catch(function (erro) {
        if (meu === pedido) mostrarEstado(erro.message || 'A pesquisa falhou.', true);
      });
  }

  // "Sousou no Frieren · TV · 2023 · 28 ep."
  function info(r) {
    var partes = [];
    if (r.original) partes.push(r.original);
    if (r.tipo) partes.push(r.tipo);
    if (r.ano) partes.push(r.ano);
    if (r.episodios) partes.push(r.episodios + ' ep.');
    if (r.em_emissao) partes.push('em emissão');
    return partes.join(' · ');
  }

  // Cada resultado: capa, nome, info e os botões (o texto entra sempre com textContent)
  function desenhar(resultados) {
    lista.innerHTML = '';
    resultados.forEach(function (r) {
      var li = document.createElement('li'); li.className = 'resultado';

      var capa = document.createElement(r.capa ? 'img' : 'span');
      capa.className = 'resultado-capa';
      if (r.capa) { capa.src = r.capa; capa.alt = ''; capa.loading = 'lazy'; capa.referrerPolicy = 'no-referrer'; }

      var texto = document.createElement('div'); texto.className = 'resultado-texto';
      var nome = document.createElement('p'); nome.className = 'resultado-nome'; nome.textContent = r.nome;
      var detalhes = document.createElement('p'); detalhes.className = 'resultado-info'; detalhes.textContent = info(r);
      var acoes = document.createElement('div'); acoes.className = 'resultado-acoes';

      // biblioteca = já é tua; convite = alguém te convidou para ela
      var ja = jaCa[r.mal_id];
      if (ja) {
        var aviso = document.createElement('span'); aviso.className = 'resultado-ja';
        aviso.textContent = ja === 'convite' ? 'Tens um convite para esta: aceita-o no Início' : '✓ Já está na tua biblioteca';
        acoes.appendChild(aviso);
      } else {
        botoesDeAdicionar(acoes, r);
      }

      texto.appendChild(nome); texto.appendChild(detalhes); texto.appendChild(acoes);
      li.appendChild(capa); li.appendChild(texto);
      lista.appendChild(li);
    });
  }

  // "Só para mim" + convidar alguém (com uma só pessoa, o botão já diz o nome; com várias, abre a escolha)
  function botoesDeAdicionar(acoes, r) {
    acoes.innerHTML = '';
    acoes.appendChild(botao(ligados.length ? 'Só para mim' : 'Adicionar', '', function () { adicionar(r, 'ver', 0); }));
    if (ligados.length === 1) {
      acoes.appendChild(botao('Quero ver com ' + ligados[0].nome, 'secundario', function () { adicionar(r, 'propor', ligados[0].id); }));
    } else if (ligados.length > 1) {
      acoes.appendChild(botao('Ver com…', 'secundario', function () {
        acoes.innerHTML = '';
        ligados.forEach(function (l) {
          acoes.appendChild(botao(l.nome, 'secundario', function () { adicionar(r, 'propor', l.id); }));
        });
        acoes.appendChild(botao('‹ Voltar', 'link', function () { botoesDeAdicionar(acoes, r); }));
      }));
    }
  }

  function botao(texto, extra, acao) {
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'btn-pequeno ' + extra; b.textContent = texto;
    b.addEventListener('click', acao);
    return b;
  }

  function bloquear(sim) {
    ocupado = sim;
    input.disabled = sim;
    lista.querySelectorAll('button').forEach(function (b) { b.disabled = sim; });
  }

  // Adiciona (ou propõe): busca os episódios (Jikan ou Kitsu) e manda tudo ao servidor
  function adicionar(r, modo, com) {
    if (ocupado) return;
    bloquear(true);
    mostrarEstado('A buscar os episódios de ' + r.nome + '…');

    Anime.episodios(r.mal_id, function (n) {
      mostrarEstado('A buscar os episódios de ' + r.nome + '… ' + n + (r.episodios ? ' de ' + r.episodios : ''));
    })
      .then(function (eps) {
        mostrarEstado('A guardar ' + r.nome + '…');
        return enviarAoServidor(folha.dataset.urlAdicionar, {
          mal_id: r.mal_id, modo: modo, com: com || 0, info: JSON.stringify(r), episodios: JSON.stringify(eps.lista), fonte: eps.fonte
        });
      })
      .then(function (resposta) {
        // Vai para a série nova (ou para as propostas). Se só mudar o "#", recarrega para a ver.
        var destino = new URL(resposta.url, location.href);
        if (destino.pathname + destino.search === location.pathname + location.search) {
          location.hash = destino.hash;
          location.reload();
        } else {
          location.href = destino.href;
        }
      })
      .catch(function (erro) {
        bloquear(false);
        mostrarEstado(erro.message || 'Não foi possível adicionar.', true);
      });
  }
})();

// ---------- Capas em falta (Início): o browser vai buscá-las ao AniList e guarda-as ----------
(function () {
  var fila = document.getElementById('fila');
  if (!fila || !window.fetch || !window.Promise) return;
  var semCapa = [];
  try { semCapa = JSON.parse(fila.dataset.semCapa || '[]'); } catch (e) { return; }

  // Uma de cada vez (o Jikan só aceita 3 pedidos por segundo); uma falha não pára as outras
  semCapa.reduce(function (cadeia, s) {
    return cadeia.then(function () {
      return Anime.anime(s.mal)
        .then(function (info) { return enviarAoServidor(fila.dataset.url, { serie: s.serie, info: JSON.stringify(info) }); })
        .then(function (resposta) {
          // Troca as iniciais pela capa, sem recarregar (na tua fila e na do par)
          if (!resposta.capa) return;
          document.querySelectorAll('.capa-item[data-serie="' + s.serie + '"] .capa').forEach(function (item) {
            var img = document.createElement('img');
            img.className = 'capa'; img.src = resposta.capa; img.alt = ''; img.referrerPolicy = 'no-referrer';
            item.replaceWith(img);
          });
        })
        .catch(function () { /* fica para a próxima visita */ });
    });
  }, Promise.resolve());
})();

// ---------- Página da série: fillers que faltam / episódios novos de séries em emissão ----------
// O servidor pede (id="sincronizar"); o browser vai buscar os dados e manda-os em segundo plano.
// No máximo uma vez por hora por série, para não gastar dados quando o Jikan está em baixo.
(function () {
  var marca = document.getElementById('sincronizar');
  if (!marca || !window.fetch || !window.Promise) return;
  var id = marca.dataset.mal, chave = 'sinc-' + marca.dataset.serie;
  try {
    if (Date.now() - +(localStorage.getItem(chave) || 0) < 3600000) return;
    localStorage.setItem(chave, String(Date.now()));
  } catch (e) { /* sem armazenamento: tenta na mesma */ }

  var emEmissao = marca.dataset.emEmissao === '1';
  Anime.anime(id)
    .then(function (info) {
      // Acabada: só faltam os fillers (Jikan). Em emissão: também episódios novos (Jikan ou Kitsu).
      return Anime.episodios(id, null, !emEmissao).then(function (eps) {
        return enviarAoServidor(marca.dataset.url, {
          serie: marca.dataset.serie, info: JSON.stringify(info), episodios: JSON.stringify(eps.lista), fonte: eps.fonte
        });
      });
    })
    .then(function (resposta) {
      if (!resposta.novos) return;
      var aviso = document.getElementById('aviso');
      aviso.textContent = (resposta.novos === 1 ? 'Saiu 1 episódio novo' : 'Saíram ' + resposta.novos + ' episódios novos') + ' · toca para ver';
      aviso.hidden = false;
      aviso.classList.add('tocavel');   // o CSS põe-lhe o cursor de mão
      aviso.addEventListener('click', function () { location.reload(); });
      requestAnimationFrame(function () { aviso.classList.add('mostrar'); });
    })
    .catch(function () { /* tenta outra vez mais tarde */ });
})();

// ---------- Popup "O que há de novo" (Início) ----------
// Abre sozinho quando há novidades por ver; fechar de qualquer forma (botão, tocar fora,
// "voltar" do Android) marca-as como vistas no servidor, para não voltarem a aparecer.
(function () {
  var folha = document.getElementById('folha-novidades');
  var form = document.getElementById('form-novidades');
  if (!folha || !form || !folha.showModal) return;

  var gravado = false;
  function marcarVistas() {
    if (gravado) return;
    gravado = true;
    var dados = new FormData(form);
    fetch(form.action, { method: 'POST', body: dados, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .catch(function () { gravado = false; });   // sem rede: volta a aparecer na próxima vez
  }

  form.addEventListener('submit', function (ev) { ev.preventDefault(); folha.close(); });
  folha.addEventListener('click', function (ev) { if (ev.target === folha) folha.close(); });   // tocar fora fecha
  folha.addEventListener('close', marcarVistas);

  // Um bocadinho depois de a página aparecer, para não abrir "aos saltos"
  setTimeout(function () { folha.showModal(); }, 400);
})();

// ---------- Folhas simples: [data-abrir="id"] abre o <dialog>; [data-fechar] ou tocar fora fecha ----------
(function () {
  document.querySelectorAll('[data-abrir]').forEach(function (b) {
    var d = document.getElementById(b.dataset.abrir);
    if (!d || !d.showModal) return;
    b.addEventListener('click', function () { d.showModal(); });
    d.addEventListener('click', function (ev) { if (ev.target === d) d.close(); });
    d.querySelectorAll('[data-fechar]').forEach(function (f) { f.addEventListener('click', function () { d.close(); }); });
  });
})();

// ---------- Perfil: procurar atualizações ----------
// As páginas vêm sempre do servidor; o que pode ficar velho são o CSS/JS em cache. O botão pede ao
// service worker que procure uma versão nova, apaga as caches e recarrega; depois confirma.
(function () {
  var btn = document.getElementById('btn-atualizar');
  var estado = document.getElementById('atualizacao-estado');
  if (!btn || !estado) return;

  // Depois do recarregamento: confirma
  try {
    if (sessionStorage.getItem('ad-atualizado')) {
      sessionStorage.removeItem('ad-atualizado');
      estado.textContent = 'Tudo atualizado ✓ Estás na versão mais recente.';
    }
  } catch (e) { /* sem sessionStorage: sem confirmação */ }

  btn.addEventListener('click', function () {
    btn.disabled = true;
    estado.textContent = 'A procurar…';
    var sw = 'serviceWorker' in navigator ? navigator.serviceWorker.getRegistration().then(function (r) { return r && r.update(); }) : Promise.resolve();
    var limpar = 'caches' in window ? caches.keys().then(function (ks) { return Promise.all(ks.map(function (k) { return caches.delete(k); })); }) : Promise.resolve();
    Promise.all([sw, limpar])
      .then(function () { try { sessionStorage.setItem('ad-atualizado', '1'); } catch (e) { /* ignora */ } location.reload(); })
      .catch(function () { estado.textContent = 'Não foi possível procurar agora. Verifica a ligação.'; btn.disabled = false; });
  });
})();

// ---------- App instalável: regista o service worker ----------
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('sw.js').catch(function () { /* sem SW a app funciona na mesma */ });
  });
}

// ---------- Cores: tocar numa amostra do perfil mostra logo a paleta (só grava com "Guardar cores") ----------
document.querySelectorAll('#form-paleta input[name="paleta"]').forEach(function (r) {
  r.addEventListener('change', function () { document.documentElement.setAttribute('data-paleta', r.value); });
});

// =====================================================================================
// "Uau" (como na app 1.8.0): números a contar, corrida, entradas em cascata, marcar com
// impacto, celebração ao acabar uma série, medalhas, stories da retrospetiva e capas que voam.
// Todo o texto vindo do servidor é inserido como texto (textContent), nunca como HTML.
// =====================================================================================

// Quem pediu menos movimento no sistema fica sem animações (só o resultado final)
var poucoMovimento = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Cor de uma variável do CSS (ex.: '--tu'), com uma de reserva
function corCss(nome, reserva) {
  var v = getComputedStyle(document.body).getPropertyValue(nome).trim();
  return v || reserva;
}

// ---------- Números que sobem de 0 até ao valor ([data-contar]) ----------
function contarNumero(el, duracao) {
  var alvo = parseInt(el.dataset.contar, 10) || 0;
  if (poucoMovimento || alvo === 0) { el.textContent = alvo; return; }
  var inicio = null;
  function passo(t) {
    if (inicio === null) inicio = t;
    var p = Math.min(1, (t - inicio) / (duracao || 900));
    var suave = 1 - Math.pow(1 - p, 3);              // easeOutCubic
    el.textContent = Math.round(alvo * suave);
    if (p < 1) requestAnimationFrame(passo);
  }
  el.textContent = '0';
  requestAnimationFrame(passo);
}
document.querySelectorAll('[data-contar]').forEach(function (el) { contarNumero(el); });

// ---------- Corrida no placar do Início: a foto corre até à % de cada um ----------
document.querySelectorAll('.corrida[data-pct]').forEach(function (pista) {
  var foto = pista.querySelector('.corrida-foto');
  if (!foto) return;
  var pct = Math.max(0, Math.min(100, parseInt(pista.dataset.pct, 10) || 0));
  // Começa no 0 e, no frame seguinte, vai até à % (a transição está no CSS)
  foto.style.left = '0px';
  requestAnimationFrame(function () {
    requestAnimationFrame(function () {
      foto.style.left = 'calc((100% - ' + foto.offsetWidth + 'px) * ' + (pct / 100) + ')';
      pista.classList.add('a-correr');
    });
  });
});

// ---------- Entrada em cascata: blocos e listas aparecem um a um ----------
(function () {
  var blocos = document.querySelectorAll('.cascata, .pagina-inicio .inicio .painel, .pagina-inicio .biblioteca, .capa-item');
  blocos.forEach(function (el, i) {
    if (poucoMovimento) return;
    el.style.animationDelay = (Math.min(i, 8) * 55) + 'ms';
    el.classList.add('entra');
  });
})();

// ---------- Partículas a saltar de um ponto do ecrã ----------
function explosao(x, y, cores, quantas) {
  if (poucoMovimento) return;
  var tela = document.createElement('canvas');
  tela.className = 'efeito-tela';
  var escala = window.devicePixelRatio || 1;
  tela.width = innerWidth * escala;
  tela.height = innerHeight * escala;
  document.body.appendChild(tela);
  var ctx = tela.getContext('2d');
  ctx.scale(escala, escala);

  var particulas = [];
  for (var i = 0; i < (quantas || 26); i++) {
    particulas.push({
      a: Math.random() * Math.PI * 2, v: 140 + Math.random() * 220, t: 4 + Math.random() * 6,
      r: (Math.random() - .5) * 10, c: cores[Math.floor(Math.random() * cores.length)], redonda: Math.random() < .5
    });
  }
  var inicio = null;
  function passo(agora) {
    if (inicio === null) inicio = agora;
    var t = Math.min(1, (agora - inicio) / 850);
    var s = 1 - Math.pow(1 - t, 3);
    ctx.clearRect(0, 0, innerWidth, innerHeight);
    particulas.forEach(function (p) {
      var px = x + Math.cos(p.a) * p.v * s;
      var py = y + Math.sin(p.a) * p.v * s + 120 * t * t;   // sai, abranda e cai um pouco
      ctx.save();
      ctx.globalAlpha = 1 - t;
      ctx.fillStyle = p.c;
      ctx.translate(px, py);
      ctx.rotate(p.r * t);
      if (p.redonda) { ctx.beginPath(); ctx.arc(0, 0, p.t / 2, 0, Math.PI * 2); ctx.fill(); }
      else ctx.fillRect(-p.t / 2, -p.t * .27, p.t, p.t * .55);
      ctx.restore();
    });
    if (t < 1) requestAnimationFrame(passo); else tela.remove();
  }
  requestAnimationFrame(passo);
}

// ❤️ que cresce num ponto, sobe e desaparece
function coracao(x, y) {
  if (poucoMovimento) return;
  var c = document.createElement('span');
  c.className = 'coracao-voa';
  c.textContent = '❤️';
  c.style.left = x + 'px';
  c.style.top = y + 'px';
  document.body.appendChild(c);
  c.addEventListener('animationend', function () { c.remove(); });
}

// Marcar com impacto: os cartões marcados viram; partículas no do centro; ❤️ se os companheiros já o tinham visto
function festejarCartoes(cartoes, centro) {
  cartoes.forEach(function (c) {
    c.classList.remove('virar');
    void c.offsetWidth;                              // reinicia a animação se se repetir
    c.classList.add('virar');
    c.addEventListener('animationend', function fim() { c.classList.remove('virar'); c.removeEventListener('animationend', fim); });
  });
  var alvo = centro && cartoes.indexOf(centro) >= 0 ? centro : cartoes[0];
  if (!alvo) return;
  if (navigator.vibrate) navigator.vibrate(25);
  var r = alvo.getBoundingClientRect();
  var x = r.left + r.width / 2, y = r.top + r.height / 2;
  explosao(x, y, [corCss('--acento', '#F5C89A'), corCss('--tu', '#B9D3B0'), corCss('--tu-txt', '#4E7A43')], cartoes.length > 1 ? 40 : 26);
  if (cartoes.length === 1 && (alvo.dataset.com || '').indexOf('1') >= 0) {
    setTimeout(function () { if (navigator.vibrate) navigator.vibrate([0, 40]); coracao(x, y); }, 260);
  }
}

// ---------- Chuva de confettis (por cima de um elemento) ----------
function confettis(dentro, cores) {
  if (poucoMovimento) return;
  var tela = document.createElement('canvas');
  tela.className = 'confettis';
  dentro.appendChild(tela);
  var escala = window.devicePixelRatio || 1;
  var L = dentro.clientWidth, A = dentro.clientHeight;
  tela.width = L * escala; tela.height = A * escala;
  var ctx = tela.getContext('2d');
  ctx.scale(escala, escala);
  var papeis = [];
  for (var i = 0; i < 140; i++) {
    papeis.push({ x: Math.random(), atraso: Math.random() * .35, v: .55 + Math.random() * .6, b: 10 + Math.random() * 26,
                  r: (Math.random() - .5) * 14, l: 6 + Math.random() * 6, c: cores[Math.floor(Math.random() * cores.length)] });
  }
  var inicio = null;
  function passo(agora) {
    if (inicio === null) inicio = agora;
    var t = Math.min(1, (agora - inicio) / 3600);
    ctx.clearRect(0, 0, L, A);
    papeis.forEach(function (p) {
      var local = Math.max(0, Math.min(1, (t - p.atraso) / (1 - p.atraso)));
      if (local <= 0) return;
      var y = -20 + (A + 40) * local * p.v * 1.4;
      if (y > A + 20) return;
      var x = L * p.x + Math.sin(local * 10 + p.x * 6) * p.b;
      ctx.save();
      ctx.globalAlpha = local > .85 ? (1 - local) / .15 : 1;
      ctx.fillStyle = p.c;
      ctx.translate(x, y);
      ctx.rotate(p.r * local);
      ctx.fillRect(-p.l / 2, -p.l / 4, p.l, p.l / 2);
      ctx.restore();
    });
    if (t < 1) requestAnimationFrame(passo); else tela.remove();
  }
  requestAnimationFrame(passo);
}

// ---------- Partilhar uma imagem (canvas): folha de partilha do telemóvel ou descarregar ----------
function partilharTela(tela, nomeFicheiro, texto) {
  return new Promise(function (ok) {
    try {
      tela.toBlob(function (blob) {
        if (!blob) { ok(false); return; }
        var ficheiro = new File([blob], nomeFicheiro, { type: 'image/png' });
        if (navigator.canShare && navigator.canShare({ files: [ficheiro] })) {
          navigator.share({ files: [ficheiro], text: texto }).then(function () { ok(true); }, function () { ok(true); });
        } else {
          // Sem partilha de ficheiros (computador): descarrega a imagem
          var a = document.createElement('a');
          a.href = URL.createObjectURL(blob);
          a.download = nomeFicheiro;
          document.body.appendChild(a);
          a.click();
          a.remove();
          ok(true);
        }
      }, 'image/png');
    } catch (e) { ok(false); }   // imagem de outro site sem CORS: o canvas fica "sujo" e não exporta
  });
}

// Carrega uma imagem para desenhar no canvas (null se falhar ou se o site não deixar)
function carregarImagem(url) {
  return new Promise(function (ok) {
    if (!url) { ok(null); return; }
    var img = new Image();
    img.crossOrigin = 'anonymous';
    img.referrerPolicy = 'no-referrer';
    img.onload = function () { ok(img); };
    img.onerror = function () { ok(null); };
    img.src = url;
  });
}

// Texto partido em linhas que caibam numa largura (para o canvas)
function linhasDeTexto(ctx, texto, largura) {
  var palavras = texto.split(' '), linhas = [], atual = '';
  palavras.forEach(function (p) {
    var tenta = atual ? atual + ' ' + p : p;
    if (ctx.measureText(tenta).width > largura && atual) { linhas.push(atual); atual = p; } else atual = tenta;
  });
  if (atual) linhas.push(atual);
  return linhas;
}

// ---------- Celebração ao acabar uma série (página da série) ----------
function celebrarSerie(json, comigo) {
  var d;
  try { d = JSON.parse(json || '{}'); } catch (e) { return; }
  var juntos = comigo.length > 0;
  var acento = corCss('--acento', '#F5C89A');

  // Uma pessoa: foto, ou a inicial num círculo
  function avatar(p, cor) {
    var s = document.createElement('span');
    s.className = 'avatar avatar-' + cor + ' cel-avatar';
    if (p.foto) { var img = document.createElement('img'); img.src = p.foto; img.alt = ''; s.appendChild(img); }
    else s.textContent = p.inicial || '?';
    return s;
  }
  function el(tag, classe, texto) {
    var e = document.createElement(tag);
    if (classe) e.className = classe;
    if (texto !== undefined) e.textContent = texto;
    return e;
  }

  // Ecrã por cima de tudo
  var fundo = el('div', 'celebracao');
  var cartao = el('div', 'cel-cartao');
  if (d.capa) { var desf = el('img', 'cel-fundo'); desf.src = d.capa; desf.alt = ''; desf.referrerPolicy = 'no-referrer'; cartao.appendChild(desf); }
  var dentro = el('div', 'cel-dentro');
  dentro.appendChild(el('p', 'cel-titulo', '🎉  ' + (juntos ? 'ACABÁMOS' : 'ACABEI')));
  if (d.capa) { var capa = el('img', 'cel-capa'); capa.src = d.capa; capa.alt = ''; capa.referrerPolicy = 'no-referrer'; dentro.appendChild(capa); }
  dentro.appendChild(el('p', 'cel-nome', d.nome));
  var fotos = el('div', 'cel-fotos');
  fotos.appendChild(avatar(d.eu, 'tu'));
  comigo.forEach(function (p) { fotos.appendChild(avatar(p, 'par')); });
  dentro.appendChild(fotos);
  if (juntos) dentro.appendChild(el('p', 'cel-quem', d.eu.nome + ' e ' + comigo.map(function (p) { return p.nome; }).join(' e ')));
  var nums = el('div', 'cel-numeros');
  [[d.total, 'episódios'], [d.horas, 'horas']].concat(d.fillers > 0 ? [[d.fillers, 'fillers']] : []).forEach(function (n) {
    var bloco = el('div');
    var b = el('b', '', '0'); b.dataset.contar = n[0];
    bloco.appendChild(b);
    bloco.appendChild(el('span', '', n[1]));
    nums.appendChild(bloco);
  });
  dentro.appendChild(nums);
  var hoje = new Date();
  var meses = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
  dentro.appendChild(el('p', 'cel-rodape', 'anime a dois · ' + hoje.getDate() + ' ' + meses[hoje.getMonth()] + ' ' + hoje.getFullYear()));
  cartao.appendChild(dentro);
  fundo.appendChild(cartao);

  var botoes = el('div', 'cel-botoes');
  var fechar = el('button', 'btn btn-secundario', 'Fechar'); fechar.type = 'button';
  var partilhar = el('button', 'btn', 'Partilhar'); partilhar.type = 'button';
  botoes.appendChild(fechar); botoes.appendChild(partilhar);
  fundo.appendChild(botoes);
  document.body.appendChild(fundo);

  fundo.querySelectorAll('[data-contar]').forEach(function (b) { contarNumero(b, 1500); });
  confettis(fundo, [acento, corCss('--tu', '#B9D3B0'), corCss('--par', '#F5C89A'), '#FFD66B', '#FFFFFF']);
  if (navigator.vibrate) navigator.vibrate([0, 60, 40, 60]);
  fechar.addEventListener('click', function () { fundo.remove(); });

  // Partilhar: o cartão desenhado à mão num canvas 1080×1920 (formato stories)
  partilhar.addEventListener('click', function () {
    partilhar.disabled = true;
    carregarImagem(d.capa).then(function (img) {
      var L = 1080, A = 1920, tela = document.createElement('canvas');
      tela.width = L; tela.height = A;
      var ctx = tela.getContext('2d');
      var grad = ctx.createLinearGradient(0, 0, 0, A);
      grad.addColorStop(0, acento); grad.addColorStop(.62, '#14161E'); grad.addColorStop(1, '#0B0C10');
      ctx.fillStyle = grad; ctx.fillRect(0, 0, L, A);
      ctx.textAlign = 'center'; ctx.fillStyle = '#FFFFFF';
      ctx.font = '800 42px "M PLUS Rounded 1c", sans-serif';
      ctx.fillText('🎉  ' + (juntos ? 'ACABÁMOS' : 'ACABEI'), L / 2, 170);
      var y = 240;
      if (img) {                                    // capa ao centro (se o site da imagem deixar)
        var cw = 450, ch = cw * 1.42;
        ctx.save(); ctx.shadowColor = 'rgba(0,0,0,.5)'; ctx.shadowBlur = 60; ctx.shadowOffsetY = 30;
        ctx.drawImage(img, (L - cw) / 2, y, cw, ch); ctx.restore();
        y += ch + 90;
      } else y += 120;
      ctx.font = '800 72px "M PLUS Rounded 1c", sans-serif';
      linhasDeTexto(ctx, d.nome, L - 160).slice(0, 2).forEach(function (l) { ctx.fillText(l, L / 2, y); y += 86; });
      if (juntos) { ctx.font = '500 40px "M PLUS Rounded 1c", sans-serif'; ctx.fillStyle = 'rgba(255,255,255,.75)';
        ctx.fillText(d.eu.nome + ' e ' + comigo.map(function (p) { return p.nome; }).join(' e '), L / 2, y + 10); }
      var numeros = [[d.total, 'episódios'], [d.horas, 'horas']].concat(d.fillers > 0 ? [[d.fillers, 'fillers']] : []);
      numeros.forEach(function (n, i) {
        var x = L / (numeros.length * 2) * (i * 2 + 1);
        ctx.fillStyle = '#FFFFFF'; ctx.font = '800 96px "M PLUS Rounded 1c", sans-serif'; ctx.fillText(String(n[0]), x, A - 330);
        ctx.fillStyle = 'rgba(255,255,255,.6)'; ctx.font = '500 38px "M PLUS Rounded 1c", sans-serif'; ctx.fillText(n[1], x, A - 270);
      });
      ctx.fillStyle = 'rgba(255,255,255,.55)'; ctx.font = '500 34px "M PLUS Rounded 1c", sans-serif';
      ctx.fillText('anime a dois · ' + hoje.getDate() + ' ' + meses[hoje.getMonth()] + ' ' + hoje.getFullYear(), L / 2, A - 120);
      return partilharTela(tela, 'anime-a-dois-' + (form_slug() || 'serie') + '.png', (juntos ? 'Acabámos ' : 'Acabei ') + d.nome + ' no Anime a Dois 🎉');
    }).then(function () { partilhar.disabled = false; });
  });

  // Slug da série (para o nome do ficheiro)
  function form_slug() { var f = document.getElementById('form-marcar'); return f ? f.dataset.serie : ''; }
}

// ---------- Medalhas: tocar numa abre o detalhe ----------
(function () {
  var folha = document.getElementById('folha-medalha');
  if (!folha || !folha.showModal) return;
  document.querySelectorAll('[data-medalha]').forEach(function (b) {
    b.addEventListener('click', function () {
      var obtida = b.dataset.obtida === '1';
      var emblema = document.getElementById('md-emblema');
      emblema.className = 'emblema emblema-grande emblema-' + b.dataset.nivel + (obtida ? '' : ' bloqueada');
      document.getElementById('md-emoji').textContent = b.dataset.emoji;
      document.getElementById('md-nome').textContent = b.dataset.nome;
      document.getElementById('md-descricao').textContent = b.dataset.descricao;
      var estado = document.getElementById('md-obtida');
      estado.textContent = obtida ? 'Obtida · ' + b.dataset.nivelTexto : '';
      estado.className = 'md-nivel nivel-' + b.dataset.nivel;
      var barra = document.getElementById('md-barra');
      barra.hidden = obtida;
      barra.value = parseInt(b.dataset.pct, 10) || 0;
      document.getElementById('md-progresso').textContent = obtida ? '' : b.dataset.progresso + ' · ' + b.dataset.pct + '%';
      folha.showModal();
    });
  });
})();

// ---------- Retrospetiva: stories ----------
(function () {
  var raiz = document.getElementById('stories');
  if (!raiz) return;
  var slides = Array.prototype.slice.call(raiz.querySelectorAll('.story'));
  var barras = document.getElementById('stories-barras');
  if (!slides.length) return;
  var DURACAO = 5000;          // cada slide (menos o último) avança sozinho ao fim de 5 s
  var atual = 0, inicio = 0, gasto = 0, pausa = false, relogio = null;

  // Uma barrinha por slide
  var enchimentos = slides.map(function () {
    var b = document.createElement('span'); var i = document.createElement('i');
    b.appendChild(i); barras.appendChild(b); return i;
  });

  // Gráfico do ano: alturas a partir de data-valor
  raiz.querySelectorAll('.story-meses').forEach(function (g) {
    var max = parseInt(g.dataset.maximo, 10) || 1;
    g.querySelectorAll('i[data-valor]').forEach(function (i) {
      i.style.height = Math.max(4, Math.round((parseInt(i.dataset.valor, 10) || 0) / max * 100)) + '%';
    });
  });

  function mostrar(n) {
    atual = Math.max(0, Math.min(slides.length - 1, n));
    slides.forEach(function (s, i) { s.hidden = i !== atual; });
    enchimentos.forEach(function (e, i) { e.style.width = i < atual ? '100%' : '0%'; });
    gasto = 0; inicio = performance.now();
    slides[atual].classList.remove('entra-story'); void slides[atual].offsetWidth; slides[atual].classList.add('entra-story');
    if (atual === slides.length - 1) enchimentos[atual].style.width = '100%';
    if (navigator.vibrate) navigator.vibrate(8);
  }
  function seguinte() { if (atual < slides.length - 1) mostrar(atual + 1); }
  function anterior() { mostrar(atual - 1); }

  // O tempo do slide atual (para a barrinha e o avanço automático)
  function passo(agora) {
    if (!pausa && atual < slides.length - 1) {
      var t = gasto + (agora - inicio);
      enchimentos[atual].style.width = Math.min(100, t / DURACAO * 100) + '%';
      if (t >= DURACAO) seguinte();
    }
    relogio = requestAnimationFrame(passo);
  }

  // Toque: à direita avança, à esquerda recua; manter carregado pausa
  var palco = document.getElementById('stories-palco');
  var carregado = 0;
  palco.addEventListener('pointerdown', function () { carregado = performance.now(); pausa = true; gasto += performance.now() - inicio; });
  palco.addEventListener('pointerup', function (ev) {
    pausa = false; inicio = performance.now();
    if (performance.now() - carregado > 300) return;           // foi um "manter": só pausa
    if (ev.target.closest('button, a')) return;                // botões do cartão final
    var r = palco.getBoundingClientRect();
    if (ev.clientX < r.left + r.width * .33) anterior(); else seguinte();
  });
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'ArrowRight') seguinte();
    if (ev.key === 'ArrowLeft') anterior();
    if (ev.key === 'Escape') location.href = raiz.dataset.sair;
  });

  mostrar(0);
  relogio = requestAnimationFrame(passo);

  // Cartão final → imagem desenhada à mão (texto só: não depende de imagens de outros sites)
  var btn = document.getElementById('retro-partilhar');
  var cartao = document.getElementById('retro-cartao');
  if (btn && cartao) {
    btn.addEventListener('click', function () {
      var d; try { d = JSON.parse(cartao.dataset.cartao); } catch (e) { return; }
      btn.disabled = true;
      var L = 1080, A = 1350, tela = document.createElement('canvas');
      tela.width = L; tela.height = A;
      var ctx = tela.getContext('2d');
      var grad = ctx.createLinearGradient(0, 0, L, A);
      grad.addColorStop(0, corCss('--tu', '#B9D3B0')); grad.addColorStop(1, corCss('--par', '#F5C89A'));
      ctx.fillStyle = grad; ctx.fillRect(0, 0, L, A);
      var tinta = '#2A2433';
      ctx.fillStyle = tinta; ctx.textAlign = 'left';
      ctx.font = '800 44px "M PLUS Rounded 1c", sans-serif'; ctx.fillText('anime a dois', 90, 140);
      ctx.font = '800 84px "M PLUS Rounded 1c", sans-serif';
      var y = 280;
      linhasDeTexto(ctx, d.titulo, L - 180).forEach(function (l) { ctx.fillText(l, 90, y); y += 96; });
      y += 40;
      d.linhas.forEach(function (l) {
        ctx.textAlign = 'left'; ctx.font = '500 44px "M PLUS Rounded 1c", sans-serif'; ctx.globalAlpha = .7; ctx.fillText(l[0], 90, y);
        ctx.textAlign = 'right'; ctx.font = '800 52px "M PLUS Rounded 1c", sans-serif'; ctx.globalAlpha = 1; ctx.fillText(l[1], L - 90, y);
        y += 110;
      });
      partilharTela(tela, 'anime-a-dois-retrospetiva.png', d.titulo + ' no Anime a Dois 📼').then(function () { btn.disabled = false; });
    });
  }
})();

// ---------- Capas que voam (View Transitions entre páginas, nos browsers que as têm) ----------
// A capa tocada na fila do Início ganha o mesmo nome que a capa do topo da página da série:
// o browser faz a animação de uma para a outra. Sem suporte, a página abre como sempre.
document.querySelectorAll('.capa-item[href]').forEach(function (a) {
  a.addEventListener('click', function () {
    document.querySelectorAll('.capa-voa').forEach(function (x) { x.classList.remove('capa-voa'); });
    var capa = a.querySelector('.capa');
    if (capa) capa.classList.add('capa-voa');
  });
});
