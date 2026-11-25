// Capturas de ecrã do mascote (para o Gustavo aprovar o aspeto). NÃO corre no CI: precisa do Playwright e do Chromium.
// Uso: node tests/mascote_capturas.js [pasta-de-saída] [especie] [fase]
//   ex.: node tests/mascote_capturas.js /tmp/capturas raposa      (todas as fases e humores da Raposa)
// Abre mascote.html por file://, manda um estado ao bicho (Mascote.definirEstado), congela um quadro e grava um PNG.

const path = require('path');
const fs = require('fs');

// O Playwright vem instalado a nível global no ambiente cloud
let chromium;
try { ({ chromium } = require('playwright')); } catch (e) { ({ chromium } = require('/opt/node22/lib/node_modules/playwright')); }

const saida = path.resolve(process.argv[2] || 'capturas');
const soEspecie = process.argv[3];
const soFase = process.argv[4];
const pagina = 'file://' + path.join(__dirname, '..', 'public', 'mascote', 'mascote.html');

const FASES = ['cria', 'jovem', 'adulta', 'lendaria'];
const HUMORES = ['feliz', 'aborrecida', 'a_dormir'];

(async () => {
  fs.mkdirSync(saida, { recursive: true });
  const browser = await chromium.launch({ args: ['--use-gl=angle', '--use-angle=swiftshader', '--enable-unsafe-swiftshader', '--ignore-gpu-blocklist'] });
  const ctx = await browser.newContext({ viewport: { width: 420, height: 460 }, deviceScaleFactor: 1.5 });
  const pg = await ctx.newPage();
  const erros = [];
  pg.on('pageerror', (e) => erros.push(String(e)));
  pg.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
  await pg.goto(pagina);
  await pg.waitForFunction(() => window.Mascote && window.Mascote.iniciar);
  // Recria o bicho com captura ligada (preserveDrawingBuffer) para o screenshot apanhar o WebGL
  await pg.evaluate(() => {
    const c = document.getElementById('mascote');
    window.Mascote.iniciar(c, { modo: 'ecra', captura: true });
  });

  const tirar = async (nome, estado, animacao, t) => {
    await pg.evaluate(([e, a, tt]) => { window.Mascote.definirEstado(e); window.Mascote.congelar(tt, a); }, [estado, animacao || null, t === undefined ? 1.3 : t]);
    await pg.waitForTimeout(80);
    await pg.screenshot({ path: path.join(saida, nome + '.png') });
  };
  const base = (extra) => Object.assign({ especie: 'raposa', nome: 'Kuro', cor: 0, fase: 'adulta', humor: 'feliz', episodiosJuntos: 400, deCostas: false, podeEscolher: false }, extra);

  // O ovo (igual para todos)
  if (!soEspecie || soEspecie === 'ovo') {
    await tirar('ovo', base({ especie: null, fase: 'ovo', nome: null }));
    await tirar('ovo-a-abrir', base({ especie: null, fase: 'cria', podeEscolher: true, nome: null }));
  }

  for (const especie of ['raposa', 'gato']) {
    if (soEspecie && soEspecie !== especie) continue;
    for (const fase of FASES) {
      if (soFase && soFase !== fase) continue;
      for (const humor of HUMORES) await tirar(`${especie}-${fase}-${humor}`, base({ especie, fase, humor }));
    }
    if (!soFase) {
      await tirar(`${especie}-adulta-costas`, base({ especie, deCostas: true }));
      await tirar(`${especie}-adulta-salta`, base({ especie }), 'salta', 0.35);
      await tirar(`${especie}-adulta-festinha`, base({ especie }), 'festinha', 0.4);
      await tirar(`${especie}-cores`, base({ especie, cor: 3 }));
    }
  }

  await browser.close();
  console.log('Capturas em ' + saida + ' (' + fs.readdirSync(saida).length + ' ficheiros)');
  if (erros.length) { console.log('Erros na página:\n' + erros.slice(0, 8).join('\n')); process.exit(1); }
})();
