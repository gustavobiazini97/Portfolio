// Aplica o tema guardado (claro/escuro) no <head>, antes de a página ser desenhada.
// Sem escolha guardada, segue o tema do sistema.
(function () {
  var tema = null;
  try { tema = localStorage.getItem('pf-tema'); } catch (e) { /* sem localStorage */ }
  if (tema !== 'light' && tema !== 'dark') {
    tema = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  document.documentElement.setAttribute('data-theme', tema);
})();
