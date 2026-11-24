import {
    registrarComPlano,
    obterTokenPendencia,
    obterEmailPendencia,
    salvarTokenPendencia,
    salvarEmailPendencia,
    salvarAgenciaPendencia
} from '../servicos/servicoAutenticacao.js';
import { requisicaoApi } from '../servicos/api.js';
import { gerarNovaCobranca } from '../servicos/servicoPlanos.js';

const parametrosUrl = new URLSearchParams(window.location.search);
const modoTroca = parametrosUrl.get('modo') === 'troca';
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
    : (modoTroca ? 'selecionar-plano.html?modo=troca' : 'selecionar-plano.html');

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

if (!modoTroca && !modoRenovacao && (!dadosPreCadastro?.nome_agencia || !dadosPreCadastro.verification_token || dadosPreCadastro.email !== dadosPreCadastro.email_verificado)) {
    window.location.replace('cadastro.html');
} else if (!planoSelecionado || !planoSelecionado.id) {
    window.location.replace(urlVoltarPlano);
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
} else if (!modoTroca && dadosPreCadastro) {
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
        const membros = planoSelecionado.limite_membros_equipe;
        const minutas = planoSelecionado.limite_minutas_ia ?? 3;
        
        let textoMembros = 'Membros ilimitados';
        if (membros !== null && membros !== undefined) {
            const numMembros = Number(membros);
            textoMembros = numMembros === 1 ? '1 membro (somente titular)' : `Até ${numMembros} membros de equipe`;
        }
        resumoPlanoCotas.textContent = `Até ${startups} ${startups === 1 ? 'startup' : 'startups'} • ${textoMembros} • ${minutas} minutas IA/mês`;
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

function descreverImpedimentos(impedimentos) {
    const instrucoes = [];
    if (impedimentos?.startups) instrucoes.push('Remova ' + impedimentos.startups.excedente + ' startup(s).');
    if (impedimentos?.membros) instrucoes.push('Libere ' + impedimentos.membros.excedente + ' vaga(s) de equipe, incluindo convites pendentes.');
    return instrucoes.join(' ');
}

let simulacao;
async function carregarSimulacao() {
    btnFinalizar.disabled = true;
    const resumo = document.getElementById('simulacao-troca');
    const metodos = document.querySelector('.metodos-pagamento-grid');
    metodos.style.display = 'none';
    document.querySelector('.planos-nota-rodape').hidden = true;
    document.getElementById('titulo-pagina').textContent = 'Revise a troca de plano';
    document.getElementById('subtitulo-pagina').textContent = 'Confira as condições antes de confirmar.';
    try {
        simulacao = await requisicaoApi('/assinatura/simular-troca?plano_id=' + encodeURIComponent(planoSelecionado.id));
        resumo.hidden = false;
        if (!simulacao.permitido) {
            resumo.textContent = descreverImpedimentos(simulacao.impedimentos);
            btnFinalizar.textContent = 'Troca bloqueada pelas cotas';
            return;
        }
        if (simulacao.tipo === 'downgrade') {
            const data = new Date(simulacao.data_vigencia).toLocaleDateString('pt-BR');
            resumo.textContent = 'Seu plano atual permanece ativo até ' + data + ', quando entra em vigor o plano ' + simulacao.plano_destino.nome + '. Nenhuma cobrança hoje.';
            btnFinalizar.textContent = 'Agendar downgrade';
        } else {
            metodos.style.display = '';
            const bonus = simulacao.tipo === 'upgrade'
                ? simulacao.dias_restantes + ' dia(s) restantes convertidos em +' + simulacao.dias_extras + ' dias bônus. '
                : '';
            resumo.textContent = 'Pagamento hoje de ' + formatarPreco(simulacao.valor_a_pagar) + '. ' + bonus
                + 'O plano ' + simulacao.plano_destino.nome + ' ativa automaticamente após a confirmação do pagamento, por ' + simulacao.novo_ciclo_dias + ' dias.';
            btnFinalizar.textContent = 'Confirmar troca e pagar';
        }
        btnFinalizar.disabled = false;
    } catch (erro) {
        exibirErro(erro.message);
    }
}

if (modoTroca && planoSelecionado) carregarSimulacao();

btnFinalizar?.addEventListener('click', async () => {
    if (!planoSelecionado) return;
    limparErro();

    btnFinalizar.disabled = true;
    const textoOriginal = btnFinalizar.textContent;
    btnFinalizar.textContent = 'Processando e gerando checkout...';

    try {
        if (modoTroca) {
            const resposta = await requisicaoApi('/assinatura/trocar-plano', {
                method: 'POST',
                body: JSON.stringify({ plano_id: planoSelecionado.id, forma_pagamento: formaPagamentoSelecionada, tipo_simulado: simulacao.tipo })
            });
            sessionStorage.removeItem('plano_selecionado');
            if (resposta.url_checkout) {
                window.location.assign(resposta.url_checkout);
            } else {
                btnFinalizar.textContent = 'Downgrade agendado';
                linkAlterarPlano.href = 'selecionar-startup.html';
                linkAlterarPlano.textContent = '← Voltar à consultoria';
            }
        } else if (modoRenovacao && (tokenPendencia || emailPendencia)) {
            const resposta = sessionStorage.getItem('renovacao_autenticada') === '1'
                ? await requisicaoApi('/assinatura/renovar', {
                    method: 'POST', body: JSON.stringify({ forma_pagamento: formaPagamentoSelecionada })
                })
                : await gerarNovaCobranca(planoSelecionado.id, formaPagamentoSelecionada);
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
                verification_token: dadosPreCadastro.verification_token,
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
        if (!modoRenovacao && !modoTroca && erro.dados?.codigo_erro === 'VERIFICACAO_EMAIL_INVALIDA') {
            const { nome_agencia, name, email } = dadosPreCadastro;
            sessionStorage.setItem('dados_pre_cadastro', JSON.stringify({ nome_agencia, name, email }));
            window.location.replace('cadastro.html?revalidar=1');
            return;
        }
        exibirErro(descreverImpedimentos(erro.dados?.impedimentos) || erro.message || 'Erro ao finalizar escolha de pagamento. Tente novamente.');
        btnFinalizar.disabled = false;
        btnFinalizar.textContent = textoOriginal;
    }
});
