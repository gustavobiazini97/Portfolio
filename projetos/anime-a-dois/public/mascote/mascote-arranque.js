// Arranque da página mascote.html (usada pela app numa WebView): inicia o bicho em ecrã inteiro.
// O estado chega depois, enviado pela app com Mascote.definirEstado(...), e a app liga Mascote.ponte para receber os toques.

(function (g) {
  'use strict';
  const contentor = document.getElementById('mascote');
  if (contentor && g.Mascote) g.Mascote.iniciar(contentor, { modo: g.location.hash === '#cartao' ? 'cartao' : 'ecra' });
})(window);
