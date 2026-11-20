import {
    registrarComPlano,
    obterTokenPendencia,
    obterEmailPendencia,
    salvarTokenPendencia,
    salvarEmailPendencia,
    salvarAgenciaPendencia
} from '../servicos/servicoAutenticacao.js';
import { gerarNovaCobranca } from '../servicos/servicoPlanos.js';

const parametrosUrl = new URLSearchParams(window.location.search);
const modoRenovacao = parametrosUrl.get('modo') === 'renovacao';

const linkAlterarPlano = document.getElementById('link-alterar-plano');
const linkCardAlterarPlano = document.getElementById('link-card-alterar-plano');
const resumoPreCadastro = document.getElementById('resumo-pre-cadastro');
const resumoPlanoNome = document.getElementById('resumo-plano-nome');
const resumoPlanoCotas = document.getElementById('resumo-plano-cotas');
const resumoPlanoPreco = document.getElementById('resumo-plano-preco');
const divMensagemErro = document.getElementById('mensagem-erro');
const cardPix = document.getElementById('card-metodo-pix');
const cardCartao = document.getElementById('card-metodo-card');
const btnFinalizar = document.getElementById('btn-finalizar-pagamento');

const urlVoltarPlano = modoRenovacao
    ? 'selecionar-plano.html?modo=renovacao'
    : 'selecionar-plano.html';

if (linkAlterarPlano) linkAlterarPlano.href = urlVoltarPlano;
if (linkCardAlterarPlano) linkCardAlterarPlano.href = urlVoltarPlano;

let planoSelecionado = null;
try {
    const brutoPlano = sessionStorage.getItem('plano_selecionado');
    if (brutoPlano) {
        planoSelecionado = JSON.parse(brutoPlano);
    }
} catch (_) {
    planoSelecionado = null;
}

let dadosPreCadastro = null;
try {
    const brutoPre = sessionStorage.getItem('dados_pre_cadastro');
    if (brutoPre) {
        dadosPreCadastro = JSON.parse(brutoPre);
    }
} catch (_) {
    dadosPreCadastro = null;
}

const tokenPendencia = obterTokenPendencia();
const emailPendencia = obterEmailPendencia();

if (!planoSelecionado || !planoSelecionado.id) {
    window.location.replace(urlVoltarPlano);
} else if (!modoRenovacao && (!dadosPreCadastro || !dadosPreCadastro.nome_agencia)) {
    window.location.replace('cadastro.html');
} else if (modoRenovacao && !tokenPendencia && !emailPendencia) {
    window.location.replace('login.html');
}

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
    preencherResumoContexto([
        { rotulo: 'Conta', valor: emailPendencia }
    ]);
} else if (dadosPreCadastro) {
    preencherResumoContexto([
        { rotulo: 'Consultoria', valor: dadosPreCadastro.nome_agencia },
        { rotulo: 'E-mail', valor: dadosPreCadastro.email }
    ]);
}

function formatarPreco(preco) {
    const numero = Number(preco);
    const valorValido = (!isNaN(numero) && numero > 0) ? numero : 49.90;
    return `R$ ${valorValido.toFixed(2).replace('.', ',')}`;
}

if (planoSelecionado) {
    if (resumoPlanoNome) {
        resumoPlanoNome.textContent = `Plano ${planoSelecionado.nome}`;
    }
    if (resumoPlanoPreco) {
        resumoPlanoPreco.innerHTML = `${formatarPreco(planoSelecionado.preco)}<span>/mês</span>`;
    }
    if (resumoPlanoCotas) {
        const startups = planoSelecionado.limite_startups ?? 1;
        const membros = planoSelecionado.limite_membros_equipe ?? 1;
        const minutas = planoSelecionado.limite_minutas_ia ?? 3;
        resumoPlanoCotas.textContent = `Até ${startups} ${startups === 1 ? 'startup' : 'startups'} • Até ${membros} ${membros === 1 ? 'membro' : 'membros'} • ${minutas} minutas IA/mês`;
    }
}

let formaPagamentoSelecionada = 'PIX';

function selecionarMetodoPagamento(metodo) {
    formaPagamentoSelecionada = metodo === 'CARD' ? 'CARD' : 'PIX';

    if (cardPix && cardCartao) {
        const ehPix = formaPagamentoSelecionada === 'PIX';
        cardPix.classList.toggle('selecionado', ehPix);
        cardPix.setAttribute('aria-checked', ehPix ? 'true' : 'false');

        cardCartao.classList.toggle('selecionado', !ehPix);
        cardCartao.setAttribute('aria-checked', !ehPix ? 'true' : 'false');
    }
}

cardPix?.addEventListener('click', () => selecionarMetodoPagamento('PIX'));
cardCartao?.addEventListener('click', () => selecionarMetodoPagamento('CARD'));

[cardPix, cardCartao].forEach((card) => {
    card?.addEventListener('keydown', (evento) => {
        if (evento.key === 'Enter' || evento.key === ' ') {
            evento.preventDefault();
            selecionarMetodoPagamento(card.dataset.metodo);
        }
    });
});

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

btnFinalizar?.addEventListener('click', async () => {
    if (!planoSelecionado) return;
    limparErro();

    btnFinalizar.disabled = true;
    const textoOriginal = btnFinalizar.textContent;
    btnFinalizar.textContent = 'Processando e gerando checkout...';

    try {
        if (modoRenovacao && (tokenPendencia || emailPendencia)) {
            const resposta = await gerarNovaCobranca(planoSelecionado.id, formaPagamentoSelecionada);
            const urlCheckout = resposta?.url_checkout;

            if (urlCheckout && urlCheckout.startsWith('http')) {
                salvarAgenciaPendencia({
                    url_checkout: urlCheckout,
                    forma_pagamento: formaPagamentoSelecionada
                });
                sessionStorage.removeItem('dados_pre_cadastro');
                sessionStorage.removeItem('plano_selecionado');
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
                plano_id: planoSelecionado.id,
                forma_pagamento: formaPagamentoSelecionada
            });

            const urlCheckout = resposta?.url_checkout;
            const novoTokenPend = resposta?.token_pendencia || resposta?.access_token || resposta?.token;
            if (novoTokenPend) {
                salvarTokenPendencia(novoTokenPend);
            }
            if (dadosPreCadastro.email) {
                salvarEmailPendencia(dadosPreCadastro.email);
            }

            if (urlCheckout && urlCheckout.startsWith('http')) {
                salvarAgenciaPendencia({
                    url_checkout: urlCheckout,
                    forma_pagamento: formaPagamentoSelecionada
                });
                sessionStorage.removeItem('dados_pre_cadastro');
                sessionStorage.removeItem('plano_selecionado');
                window.location.replace(urlCheckout);
            } else {
                throw new Error('Gateway de pagamento não retornou o link de checkout.');
            }
        } else {
            window.location.replace('cadastro.html');
        }
    } catch (erro) {
        exibirErro(erro.message || 'Erro ao finalizar escolha de pagamento. Tente novamente.');
        btnFinalizar.disabled = false;
        btnFinalizar.textContent = textoOriginal;
    }
});
