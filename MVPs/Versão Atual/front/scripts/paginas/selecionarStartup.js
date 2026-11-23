import {
    usuarioEstaAutenticado,
    salvarStartupAtiva,
    obterUsuarioLogado,
    obterPerfilUsuario,
    removerToken,
    usuarioEhTitular,
    usuarioTemPermissao
} from '../servicos/servicoAutenticacao.js';
import { listarStartupsComCota } from '../servicos/servicoStartups.js';
import {
    abrirModalMinhaAssinatura,
    exibirModalLimiteStartups
} from '../componentes/seletorGlobalStartup.js';

const campoBusca = document.getElementById('campo-busca-startup');
const botaoNovaStartup = document.getElementById('botao-nova-startup');
const botaoMinhaAssinatura = document.getElementById('botao-minha-assinatura');
const containerLista = document.getElementById('lista-startups');
const divMensagemErro = document.getElementById('mensagem-erro');
const divMensagemSucesso = document.getElementById('mensagem-sucesso');
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

function aplicarPermissoesSelecionarStartup(usuario) {
    const ehOwner = usuarioEhTitular(usuario);
    const podeCriarStartup = usuarioTemPermissao('startups.criar_editar', usuario);

    if (botaoMinhaAssinatura) {
        botaoMinhaAssinatura.style.display = ehOwner ? '' : 'none';
    }
    if (botaoNovaStartup) {
        botaoNovaStartup.style.display = podeCriarStartup ? '' : 'none';
    }
}

aplicarPermissoesSelecionarStartup(usuarioLogado);

obterPerfilUsuario().then((usuarioAtualizado) => {
    if (usuarioAtualizado) {
        if (nomeConsultorTopbar && badgeConsultor) {
            nomeConsultorTopbar.textContent = usuarioAtualizado.name || usuarioAtualizado.email;
            badgeConsultor.hidden = false;
        }
        aplicarPermissoesSelecionarStartup(usuarioAtualizado);
    }
});

if (botaoMinhaAssinatura) {
    botaoMinhaAssinatura.addEventListener('click', () => {
        abrirModalMinhaAssinatura();
    });
}

if (botaoSairSessao) {
    botaoSairSessao.addEventListener('click', () => {
        removerToken();
        window.location.href = 'login.html';
    });
}

let cotaAtual = {
    startups_atual: 0,
    startups_limite: null,
    pode_criar_startup: true,
    plano_nome: 'Standard'
};

function verificarFeedbackPosExclusao() {
    const params = new URLSearchParams(window.location.search);
    const msgFlash = sessionStorage.getItem('startup_excluida_feedback');
    const veioDeExclusao = params.get('excluida') === '1' || Boolean(msgFlash);

    if (veioDeExclusao && divMensagemSucesso) {
        divMensagemSucesso.textContent = msgFlash || 'Startup excluída com sucesso. Uma vaga foi liberada no seu plano.';
        divMensagemSucesso.hidden = false;
        sessionStorage.removeItem('startup_excluida_feedback');

        if (params.has('excluida')) {
            params.delete('excluida');
            const novaQuery = params.toString();
            const novaUrl = `${window.location.pathname}${novaQuery ? '?' + novaQuery : ''}`;
            window.history.replaceState({}, document.title, novaUrl);
        }
    }
}

function atualizarContadorECota(quantidadeLista, cota = null) {
    if (cota && typeof cota === 'object') {
        cotaAtual = {
            startups_atual: Number(cota.startups_atual ?? quantidadeLista),
            startups_limite: cota.startups_limite !== undefined && cota.startups_limite !== null
                ? Number(cota.startups_limite)
                : null,
            pode_criar_startup: cota.pode_criar_startup !== undefined
                ? Boolean(cota.pode_criar_startup)
                : true,
            plano_nome: cota.plano_nome || 'Standard'
        };
    } else {
        cotaAtual.startups_atual = quantidadeLista;
    }

    if (contadorStartups) {
        const atual = cotaAtual.startups_atual;
        const limite = cotaAtual.startups_limite;

        if (limite !== null && limite > 0) {
            contadorStartups.textContent = `${atual} / ${limite} EMPRESAS`;
            const atingiu = atual >= limite;
            contadorStartups.className = `contador-cota-badge ${atingiu ? 'limite-atingido' : ''}`;
        } else {
            contadorStartups.textContent = atual === 1 ? '1 EMPRESA' : `${atual} EMPRESAS`;
            contadorStartups.className = 'contador-cota-badge';
        }
    }

    if (botaoNovaStartup) {
        botaoNovaStartup.classList.toggle('limite-atingido', cotaAtual.pode_criar_startup === false);
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

function renderizarLista(startups, termo = '', cota = null) {
    containerLista.innerHTML = '';
    const lista = Array.isArray(startups) ? startups : [];
    const cotaResolvida = cota || lista[0]?.cota || null;
    atualizarContadorECota(lista.length, cotaResolvida);

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

        const cardStartup = document.createElement('div');
        cardStartup.className = 'botao-item-startup';
        cardStartup.setAttribute('role', 'button');
        cardStartup.setAttribute('tabindex', '0');

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

        const direita = document.createElement('div');
        direita.className = 'startup-item-direita';

        const hoverHint = document.createElement('span');
        hoverHint.className = 'startup-hover-hint';
        hoverHint.textContent = 'Selecionar Empresa';

        const btnConfig = document.createElement('button');
        btnConfig.type = 'button';
        btnConfig.className = 'btn-config-startup-card';
        btnConfig.title = 'Configurações da Startup';
        btnConfig.setAttribute('aria-label', `Configurações da Startup ${nomeExibicao}`);
        btnConfig.innerHTML = `
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="3"></circle>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
            </svg>
        `;

        btnConfig.addEventListener('click', (evento) => {
            evento.stopPropagation();
            salvarStartupAtiva(startup.id, nomeExibicao);
            window.location.href = `minhas-startups.html?id=${encodeURIComponent(startup.id)}`;
        });

        direita.appendChild(hoverHint);
        direita.appendChild(btnConfig);

        cardStartup.appendChild(esquerda);
        cardStartup.appendChild(direita);

        const selecionarCard = () => {
            salvarStartupAtiva(startup.id, nomeExibicao);
            window.location.href = 'busca-editais.html';
        };

        cardStartup.addEventListener('click', selecionarCard);
        cardStartup.addEventListener('keydown', (evento) => {
            if (evento.key === 'Enter' || evento.key === ' ') {
                if (document.activeElement === btnConfig) return;
                evento.preventDefault();
                selecionarCard();
            }
        });

        containerLista.appendChild(cardStartup);
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
        const resposta = await listarStartupsComCota(termo);
        if (idRequisicao !== requisicaoAtualId) return;

        const startups = Array.isArray(resposta)
            ? resposta
            : (resposta?.data || resposta?.startups || []);
        const cota = resposta?.cota || startups[0]?.cota || null;

        renderizarLista(startups, termo, cota);
    } catch (erro) {
        if (idRequisicao !== requisicaoAtualId) return;
        if (containerLista) containerLista.innerHTML = '';
        atualizarContadorECota(0);
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
    if (cotaAtual && cotaAtual.pode_criar_startup === false) {
        exibirModalLimiteStartups(cotaAtual);
        return;
    }
    window.location.href = 'cadastrar-startup.html';
});

verificarFeedbackPosExclusao();
carregarStartups();
