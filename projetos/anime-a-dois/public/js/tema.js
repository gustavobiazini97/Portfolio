// Aplica o tema guardado ANTES de a página aparecer (evita o "piscar" de claro para escuro).
// Carregado no <head> sem defer, por isso tem de ser mínimo.
(function () {
  var tema = 'claro';
  try { tema = localStorage.getItem('tema') || 'claro'; } catch (e) { /* modo privado: fica claro */ }
  document.documentElement.setAttribute('data-tema', tema);
})();
