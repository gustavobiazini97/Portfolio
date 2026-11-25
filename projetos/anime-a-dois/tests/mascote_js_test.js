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

// ---------- Motor ----------
teste('motor: funções públicas e ponte por defeito (não faz nada)', () => {
  for (const f of ['iniciar', 'definirEstado', 'evento', 'congelar']) assert.strictEqual(typeof M[f], 'function', f);
  assert.strictEqual(M.ponte.enviar({ tipo: 'toque' }), undefined);
});

console.log(falhas ? '\n' + falhas + ' falha(s)' : '\nTudo ok');
process.exit(falhas ? 1 : 0);
