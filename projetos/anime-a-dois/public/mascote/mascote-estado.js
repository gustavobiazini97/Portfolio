// Mascote do casal — estado e ferramentas comuns.
//   Mascote.Estado.parametros(estado)  transforma o JSON da API (GET /mascote) nos parâmetros que o desenho usa.
//   Mascote.Util                       paletas, formas e a pose base (respirar, piscar, saltar, dormir...) partilhadas pelos corpos.
// Script clássico (sem módulos): funciona no browser (window) e no Node (globalThis), para se poder testar sem WebGL.

(function (g) {
  'use strict';

  const M = (g.Mascote = g.Mascote || {});
  const TAU = Math.PI * 2;

  // ---------- Estado → parâmetros ----------

  const FASES = ['ovo', 'cria', 'jovem', 'adulta', 'lendaria'];
  const HUMORES = ['feliz', 'aborrecida', 'a_dormir'];
  const ESPECIES = ['raposa', 'gato'];

  // Tamanho de cada fase (o bicho cresce)
  const ESCALA = { ovo: 0.9, cria: 0.7, jovem: 0.85, adulta: 1, lendaria: 1.05 };

  // Caudas por espécie e fase: a Raposa ganha caudas ao crescer (9 na lendária, como o Kurama); o Gato tem 2 só na lendária
  const CAUDAS = {
    raposa: { ovo: 0, cria: 1, jovem: 2, adulta: 4, lendaria: 9 },
    gato:   { ovo: 0, cria: 1, jovem: 1, adulta: 1, lendaria: 2 },
  };

  M.Estado = {
    // Aceita qualquer coisa (null, texto, campos em falta...): o que não for válido vira o valor seguro (ovo, feliz).
    parametros(estado) {
      const e = estado && typeof estado === 'object' ? estado : {};
      const fase = FASES.includes(e.fase) ? e.fase : 'ovo';
      const especie = ESPECIES.includes(e.especie) ? e.especie : null;
      const humor = HUMORES.includes(e.humor) ? e.humor : 'feliz';

      // Sem espécie (ainda não escolheram) continua a ser o ovo, mesmo que já tenha chocado
      const corpo = fase === 'ovo' || especie === null ? 'ovo' : especie;
      const cor = Number.isInteger(e.cor) && e.cor >= 0 && e.cor <= 5 ? e.cor : 0;

      return {
        corpo,                                              // 'ovo' | 'raposa' | 'gato': qual função de corpo desenhar
        especie,
        fase,
        humor,
        deCostas: e.deCostas === true,
        podeEscolher: e.podeEscolher === true,
        cor,
        nome: typeof e.nome === 'string' && e.nome !== '' ? e.nome : null,
        caudas: corpo === 'ovo' ? 0 : CAUDAS[corpo][fase],
        marca: corpo !== 'ovo' && fase === 'lendaria',      // o 二 na testa
        escala: ESCALA[fase],
      };
    },
  };

  // ---------- Cores ----------

  // Uma paleta por cor (0–5), com o mesmo índice das paletas por pessoa: pastel, nada de tons chocantes
  const BASES = [
    { corpo: '#F4B183', acento: '#9FC9A0' },   // 0 sálvia e alperce
    { corpo: '#A9C4EE', acento: '#F4B6CF' },   // 1 azul e rosa
    { corpo: '#C9B6F2', acento: '#F2D98C' },   // 2 roxo e amarelo
    { corpo: '#9FD8D2', acento: '#F4B6A0' },   // 3 menta e pêssego
    { corpo: '#F4B6CF', acento: '#A9C4EE' },   // 4 rosa e azul
    { corpo: '#D9D2C5', acento: '#C7DFA0' },   // 5 creme e limão
  ];

  // Mistura duas cores hexadecimais (t = 0 devolve a, t = 1 devolve b)
  function mistura(a, b, t) {
    const n = (h) => [1, 3, 5].map((i) => parseInt(h.slice(i, i + 2), 16));
    const [ra, rb] = [n(a), n(b)];
    const c = ra.map((v, i) => Math.round(v + (rb[i] - v) * t));
    return '#' + c.map((v) => v.toString(16).padStart(2, '0')).join('');
  }

  // ---------- Ferramentas dos corpos ----------

  const Util = (M.Util = {});

  Util.mistura = mistura;

  // Cores da paleta i: corpo, claro (barriga, peito), escuro (sombras, nariz) e acento (bochechas, detalhes)
  Util.paleta = function (i) {
    const b = BASES[Number.isInteger(i) && i >= 0 && i < BASES.length ? i : 0];
    return {
      corpo: b.corpo,
      claro: mistura(b.corpo, '#ffffff', 0.6),
      escuro: mistura(b.corpo, '#5a4a4a', 0.55),
      acento: b.acento,
      rubor: mistura(b.corpo, '#e8808f', 0.5),     // bochechas e interior das orelhas
    };
  };

  // Aura da lendária: brilho redondo e suave. No browser é um sprite com degradê; sem canvas (testes em Node) é uma esfera simples.
  // A opacidade pulsa em poseBase (c.aura.material.opacity).
  Util.criarAura = function () {
    const T = g.THREE;
    if (typeof document === 'undefined') {
      return new T.Mesh(new T.SphereGeometry(1.2, 12, 8), new T.MeshBasicMaterial({ color: 0xfff0b8, transparent: true, opacity: 0.14, depthWrite: false }));
    }
    const c = document.createElement('canvas');
    c.width = c.height = 128;
    const x = c.getContext('2d');
    const gr = x.createRadialGradient(64, 64, 4, 64, 64, 62);
    gr.addColorStop(0, 'rgba(255,240,184,0.95)');
    gr.addColorStop(0.55, 'rgba(255,240,184,0.35)');
    gr.addColorStop(1, 'rgba(255,240,184,0)');
    x.fillStyle = gr;
    x.fillRect(0, 0, 128, 128);
    const sprite = new T.Sprite(new T.SpriteMaterial({ map: new T.CanvasTexture(c), transparent: true, opacity: 0.5, depthWrite: false }));
    sprite.scale.setScalar(3.4);
    return sprite;
  };

  // Material macio e sem brilho (Lambert); transparent só para a aura
  Util.mat = function (cor, opcoes) {
    return new g.THREE.MeshLambertMaterial(Object.assign({ color: cor }, opcoes || {}));
  };

  // Elipsoide: esfera de raio r esticada em x, y, z
  Util.elipsoide = function (r, cor, sx, sy, sz, nome) {
    const m = new g.THREE.Mesh(new g.THREE.SphereGeometry(r, 24, 16), Util.mat(cor));
    m.scale.set(sx, sy, sz);
    if (nome) m.name = nome;
    return m;
  };

  // Cone (pontas das orelhas)
  Util.cone = function (r, h, cor, lados, nome) {
    const m = new g.THREE.Mesh(new g.THREE.ConeGeometry(r, h, lados || 16), Util.mat(cor));
    if (nome) m.name = nome;
    return m;
  };

  // Guarda a pose de repouso de cada objeto (para a animação poder partir sempre do mesmo sítio)
  Util.registar = function (c, objetos) {
    c.repouso = c.repouso || [];
    for (const o of objetos) {
      if (!o) continue;
      o.userData.repouso = { p: o.position.clone(), r: o.rotation.clone(), s: o.scale.clone() };
      c.repouso.push(o);
    }
  };

  // Volta tudo à pose de repouso (chamado no início de cada quadro)
  Util.repor = function (c) {
    for (const o of c.repouso || []) {
      const r = o.userData.repouso;
      o.position.copy(r.p);
      o.rotation.copy(r.r);
      o.scale.copy(r.s);
    }
  };

  // Entrada com um pequeno "pop" (usada ao chocar)
  const easeOutBack = (u) => 1 + 2.7 * Math.pow(u - 1, 3) + 1.7 * Math.pow(u - 1, 2);

  // Pose comum a todos os corpos. t = segundos desde que a animação começou; i = intensidade (0–1).
  // Cada espécie chama isto primeiro e depois acrescenta os seus movimentos.
  Util.poseBase = function (c, nome, t, i) {
    i = i === undefined ? 1 : i;
    Util.repor(c);

    const gr = c.grupo;
    const esc = c.escala;
    const P = c.partes;
    let fecho = 0;      // 0 = olhos abertos, 1 = fechados
    let sorriso = 1;    // 1 = sorriso; perto de 0 = boca reta

    // Pisca-pisca de 4 em 4 segundos
    const ciclo = ((t % 4) + 4) % 4;
    if (ciclo < 0.14) fecho = Math.sin((ciclo / 0.14) * Math.PI);

    // Respirar: o corpo sobe e desce de leve
    gr.scale.set(esc, esc * (1 + 0.025 * Math.sin((t * TAU) / 3)), esc);

    switch (nome) {
      case 'salta': {
        // Agacha, sobe e cai: esticar e achatar
        const u = Math.min(t / 0.7, 1);
        const h = Math.sin(u * Math.PI);
        gr.position.y = 0.9 * h * i;
        gr.scale.set(esc * (1 + 0.08 * (1 - h)), esc * (1 - 0.14 * (1 - h) + 0.1 * h), esc * (1 + 0.08 * (1 - h)));
        break;
      }
      case 'festinha':
        // Olhos fechados, sorriso grande e a balançar
        fecho = 1;
        sorriso = 1.3;
        gr.rotation.z = 0.08 * Math.sin(t * 9) * i;
        break;
      case 'dorme':
        // Olhos fechados, corpo mais baixo e cabeça pendente; respira mais devagar
        fecho = 1;
        sorriso = 0.4;
        gr.scale.set(esc, esc * (0.9 + 0.02 * Math.sin((t * TAU) / 4)), esc);
        if (P.cabeca) {
          P.cabeca.position.y -= 0.12;
          P.cabeca.rotation.x = 0.25;
        }
        break;
      case 'aborrecida':
        // Boca reta e a cara voltada para o lado
        sorriso = 0.1;
        if (P.cabeca) {
          P.cabeca.rotation.y = 0.5;
          P.cabeca.rotation.z = 0.08;
        }
        break;
      case 'costas':
        // De costas para quem está muito atrás
        gr.rotation.y = Math.PI;
        break;
      case 'eclodir': {
        // Aparece com um pop (ao sair do ovo)
        const u = Math.min(t / 1.2, 1);
        gr.scale.setScalar(esc * Math.max(0.01, easeOutBack(u)));
        break;
      }
      default:
        break;
    }

    // Pálpebras: esferas da cor do pelo que tapam o olho (scale.y de ~0 a 1)
    for (const p of P.palpebras || []) p.scale.y = Math.max(0.02, fecho);
    // Boca: o sorriso achata-se quando está aborrecida ou a dormir
    if (P.boca) P.boca.scale.y = sorriso;
    // Aura da lendária: pulsa devagar
    if (c.aura) c.aura.material.opacity = (c.aura.isSprite ? 0.55 : 0.14) + (c.aura.isSprite ? 0.2 : 0.06) * Math.sin((t * TAU) / 2.5);
  };
})(typeof window !== 'undefined' ? window : globalThis);
