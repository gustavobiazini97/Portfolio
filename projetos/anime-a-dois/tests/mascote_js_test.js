// Testes do mascote que não precisam de browser (lógica de estado e construção dos corpos 3D).
// O three.js constrói geometrias sem WebGL, por isso os corpos testam-se em Node.
// Correr com: node tests/mascote_js_test.js
//
// Os scripts do mascote são clássicos (escrevem em window.Mascote); aqui carregam-se com globalThis no lugar de window.

const assert = require('assert');
const fs = require('fs');
const path = require('path');

const pasta = path.join(__dirname, '..', 'public', 'mascote');
globalThis.THREE = require(path.join(pasta, 'three.min.js'));

// Carrega os scripts pela ordem do mascote.html (os que ainda não existem são saltados)
for (const f of ['mascote-estado.js', 'mascote-ovo.js', 'mascote-raposa.js', 'mascote-gato.js', 'mascote-motor.js']) {
  const caminho = path.join(pasta, f);
  if (fs.existsSync(caminho)) require(caminho);
}
const M = globalThis.Mascote;

let falhas = 0;
function teste(nome, fn) {
  try {
    fn();
    console.log('ok    ' + nome);
  } catch (e) {
    falhas++;
    console.log('FALHA ' + nome + '  (' + e.message.split('\n')[0] + ')');
  }
}

const estado = (extra) => Object.assign({ especie: 'raposa', nome: null, cor: 0, fase: 'adulta', humor: 'feliz', episodiosJuntos: 400, deCostas: false, podeEscolher: false }, extra);

// ---------- Estado → parâmetros visuais ----------
teste('cria da raposa: 1 cauda, sem marca', () => {
  const p = M.Estado.parametros(estado({ fase: 'cria' }));
  assert.strictEqual(p.corpo, 'raposa');
  assert.strictEqual(p.caudas, 1);
  assert.strictEqual(p.marca, false);
});
teste('raposa: jovem 2, adulta 4, lendária 9 caudas', () => {
  assert.strictEqual(M.Estado.parametros(estado({ fase: 'jovem' })).caudas, 2);
  assert.strictEqual(M.Estado.parametros(estado({ fase: 'adulta' })).caudas, 4);
  assert.strictEqual(M.Estado.parametros(estado({ fase: 'lendaria' })).caudas, 9);
});
teste('marca 二 só na lendária', () => {
  assert.strictEqual(M.Estado.parametros(estado({ fase: 'adulta' })).marca, false);
  assert.strictEqual(M.Estado.parametros(estado({ fase: 'lendaria' })).marca, true);
  assert.strictEqual(M.Estado.parametros(estado({ fase: 'lendaria', especie: 'gato' })).marca, true);
});
teste('gato: 1 cauda até à adulta, 2 na lendária', () => {
  assert.strictEqual(M.Estado.parametros(estado({ especie: 'gato', fase: 'cria' })).caudas, 1);
  assert.strictEqual(M.Estado.parametros(estado({ especie: 'gato', fase: 'adulta' })).caudas, 1);
  assert.strictEqual(M.Estado.parametros(estado({ especie: 'gato', fase: 'lendaria' })).caudas, 2);
});
teste('ovo: corpo ovo e 0 caudas', () => {
  const p = M.Estado.parametros(estado({ especie: null, fase: 'ovo' }));
  assert.strictEqual(p.corpo, 'ovo');
  assert.strictEqual(p.caudas, 0);
});
teste('já chocou mas sem espécie: continua o ovo, e pode escolher', () => {
  const p = M.Estado.parametros(estado({ especie: null, fase: 'cria', podeEscolher: true }));
  assert.strictEqual(p.corpo, 'ovo');
  assert.strictEqual(p.podeEscolher, true);
});
teste('escala cresce com a fase', () => {
  const e = (f) => M.Estado.parametros(estado({ fase: f })).escala;
  assert.ok(e('cria') < e('jovem') && e('jovem') < e('adulta') && e('adulta') < e('lendaria'));
});
teste('estados inválidos dão parâmetros seguros (ovo, feliz), sem lançar', () => {
  for (const mau of [null, undefined, {}, { fase: 'xyz' }, { especie: 'dragao', fase: 'adulta' }, 'texto', 42]) {
    const p = M.Estado.parametros(mau);
    assert.strictEqual(p.corpo, 'ovo');
    assert.strictEqual(p.humor, 'feliz');
    assert.strictEqual(p.deCostas, false);
  }
});
teste('cor fora de 0–5 volta a 0; paletas dão cores em hexadecimal', () => {
  assert.strictEqual(M.Estado.parametros(estado({ cor: 9 })).cor, 0);
  assert.strictEqual(M.Estado.parametros(estado({ cor: -1 })).cor, 0);
  assert.strictEqual(M.Estado.parametros(estado({ cor: 3 })).cor, 3);
  for (let i = 0; i < 6; i++) {
    const pal = M.Util.paleta(i);
    for (const k of ['corpo', 'claro', 'escuro', 'acento']) assert.match(pal[k], /^#[0-9a-f]{6}$/i);
  }
});
teste('nome vem como texto limpo ou null', () => {
  assert.strictEqual(M.Estado.parametros(estado({ nome: 'Kuro' })).nome, 'Kuro');
  assert.strictEqual(M.Estado.parametros(estado({ nome: 42 })).nome, null);
});

// ---------- Ovo ----------
teste('ovo: constrói-se sem WebGL e segue o contrato do corpo', () => {
  const p = M.Estado.parametros(estado({ especie: null, fase: 'ovo' }));
  const c = M.Corpos.ovo(p);
  assert.ok(c.grupo.children.length > 0);
  for (const k of ['olhos', 'palpebras', 'caudas', 'orelhas']) assert.ok(Array.isArray(c.partes[k]), k);
  for (const nome of ['idle', 'salta', 'festinha', 'dorme', 'aborrecida', 'costas', 'eclodir']) c.animar(nome, 0.7, 1);
});
teste('ovo: o balanço do idle mexe no grupo', () => {
  const c = M.Corpos.ovo(M.Estado.parametros(null));
  c.animar('idle', 0, 1);
  const a = c.grupo.rotation.z;
  c.animar('idle', 0.75, 1);
  assert.notStrictEqual(c.grupo.rotation.z, a);
});

// ---------- Raposa ----------
teste('raposa: caudas 1, 2, 4 e 9 nas fases cria, jovem, adulta e lendária', () => {
  for (const [fase, n] of [['cria', 1], ['jovem', 2], ['adulta', 4], ['lendaria', 9]]) {
    const c = M.Corpos.raposa(M.Estado.parametros(estado({ especie: 'raposa', fase })));
    assert.strictEqual(c.partes.caudas.length, n, fase);
  }
});
teste('raposa: 2 orelhas, 2 olhos, 2 pálpebras; marca 二 só na lendária', () => {
  const adulta = M.Corpos.raposa(M.Estado.parametros(estado({ fase: 'adulta' })));
  assert.strictEqual(adulta.partes.orelhas.length, 2);
  assert.strictEqual(adulta.partes.olhos.length, 2);
  assert.strictEqual(adulta.partes.palpebras.length, 2);
  assert.strictEqual(adulta.grupo.getObjectByName('marca'), undefined);
  const lendaria = M.Corpos.raposa(M.Estado.parametros(estado({ fase: 'lendaria' })));
  assert.ok(lendaria.grupo.getObjectByName('marca'));
  assert.ok(lendaria.aura, 'a lendária tem aura');
});
teste('raposa: aborrecida baixa as orelhas e deixa as caudas descair, sem tapar a cara', () => {
  const c = M.Corpos.raposa(M.Estado.parametros(estado({ fase: 'adulta', humor: 'aborrecida' })));
  c.animar('idle', 0, 1);
  const orelha = c.partes.orelhas[0].rotation.z;
  const cauda = c.partes.caudas[0].rotation.x;
  c.animar('aborrecida', 1, 1);
  assert.ok(Math.abs(c.partes.orelhas[0].rotation.z - orelha) > 0.3, 'orelhas');
  assert.ok(c.partes.caudas[0].rotation.x < cauda - 0.3, 'caudas');
  // nenhuma cauda fica à frente da cara (z da cabeça ~0.14; a cara está em z > 0.5)
  c.grupo.updateMatrixWorld(true);
  c.partes.caudas.forEach((t) => assert.ok(t.position.z < 0.3, 'cauda à frente'));
});
teste('raposa: a dormir fecha as pálpebras e baixa a cabeça', () => {
  const c = M.Corpos.raposa(M.Estado.parametros(estado({ fase: 'adulta' })));
  c.animar('idle', 0, 1);
  const aberta = c.partes.palpebras[0].scale.y;
  const altura = c.partes.cabeca.position.y;
  c.animar('dorme', 1, 1);
  assert.ok(c.partes.palpebras[0].scale.y > aberta + 0.5);
  assert.ok(c.partes.cabeca.position.y < altura);
});
teste('raposa: festinha abana as caudas e salta levanta o corpo; todas as animações correm sem lançar', () => {
  const c = M.Corpos.raposa(M.Estado.parametros(estado({ fase: 'lendaria' })));
  c.animar('salta', 0.35, 1);
  assert.ok(c.grupo.position.y > 0.3);
  for (const nome of ['idle', 'festinha', 'dorme', 'aborrecida', 'costas', 'eclodir']) for (const t of [0, 0.3, 1.7, 9]) c.animar(nome, t, 1);
});
teste('raposa: de costas roda o corpo e depois volta a posição normal', () => {
  const c = M.Corpos.raposa(M.Estado.parametros(estado({ fase: 'adulta' })));
  c.animar('costas', 1, 1);
  assert.ok(Math.abs(c.grupo.rotation.y - Math.PI) < 1e-6);
  c.animar('idle', 1, 1);
  assert.strictEqual(c.grupo.rotation.y, 0);
});

// ---------- Gato ----------
teste('gato: 1 cauda até à adulta e 2 na lendária', () => {
  for (const [fase, n] of [['cria', 1], ['jovem', 1], ['adulta', 1], ['lendaria', 2]]) {
    const c = M.Corpos.gato(M.Estado.parametros(estado({ especie: 'gato', fase })));
    assert.strictEqual(c.partes.caudas.length, n, fase);
  }
});
teste('gato: 2 orelhas, 2 olhos, 2 pálpebras, 6 bigodes; marca 二 só na lendária', () => {
  const c = M.Corpos.gato(M.Estado.parametros(estado({ especie: 'gato', fase: 'adulta' })));
  assert.strictEqual(c.partes.orelhas.length, 2);
  assert.strictEqual(c.partes.olhos.length, 2);
  assert.strictEqual(c.partes.palpebras.length, 2);
  let bigodes = 0;
  c.grupo.traverse((n) => { if (n.name === 'bigode') bigodes++; });
  assert.strictEqual(bigodes, 6);
  assert.strictEqual(c.grupo.getObjectByName('marca'), undefined);
  const l = M.Corpos.gato(M.Estado.parametros(estado({ especie: 'gato', fase: 'lendaria' })));
  assert.ok(l.grupo.getObjectByName('marca'));
  assert.ok(l.aura, 'a lendária tem aura');
});
teste('gato: espreguiça-se de tempos a tempos (o corpo estica na horizontal)', () => {
  const c = M.Corpos.gato(M.Estado.parametros(estado({ especie: 'gato', fase: 'adulta' })));
  let min = Infinity, max = -Infinity;
  for (let t = 0; t < 60; t += 0.1) {
    c.animar('idle', t, 1);
    min = Math.min(min, c.grupo.scale.x);
    max = Math.max(max, c.grupo.scale.x);
  }
  assert.ok(max / min > 1.05, 'razão ' + max / min);
});
teste('gato: a dormir baixa a cabeça e fecha as pálpebras', () => {
  const c = M.Corpos.gato(M.Estado.parametros(estado({ especie: 'gato', fase: 'adulta' })));
  c.animar('idle', 0, 1);
  const altura = c.partes.cabeca.position.y;
  const aberta = c.partes.palpebras[0].scale.y;
  c.animar('dorme', 1, 1);
  assert.ok(c.partes.cabeca.position.y < altura);
  assert.ok(c.partes.palpebras[0].scale.y > aberta + 0.5);
});
teste('gato: aborrecida bate com a cauda', () => {
  const c = M.Corpos.gato(M.Estado.parametros(estado({ especie: 'gato', fase: 'adulta', humor: 'aborrecida' })));
  c.animar('aborrecida', 0, 1);
  const a = c.partes.caudas[0].rotation.z;
  c.animar('aborrecida', 0.16, 1);
  assert.notStrictEqual(c.partes.caudas[0].rotation.z, a);
});
teste('gato: não é a raposa com enfeites (cabeça e corpo com proporções próprias)', () => {
  const r = M.Corpos.raposa(M.Estado.parametros(estado({ especie: 'raposa', fase: 'adulta' })));
  const gt = M.Corpos.gato(M.Estado.parametros(estado({ especie: 'gato', fase: 'adulta' })));
  assert.notStrictEqual(gt.partes.cabeca.scale.x, r.partes.cabeca.scale.x);
  assert.notStrictEqual(gt.grupo.getObjectByName('tronco').scale.y, r.grupo.getObjectByName('tronco').scale.y);
});
teste('gato: todas as animações correm sem lançar, salta levanta o corpo e de costas roda-o', () => {
  const c = M.Corpos.gato(M.Estado.parametros(estado({ especie: 'gato', fase: 'lendaria' })));
  for (const nome of ['idle', 'festinha', 'dorme', 'aborrecida', 'costas', 'eclodir']) for (const t of [0, 0.3, 2.5, 9]) c.animar(nome, t, 1);
  c.animar('salta', 0.35, 1);
  assert.ok(c.grupo.position.y > 0.3);
  c.animar('costas', 1, 1);
  assert.ok(Math.abs(c.grupo.rotation.y - Math.PI) < 1e-6);
});

// ---------- Motor ----------
teste('motor: funções públicas e ponte por defeito (não faz nada)', () => {
  for (const f of ['iniciar', 'definirEstado', 'evento', 'congelar']) assert.strictEqual(typeof M[f], 'function', f);
  assert.strictEqual(M.ponte.enviar({ tipo: 'toque' }), undefined);
});

console.log(falhas ? '\n' + falhas + ' falha(s)' : '\nTudo ok');
process.exit(falhas ? 1 : 0);
