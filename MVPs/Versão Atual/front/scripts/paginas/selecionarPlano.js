import { registrarComPlano, obterTokenPendencia } from '../servicos/servicoAutenticacao.js';
import { buscarPlanos, gerarNovaCobranca } from '../servicos/servicoPlanos.js';

const containerListaPlanos = document.getElementById('lista-planos');
const divMensagemErro = document.getElementById('mensagem-erro');
const linkVoltar = document.getElementById('link-voltar');
const subtituloPagina = document.getElementById('subtitulo-pagina');

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

if (modoRenovacao && tokenPendencia) {
    if (linkVoltar) {
        linkVoltar.href = 'pagamento-pendente.html';
        linkVoltar.textContent = '← Voltar para Pagamento Pendente';
    }
    if (subtituloPagina) {
        subtituloPagina.textContent = 'Escolha um plano para gerar uma nova cobrança e liberar o acesso à sua conta.';
    }
} else if (!dadosPreCadastro) {
    window.location.replace('cadastro.html');
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

function formatarPreco(preco) {
    const numero = Number(preco);
    const valorValido = (!isNaN(numero) && numero > 0) ? numero : 49.90;
    return `R$ ${valorValido.toFixed(2).replace('.', ',')}<span style="font-size: 0.85rem; font-weight: 400; color: var(--text-muted);">/mês</span>`;
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
                descricao: 'Ideal para consultores individuais iniciando no ecossistema.'
            },
            {
                id: 2,
                nome: 'Pro',
                preco: 99.90,
                limite_startups: 10,
                limite_membros_equipe: 5,
                descricao: 'Para consultorias em expansão gerenciarem múltiplas startups.'
            },
            {
                id: 3,
                nome: 'Enterprise',
                preco: 299.90,
                limite_startups: 50,
                limite_membros_equipe: 15,
                descricao: 'Solução completa para grandes consultorias e hubs de inovação.'
            }
        ];

        containerListaPlanos.innerHTML = '';

        planosExibicao.forEach((plano) => {
            const cartao = document.createElement('div');
            cartao.className = 'bloco-plano';
            cartao.style.position = 'relative';

            if (plano.nome.toLowerCase().includes('pro')) {
                cartao.style.borderColor = 'var(--color-primary)';
                cartao.style.boxShadow = '0 0 25px rgba(99, 102, 241, 0.25)';
            }

            const cabecalho = document.createElement('div');
            cabecalho.style.width = '100%';

            const distintivo = document.createElement('div');
            if (plano.nome.toLowerCase().includes('pro')) {
                distintivo.style.display = 'inline-block';
                distintivo.style.fontSize = '0.75rem';
                distintivo.style.fontWeight = '700';
                distintivo.style.textTransform = 'uppercase';
                distintivo.style.color = '#fff';
                distintivo.style.background = 'linear-gradient(135deg, #6366f1, #8b5cf6)';
                distintivo.style.padding = '3px 10px';
                distintivo.style.borderRadius = '999px';
                distintivo.style.marginBottom = '8px';
                distintivo.textContent = 'Mais Popular';
            }

            const titulo = document.createElement('h2');
            titulo.textContent = plano.nome;
            titulo.style.fontSize = '1.35rem';
            titulo.style.fontWeight = '700';
            titulo.style.color = 'var(--text-main)';

            const elementoPreco = document.createElement('div');
            elementoPreco.style.fontSize = '1.75rem';
            elementoPreco.style.fontWeight = '800';
            elementoPreco.style.margin = '14px 0 8px 0';
            elementoPreco.style.color = '#a5b4fc';
            elementoPreco.innerHTML = formatarPreco(plano.preco ?? plano.preco_mensal);

            const detalhes = document.createElement('div');
            detalhes.style.width = '100%';
            detalhes.style.fontSize = '0.86rem';
            detalhes.style.color = 'var(--text-muted)';
            detalhes.style.display = 'flex';
            detalhes.style.flexDirection = 'column';
            detalhes.style.gap = '8px';
            detalhes.style.margin = '16px 0';
            detalhes.style.textAlign = 'left';

            const limiteStartups = plano.limite_startups ?? 1;
            const limiteMembros = plano.limite_membros_equipe ?? plano.limite_usuarios ?? 1;

            detalhes.innerHTML = `
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="color: #10b981;">✔</span> Até <strong>${limiteStartups}</strong> startups
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="color: #10b981;">✔</span> Até <strong>${limiteMembros}</strong> membros na equipe
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="color: #10b981;">✔</span> Pipeline Kanban & Match IA
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="color: #10b981;">✔</span> Integração AbacatePay PIX
                </div>
            `;

            const botao = document.createElement('button');
            botao.type = 'button';
            botao.textContent = 'Selecionar Plano';
            botao.style.marginTop = 'auto';

            botao.addEventListener('click', async () => {
                limparErro();
                const todosBotoes = containerListaPlanos.querySelectorAll('button');
                todosBotoes.forEach(b => b.disabled = true);
                botao.textContent = 'Gerando pagamento...';

                try {
                    if (modoRenovacao && tokenPendencia) {
                        const resposta = await gerarNovaCobranca(plano.id);
                        const urlCheckout = resposta.url_checkout || resposta.checkout_url;

                        if (urlCheckout && urlCheckout.startsWith('http')) {
                            sessionStorage.removeItem('dados_pre_cadastro');
                            window.location.replace(urlCheckout);
                        } else {
                            throw new Error('Não foi possível gerar nova cobrança para este plano.');
                        }
                    } else if (dadosPreCadastro) {
                        const resposta = await registrarComPlano({
                            name: dadosPreCadastro.name,
                            email: dadosPreCadastro.email,
                            password: dadosPreCadastro.password,
                            plano_id: plano.id
                        });

                        const urlCheckout = resposta.url_checkout || resposta.checkout_url;

                        if (urlCheckout && urlCheckout.startsWith('http')) {
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

            if (plano.nome.toLowerCase().includes('pro')) {
                cabecalho.appendChild(distintivo);
            }
            cabecalho.appendChild(titulo);
            cabecalho.appendChild(elementoPreco);

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
