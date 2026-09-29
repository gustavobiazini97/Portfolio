const root = document.documentElement;
const toggle = document.getElementById('toggle');
const ico = document.getElementById('toggle-ico');
const KEY = 'pf-hybrid-theme';
function setTheme(t){ root.setAttribute('data-theme', t); ico.textContent = t === 'dark' ? '☀' : '☾'; try{ localStorage.setItem(KEY, t); }catch(e){} }
try { setTheme(localStorage.getItem(KEY) || 'light'); } catch(e) { setTheme('light'); }
toggle.addEventListener('click', () => setTheme(root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'));

// Scrolled state
const bar = document.getElementById('topbar');
const onScroll = () => bar.classList.toggle('scrolled', window.scrollY > 12);
window.addEventListener('scroll', onScroll); onScroll();

// Reveal on scroll, with safe fallback
const io = new IntersectionObserver((entries) => {
  entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
}, { threshold: 0.12 });
const reveals = document.querySelectorAll('.reveal');
reveals.forEach((el, i) => { el.style.transitionDelay = (i % 5 * 45) + 'ms'; io.observe(el); });
setTimeout(() => reveals.forEach(el => { if (!el.classList.contains('in')) { el.style.transition = 'none'; el.classList.add('in'); } }), 900);
