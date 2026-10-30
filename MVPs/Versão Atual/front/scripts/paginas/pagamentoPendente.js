import { 
    consultarPagamentoAnterior, 
    cancelarCadastroPendente, 
    obterTokenPendencia, 
    obterEmailPendencia, 
    limparDadosPendencia,
    removerToken 
} from '../servicos/servicoAutenticacao.js';

const tokenPendencia = obterTokenPendencia();
const emailPendencia = obterEmailPendencia();

const emailDestaque = document.getElementById('email-destaque');
const mensagemFeedback = document.getElementById('mensagem-feedback');
const botaoConsultarAnterior = document.getElementById('botao-consultar-anterior');
const botaoGerarNova = document.getElementById('botao-gerar-nova');
const botaoCancelarConta = document.getElementById('botao-cancelar-conta');

// Se não houver token de pendência, redireciona para a tela de login
if (!tokenPendencia) {
    window.location.href = 'login.html';
}

if (emailDestaque && emailPendencia) {
    emailDestaque.textContent = emailPendencia;
}

function exibirFeedback(mensagem, tipo = 'erro') {
    if (!mensagemFeedback) return;
    mensagemFeedback.textContent = mensagem;
    mensagemFeedback.hidden = false;

    if (tipo === 'sucesso') {
        mensagemFeedback.classList.add('caixa-feedback-sucesso');
    } else {
        mensagemFeedback.classList.remove('caixa-feedback-sucesso');
    }
}

function limparFeedback() {
    if (!mensagemFeedback) return;
    mensagemFeedback.textContent = '';
    mensagemFeedback.hidden = true;
    mensagemFeedback.classList.remove('caixa-feedback-sucesso');
}

// =========================================================================
// OPÇÃO 1 - Consultar Janela de Pagamento Anterior
// =========================================================================
botaoConsultarAnterior?.addEventListener('click', async () => {
    limparFeedback();
    botaoConsultarAnterior.disabled = true;
    const textoOriginal = botaoConsultarAnterior.textContent;
    botaoConsultarAnterior.textContent = 'Consultando pagamento...';

    try {
        const resposta = await consultarPagamentoAnterior();

        if (resposta.pago) {
            exibirFeedback('O pagamento desta conta já foi confirmado! Redirecionando para login...', 'sucesso');
            limparDadosPendencia();
            setTimeout(() => {
                window.location.href = 'login.html';
            }, 1800);
            return;
        }

        if (resposta.expirado) {
            exibirFeedback('O pagamento anterior expirou. Por favor, utilize a opção abaixo para gerar uma nova cobrança.');
            return;
        }

        const urlCheckout = resposta.checkout_url 
            || resposta.url_checkout 
            || (resposta.dados && (resposta.dados.checkout_url || resposta.dados.url_checkout));

        if (urlCheckout) {
            window.location.href = urlCheckout;
        } else {
            exibirFeedback('O pagamento anterior expirou. Por favor, utilize a opção abaixo para gerar uma nova cobrança.');
        }

    } catch (erro) {
        // Se a API retornar erro de expiração (ex: 400 ou 410)
        const mensagemErro = erro.message || '';
        if (mensagemErro.toLowerCase().includes('expirou') || mensagemErro.toLowerCase().includes('expirado') || erro.status === 410 || erro.status === 400) {
            exibirFeedback('O pagamento anterior expirou. Por favor, utilize a opção abaixo para gerar uma nova cobrança.');
        } else {
            exibirFeedback(mensagemErro || 'Não foi possível consultar o pagamento anterior. Gere uma nova cobrança.');
        }
    } finally {
        botaoConsultarAnterior.disabled = false;
        botaoConsultarAnterior.textContent = textoOriginal;
    }
});

// =========================================================================
// OPÇÃO 2 - Gerar Nova Cobrança (Escolher Plano)
// =========================================================================
botaoGerarNova?.addEventListener('click', () => {
    window.location.href = 'selecionar-plano.html?modo=renovacao';
});

// =========================================================================
// OPÇÃO 3 - Cancelar Pagamento e Excluir Conta
// =========================================================================
botaoCancelarConta?.addEventListener('click', async () => {
    limparFeedback();

    const confirmado = confirm(
        'Atenção: ao cancelar o pagamento, sua conta pendente será excluída permanentemente e você voltará para a tela de cadastro.\n\nDeseja realmente continuar?'
    );

    if (!confirmado) {
        return;
    }

    botaoConsultarAnterior.disabled = true;
    botaoGerarNova.disabled = true;
    botaoCancelarConta.disabled = true;
    botaoCancelarConta.textContent = 'Excluindo conta...';

    try {
        await cancelarCadastroPendente();

        limparDadosPendencia();
        removerToken();
        sessionStorage.removeItem('dados_pre_cadastro');

        alert('Sua conta pendente e dados de pagamento foram cancelados e excluídos com sucesso.');
        window.location.href = 'cadastro.html';

    } catch (erro) {
        exibirFeedback(erro.message || 'Erro ao cancelar pagamento e excluir conta.');
        botaoConsultarAnterior.disabled = false;
        botaoGerarNova.disabled = false;
        botaoCancelarConta.disabled = false;
        botaoCancelarConta.textContent = 'Cancelar Pagamento e Excluir Conta';
    }
});
