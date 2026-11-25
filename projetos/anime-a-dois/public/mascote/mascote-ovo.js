// Mascote do casal — o ovo (igual para todos até chocar; depois escolhem a espécie).
// Segue o contrato dos corpos: Mascote.Corpos.ovo(p) → { grupo, partes, escala, animar(nome, t, intensidade) }.

(function (g) {
  'use strict';

  const M = (g.Mascote = g.Mascote || {});
  const Corpos = (M.Corpos = M.Corpos || {});
  const TAU = Math.PI * 2;

  Corpos.ovo = function (p) {
    const cores = M.Util.paleta(p.cor);
    const grupo = new g.THREE.Group();
    grupo.name = 'ovo';

    // Casca: elipsoide alto e estreito, num tom suave da cor escolhida
    const casca = M.Util.elipsoide(1, M.Util.mistura(cores.corpo, '#ffffff', 0.4), 0.6, 0.78, 0.6, 'casca');
    casca.position.y = 0.78;
    grupo.add(casca);

    // Manchas na casca (esferas achatadas à superfície; ângulos escolhidos à mão para ficar natural)
    const manchas = [[0.9, 0.4, 0.16], [1.4, 2.4, 0.12], [2.0, 4.2, 0.14], [2.4, 1.1, 0.1], [0.5, 5.4, 0.1], [1.7, 3.3, 0.09]];
    for (const [teta, fi, r] of manchas) {
      const m = M.Util.elipsoide(r, cores.acento, 1, 0.55, 1, 'mancha');
      const x = 0.6 * Math.sin(teta) * Math.cos(fi);
      const y = 0.78 + 0.78 * Math.cos(teta);
      const z = 0.6 * Math.sin(teta) * Math.sin(fi);
      m.position.set(x, y, z);
      m.lookAt(x * 2, y, z * 2);     // vira a mancha para fora
      grupo.add(m);
    }

    const corpo = {
      grupo,
      escala: p.escala,
      // O ovo não tem cara nem cauda: as listas existem (vazias) para os corpos terem todos a mesma forma
      partes: { cabeca: null, olhos: [], palpebras: [], boca: null, caudas: [], orelhas: [] },
      animar(nome, t, intensidade) {
        M.Util.poseBase(corpo, nome, t, intensidade);
        if (nome === 'eclodir') {
          // A tremer cada vez menos, até abrir (o motor troca pelo bicho no fim)
          const u = Math.min(t / 0.9, 1);
          grupo.scale.setScalar(corpo.escala * (1 + 0.04 * u));
          grupo.rotation.z = 0.35 * Math.sin(t * 28) * (1 - u);
        } else if (nome === 'festinha') {
          grupo.rotation.z = 0.12 * Math.sin(t * 9);
        } else if (nome !== 'salta') {
          // Balança devagar à espera de chocar
          grupo.rotation.z = 0.05 * Math.sin((t * TAU) / 3);
        }
      },
    };
    M.Util.registar(corpo, [grupo]);
    return corpo;
  };
})(typeof window !== 'undefined' ? window : globalThis);
