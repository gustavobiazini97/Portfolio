// Comportamentos do portfólio: tema, barra de cima, códigos de barras, detalhes dos projetos e PWA.
'use strict';

const raiz = document.documentElement;

// ─── Tema claro/escuro (o tema.js já aplicou o guardado) ───────────────────
const corBarra = document.getElementById('theme-color');
function aplicarTema(tema) {
  raiz.setAttribute('data-theme', tema);
  // Cor da barra do sistema na app instalada, igual ao fundo
  if (corBarra) corBarra.content = tema === 'dark' ? '#10161c' : '#e4e8ec';
  try { localStorage.setItem('pf-tema', tema); } catch (e) { /* sem localStorage */ }
}
document.getElementById('tema').addEventListener('click', () => {
  aplicarTema(raiz.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
});

// ─── Barra de cima: linha por baixo depois de rolar ────────────────────────
const topo = document.getElementById('topo');
const aoRolar = () => topo.classList.toggle('rolado', window.scrollY > 8);
window.addEventListener('scroll', aoRolar, { passive: true });
aoRolar();

// ─── Códigos de barras ─────────────────────────────────────────────────────
// Cada projeto tem o seu: as barras saem de um "hash" do texto em data-barras,
// por isso são sempre iguais para o mesmo projeto (decoração, sem significado).
function hash(texto) {
  let h = 2166136261;
  for (let i = 0; i < texto.length; i++) {
    h ^= texto.charCodeAt(i);
    h = Math.imul(h, 16777619);
  }
  return h >>> 0;
}
document.querySelectorAll('[data-barras]').forEach(el => {
  let semente = hash(el.dataset.barras);
  const ns = 'http://www.w3.org/2000/svg';
  const svg = document.createElementNS(ns, 'svg');
  svg.setAttribute('viewBox', '0 0 100 30');
  svg.setAttribute('preserveAspectRatio', 'none');
  let x = 0;
  while (x < 100) {
    // Gerador pseudo-aleatório simples (xorshift) a partir da semente
    semente ^= semente << 13; semente ^= semente >>> 17; semente ^= semente << 5; semente >>>= 0;
    const largura = 0.8 + (semente % 4) * 0.7;      // barra fina a grossa
    const espaco = 0.8 + ((semente >>> 4) % 3) * 0.7;
    if (x + largura > 100) break;
    const barra = document.createElementNS(ns, 'rect');
    barra.setAttribute('x', x.toFixed(2));
    barra.setAttribute('y', '0');
    barra.setAttribute('width', largura.toFixed(2));
    barra.setAttribute('height', '30');
    svg.appendChild(barra);
    x += largura + espaco;
  }
  el.appendChild(svg);
});

// ─── Detalhes de um projeto numa janela ────────────────────────────────────
const janela = document.getElementById('detalhe');
const estadoJanela = janela.querySelector('.detalhe-estado');
document.querySelectorAll('.etq-face').forEach(face => {
  face.addEventListener('click', () => {
    const etiqueta = face.closest('.etiqueta');
    const estado = etiqueta.querySelector('.etq-estado');
    // Barra de estado: mesma cor e texto da etiqueta
    estadoJanela.className = 'detalhe-estado' + (estado.classList.contains('estado-producao') ? ' estado-producao' : '');
    estadoJanela.replaceChildren(...Array.from(estado.cloneNode(true).childNodes));
    janela.querySelector('.detalhe-titulo').textContent = etiqueta.querySelector('.etq-titulo').textContent;
    janela.querySelector('.detalhe-origem').textContent = etiqueta.querySelector('.etq-origem').textContent;
    janela.querySelector('.detalhe-tags').replaceChildren(...Array.from(etiqueta.querySelector('.tags').cloneNode(true).childNodes));
    janela.querySelector('.detalhe-info').replaceChildren(etiqueta.querySelector('.etq-info').content.cloneNode(true));
    janela.showModal();
    janela.scrollTop = 0;
  });
});
janela.querySelector('.detalhe-fechar').addEventListener('click', () => janela.close());
// Clicar fora da janela (no fundo escurecido) também fecha
janela.addEventListener('click', e => { if (e.target === janela) janela.close(); });

// ─── Instalar como app ─────────────────────────────────────────────────────
// O Chrome avisa (beforeinstallprompt) quando a app pode ser instalada; só então aparece o botão
const botaoInstalar = document.getElementById('instalar');
let pedidoInstalar = null;
window.addEventListener('beforeinstallprompt', e => {
  e.preventDefault();                 // trava a mini-barra automática; usamos o nosso botão
  pedidoInstalar = e;
  botaoInstalar.hidden = false;
});
botaoInstalar.addEventListener('click', async () => {
  if (!pedidoInstalar) return;
  pedidoInstalar.prompt();
  await pedidoInstalar.userChoice;    // o aviso só pode ser usado uma vez
  pedidoInstalar = null;
  botaoInstalar.hidden = true;
});
window.addEventListener('appinstalled', () => { botaoInstalar.hidden = true; });

// ─── Partilhar (menu nativo do telemóvel) ──────────────────────────────────
const botaoPartilhar = document.getElementById('partilhar');
if (navigator.share) {
  botaoPartilhar.hidden = false;
  botaoPartilhar.addEventListener('click', () => {
    navigator.share({
      title: 'Gustavo Biazini — Portfólio',
      text: 'Projetos do Gustavo: web em PHP, apps Android e C#.',
      url: 'https://gustavobiazini97.github.io/Portfolio/site/'
    }).catch(() => {});               // cancelar a partilha não é erro
  });
}

// ─── Service worker (abrir offline e instalar) ─────────────────────────────
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('sw.js').catch(() => {});
  });
}
