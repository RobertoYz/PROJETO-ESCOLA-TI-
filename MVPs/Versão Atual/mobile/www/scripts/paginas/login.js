import { realizarLogin, salvarToken, limparStartupAtiva, salvarTokenPendencia, salvarEmailPendencia, salvarAgenciaPendencia, limparDadosPendencia, salvarUsuarioLogado } from '../servicos/servicoAutenticacao.js';

const formulario = document.getElementById('formulario-login');
const campoEmail = document.getElementById('campo-email');
const campoSenha = document.getElementById('campo-senha');
const divMensagemErro = document.getElementById('mensagem-erro');
const botaoConfirmar = document.getElementById('botao-confirmar');

function exibirErro(mensagem) {
    divMensagemErro.textContent = mensagem;
    divMensagemErro.hidden = false;
}

formulario.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    divMensagemErro.hidden = true;

    const email = campoEmail.value.trim();
    const senha = campoSenha.value;

    if (!email || !senha) {
        exibirErro('Informe seu e-mail e sua senha.');
        return;
    }

    botaoConfirmar.disabled = true;
    botaoConfirmar.textContent = 'Entrando...';

    try {
        const resposta = await realizarLogin({ email, password: senha });

        if (resposta.codigo === 'PAGAMENTO_PENDENTE' || resposta.codigo_erro === 'PAGAMENTO_PENDENTE') {
            const tokenPendencia = resposta.token_pendencia || resposta.access_token || resposta.token;
            salvarTokenPendencia(tokenPendencia || '');
            if (resposta.usuario) {
                salvarUsuarioLogado(resposta.usuario);
            }
            if (resposta.agencia) {
                salvarAgenciaPendencia(resposta.agencia);
            }
            salvarEmailPendencia(email);
            window.location.href = 'pagamento-pendente.html';
            return;
        }

        // Login aprovado com assinatura ativa
        salvarToken(resposta.token || resposta.access_token);
        const usuario = resposta.dados?.usuario || resposta.usuario;
        if (usuario) {
            salvarUsuarioLogado(usuario);
        } else {
            salvarUsuarioLogado({ email });
        }
        limparStartupAtiva();
        sessionStorage.removeItem('dados_pre_cadastro');
        limparDadosPendencia();
        window.location.href = 'selecionar-startup.html';

    } catch (erro) {
        // Trata retorno HTTP 403 de Pagamento Pendente
        if (erro.status === 403 && (erro.dados?.codigo === 'PAGAMENTO_PENDENTE' || erro.dados?.codigo_erro === 'PAGAMENTO_PENDENTE')) {
            const tokenPendencia = erro.dados.token_pendencia || erro.dados.access_token || erro.dados.token;
            salvarTokenPendencia(tokenPendencia || '');
            if (erro.dados.usuario) {
                salvarUsuarioLogado(erro.dados.usuario);
            }
            if (erro.dados.agencia) {
                salvarAgenciaPendencia(erro.dados.agencia);
            }
            salvarEmailPendencia(email);
            window.location.href = 'pagamento-pendente.html';
            return;
        }

        exibirErro(erro.message || 'Erro ao realizar login. Verifique suas credenciais.');
    } finally {
        botaoConfirmar.disabled = false;
        botaoConfirmar.textContent = 'Entrar';
    }
});
