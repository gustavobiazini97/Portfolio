// Comportamentos da interface. Tudo é "melhoria": sem JavaScript a app funciona na mesma.
// Nada de innerHTML com dados do servidor: o texto entra sempre por textContent (contra XSS).
'use strict';

document.addEventListener('DOMContentLoaded', function () {
    temaAlternar();
    sinoAtualizar();
    sugestoesFaq();
    prioridadePrevia();
    autoSubmeter();
    confirmarAntes();
    anexosEscolhidos();
    notaInterna();
    copiarSegredo();
    fecharMenuConta();
});

// ─── Tema claro/escuro ─────────────────────────────────────────────────────
function temaAlternar() {
    document.querySelectorAll('[data-tema-alternar]').forEach(function (botao) {
        botao.addEventListener('click', function () {
            var novo = document.documentElement.getAttribute('data-tema') === 'escuro' ? 'claro' : 'escuro';
            document.documentElement.setAttribute('data-tema', novo);
            try { localStorage.setItem('helpdesk-tema', novo); } catch (e) { /* sem localStorage: só nesta página */ }
        });
    });
}

// ─── Sino: número de notificações por ler, atualizado a cada minuto ────────
function sinoAtualizar() {
    var badge = document.querySelector('[data-sino-badge]');
    if (!badge) return;
    var parar = false;

    function contar() {
        // Separador escondido: não gasta pedidos
        if (parar || document.visibilityState !== 'visible') return;
        fetch('index.php?c=notificacao&a=contar', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) {
                if (r.status === 401) { parar = true; return null; }   // sessão expirou
                return r.ok ? r.json() : null;
            })
            .then(function (dados) {
                if (!dados || typeof dados.naoLidas !== 'number') return;
                badge.textContent = dados.naoLidas > 99 ? '99+' : String(dados.naoLidas);
                badge.hidden = dados.naoLidas === 0;
            })
            .catch(function () { /* sem rede: tenta no próximo minuto */ });
    }
    setInterval(contar, 60000);
    document.addEventListener('visibilitychange', contar);
}

// ─── Novo pedido: artigos da FAQ enquanto se escreve o título ──────────────
function sugestoesFaq() {
    var campo = document.querySelector('[data-sugerir-faq]');
    var caixa = document.querySelector('[data-sugestoes]');
    var lista = document.querySelector('[data-sugestoes-lista]');
    if (!campo || !caixa || !lista) return;

    var temporizador = null;
    var ultimo = '';
    var controlador = null;

    campo.addEventListener('input', function () {
        clearTimeout(temporizador);
        // Espera que a pessoa pare de escrever (350 ms) antes de perguntar ao servidor
        temporizador = setTimeout(procurar, 350);
    });

    function procurar() {
        var q = campo.value.trim();
        if (q.length < 3) { caixa.hidden = true; return; }
        if (q === ultimo) return;
        ultimo = q;
        if (controlador) controlador.abort();   // cancela o pedido anterior, se ainda estiver a meio
        controlador = new AbortController();

        fetch('index.php?c=faq&a=sugerir&q=' + encodeURIComponent(q), {
            credentials: 'same-origin', signal: controlador.signal, headers: { 'Accept': 'application/json' }
        })
            .then(function (r) { return r.ok ? r.json() : { artigos: [] }; })
            .then(function (dados) { mostrar(dados.artigos || []); })
            .catch(function () { /* cancelado ou sem rede */ });
    }

    function mostrar(artigos) {
        lista.replaceChildren();
        artigos.forEach(function (a) {
            var li = document.createElement('li');
            var link = document.createElement('a');
            link.href = a.url;
            link.target = '_blank';            // novo separador: o formulário não se perde
            link.rel = 'noopener noreferrer';
            var titulo = document.createElement('b');
            titulo.textContent = a.titulo;
            var resumo = document.createElement('span');
            resumo.textContent = a.resumo;
            link.append(titulo, resumo);
            li.append(link);
            lista.append(li);
        });
        caixa.hidden = artigos.length === 0;
    }
}

// ─── Novo pedido: prioridade estimada (o servidor calcula a sério) ─────────
function prioridadePrevia() {
    var saida = document.querySelector('[data-prioridade-previa]');
    if (!saida) return;
    var nomes = { 1: 'Crítica', 2: 'Alta', 3: 'Média', 4: 'Baixa' };
    var form = saida.closest('form');

    function atualizar() {
        var i = form.querySelector('input[name="impacto"]:checked');
        var u = form.querySelector('input[name="urgencia"]:checked');
        if (!i || !u) { saida.textContent = ''; return; }
        // Mesma matriz do servidor (Sla::prioridade): soma 6 → P1, 5 → P2, 4 → P3, resto → P4
        var soma = Number(i.value) + Number(u.value);
        var p = soma >= 6 ? 1 : soma === 5 ? 2 : soma === 4 ? 3 : 4;
        saida.textContent = 'Prioridade estimada: P' + p + ' ' + nomes[p];
        saida.className = 'prioridade-previa p' + p;
    }
    form.addEventListener('change', atualizar);
    atualizar();
}

// ─── Filtros: mudar um select aplica logo ──────────────────────────────────
function autoSubmeter() {
    document.querySelectorAll('form[data-auto-submeter] select').forEach(function (s) {
        s.addEventListener('change', function () { s.form.submit(); });
    });
}

// ─── Confirmação antes de ações destrutivas ────────────────────────────────
function confirmarAntes() {
    document.querySelectorAll('form[data-confirmar]').forEach(function (f) {
        f.addEventListener('submit', function (ev) {
            if (!window.confirm(f.getAttribute('data-confirmar'))) ev.preventDefault();
        });
    });
}

// ─── Anexos: limite de ficheiros e nomes escolhidos ────────────────────────
function anexosEscolhidos() {
    document.querySelectorAll('input[type="file"][data-max]').forEach(function (input) {
        var max = Number(input.getAttribute('data-max')) || 3;
        var rotulo = input.closest('label') ? input.closest('label').querySelector('[data-anexos-rotulo]') : null;
        input.addEventListener('change', function () {
            if (input.files.length > max) {
                window.alert('Podes anexar no máximo ' + max + ' ficheiros de cada vez.');
                input.value = '';
            }
            if (rotulo) {
                rotulo.textContent = input.files.length
                    ? Array.prototype.map.call(input.files, function (f) { return f.name; }).join(', ')
                    : 'Anexar ficheiros';
            }
        });
    });
}

// ─── Resposta do TI: realça o formulário quando é nota interna ─────────────
function notaInterna() {
    var caixa = document.querySelector('[data-nota-interna]');
    if (!caixa) return;
    var form = caixa.closest('form');
    caixa.addEventListener('change', function () {
        form.classList.toggle('interna', caixa.checked);
    });
}

// ─── 2FA: tocar no segredo copia-o ─────────────────────────────────────────
function copiarSegredo() {
    document.querySelectorAll('[data-copiar]').forEach(function (el) {
        el.title = 'Tocar para copiar';
        el.addEventListener('click', function () {
            if (!navigator.clipboard) return;
            navigator.clipboard.writeText(el.textContent.replace(/\s+/g, '')).then(function () {
                el.classList.add('copiado');
                setTimeout(function () { el.classList.remove('copiado'); }, 1500);
            });
        });
    });
}

// ─── Menu da conta: fecha ao clicar fora ───────────────────────────────────
function fecharMenuConta() {
    var menu = document.querySelector('details.conta');
    if (!menu) return;
    document.addEventListener('click', function (ev) {
        if (menu.open && !menu.contains(ev.target)) menu.open = false;
    });
}
