const ehCapacitorApp =
    window.Capacitor !== undefined ||
    window.location.protocol === 'capacitor:' ||
    (window.location.hostname === 'localhost' && window.location.protocol === 'https:') ||
    (navigator.userAgent && (navigator.userAgent.includes('wv') || navigator.userAgent.includes('Capacitor')));

if (ehCapacitorApp) {
    window.location.replace('paginas/login.html');
}

const btnTema = document.getElementById('btnAlternarTema');
const iconeSol = document.getElementById('iconeSol');
const iconeLua = document.getElementById('iconeLua');

function aplicarTema(escuro) {
    document.body.classList.toggle('dark-mode', escuro);
    if (iconeSol) iconeSol.style.display = escuro ? 'block' : 'none';
    if (iconeLua) iconeLua.style.display = escuro ? 'none' : 'block';
    localStorage.setItem('tema_preferido', escuro ? 'dark' : 'light');
}

const temaSalvo = localStorage.getItem('tema_preferido');
aplicarTema(
    temaSalvo === 'dark' || (!temaSalvo && window.matchMedia('(prefers-color-scheme: dark)').matches)
);

if (btnTema) {
    btnTema.addEventListener('click', () => {
        aplicarTema(!document.body.classList.contains('dark-mode'));
    });
}

const topbar = document.getElementById('topbar');
const btnMenu = document.getElementById('btnMenuMobile');

if (topbar && btnMenu) {
    btnMenu.addEventListener('click', () => {
        const aberto = topbar.classList.toggle('menu-open');
        btnMenu.setAttribute('aria-expanded', String(aberto));
    });

    topbar.querySelectorAll('.nav-list a').forEach((link) => {
        link.addEventListener('click', () => {
            topbar.classList.remove('menu-open');
            btnMenu.setAttribute('aria-expanded', 'false');
        });
    });
}

if (localStorage.getItem('token_autenticacao')) {
    document.querySelectorAll('[data-auth-entrar]').forEach((link) => {
        link.textContent = 'Acessar painel';
        link.setAttribute('href', 'paginas/selecionar-startup.html');
    });

    document.querySelectorAll('[data-auth-cadastro]').forEach((link) => {
        const itemLista = link.closest('.nav-mobile-item');
        if (itemLista) {
            itemLista.style.display = 'none';
        } else {
            link.style.display = 'none';
        }
    });
}

const tabs = Array.from(document.querySelectorAll('[data-app-tab]'));
const panels = Array.from(document.querySelectorAll('[data-app-panel]'));
const sidebarItems = Array.from(document.querySelectorAll('[data-sidebar-item]'));

function ativarTela(tabAlvo) {
    const chave = tabAlvo.getAttribute('data-app-tab');

    tabs.forEach((tab) => {
        const selecionada = tab === tabAlvo;
        tab.setAttribute('aria-selected', String(selecionada));
        tab.setAttribute('tabindex', selecionada ? '0' : '-1');
    });

    panels.forEach((panel) => {
        panel.hidden = panel.getAttribute('data-app-panel') !== chave;
    });

    sidebarItems.forEach((item) => {
        item.classList.toggle('is-active', item.getAttribute('data-sidebar-item') === chave);
    });
}

tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => ativarTela(tab));
    tab.addEventListener('keydown', (evento) => {
        if (evento.key !== 'ArrowRight' && evento.key !== 'ArrowLeft') return;
        evento.preventDefault();
        const proximo = evento.key === 'ArrowRight'
            ? (index + 1) % tabs.length
            : (index - 1 + tabs.length) % tabs.length;
        tabs[proximo].focus();
        ativarTela(tabs[proximo]);
    });
});
