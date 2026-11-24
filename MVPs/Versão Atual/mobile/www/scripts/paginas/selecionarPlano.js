import {
    obterTokenPendencia,
    obterEmailPendencia
} from '../servicos/servicoAutenticacao.js';
import { requisicaoApi } from '../servicos/api.js';
import { buscarPlanos } from '../servicos/servicoPlanos.js';

const containerListaPlanos = document.getElementById('lista-planos');
const divMensagemErro = document.getElementById('mensagem-erro');
const linkVoltar = document.getElementById('link-voltar');
const subtituloPagina = document.getElementById('subtitulo-pagina');
const resumoPreCadastro = document.getElementById('resumo-pre-cadastro');

const parametrosUrl = new URLSearchParams(window.location.search);
const modoTroca = parametrosUrl.get('modo') === 'troca';
const modoRenovacao = parametrosUrl.get('modo') === 'renovacao';

const dadosPreCadastroBrutos = sessionStorage.getItem('dados_pre_cadastro');
let dadosPreCadastro = null;
try {
    if (dadosPreCadastroBrutos) {
        dadosPreCadastro = JSON.parse(dadosPreCadastroBrutos);
    }
} catch (excecao) {
    dadosPreCadastro = null;
}

const tokenPendencia = obterTokenPendencia();
const emailPendencia = obterEmailPendencia();

function preencherResumoContexto(itens) {
    if (!resumoPreCadastro || !itens.length) return;

    resumoPreCadastro.innerHTML = '';
    itens.forEach(({ rotulo, valor }) => {
        if (!valor) return;
        const item = document.createElement('span');
        const textoRotulo = document.createTextNode(`${rotulo}: `);
        const forte = document.createElement('strong');
        forte.textContent = valor;
        item.appendChild(textoRotulo);
        item.appendChild(forte);
        resumoPreCadastro.appendChild(item);
    });

    resumoPreCadastro.hidden = resumoPreCadastro.childElementCount === 0;
}

if (modoTroca) {
    linkVoltar.href = 'selecionar-startup.html';
    linkVoltar.textContent = '← Voltar';
    subtituloPagina.textContent = 'Selecione o plano para consultar o valor, a vigência e as cotas antes de confirmar.';
} else if (modoRenovacao && (tokenPendencia || emailPendencia)) {
    if (linkVoltar) {
        linkVoltar.href = 'pagamento-pendente.html';
        linkVoltar.textContent = '← Voltar para Pagamento Pendente';
    }
    if (subtituloPagina) {
        subtituloPagina.textContent = 'Escolha um plano para avançar à seleção da forma de pagamento e liberar o acesso à sua conta.';
    }
    preencherResumoContexto([
        { rotulo: 'Conta', valor: emailPendencia }
    ]);
} else if (!dadosPreCadastro?.nome_agencia || !dadosPreCadastro.verification_token || dadosPreCadastro.email !== dadosPreCadastro.email_verificado) {
    window.location.replace('cadastro.html');
} else {
    preencherResumoContexto([
        { rotulo: 'Consultoria', valor: dadosPreCadastro.nome_agencia },
        { rotulo: 'E-mail', valor: dadosPreCadastro.email }
    ]);
}

function exibirErro(mensagem) {
    if (divMensagemErro) {
        divMensagemErro.textContent = mensagem;
        divMensagemErro.hidden = false;
        divMensagemErro.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

function limparErro() {
    if (divMensagemErro) {
        divMensagemErro.textContent = '';
        divMensagemErro.hidden = true;
    }
}

function formatarTextoMembros(limite) {
    if (limite === null || limite === undefined) {
        return 'Membros de equipe ilimitados';
    }
    const limiteNumerico = Number(limite);
    if (limiteNumerico === 1) {
        return '<strong>1</strong> membro (somente titular)';
    }
    return `Até <strong>${limiteNumerico}</strong> membros de equipe`;
}

function obterDescricaoPadrao(nomePlano) {
    const nome = (nomePlano || '').toLowerCase();
    if (nome.includes('enterprise')) {
        return 'Solução completa para grandes consultorias e hubs de inovação.';
    }
    if (nome.includes('pro')) {
        return 'Para consultorias em expansão gerenciarem múltiplas startups.';
    }
    return 'Ideal para consultores individuais iniciando no ecossistema.';
}

function obterLimiteMinutasPadrao(nomePlano) {
    const nome = (nomePlano || '').toLowerCase();
    if (nome.includes('enterprise')) return 50;
    if (nome.includes('pro')) return 15;
    return 3;
}

function formatarPreco(preco) {
    const numero = Number(preco);
    const valorValido = (!isNaN(numero) && numero > 0) ? numero : 49.90;
    return `R$ ${valorValido.toFixed(2).replace('.', ',')}`;
}

async function carregarPlanosNaTela() {
    limparErro();

    try {
        let planos = [];
        try {
            planos = await buscarPlanos();
        } catch (excecao) {
            // Usa fallback padrão se houver falha na listagem
        }

        const planosExibicao = (Array.isArray(planos) && planos.length > 0) ? planos : [
            {
                id: 1,
                nome: 'Standard',
                preco: 49.90,
                limite_startups: 2,
                limite_membros_equipe: 1,
                limite_minutas_ia: 3,
                descricao: 'Ideal para consultores individuais iniciando no ecossistema.'
            },
            {
                id: 2,
                nome: 'Pro',
                preco: 99.90,
                limite_startups: 10,
                limite_membros_equipe: 5,
                limite_minutas_ia: 15,
                descricao: 'Para consultorias em expansão gerenciarem múltiplas startups.'
            },
            {
                id: 3,
                nome: 'Enterprise',
                preco: 299.90,
                limite_startups: 50,
                limite_membros_equipe: 15,
                limite_minutas_ia: 50,
                descricao: 'Solução completa para grandes consultorias e hubs de inovação.'
            }
        ];

        containerListaPlanos.innerHTML = '';

        planosExibicao.forEach((plano) => {
            const ehPro = (plano.nome || '').toLowerCase().includes('pro');

            const cartao = document.createElement('article');
            cartao.className = ehPro ? 'plano-card plano-card--destaque' : 'plano-card';

            const cabecalho = document.createElement('div');
            cabecalho.className = 'plano-card-topo';

            if (ehPro) {
                const distintivo = document.createElement('span');
                distintivo.className = 'plano-badge';
                distintivo.textContent = 'Mais Popular';
                cabecalho.appendChild(distintivo);
            }

            const titulo = document.createElement('h3');
            titulo.className = 'plano-nome';
            titulo.textContent = plano.nome;

            const descricao = document.createElement('p');
            descricao.className = 'plano-descricao';
            descricao.textContent = plano.descricao || obterDescricaoPadrao(plano.nome);

            const elementoPreco = document.createElement('div');
            elementoPreco.className = 'plano-preco';
            elementoPreco.innerHTML = `
                <span class="plano-preco-valor">${formatarPreco(plano.preco ?? plano.preco_mensal)}</span>
                <span class="plano-preco-periodo">/mês</span>
            `;

            cabecalho.appendChild(titulo);
            cabecalho.appendChild(descricao);
            cabecalho.appendChild(elementoPreco);

            const limiteStartups = plano.limite_startups ?? 1;
            const limiteMembros = plano.limite_membros_equipe !== undefined ? plano.limite_membros_equipe : (plano.limite_usuarios ?? 1);
            const limiteMinutas = plano.limite_minutas_ia ?? obterLimiteMinutasPadrao(plano.nome);

            const detalhes = document.createElement('ul');
            detalhes.className = 'plano-beneficios';
            detalhes.innerHTML = `
                <li class="plano-beneficio">
                    <span class="plano-beneficio-icone" aria-hidden="true">✔</span>
                    <span>Até <strong>${limiteStartups}</strong> ${limiteStartups === 1 ? 'startup' : 'startups'} gerenciadas</span>
                </li>
                <li class="plano-beneficio">
                    <span class="plano-beneficio-icone" aria-hidden="true">✔</span>
                    <span>${formatarTextoMembros(limiteMembros)}</span>
                </li>
                <li class="plano-beneficio">
                    <span class="plano-beneficio-icone" aria-hidden="true">✔</span>
                    <span>Até <strong>${limiteMinutas}</strong> minutas técnicas IA/mês</span>
                </li>
                <li class="plano-beneficio">
                    <span class="plano-beneficio-icone" aria-hidden="true">✔</span>
                    <span>Pipeline Kanban &amp; Match IA</span>
                </li>
                <li class="plano-beneficio">
                    <span class="plano-beneficio-icone" aria-hidden="true">✔</span>
                    <span>Pagamento via PIX ou Cartão (AbacatePay)</span>
                </li>
            `;

            const botao = document.createElement('button');
            botao.type = 'button';
            botao.className = 'btn-selecionar-plano';
            botao.textContent = 'Selecionar Plano';

            botao.addEventListener('click', async () => {
                limparErro();

                const planoParaSalvar = {
                    id: plano.id,
                    nome: plano.nome,
                    preco: Number(plano.preco ?? plano.preco_mensal ?? 49.90),
                    limite_startups: limiteStartups,
                    limite_membros_equipe: limiteMembros,
                    limite_minutas_ia: limiteMinutas,
                    descricao: plano.descricao || obterDescricaoPadrao(plano.nome)
                };

                sessionStorage.setItem('plano_selecionado', JSON.stringify(planoParaSalvar));

                sessionStorage.removeItem('renovacao_autenticada');
                let usarTroca = modoTroca;
                if (modoRenovacao && (tokenPendencia || localStorage.getItem('token_autenticacao'))) {
                    try {
                        const atual = await requisicaoApi('/assinatura/atual');
                        if (atual.status_pagamento_real !== 'pendente' || atual.upgrade_pendente) {
                            sessionStorage.setItem('renovacao_autenticada', '1');
                        }
                        usarTroca = Number(atual.plano.id) !== Number(plano.id);
                    } catch (erro) {
                        exibirErro(erro.message);
                        return;
                    }
                }
                if (usarTroca) {
                    window.location.href = 'escolher-pagamento.html?modo=troca';
                    return;
                }
                const destino = modoRenovacao
                    ? 'escolher-pagamento.html?modo=renovacao'
                    : 'escolher-pagamento.html';
                window.location.href = destino;
            });

            cartao.appendChild(cabecalho);
            cartao.appendChild(detalhes);
            cartao.appendChild(botao);

            containerListaPlanos.appendChild(cartao);
        });

    } catch (erro) {
        exibirErro(erro.message || 'Erro ao carregar lista de planos.');
    }
}

carregarPlanosNaTela();
