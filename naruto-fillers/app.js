const SHOWS = ['naruto', 'naruto-shippuden', 'boruto-naruto-next-generations'];
const EP_MINUTES = 23;

const $ = (sel) => document.querySelector(sel);
const cache = {};
let current = null;
let localData = null;

async function loadShow(show) {
  if (cache[show]) return cache[show];
  // 1) PHP (ao vivo, com cache no servidor)  2) cópia local em JSON
  try {
    const res = await fetch(`fillers.php?show=${show}`);
    if (res.ok && res.headers.get('content-type')?.includes('json')) {
      return (cache[show] = await res.json());
    }
  } catch { /* sem PHP (ex.: GitHub Pages ou ficheiro local) */ }
  localData ??= await fetch('data/fillers.json').then((r) => r.json());
  return (cache[show] = { ...localData.shows[show], source: 'offline' });
}

// Agrupa episódios consecutivos: [26], [97], [101..106], ...
function groupRuns(episodes) {
  const runs = [];
  for (const ep of episodes) {
    const last = runs.at(-1);
    if (last && ep.n === last.at(-1).n + 1) last.push(ep);
    else runs.push([ep]);
  }
  return runs;
}

const label = (run) => run.length === 1 ? `${run[0].n}` : `${run[0].n}–${run.at(-1).n}`;
const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

function renderStats(data) {
  const count = data.episodes.length;
  const pct = Math.round((count / data.total) * 100);
  const hours = Math.round((count * EP_MINUTES) / 60);
  $('#stats').innerHTML = `
    <div class="stat"><b>${count}</b><small>episódios filler</small></div>
    <div class="stat"><b>${pct}%</b><small>de ${data.total} episódios</small><div class="bar"><i style="width:${pct}%"></i></div></div>
    <div class="stat"><b>≈ ${hours} h</b><small>que podes poupar</small></div>`;
}

function render() {
  const data = current;
  const q = $('#search').value.trim().toLowerCase();
  const episodes = data.episodes.filter((ep) =>
    !q || String(ep.n) === q || String(ep.n).startsWith(q) || (ep.title || '').toLowerCase().includes(q));
  const runs = groupRuns(episodes);

  $('#ranges').innerHTML = q ? '' : runs.map((r) => `<a href="#g-${r[0].n}">${label(r)}</a>`).join('');

  $('#list').innerHTML = runs.length ? runs.map((run) => `
    <section class="group" id="g-${run[0].n}">
      <h2>Ep. ${label(run)} <small>${run.length} ${run.length === 1 ? 'episódio' : 'episódios'}</small></h2>
      <div class="grid">${run.map((ep) => `
        <article class="ep">
          <span class="n">${ep.n}</span>
          <span class="info">
            <span class="t">${esc(ep.title || 'Episódio filler')}</span>
            ${ep.date ? `<span class="d">${esc(ep.date)}</span>` : ''}
          </span>
        </article>`).join('')}
      </div>
    </section>`).join('')
    : '<p class="empty">Nenhum filler encontrado.</p>';
}

async function select(show) {
  if (!SHOWS.includes(show)) show = SHOWS[0];
  document.querySelectorAll('.tabs a').forEach((a) => a.classList.toggle('active', a.dataset.show === show));
  $('#list').innerHTML = '<p class="loading">A carregar…</p>';
  try {
    current = await loadShow(show);
  } catch {
    $('#list').innerHTML = '<p class="empty">Não foi possível carregar a lista.</p>';
    return;
  }
  document.title = `${current.name} · Fillers`;
  $('#source').innerHTML = current.source === 'live'
    ? '<span class="badge live" title="Lista lida agora de animefillerlist.com">ao vivo</span>'
    : '<span class="badge" title="Sem ligação ao animefillerlist.com: a mostrar a lista guardada na página">lista guardada</span>';
  renderStats(current);
  render();
}

// Âncoras de grupo (#g-123) não mudam de saga.
function route() {
  const hash = location.hash.slice(1);
  if (!hash.startsWith('g-') || !current) select(hash);
}

$('#search').addEventListener('input', render);
document.querySelectorAll('.toggle button').forEach((btn) => btn.addEventListener('click', () => {
  document.querySelectorAll('.toggle button').forEach((b) => b.setAttribute('aria-pressed', b === btn));
  $('#list').classList.toggle('compact', btn.dataset.view === 'compact');
  try { localStorage.setItem('view', btn.dataset.view); } catch {}
}));
try {
  if (localStorage.getItem('view') === 'compact') document.querySelector('[data-view="compact"]').click();
} catch {}

window.addEventListener('hashchange', route);
route();
