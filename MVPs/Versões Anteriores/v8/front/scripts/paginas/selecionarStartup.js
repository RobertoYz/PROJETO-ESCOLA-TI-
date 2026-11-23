import {
    usuarioEstaAutenticado,
    salvarStartupAtiva,
    obterUsuarioLogado,
    removerToken
} from '../servicos/servicoAutenticacao.js';
import { listarStartupsDaConsultoria } from '../servicos/servicoStartups.js';

const campoBusca = document.getElementById('campo-busca-startup');
const botaoNovaStartup = document.getElementById('botao-nova-startup');
const containerLista = document.getElementById('lista-startups');
const divMensagemErro = document.getElementById('mensagem-erro');
const contadorStartups = document.getElementById('contador-startups');
const badgeConsultor = document.getElementById('consultor-identificacao');
const nomeConsultorTopbar = document.getElementById('nome-consultor-topbar');
const botaoSairSessao = document.getElementById('botao-sair-sessao');

if (!usuarioEstaAutenticado()) {
    window.location.href = 'login.html';
}

const usuarioLogado = obterUsuarioLogado();
if (usuarioLogado && (usuarioLogado.name || usuarioLogado.email) && badgeConsultor && nomeConsultorTopbar) {
    nomeConsultorTopbar.textContent = usuarioLogado.name || usuarioLogado.email;
    badgeConsultor.hidden = false;
}

if (botaoSairSessao) {
    botaoSairSessao.addEventListener('click', () => {
        removerToken();
        window.location.href = 'login.html';
    });
}

function atualizarContador(quantidade) {
    if (!contadorStartups) return;
    if (quantidade === 0) {
        contadorStartups.textContent = '0 empresas';
    } else if (quantidade === 1) {
        contadorStartups.textContent = '1 empresa';
    } else {
        contadorStartups.textContent = `${quantidade} empresas`;
    }
}

function obterIniciais(nome) {
    const partes = (nome || 'AN').trim().split(/\s+/).filter(Boolean);
    if (partes.length === 1) {
        return partes[0].slice(0, 2).toUpperCase();
    }
    return (partes[0][0] + partes[1][0]).toUpperCase();
}

function renderizarCarregando() {
    containerLista.innerHTML = '';
    const loading = document.createElement('div');
    loading.className = 'estado-carregando';
    loading.textContent = 'Carregando carteira de startups...';
    containerLista.appendChild(loading);
}

function renderizarLista(startups, termo = '') {
    containerLista.innerHTML = '';
    const lista = Array.isArray(startups) ? startups : [];
    atualizarContador(lista.length);

    if (lista.length === 0) {
        const caixaVazia = document.createElement('div');
        caixaVazia.className = 'estado-lista-vazia';

        const tituloVazio = document.createElement('div');
        tituloVazio.className = 'estado-lista-vazia-titulo';
        tituloVazio.textContent = termo
            ? 'Nenhuma empresa encontrada para este filtro'
            : 'Nenhuma startup cadastrada';

        const textoVazio = document.createElement('p');
        textoVazio.className = 'texto-sem-startup';
        textoVazio.textContent = termo
            ? 'Verifique o nome fantasia, razão social ou CNPJ informado e tente novamente.'
            : 'Cadastre a primeira startup da sua consultoria para iniciar a busca por editais e submissões.';

        caixaVazia.appendChild(tituloVazio);
        caixaVazia.appendChild(textoVazio);
        containerLista.appendChild(caixaVazia);
        return;
    }

    lista.forEach((startup) => {
        const nomeExibicao = startup.nome_fantasia || startup.razao_social || 'Startup';

        const botaoStartup = document.createElement('button');
        botaoStartup.type = 'button';
        botaoStartup.className = 'botao-item-startup';

        const esquerda = document.createElement('div');
        esquerda.className = 'startup-item-esquerda';

        const monograma = document.createElement('div');
        monograma.className = 'startup-monograma';
        monograma.setAttribute('aria-hidden', 'true');
        monograma.textContent = obterIniciais(nomeExibicao);

        const dados = document.createElement('div');
        dados.className = 'startup-dados';

        const nomeEl = document.createElement('div');
        nomeEl.className = 'startup-nome';
        nomeEl.textContent = nomeExibicao;

        const subtitulo = document.createElement('div');
        subtitulo.className = 'startup-subtitulo';

        const metadados = [];
        if (startup.nome_fantasia && startup.razao_social && startup.nome_fantasia !== startup.razao_social) {
            metadados.push(startup.razao_social);
        }
        if (startup.cnpj) {
            metadados.push(`CNPJ: ${formatarCnpjExibicao(startup.cnpj)}`);
        }
        if (startup.nicho_atuacao) {
            metadados.push(startup.nicho_atuacao);
        }
        if (startup.cidade || startup.estado) {
            metadados.push([startup.cidade, startup.estado].filter(Boolean).join('/'));
        }

        if (metadados.length > 0) {
            metadados.forEach((texto, indice) => {
                if (indice > 0) {
                    const separador = document.createElement('span');
                    separador.textContent = '•';
                    separador.setAttribute('aria-hidden', 'true');
                    subtitulo.appendChild(separador);
                }
                const tag = document.createElement('span');
                tag.className = 'startup-tag-meta';
                tag.textContent = texto;
                subtitulo.appendChild(tag);
            });
        } else {
            const tagPadrao = document.createElement('span');
            tagPadrao.className = 'startup-tag-meta';
            tagPadrao.textContent = 'Empresa vinculada à consultoria';
            subtitulo.appendChild(tagPadrao);
        }

        dados.appendChild(nomeEl);
        dados.appendChild(subtitulo);
        esquerda.appendChild(monograma);
        esquerda.appendChild(dados);

        const acao = document.createElement('div');
        acao.className = 'startup-item-acao';
        acao.innerHTML = `
            <span>Acessar painel</span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
            </svg>
        `;

        botaoStartup.appendChild(esquerda);
        botaoStartup.appendChild(acao);

        botaoStartup.addEventListener('click', () => {
            salvarStartupAtiva(startup.id, nomeExibicao);
            window.location.href = 'busca-editais.html';
        });

        containerLista.appendChild(botaoStartup);
    });
}

function formatarCnpjExibicao(cnpj) {
    const digitos = String(cnpj || '').replace(/\D/g, '');
    if (digitos.length !== 14) return cnpj;
    return `${digitos.slice(0, 2)}.${digitos.slice(2, 5)}.${digitos.slice(5, 8)}/${digitos.slice(8, 12)}-${digitos.slice(12, 14)}`;
}

let requisicaoAtualId = 0;
let ultimoTermoBuscado = null;

async function carregarStartups(termo = '') {
    const idRequisicao = ++requisicaoAtualId;
    ultimoTermoBuscado = termo;
    if (divMensagemErro) divMensagemErro.hidden = true;
    renderizarCarregando();
    try {
        const startups = await listarStartupsDaConsultoria(termo);
        if (idRequisicao !== requisicaoAtualId) return;
        renderizarLista(startups, termo);
    } catch (erro) {
        if (idRequisicao !== requisicaoAtualId) return;
        if (containerLista) containerLista.innerHTML = '';
        atualizarContador(0);
        if (divMensagemErro) {
            divMensagemErro.textContent = erro.message || 'Não foi possível carregar as startups da consultoria.';
            divMensagemErro.hidden = false;
        }
    }
}

let timeoutBusca = null;
campoBusca?.addEventListener('input', (evento) => {
    const termo = evento.target.value.trim();
    clearTimeout(timeoutBusca);

    if (termo === ultimoTermoBuscado) return;

    if (termo === '') {
        carregarStartups('');
        return;
    }

    timeoutBusca = setTimeout(() => {
        carregarStartups(termo);
    }, 180);
});

campoBusca?.addEventListener('keydown', (evento) => {
    if (evento.key === 'Escape' && campoBusca.value) {
        clearTimeout(timeoutBusca);
        campoBusca.value = '';
        if (ultimoTermoBuscado !== '') {
            carregarStartups('');
        }
    } else if (evento.key === 'Enter') {
        evento.preventDefault();
        clearTimeout(timeoutBusca);
        const termo = campoBusca.value.trim();
        if (termo !== ultimoTermoBuscado) {
            carregarStartups(termo);
        }
    }
});

botaoNovaStartup?.addEventListener('click', () => {
    window.location.href = 'cadastrar-startup.html';
});

carregarStartups();
