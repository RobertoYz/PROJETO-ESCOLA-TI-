/**
 * Tema claro/escuro compartilhado por todas as páginas.
 * O estado vive em <html data-tema="claro|escuro">; tokens.css reage a ele.
 * Para compatibilidade com código antigo, também espelha body.dark-mode.
 */

const CHAVE = 'tema_preferido';

export function temaAtualEscuro() {
    return document.documentElement.getAttribute('data-tema') === 'escuro';
}

export function aplicarTema(escuro) {
    document.documentElement.setAttribute('data-tema', escuro ? 'escuro' : 'claro');
    document.body?.classList.toggle('dark-mode', escuro);
    try { localStorage.setItem(CHAVE, escuro ? 'dark' : 'light'); } catch { /* ignore */ }
    document.querySelectorAll('[data-acao="alternar-tema"]').forEach(atualizarBotao);
}

function atualizarBotao(botao) {
    const escuro = temaAtualEscuro();
    botao.setAttribute('aria-pressed', String(escuro));
    botao.setAttribute('aria-label', escuro ? 'Usar tema claro' : 'Usar tema escuro');
    botao.title = botao.getAttribute('aria-label');
    const icone = botao.querySelector('.ph');
    if (icone) icone.className = `ph ${escuro ? 'ph-sun' : 'ph-moon'}`;
}

/** Liga todos os botões com data-acao="alternar-tema". */
export function inicializarTema() {
    document.body?.classList.toggle('dark-mode', temaAtualEscuro());
    document.querySelectorAll('[data-acao="alternar-tema"]').forEach((botao) => {
        atualizarBotao(botao);
        botao.addEventListener('click', () => aplicarTema(!temaAtualEscuro()));
    });
}
