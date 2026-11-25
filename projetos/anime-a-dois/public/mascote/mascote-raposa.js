// Mascote do casal — a Raposa: corpo esguio, focinho comprido, orelhas grandes e pontiagudas e caudas
// que aumentam com a fase (1, 2, 4 e 9, como o Kurama). Feita só com formas macias, sem modelos externos.
// Contrato: Mascote.Corpos.raposa(p) → { grupo, partes, escala, aura?, animar(nome, t, intensidade) }.
//
// Eixos: y para cima, z para a frente (a câmara está em +z).

(function (g) {
  'use strict';

  const M = (g.Mascote = g.Mascote || {});
  const Corpos = (M.Corpos = M.Corpos || {});
  const U = () => M.Util;
  const TAU = Math.PI * 2;

  Corpos.raposa = function (p) {
    const T = g.THREE;
    const cor = U().paleta(p.cor);
    const grupo = new T.Group();
    grupo.name = 'raposa';
    const el = (r, c, sx, sy, sz, nome) => U().elipsoide(r, c, sx, sy, sz, nome);

    // ---------- Corpo: tronco, peito claro e patas ----------
    const tronco = el(1, cor.corpo, 0.5, 0.5, 0.62, 'tronco');
    tronco.position.set(0, 0.55, -0.05);
    const peito = el(1, cor.claro, 0.33, 0.38, 0.2, 'peito');
    peito.position.set(0, 0.56, 0.4);
    const patas = [-1, 1].map((lado) => {
      const pata = el(1, cor.escuro, 0.13, 0.1, 0.2, 'pata');
      pata.position.set(0.2 * lado, 0.09, 0.32);
      return pata;
    });
    grupo.add(tronco, peito, ...patas);

    // ---------- Cabeça: crânio, focinho comprido, nariz, bochechas, olhos, boca e orelhas ----------
    const cabeca = new T.Group();
    cabeca.name = 'cabeca';
    cabeca.position.set(0, 1.12, 0.14);
    cabeca.scale.set(1.08, 0.95, 1.05);                 // cabeça larga e baixa (o Gato tem-na mais pequena)
    const cranio = el(1, cor.corpo, 0.46, 0.38, 0.42, 'cranio');
    const focinho = el(1, cor.claro, 0.17, 0.14, 0.36, 'focinho');
    focinho.position.set(0, -0.09, 0.42);
    const nariz = el(0.05, cor.escuro, 1, 0.8, 1, 'nariz');
    nariz.position.set(0, -0.04, 0.76);
    const bochechas = [-1, 1].map((lado) => {
      const b = el(1, cor.rubor, 0.09, 0.055, 0.04, 'bochecha');
      b.position.set(0.3 * lado, -0.08, 0.28);
      b.rotation.y = 0.5 * lado;
      return b;
    });
    cabeca.add(cranio, focinho, nariz, ...bochechas);

    // Olhos pretos com brilho; as pálpebras são esferas da cor do pelo que os tapam ao fechar (scale.y de ~0 a 1)
    const olhos = [];
    const palpebras = [];
    for (const lado of [-1, 1]) {
      const olho = el(0.06, '#2f2a2e', 1, 1.15, 0.7, 'olho');
      olho.position.set(0.2 * lado, 0.06, 0.37);
      const brilho = el(0.02, '#ffffff', 1, 1, 1, 'brilho');
      brilho.position.set(0.012 * lado + 0.01, 0.028, 0.045);
      olho.add(brilho);
      const palpebra = el(0.075, cor.corpo, 1.05, 1, 0.8, 'palpebra');
      palpebra.position.copy(olho.position);
      palpebra.position.z += 0.008;
      palpebra.scale.y = 0.02;
      olhos.push(olho);
      palpebras.push(palpebra);
      cabeca.add(olho, palpebra);
    }

    // Boca: meia-lua virada para baixo (sorriso); scale.y achata-a quando está aborrecida
    const boca = new T.Mesh(new T.TorusGeometry(0.06, 0.011, 8, 20, Math.PI), U().mat(cor.escuro));
    boca.name = 'boca';
    boca.rotation.z = Math.PI;
    boca.position.set(0, -0.13, 0.7);
    cabeca.add(boca);

    // Orelhas grandes e pontiagudas, com o interior da cor de acento
    const orelhas = [];
    for (const lado of [-1, 1]) {
      const orelha = U().cone(0.17, 0.44, cor.corpo, 18, 'orelha');
      orelha.position.set(0.25 * lado, 0.46, -0.02);
      orelha.rotation.z = -0.28 * lado;
      const dentro = U().cone(0.09, 0.3, cor.rubor, 14, 'orelha-dentro');
      dentro.position.set(0, -0.03, 0.06);
      orelha.add(dentro);
      orelhas.push(orelha);
      cabeca.add(orelha);
    }

    // Marca na testa (só na lendária): o 二, duas barras
    if (p.marca) {
      const marca = new T.Group();
      marca.name = 'marca';
      const barra = (largura, y) => {
        const b = new T.Mesh(new T.BoxGeometry(largura, 0.028, 0.02), U().mat('#c4545a'));
        b.position.y = y;
        return b;
      };
      marca.add(barra(0.13, 0.035), barra(0.22, -0.035));
      marca.position.set(0, 0.2, 0.4);
      marca.rotation.x = -0.35;                         // segue a curva da testa
      cabeca.add(marca);
    }
    grupo.add(cabeca);

    // ---------- Caudas em leque: cada uma é um grupo que roda à volta da base ----------
    // Cada cauda aponta para cima (+y) e abre-se no plano do ecrã (rotação em z), para o leque se ver de frente;
    // com poucas caudas deitam-se mais para trás (como uma cauda de raposa a sério), com muitas ficam quase em pé.
    const caudas = [];
    const n = p.caudas;
    const abertura = Math.min(2.6, 0.62 * Math.max(0, n - 1));          // largura total do leque (radianos)
    const inclinacao = -(0.3 + 0.85 * Math.pow(1 / Math.max(n, 1), 0.7));   // para trás: 1 cauda ≈ -1.15, 9 caudas ≈ -0.48
    for (let k = 0; k < n; k++) {
      const cauda = new T.Group();
      cauda.name = 'cauda';
      const corpoCauda = el(1, cor.corpo, 0.2, 0.6, 0.2, 'cauda-corpo');
      corpoCauda.position.y = 0.5;
      const ponta = el(1, cor.claro, 0.15, 0.2, 0.15, 'cauda-ponta');
      ponta.position.y = 1.02;
      cauda.add(corpoCauda, ponta);
      const fi = n === 1 ? 0 : -abertura / 2 + (abertura * k) / (n - 1);
      cauda.position.set(0, 0.6, -0.4);
      cauda.rotation.set(inclinacao, 0, fi, 'XZY');                    // primeiro abre em z, depois deita para trás
      caudas.push(cauda);
      grupo.add(cauda);
    }

    // Aura suave da lendária (a opacidade pulsa em poseBase)
    let aura = null;
    if (p.fase === 'lendaria') {
      aura = U().criarAura();
      aura.name = 'aura';
      aura.position.set(0, 1.0, -0.7);
      grupo.add(aura);
    }

    const corpo = {
      grupo,
      escala: p.escala,
      aura,
      partes: { cabeca, olhos, palpebras, boca, caudas, orelhas },
      animar(nome, t, intensidade) {
        U().poseBase(corpo, nome, t, intensidade);
        const P = corpo.partes;

        // Caudas: abanam devagar (no plano do leque), cada uma com o seu ritmo
        const ritmo = nome === 'festinha' ? 14 : 1.6;
        const amplitude = nome === 'festinha' ? 0.3 : 0.06;
        P.caudas.forEach((c, k) => { c.rotation.z += amplitude * Math.sin(t * ritmo + k * 0.7); });

        if (nome === 'idle') {
          // Fareja: de tempos a tempos levanta o focinho e o nariz mexe
          const ciclo = t % 6;
          if (ciclo > 4.2 && ciclo < 5.4) {
            const u = (ciclo - 4.2) / 1.2;
            P.cabeca.rotation.x = -0.18 * Math.sin(u * Math.PI);
            nariz.position.y += 0.012 * Math.sin(t * 22);
          }
        } else if (nome === 'aborrecida') {
          // Esconde a cara atrás das duas primeiras caudas, que passam para a frente
          const u = Math.min(t / 0.6, 1);
          const lerp = (a, b) => a + (b - a) * u;
          P.caudas.slice(0, 2).forEach((c, k) => {
            const lado = k === 0 ? 1 : -1;
            c.position.set(lerp(0, 0.2 * lado), lerp(0.6, 0.7), lerp(-0.4, 0.95));
            c.rotation.set(lerp(c.rotation.x, 0.3), 0, lerp(c.rotation.z, 0.5 * lado), 'XZY');   // cruzam-se à frente da cara
          });
        } else if (nome === 'dorme') {
          // As caudas descem para trás
          P.caudas.forEach((c) => { c.rotation.x -= 0.4; });
        } else if (nome === 'salta') {
          P.caudas.forEach((c) => { c.rotation.x += 0.25; });
        }
      },
    };
    U().registar(corpo, [grupo, cabeca, nariz, boca, ...olhos, ...palpebras, ...orelhas, ...caudas]);
    return corpo;
  };
})(typeof window !== 'undefined' ? window : globalThis);
