import {
    registrarComPlano,
    obterTokenPendencia,
    obterEmailPendencia,
    salvarTokenPendencia,
    salvarEmailPendencia,
    salvarAgenciaPendencia
} from '../servicos/servicoAutenticacao.js';
import { buscarPlanos, gerarNovaCobranca } from '../servicos/servicoPlanos.js';

const containerListaPlanos = document.getElementById('lista-planos');
const divMensagemErro = document.getElementById('mensagem-erro');
const linkVoltar = document.getElementById('link-voltar');
const subtituloPagina = document.getElementById('subtitulo-pagina');
const resumoPreCadastro = document.getElementById('resumo-pre-cadastro');

const parametrosUrl = new URLSearchParams(window.location.search);
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

if (modoRenovacao && (tokenPendencia || emailPendencia)) {
    if (linkVoltar) {
        linkVoltar.href = 'pagamento-pendente.html';
        linkVoltar.textContent = '← Voltar para Pagamento Pendente';
    }
    if (subtituloPagina) {
        subtituloPagina.textContent = 'Escolha um plano para gerar uma nova cobrança e liberar o acesso à sua conta.';
    }
    preencherResumoContexto([
        { rotulo: 'Conta', valor: emailPendencia }
    ]);
} else if (!dadosPreCadastro || !dadosPreCadastro.nome_agencia) {
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
            const limiteMembros = plano.limite_membros_equipe ?? plano.limite_usuarios ?? 1;
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
                    <span>Até <strong>${limiteMembros}</strong> ${limiteMembros === 1 ? 'membro' : 'membros'} na equipe</span>
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
                    <span>Integração AbacatePay PIX</span>
                </li>
            `;

            const botao = document.createElement('button');
            botao.type = 'button';
            botao.className = 'btn-selecionar-plano';
            botao.textContent = 'Selecionar Plano';

            botao.addEventListener('click', async () => {
                limparErro();
                const todosBotoes = containerListaPlanos.querySelectorAll('button');
                todosBotoes.forEach(b => b.disabled = true);
                botao.textContent = 'Gerando pagamento...';

                try {
                    if (modoRenovacao && (tokenPendencia || emailPendencia)) {
                        const resposta = await gerarNovaCobranca(plano.id);
                        const urlCheckout = resposta.url_checkout;

                        if (urlCheckout && urlCheckout.startsWith('http')) {
                            salvarAgenciaPendencia({ url_checkout: urlCheckout });
                            sessionStorage.removeItem('dados_pre_cadastro');
                            window.location.replace(urlCheckout);
                        } else {
                            throw new Error('Não foi possível gerar nova cobrança para este plano.');
                        }
                    } else if (dadosPreCadastro) {
                        const resposta = await registrarComPlano({
                            nome_agencia: dadosPreCadastro.nome_agencia,
                            name: dadosPreCadastro.name,
                            email: dadosPreCadastro.email,
                            password: dadosPreCadastro.password,
                            plano_id: plano.id
                        });

                        const urlCheckout = resposta.url_checkout;
                        const novoTokenPend = resposta.token_pendencia || resposta.access_token || resposta.token;
                        if (novoTokenPend) {
                            salvarTokenPendencia(novoTokenPend);
                        }
                        if (dadosPreCadastro.email) {
                            salvarEmailPendencia(dadosPreCadastro.email);
                        }

                        if (urlCheckout && urlCheckout.startsWith('http')) {
                            salvarAgenciaPendencia({ url_checkout: urlCheckout });
                            sessionStorage.removeItem('dados_pre_cadastro');
                            window.location.replace(urlCheckout);
                        } else {
                            throw new Error('Gateway de pagamento não retornou o link de checkout.');
                        }
                    } else {
                        window.location.replace('cadastro.html');
                    }
                } catch (erro) {
                    exibirErro(erro.message || 'Erro ao processar seleção de plano. Tente novamente.');
                    todosBotoes.forEach(b => b.disabled = false);
                    botao.textContent = 'Selecionar Plano';
                }
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
