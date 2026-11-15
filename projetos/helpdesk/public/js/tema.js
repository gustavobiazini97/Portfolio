// Tema claro/escuro, aplicado no <head> ANTES de a página ser desenhada (sem "piscar").
// Por defeito é claro; a escolha fica guardada neste browser.
(function () {
    var tema = 'claro';
    try {
        tema = localStorage.getItem('helpdesk-tema') || 'claro';
    } catch (e) {
        // Browser sem localStorage (modo privado antigo): fica o claro
    }
    document.documentElement.setAttribute('data-tema', tema === 'escuro' ? 'escuro' : 'claro');
})();
