// Ponte do site para o bicho (Mascote): arranca o motor no contentor [data-mascote] e, quando a pessoa
// escolhe a espécie, o nome ou a cor, guarda no servidor (POST com o token CSRF) e volta a desenhar.
// O servidor é que decide o que é válido; aqui só se mostra a mensagem dele quando recusa.
(function (g) {
  'use strict';
  var M = g.Mascote;
  var caixa = document.querySelector('[data-mascote]');
  if (!M || !caixa) return;

  var aviso = caixa.parentNode.querySelector('.mascote-aviso');

  // Mensagem curta por baixo do bicho (textContent: nunca HTML)
  function mostrarAviso(texto) {
    if (!aviso) return;
    aviso.textContent = texto || '';
    aviso.hidden = !texto;
  }

  // Campos que o servidor aceita para cada tipo de mensagem do bicho
  var CAMPO = { escolher: 'especie', nome: 'nome', cor: 'cor' };

  // O bicho avisa-nos com { tipo, ... }; toques não precisam de ir ao servidor
  M.ponte.enviar = function (m) {
    if (!m || !CAMPO[m.tipo]) return;
    var campo = CAMPO[m.tipo];
    var valor = m.tipo === 'escolher' ? m.especie : m[campo];
    if (valor === undefined || valor === null) return;

    var dados = new FormData();
    dados.append('_csrf', caixa.dataset.csrf || '');
    dados.append(campo, String(valor));

    fetch(caixa.dataset.url, { method: 'POST', body: dados, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { ok: false, mensagem: 'Não deu para guardar. Tenta outra vez.' }; }); })
      .then(function (r) {
        if (r && r.ok && r.mascote) {
          mostrarAviso('');
          M.definirEstado(r.mascote);
        } else {
          mostrarAviso((r && r.mensagem) || 'Não deu para guardar. Tenta outra vez.');
        }
      })
      .catch(function () { mostrarAviso('Sem ligação. Tenta outra vez.'); });
  };

  // Estado inicial (JSON no atributo) e arranque
  var estado = null;
  try { estado = JSON.parse(caixa.dataset.estado || 'null'); } catch (e) { estado = null; }
  M.iniciar(caixa, { modo: caixa.dataset.modo === 'cartao' ? 'cartao' : 'ecra' });
  M.definirEstado(estado);
})(window);
