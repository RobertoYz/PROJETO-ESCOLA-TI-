const paginasPublicas = ['login.html', 'cadastro.html', 'aceitar-convite.html', 'esqueci-senha.html', 'redefinir-senha.html', 'baixar-app.html'];

function verificarSessao() {
    const pagina = location.pathname.split('/').pop();
    if (!location.pathname.includes('/paginas/') || paginasPublicas.includes(pagina)) return false;
    const portal = location.pathname.endsWith('/portal-startup.html');
    const token = localStorage.getItem('token_autenticacao');
    const sessaoPortal = token && localStorage.getItem('tipo_acesso') === 'portal_startup';
    if (portal && !sessaoPortal) {
        location.replace(token ? 'selecionar-startup.html' : 'login.html');
        return true;
    } else if (!portal && sessaoPortal) {
        location.replace('portal-startup.html');
        return true;
    }
    return false;
}

window.addEventListener('pageshow', verificarSessao);
window.addEventListener('storage', verificarSessao);
if (verificarSessao()) {
    // Aguarda a navegação antes de inicializar os módulos da página protegida.
    await new Promise(resolve => window.addEventListener('pagehide', resolve, { once: true }));
}
