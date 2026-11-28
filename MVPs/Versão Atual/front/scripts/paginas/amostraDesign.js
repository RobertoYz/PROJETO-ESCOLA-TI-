import { inicializarTema } from '../componentes/tema.js';

inicializarTema();

// Mostrar/ocultar senha
document.querySelectorAll('[data-acao="mostrar-senha"]').forEach((botao) => {
    const campo = document.getElementById(botao.getAttribute('aria-controls'));
    botao.addEventListener('click', () => {
        const mostrar = campo.type === 'password';
        campo.type = mostrar ? 'text' : 'password';
        botao.setAttribute('aria-label', mostrar ? 'Ocultar senha' : 'Mostrar senha');
        botao.querySelector('.ph').className = `ph ${mostrar ? 'ph-eye-slash' : 'ph-eye'}`;
    });
});

// Checklist de senha
const senha = document.getElementById('d-senha');
const regras = {
    tamanho: (v) => v.length >= 8,
    maiuscula: (v) => /[A-Z]/.test(v),
    numero: (v) => /\d/.test(v),
};
senha?.addEventListener('input', () => {
    document.querySelectorAll('#d-senha-regras [data-regra]').forEach((item) => {
        item.classList.toggle('ok', regras[item.dataset.regra](senha.value));
    });
});

// Chips alternáveis
document.querySelectorAll('.chip[aria-pressed]').forEach((chip) => {
    chip.addEventListener('click', () => {
        chip.setAttribute('aria-pressed', String(chip.getAttribute('aria-pressed') !== 'true'));
    });
});

// Botão com carregamento
const btnCarregar = document.getElementById('btn-demo-carregar');
btnCarregar?.addEventListener('click', () => {
    btnCarregar.setAttribute('aria-busy', 'true');
    setTimeout(() => btnCarregar.removeAttribute('aria-busy'), 1500);
});

// Menu suspenso
const btnMenu = document.getElementById('btn-menu');
const menu = document.getElementById('menu-demo');
function fecharMenu() {
    menu.hidden = true;
    btnMenu.setAttribute('aria-expanded', 'false');
}
btnMenu?.addEventListener('click', (e) => {
    e.stopPropagation();
    const abrir = menu.hidden;
    menu.hidden = !abrir;
    btnMenu.setAttribute('aria-expanded', String(abrir));
    if (abrir) menu.querySelector('.menu-item')?.focus();
});
document.addEventListener('click', (e) => { if (!menu.contains(e.target)) fecharMenu(); });
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !menu.hidden) { fecharMenu(); btnMenu.focus(); }
});
menu?.addEventListener('keydown', (e) => {
    const itens = Array.from(menu.querySelectorAll('.menu-item'));
    const idx = itens.indexOf(document.activeElement);
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        itens[(idx + 1) % itens.length]?.focus();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        itens[(idx - 1 + itens.length) % itens.length]?.focus();
    }
});

// Modal de confirmação
const modal = document.getElementById('modal-demo');
document.getElementById('btn-abrir-modal')?.addEventListener('click', () => modal.showModal());
modal.querySelectorAll('[data-fechar]').forEach((b) => b.addEventListener('click', () => modal.close()));
modal.addEventListener('click', (e) => { if (e.target === modal) modal.close(); });

// Toast
document.getElementById('btn-toast')?.addEventListener('click', () => {
    const toast = document.createElement('div');
    toast.className = 'alerta alerta-sucesso toast';
    toast.setAttribute('role', 'status');
    const icone = document.createElement('i');
    icone.className = 'ph-fill ph-check-circle';
    icone.setAttribute('aria-hidden', 'true');
    const texto = document.createElement('p');
    texto.textContent = 'Alterações salvas.';
    toast.append(icone, texto);
    document.getElementById('toasts').append(toast);
    setTimeout(() => toast.remove(), 3500);
});

// Formulário: impede envio real
document.getElementById('form-demo')?.addEventListener('submit', (e) => e.preventDefault());
