import {
    usuarioEstaAutenticado,
    possuiStartupSelecionada,
    obterStartupAtiva,
    limparStartupAtiva,
    removerToken
} from '../servicos/servicoAutenticacao.js';

if (!usuarioEstaAutenticado()) {
    window.location.href = 'login.html';
} else if (!possuiStartupSelecionada()) {
    window.location.href = 'selecionar-startup.html';
}

const startupAtiva = obterStartupAtiva();
const elementoTextoStartup = document.getElementById('texto-startup-ativa');

if (startupAtiva && elementoTextoStartup) {
    elementoTextoStartup.textContent = `Perfil Ativo: ${startupAtiva.nome}`;
}

document.getElementById('botao-radar-editais').addEventListener('click', () => {
    window.location.href = 'RadarEditais.html';
});

document.getElementById('botao-kanban').addEventListener('click', () => {
    window.location.href = 'kanban.html';
});

document.getElementById('botao-trocar-startup').addEventListener('click', () => {
    limparStartupAtiva();
    window.location.href = 'selecionar-startup.html';
});

document.getElementById('botao-sair').addEventListener('click', () => {
    removerToken();
    window.location.href = 'login.html';
});
