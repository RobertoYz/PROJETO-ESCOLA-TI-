import { usuarioEstaAutenticado, removerToken } from '../servicos/servicoAutenticacao.js';

document.addEventListener('DOMContentLoaded', () => {
    // Validação da sessão/token na tela inicial
    if (!usuarioEstaAutenticado()) {
        window.location.href = 'login.html';
        return;
    }

    const botaoEditais = document.getElementById('botao-editais');
    const botaoKanban = document.getElementById('botao-kanban');
    const botaoSair = document.getElementById('botao-sair');

    if (botaoEditais) {
        botaoEditais.addEventListener('click', () => {
            window.location.href = 'RadarEditais.html';
        });
    }

    if (botaoKanban) {
        botaoKanban.addEventListener('click', () => {
            window.location.href = 'kanban.html';
        });
    }

    if (botaoSair) {
        botaoSair.addEventListener('click', () => {
            removerToken();
            window.location.href = 'login.html';
        });
    }
});
