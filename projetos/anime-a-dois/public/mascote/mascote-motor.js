// Mascote do casal — o motor: cena 3D, ciclo de animação, toque, escolha da espécie e desenho de reserva.
//
// Como se usa (o site e a app fazem o mesmo):
//   Mascote.iniciar(contentor, { modo: 'ecra' | 'cartao' })   cria o palco dentro do contentor
//   Mascote.definirEstado(estadoDaApi)                        o JSON de GET /mascote (pode chegar antes ou depois de iniciar)
//   Mascote.evento('salta' | 'festinha')                      faz o bicho reagir (ex.: a app avisa que marcaste um episódio)
//   Mascote.ponte.enviar(mensagem)                            o bicho avisa quem o mostra: { tipo: 'toque' | 'escolher', especie }
//                                                             (por defeito não faz nada; o site e a app trocam esta função)
//   Mascote.congelar(t, animacao?)                            pára o ciclo e desenha um só quadro (capturas de ecrã e testes)
//
// Regras: a animação só corre com o ecrã à vista; sem WebGL mostra um desenho fixo; o nome é sempre texto (nunca HTML).

(function (g) {
  'use strict';

  const M = (g.Mascote = g.Mascote || {});

  // Quem mostra o bicho substitui isto (site: POST com CSRF; app: canal da WebView)
  M.ponte = { enviar() {} };

  let S = null;            // a instância atual (só há uma por página)
  let pendente;            // estado que chegou antes de o bicho ser iniciado

  // Quanto dura cada reação curta (segundos)
  const DURACAO = { salta: 0.7, festinha: 0.5, eclodir: 1.2 };
  const NOME_PADRAO = 'O vosso bicho';

  // ---------- Início ----------

  M.iniciar = function (contentor, opcoes) {
    const o = Object.assign({ modo: 'ecra', captura: false }, opcoes || {});
    contentor.classList.add('mascote', 'mascote-' + o.modo);
    contentor.textContent = '';

    S = {
      contentor,
      modo: o.modo,
      params: null,
      corpo: null,
      acao: null,               // reação curta em curso: { nome, inicio, duracao }
      eclosao: null,            // ovo a tremer antes de dar lugar ao bicho: { inicio, params }
      ptr: null,                // dedo/rato carregado
      coracoes: [],
      t0: performance.now(),
      ultimoQuadro: 0,
      raf: null,
      congelado: false,
      noDoc: document.visibilityState !== 'hidden',
      naPagina: true,
      semWebGL: false,
    };

    // Palco (onde fica o canvas), nome e a escolha da espécie
    S.palco = document.createElement('div');
    S.palco.className = 'mascote-palco';
    S.rotulo = document.createElement('div');
    S.rotulo.className = 'mascote-nome';
    S.escolha = document.createElement('div');
    S.escolha.className = 'mascote-escolha mascote-oculto';
    contentor.append(S.palco, S.rotulo, S.escolha);
    montarEscolha();

    try {
      S.renderer = new g.THREE.WebGLRenderer({ antialias: true, alpha: true, preserveDrawingBuffer: !!o.captura });
      S.renderer.setClearColor(0x000000, 0);
      S.palco.appendChild(S.renderer.domElement);
      montarCena();
      ligarPonteiros();
    } catch (e) {
      S.semWebGL = true;      // sem WebGL: fica o desenho fixo
      S.renderer = null;
    }

    // Só anima quando se vê: separador escondido ou fora do ecrã pára o ciclo
    document.addEventListener('visibilitychange', () => {
      S.noDoc = document.visibilityState !== 'hidden';
      agendar();
    });
    if ('IntersectionObserver' in g) {
      new IntersectionObserver((entradas) => {
        S.naPagina = entradas[entradas.length - 1].isIntersecting;
        agendar();
      }).observe(contentor);
    }
    if ('ResizeObserver' in g) new ResizeObserver(ajustarTamanho).observe(S.palco);
    else g.addEventListener('resize', ajustarTamanho);

    if (pendente !== undefined) M.definirEstado(pendente);
    ajustarTamanho();
    agendar();
  };

  // Cena: luz suave (sem sombras duras), câmara e sombra redonda no chão
  function montarCena() {
    const T = g.THREE;
    S.cena = new T.Scene();
    S.camara = new T.PerspectiveCamera(30, 1, 0.1, 50);
    S.camara.position.set(0, 1.2, 6.4);
    S.camara.lookAt(0, 0.95, 0);
    S.cena.add(new T.HemisphereLight(0xffffff, 0xb9c0d4, 0.7));
    const sol = new T.DirectionalLight(0xffffff, 0.5);
    sol.position.set(2.5, 4, 3.5);
    S.cena.add(sol);
    S.sombra = new T.Mesh(new T.CircleGeometry(0.85, 32), new T.MeshBasicMaterial({ color: 0x000000, transparent: true, opacity: 0.09 }));
    S.sombra.rotation.x = -Math.PI / 2;
    S.sombra.position.y = 0.01;
    S.cena.add(S.sombra);
  }

  function ajustarTamanho() {
    if (!S || !S.renderer) return;
    const w = Math.max(1, S.palco.clientWidth);
    const h = Math.max(1, S.palco.clientHeight);
    S.renderer.setPixelRatio(Math.min(g.devicePixelRatio || 1, S.modo === 'cartao' ? 1.5 : 2));
    S.renderer.setSize(w, h, false);
    S.camara.aspect = w / h;
    S.camara.updateProjectionMatrix();
    desenharQuadro(performance.now());
  }

  // ---------- Estado ----------

  M.definirEstado = function (estado) {
    if (!S) {
      pendente = estado;
      return;
    }
    const p = M.Estado.parametros(estado);
    const antes = S.params;
    S.params = p;

    // Nome sempre como texto (nunca HTML)
    S.rotulo.textContent = p.nome || NOME_PADRAO;
    mostrarEscolha(p);

    if (S.semWebGL) {
      desenhoFixo(p);
      return;
    }

    if (antes === null || S.corpo === null) {
      construir(p);
    } else if (antes.corpo === 'ovo' && p.corpo !== 'ovo') {
      // O ovo choca: treme um pouco e só então dá lugar ao bicho
      S.eclosao = { inicio: performance.now(), params: p };
      iniciarAcao('eclodir');
    } else if (antes.corpo !== p.corpo || antes.fase !== p.fase || antes.cor !== p.cor || antes.caudas !== p.caudas) {
      construir(p);
      if (antes.fase !== p.fase) iniciarAcao('eclodir');    // cresceu: pop
    }
    agendar();
  };

  // Troca o corpo na cena (e liberta a memória do anterior)
  function construir(p) {
    if (S.corpo) {
      S.cena.remove(S.corpo.grupo);
      libertar(S.corpo.grupo);
    }
    S.corpo = M.Corpos[p.corpo](p);
    S.cena.add(S.corpo.grupo);
    S.sombra.scale.setScalar(p.escala);
  }

  function libertar(objeto) {
    objeto.traverse((n) => {
      if (n.geometry) n.geometry.dispose();
      if (n.material) n.material.dispose();
    });
  }

  // Animação de fundo conforme o estado (as reações curtas passam por cima)
  function animacaoBase() {
    const p = S.params;
    if (p.deCostas) return 'costas';
    if (p.humor === 'a_dormir') return 'dorme';
    if (p.humor === 'aborrecida') return 'aborrecida';
    return 'idle';
  }

  // ---------- Escolha da espécie ----------

  function montarEscolha() {
    const titulo = document.createElement('p');
    titulo.className = 'mascote-escolha-titulo';
    const botoes = document.createElement('div');
    botoes.className = 'mascote-escolha-botoes';
    for (const [especie, rotulo] of [['raposa', 'Raposa'], ['gato', 'Gato']]) {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'mascote-botao';
      b.dataset.especie = especie;
      b.textContent = rotulo;
      b.addEventListener('click', () => {
        // Só um pedido de cada vez: volta a ligar quando chegar o estado novo
        for (const x of botoes.children) x.disabled = true;
        M.ponte.enviar({ tipo: 'escolher', especie });
      });
      botoes.appendChild(b);
    }
    S.escolha.append(titulo, botoes);
    S.escolhaTitulo = titulo;
    S.escolhaBotoes = botoes;
  }

  function mostrarEscolha(p) {
    const ver = p.podeEscolher;
    S.escolha.classList.toggle('mascote-oculto', !ver);
    if (!ver) return;
    const cartao = S.modo === 'cartao';
    S.escolhaTitulo.textContent = cartao ? 'O ovo chocou! Toca para escolher o bicho.' : 'O ovo chocou! Que bicho vai sair?';
    S.escolhaBotoes.classList.toggle('mascote-oculto', cartao);
    for (const b of S.escolhaBotoes.children) b.disabled = false;
  }

  // ---------- Reações curtas ----------

  function iniciarAcao(nome) {
    S.acao = { nome, inicio: performance.now(), duracao: DURACAO[nome] || 0.7 };
    agendar();
  }

  M.evento = function (nome) {
    if (S && (nome === 'salta' || nome === 'festinha')) {
      iniciarAcao(nome);
      if (nome === 'festinha') emitirCoracao();
    }
  };

  // ---------- Toque e festinha ----------

  function ligarPonteiros() {
    const el = S.renderer.domElement;
    el.addEventListener('pointerdown', (e) => {
      S.ptr = { x: e.clientX, y: e.clientY, inicio: performance.now(), movido: 0, arrastou: false, ultimoCoracao: 0 };
      if (el.setPointerCapture) el.setPointerCapture(e.pointerId);
    });
    el.addEventListener('pointermove', (e) => {
      if (!S.ptr) return;
      S.ptr.movido += Math.hypot(e.clientX - S.ptr.x, e.clientY - S.ptr.y);
      S.ptr.x = e.clientX;
      S.ptr.y = e.clientY;
      if (S.ptr.movido > 14) {
        // Arrastar o dedo é fazer festinhas: olhos fechados e corações a subir
        S.ptr.arrastou = true;
        iniciarAcao('festinha');
        const agora = performance.now();
        if (agora - S.ptr.ultimoCoracao > 180) {
          S.ptr.ultimoCoracao = agora;
          emitirCoracao();
        }
      }
    });
    const largar = () => {
      if (!S.ptr) return;
      const toque = !S.ptr.arrastou && performance.now() - S.ptr.inicio < 500;
      S.ptr = null;
      if (toque) {
        iniciarAcao('salta');
        M.ponte.enviar({ tipo: 'toque' });
      }
    };
    el.addEventListener('pointerup', largar);
    el.addEventListener('pointercancel', () => { S.ptr = null; });
  }

  // Corações: sprites que sobem e desvanecem (no máximo 10 ao mesmo tempo)
  function texturaCoracao() {
    if (S.texCoracao) return S.texCoracao;
    const c = document.createElement('canvas');
    c.width = c.height = 64;
    const x = c.getContext('2d');
    x.fillStyle = '#e88aa0';
    x.beginPath();
    x.moveTo(32, 54);
    x.bezierCurveTo(4, 34, 10, 8, 32, 22);
    x.bezierCurveTo(54, 8, 60, 34, 32, 54);
    x.fill();
    S.texCoracao = new g.THREE.CanvasTexture(c);
    return S.texCoracao;
  }

  function emitirCoracao() {
    if (!S || !S.renderer || !S.corpo || S.coracoes.length >= 10) return;
    const sprite = new g.THREE.Sprite(new g.THREE.SpriteMaterial({ map: texturaCoracao(), transparent: true }));
    sprite.scale.setScalar(0.28);
    sprite.position.set((Math.random() - 0.5) * 0.9, 1.6 * S.params.escala, 0.4);
    S.cena.add(sprite);
    S.coracoes.push({ sprite, nasceu: performance.now() });
  }

  function atualizarCoracoes(agora) {
    S.coracoes = S.coracoes.filter((c) => {
      const idade = (agora - c.nasceu) / 1000;
      if (idade > 1.4) {
        S.cena.remove(c.sprite);
        c.sprite.material.dispose();
        return false;
      }
      c.sprite.position.y += 0.012;
      c.sprite.material.opacity = 1 - idade / 1.4;
      return true;
    });
  }

  // ---------- Ciclo de animação ----------

  function deveCorrer() {
    return S && S.renderer && !S.congelado && S.noDoc && S.naPagina;
  }

  function agendar() {
    if (S && S.raf === null && deveCorrer()) S.raf = requestAnimationFrame(quadro);
  }

  function quadro(agora) {
    S.raf = null;
    if (!deveCorrer()) return;
    // No cartão do Início basta metade dos quadros por segundo (poupa bateria)
    if (S.modo === 'cartao' && agora - S.ultimoQuadro < 33) {
      agendar();
      return;
    }
    S.ultimoQuadro = agora;
    desenharQuadro(agora);
    agendar();
  }

  function desenharQuadro(agora, forcarAnimacao, tFixo) {
    if (!S || !S.renderer || !S.corpo) return;

    // O ovo acabou de tremer: dá lugar ao bicho
    if (S.eclosao && agora - S.eclosao.inicio > 900) {
      const p = S.eclosao.params;
      S.eclosao = null;
      construir(p);
      iniciarAcao('eclodir');
    }

    let nome = animacaoBase();
    let t = tFixo !== undefined ? tFixo : (agora - S.t0) / 1000;
    if (forcarAnimacao) {
      nome = forcarAnimacao;
    } else if (S.acao) {
      const ta = (agora - S.acao.inicio) / 1000;
      if (ta > S.acao.duracao) S.acao = null;
      else { nome = S.acao.nome; t = ta; }
    }

    S.corpo.animar(nome, t, 1);
    // A sombra encolhe quando ele salta
    S.sombra.scale.setScalar(S.params.escala * (1 - 0.3 * Math.min(1, S.corpo.grupo.position.y)));
    atualizarCoracoes(agora);
    S.renderer.render(S.cena, S.camara);
  }

  // Desenha um só quadro e pára (capturas de ecrã e testes): t = segundos; animacao = 'salta', 'dorme'...
  M.congelar = function (t, animacao) {
    if (!S) return;
    S.congelado = true;
    S.acao = null;
    S.eclosao = null;
    desenharQuadro(performance.now(), animacao, t === undefined ? 1 : t);
  };

  // ---------- Desenho de reserva (sem WebGL) ----------

  // Um bicho simples em SVG, feito com atributos (nada de style inline, por causa da CSP)
  function desenhoFixo(p) {
    const cores = M.Util.paleta(p.cor);
    const ns = 'http://www.w3.org/2000/svg';
    const el = (nome, atributos) => {
      const e = document.createElementNS(ns, nome);
      for (const k in atributos) e.setAttribute(k, atributos[k]);
      return e;
    };
    const svg = el('svg', { viewBox: '0 0 120 120', role: 'img', 'aria-label': 'O vosso bicho', class: 'mascote-fixo' });
    if (p.corpo === 'ovo') {
      svg.append(el('ellipse', { cx: 60, cy: 66, rx: 32, ry: 42, fill: cores.claro }));
      svg.append(el('circle', { cx: 50, cy: 52, r: 6, fill: cores.acento }), el('circle', { cx: 72, cy: 76, r: 5, fill: cores.acento }));
    } else {
      // Orelhas: grandes e pontiagudas na raposa, mais pequenas e juntas no gato
      const alta = p.corpo === 'raposa';
      svg.append(el('polygon', { points: alta ? '28,52 38,10 58,40' : '30,50 38,22 56,42', fill: cores.corpo }));
      svg.append(el('polygon', { points: alta ? '92,52 82,10 62,40' : '90,50 82,22 64,42', fill: cores.corpo }));
      svg.append(el('ellipse', { cx: 60, cy: 72, rx: 38, ry: 34, fill: cores.corpo }));
      svg.append(el('ellipse', { cx: 60, cy: 82, rx: 22, ry: 18, fill: cores.claro }));
      svg.append(el('circle', { cx: 47, cy: 66, r: 4.5, fill: '#3b3b3b' }), el('circle', { cx: 73, cy: 66, r: 4.5, fill: '#3b3b3b' }));
      svg.append(el('ellipse', { cx: 40, cy: 76, rx: 6, ry: 3.5, fill: cores.acento, opacity: 0.7 }), el('ellipse', { cx: 80, cy: 76, rx: 6, ry: 3.5, fill: cores.acento, opacity: 0.7 }));
      svg.append(el('path', { d: 'M52 80 Q60 87 68 80', stroke: '#3b3b3b', 'stroke-width': 2.2, fill: 'none', 'stroke-linecap': 'round' }));
    }
    S.palco.textContent = '';
    S.palco.appendChild(svg);
  }
})(typeof window !== 'undefined' ? window : globalThis);
