import {
    consultarPagamentoAnterior,
    cancelarCadastroPendente,
    obterTokenPendencia,
    obterEmailPendencia,
    obterAgenciaPendencia,
    obterUsuarioLogado,
    limparDadosPendencia,
    salvarToken,
    removerToken
} from '../servicos/servicoAutenticacao.js';

let tokenPendencia = obterTokenPendencia();
let emailPendencia = obterEmailPendencia();
const agenciaPendencia = obterAgenciaPendencia();

// Fallback de resiliência: resgata e-mail do cache do usuário caso não esteja explícito no storage de pendência
if (!emailPendencia) {
    const usuarioLogado = obterUsuarioLogado();
    if (usuarioLogado?.email) {
        emailPendencia = usuarioLogado.email;
    } else {
        try {
            const preCadastro = sessionStorage.getItem('dados_pre_cadastro');
            if (preCadastro) {
                const parsed = JSON.parse(preCadastro);
                if (parsed.email) emailPendencia = parsed.email;
            }
        } catch (_) {}
    }
}

// Se não houver nenhum identificador de conta, retorna ao login corporativo
if (!tokenPendencia && !emailPendencia) {
    window.location.href = 'login.html';
}

const emailDestaque = document.getElementById('email-destaque');
const linhaAgencia = document.getElementById('linha-agencia');
const agenciaDestaque = document.getElementById('agencia-destaque');
const mensagemFeedback = document.getElementById('mensagem-feedback');
const botaoConsultarAnterior = document.getElementById('botao-consultar-anterior');
const botaoGerarNova = document.getElementById('botao-gerar-nova');
const botaoCancelarConta = document.getElementById('botao-cancelar-conta');
const painelConfirmacao = document.getElementById('painel-confirmacao');
const botaoConfirmarExclusaoSim = document.getElementById('botao-confirmar-exclusao-sim');
const botaoConfirmarExclusaoNao = document.getElementById('botao-confirmar-exclusao-nao');

// Inicializa dados corporativos na tela
if (emailDestaque && emailPendencia) {
    emailDestaque.textContent = emailPendencia;
}

if (linhaAgencia && agenciaDestaque && agenciaPendencia?.nome) {
    agenciaDestaque.textContent = agenciaPendencia.nome;
    linhaAgencia.style.display = 'flex';
}

function exibirFeedback(mensagem, tipo = 'erro') {
    if (!mensagemFeedback) return;
    mensagemFeedback.textContent = mensagem;
    mensagemFeedback.hidden = false;

    mensagemFeedback.classList.remove('success', 'warning');
    if (tipo === 'sucesso') {
        mensagemFeedback.classList.add('success');
    } else if (tipo === 'alerta') {
        mensagemFeedback.classList.add('warning');
    }
    mensagemFeedback.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function limparFeedback() {
    if (!mensagemFeedback) return;
    mensagemFeedback.textContent = '';
    mensagemFeedback.hidden = true;
    mensagemFeedback.classList.remove('success', 'warning');
}

// =========================================================================
// OPÇÃO 1 - Consultar Conciliação Bancária do Pagamento
// =========================================================================
botaoConsultarAnterior?.addEventListener('click', async () => {
    limparFeedback();
    botaoConsultarAnterior.disabled = true;
    const textoOriginalHtml = botaoConsultarAnterior.innerHTML;
    botaoConsultarAnterior.innerHTML = `
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="animation: spin 1s linear infinite;">
            <circle cx="12" cy="12" r="10" stroke-dasharray="32" stroke-dashoffset="12"></circle>
        </svg>
        Consultando conciliação...
    `;

    try {
        const resposta = await consultarPagamentoAnterior();

        if (resposta.pago) {
            exibirFeedback('Pagamento confirmado com sucesso! Redirecionando para o ambiente corporativo...', 'sucesso');
            limparDadosPendencia();

            const tokenPleno = resposta.token || resposta.access_token;
            if (tokenPleno) {
                salvarToken(tokenPleno);
                setTimeout(() => {
                    window.location.href = 'selecionar-startup.html';
                }, 1400);
            } else {
                setTimeout(() => {
                    window.location.href = 'login.html';
                }, 1400);
            }
            return;
        }

        if (resposta.expirado) {
            exibirFeedback('A cobrança PIX anterior expirou. Selecione "Gerar nova cobrança" abaixo para emitir uma nova chave.', 'alerta');
            return;
        }

        const urlCheckout = resposta.url_checkout
            || (resposta.dados && resposta.dados.url_checkout);

        if (urlCheckout && urlCheckout.startsWith('http')) {
            exibirFeedback('Cobrança em aberto localizada. Redirecionando para a tela de pagamento...', 'alerta');
            setTimeout(() => {
                window.location.href = urlCheckout;
            }, 1000);
        } else {
            exibirFeedback('A cobrança PIX anterior expirou ou não está mais ativa. Gere uma nova cobrança para prosseguir.', 'alerta');
        }

    } catch (erro) {
        const mensagemErro = erro.message || '';

        // Fallback: caso a url esteja gravada em cache e o pagamento ainda esteja dentro do prazo
        if ((erro.status === 401 || mensagemErro.toLowerCase().includes('unauthenticated')) && agenciaPendencia) {
            const urlSalva = agenciaPendencia.url_checkout;
            const expiraEm = agenciaPendencia.pagamento_expira_em ? new Date(agenciaPendencia.pagamento_expira_em) : null;
            const aindaValido = !expiraEm || expiraEm > new Date();
            if (urlSalva && aindaValido) {
                window.location.href = urlSalva;
                return;
            }
        }

        if (mensagemErro.toLowerCase().includes('expirou') || mensagemErro.toLowerCase().includes('expirado') || erro.status === 410 || erro.status === 400) {
            exibirFeedback('O pagamento anterior expirou. Por favor, utilize a opção "Gerar nova cobrança" abaixo.', 'alerta');
        } else {
            exibirFeedback(mensagemErro || 'Não foi possível consultar a conciliação bancária. Gere uma nova cobrança.');
        }
    } finally {
        botaoConsultarAnterior.disabled = false;
        botaoConsultarAnterior.innerHTML = textoOriginalHtml;
    }
});

// =========================================================================
// OPÇÃO 2 - Gerar Nova Cobrança (Escolher Plano)
// =========================================================================
botaoGerarNova?.addEventListener('click', () => {
    window.location.href = 'selecionar-plano.html?modo=renovacao';
});

// =========================================================================
// OPÇÃO 3 - Cancelar Contratação e Excluir Cadastro
// =========================================================================
botaoCancelarConta?.addEventListener('click', () => {
    limparFeedback();
    if (painelConfirmacao) {
        painelConfirmacao.hidden = false;
        botaoCancelarConta.style.display = 'none';
    }
});

botaoConfirmarExclusaoNao?.addEventListener('click', () => {
    if (painelConfirmacao) {
        painelConfirmacao.hidden = true;
        botaoCancelarConta.style.display = 'inline-block';
    }
});

botaoConfirmarExclusaoSim?.addEventListener('click', async () => {
    limparFeedback();
    botaoConfirmarExclusaoSim.disabled = true;
    botaoConfirmarExclusaoNao.disabled = true;
    botaoConfirmarExclusaoSim.textContent = 'Cancelando...';

    try {
        await cancelarCadastroPendente();

        limparDadosPendencia();
        removerToken();
        sessionStorage.removeItem('dados_pre_cadastro');

        alert('O cadastro pendente foi cancelado com sucesso. O e-mail corporativo está liberado para novo registro.');
        window.location.href = 'cadastro.html';

    } catch (erro) {
        exibirFeedback(erro.message || 'Erro ao cancelar cadastro pendente.');
        botaoConfirmarExclusaoSim.disabled = false;
        botaoConfirmarExclusaoNao.disabled = false;
        botaoConfirmarExclusaoSim.textContent = 'Sim, cancelar e excluir';
    }
});
