// Mascote do casal — o Gato: corpo esguio e flexível, cabeça pequena e redonda, focinho curto, bigodes,
// orelhas triangulares e cauda comprida (duas na lendária). Feito só com formas macias, sem modelos externos.
// Contrato: Mascote.Corpos.gato(p) → { grupo, partes, escala, aura?, animar(nome, t, intensidade) }.
// Movimentos próprios: espreguiça-se, lambe a pata, dorme enrolado e vira costas a bater com a cauda.
//
// Eixos: y para cima, z para a frente (a câmara está em +z).

(function (g) {
  'use strict';

  const M = (g.Mascote = g.Mascote || {});
  const Corpos = (M.Corpos = M.Corpos || {});
  const U = () => M.Util;

  Corpos.gato = function (p) {
    const T = g.THREE;
    const cor = U().paleta(p.cor);
    const grupo = new T.Group();
    grupo.name = 'gato';
    const el = (r, c, sx, sy, sz, nome) => U().elipsoide(r, c, sx, sy, sz, nome);

    // ---------- Corpo: tronco mais comprido e fino do que o da Raposa, peito claro e patas ----------
    const tronco = el(1, cor.corpo, 0.44, 0.46, 0.66, 'tronco');
    tronco.position.set(0, 0.52, -0.08);
    const peito = el(1, cor.claro, 0.28, 0.34, 0.18, 'peito');
    peito.position.set(0, 0.52, 0.42);
    const patas = [-1, 1].map((lado) => {
      const pata = el(1, cor.claro, 0.11, 0.1, 0.18, 'pata');       // patas "de meias" claras
      pata.position.set(0.17 * lado, 0.09, 0.36);
      return pata;
    });
    grupo.add(tronco, peito, ...patas);

    // ---------- Cabeça: pequena e redonda, focinho curto, nariz rosa ----------
    const cabeca = new T.Group();
    cabeca.name = 'cabeca';
    cabeca.position.set(0, 1.04, 0.18);
    cabeca.scale.set(0.9, 0.9, 0.9);                    // mais pequena do que a da Raposa (que é larga e baixa)
    const cranio = el(1, cor.corpo, 0.42, 0.38, 0.38, 'cranio');
    const focinho = el(1, cor.claro, 0.15, 0.1, 0.13, 'focinho');   // focinho curto
    focinho.position.set(0, -0.1, 0.34);
    const nariz = el(0.035, cor.rubor, 1.2, 0.8, 1, 'nariz');
    nariz.position.set(0, -0.05, 0.46);
    const bochechas = [-1, 1].map((lado) => {
      const b = el(1, cor.rubor, 0.08, 0.05, 0.04, 'bochecha');
      b.position.set(0.28 * lado, -0.1, 0.26);
      b.rotation.y = 0.5 * lado;
      return b;
    });
    cabeca.add(cranio, focinho, nariz, ...bochechas);

    // Olhos grandes (o gato tem-nos mais afastados e redondos); as pálpebras tapam-nos ao fechar
    const olhos = [];
    const palpebras = [];
    for (const lado of [-1, 1]) {
      const olho = el(0.065, '#2f2a2e', 1, 1.25, 0.7, 'olho');
      olho.position.set(0.19 * lado, 0.04, 0.33);
      const brilho = el(0.022, '#ffffff', 1, 1, 1, 'brilho');
      brilho.position.set(0.012 * lado + 0.01, 0.03, 0.045);
      olho.add(brilho);
      const palpebra = el(0.08, cor.corpo, 1.05, 1.05, 0.8, 'palpebra');
      palpebra.position.copy(olho.position);
      palpebra.position.z += 0.008;
      palpebra.scale.y = 0.02;
      olhos.push(olho);
      palpebras.push(palpebra);
      cabeca.add(olho, palpebra);
    }

    // Boca: pequeno "w" feito com meia-lua (sorriso); scale.y achata-a quando está aborrecida
    const boca = new T.Mesh(new T.TorusGeometry(0.045, 0.009, 8, 16, Math.PI), U().mat(cor.escuro));
    boca.name = 'boca';
    boca.rotation.z = Math.PI;
    boca.position.set(0, -0.15, 0.44);
    cabeca.add(boca);

    // Bigodes: três de cada lado, finos e ligeiramente abertos em leque
    const bigodes = [];
    for (const lado of [-1, 1]) {
      for (let k = 0; k < 3; k++) {
        const b = new T.Mesh(new T.CylinderGeometry(0.006, 0.006, 0.3, 5), U().mat('#7a6a66'));
        b.name = 'bigode';
        b.rotation.z = Math.PI / 2 + (k - 1) * 0.2 * lado;   // deitado na horizontal, aberto em leque
        b.position.set(0.3 * lado, -0.09 + (1 - k) * 0.025, 0.34);
        b.rotation.y = 0.35 * lado;                          // voltado um pouco para fora
        bigodes.push(b);
        cabeca.add(b);
      }
    }

    // Orelhas triangulares, mais curtas e mais juntas do que as da Raposa
    const orelhas = [];
    for (const lado of [-1, 1]) {
      const orelha = U().cone(0.15, 0.3, cor.corpo, 4, 'orelha');   // 4 lados = pirâmide, mais "felina"
      orelha.position.set(0.24 * lado, 0.4, 0);
      orelha.rotation.z = -0.35 * lado;
      const dentro = U().cone(0.08, 0.2, cor.rubor, 4, 'orelha-dentro');
      dentro.position.set(0, -0.02, 0.05);
      orelha.add(dentro);
      orelhas.push(orelha);
      cabeca.add(orelha);
    }

    // Marca na testa (só na lendária): o 二, duas barras
    if (p.marca) {
      const marca = new T.Group();
      marca.name = 'marca';
      const barra = (largura, y) => {
        const b = new T.Mesh(new T.BoxGeometry(largura, 0.026, 0.02), U().mat('#c4545a'));
        b.position.y = y;
        return b;
      };
      marca.add(barra(0.12, 0.032), barra(0.2, -0.032));
      marca.position.set(0, 0.2, 0.34);
      marca.rotation.x = -0.4;
      cabeca.add(marca);
    }
    grupo.add(cabeca);

    // ---------- Cauda(s): compridas e finas, a subir para trás e a curvar; a ponta é mais escura ----------
    const caudas = [];
    const n = p.caudas;
    for (let k = 0; k < n; k++) {
      const cauda = new T.Group();
      cauda.name = 'cauda';
      const corpoCauda = el(1, cor.corpo, 0.1, 0.62, 0.1, 'cauda-corpo');
      corpoCauda.position.y = 0.55;
      const ponta = el(1, cor.escuro, 0.09, 0.2, 0.09, 'cauda-ponta');
      ponta.position.y = 1.12;
      cauda.add(corpoCauda, ponta);
      const lado = n === 1 ? 1 : (k === 0 ? -1 : 1);       // a única cauda sai para a direita; as duas abrem-se em V
      cauda.position.set(0.14 * lado, 0.45, -0.55);
      cauda.rotation.set(-0.4, 0, -0.5 * lado, 'XZY');     // sobe um pouco para trás e abre para o lado, para se ver de frente
      caudas.push(cauda);
      grupo.add(cauda);
    }

    // Aura suave da lendária (a opacidade pulsa em poseBase)
    let aura = null;
    if (p.fase === 'lendaria') {
      aura = U().criarAura();
      aura.name = 'aura';
      aura.position.set(0, 0.95, -0.5);
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

        // Cauda: balança devagar; cada uma com o seu ritmo (na festinha abana depressa)
        const ritmo = nome === 'festinha' ? 12 : 1.4;
        const amplitude = nome === 'festinha' ? 0.3 : 0.12;
        P.caudas.forEach((c, k) => { c.rotation.z += amplitude * Math.sin(t * ritmo + k * 1.3); });

        // Orelhas mexem-se de vez em quando
        const sacode = (t % 7) > 6.7 ? Math.sin((t % 7 - 6.7) * 40) * 0.12 : 0;
        P.orelhas[0].rotation.z += sacode;

        if (nome === 'idle') {
          // Ciclo de 10 s: espreguiça-se (6–8 s) e lambe a pata (2–4 s)
          const c = t % 10;
          if (c > 6 && c < 8) {
            const u = Math.sin(((c - 6) / 2) * Math.PI);          // 0 → 1 → 0
            corpo.grupo.scale.x *= 1 + 0.14 * u;                  // estica na horizontal
            corpo.grupo.scale.y *= 1 - 0.1 * u;                   // e baixa
            P.cabeca.position.z += 0.12 * u;
            P.cabeca.position.y -= 0.1 * u;
            P.cabeca.rotation.x = 0.2 * u;
          } else if (c > 2 && c < 4) {
            const u = Math.sin(((c - 2) / 2) * Math.PI);
            patas[0].position.y += 0.55 * u;                      // a pata sobe até à boca
            patas[0].position.z += 0.1 * u;
            patas[0].position.x -= 0.06 * u;
            P.cabeca.rotation.x = 0.15 * u;                       // e a cabeça desce ao encontro dela
            P.cabeca.rotation.z = 0.1 * u * Math.sin(t * 14);
          }
        } else if (nome === 'aborrecida') {
          // Vira o corpo para o lado e bate com a cauda (impaciente)
          corpo.grupo.rotation.y += 0.8;
          P.caudas.forEach((c) => { c.rotation.z += 0.45 * Math.sin(t * 11); });
        } else if (nome === 'dorme') {
          // Enrola-se: a cauda deita-se à volta do corpo e as orelhas descaem
          P.caudas.forEach((c, k) => {
            c.rotation.x = 1.3;
            c.rotation.z = (k === 0 ? 1 : -1) * 1.35;
            c.position.set(0, 0.2, -0.25);
          });
          P.orelhas.forEach((o) => { o.rotation.z *= 1.5; });
        } else if (nome === 'salta') {
          P.caudas.forEach((c) => { c.rotation.x += 0.3; });      // cauda ao alto
        }
      },
    };
    // A pata da frente da esquerda mexe-se (lamber), por isso também fica registada
    U().registar(corpo, [grupo, cabeca, nariz, boca, ...olhos, ...palpebras, ...orelhas, ...caudas, ...patas]);
    return corpo;
  };
})(typeof window !== 'undefined' ? window : globalThis);
