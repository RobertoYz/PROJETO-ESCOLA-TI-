import { usuarioEstaAutenticado, salvarStartupAtiva } from '../servicos/servicoAutenticacao.js';
import { listarStartupsDaConsultoria } from '../servicos/servicoStartups.js';

const campoBusca = document.getElementById('campo-busca-startup');
const botaoNovaStartup = document.getElementById('botao-nova-startup');
const containerLista = document.getElementById('lista-startups');
const divMensagemErro = document.getElementById('mensagem-erro');

if (!usuarioEstaAutenticado()) {
    window.location.href = 'login.html';
}

function renderizarLista(startups) {
    containerLista.innerHTML = '';

    if (!startups || startups.length === 0) {
        const textoVazio = document.createElement('p');
        textoVazio.className = 'texto-sem-startup';
        textoVazio.textContent = 'Nenhuma startup cadastrada';
        containerLista.appendChild(textoVazio);
        return;
    }

    startups.forEach((startup) => {
        const nomeExibicao = startup.nome_fantasia || startup.razao_social;
        const botaoStartup = document.createElement('button');
        botaoStartup.type = 'button';
        botaoStartup.className = 'botao-item-startup';
        botaoStartup.textContent = nomeExibicao;

        botaoStartup.addEventListener('click', () => {
            salvarStartupAtiva(startup.id, nomeExibicao);
            window.location.href = 'inicio.html';
        });

        containerLista.appendChild(botaoStartup);
    });
}

async function carregarStartups(termo = '') {
    divMensagemErro.hidden = true;
    try {
        const startups = await listarStartupsDaConsultoria(termo);
        renderizarLista(startups);
    } catch (erro) {
        divMensagemErro.textContent = erro.message;
        divMensagemErro.hidden = false;
    }
}

campoBusca.addEventListener('input', (evento) => {
    carregarStartups(evento.target.value.trim());
});

botaoNovaStartup.addEventListener('click', () => {
    window.location.href = 'cadastrar-startup.html';
});

carregarStartups();
