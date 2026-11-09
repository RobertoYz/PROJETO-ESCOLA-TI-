import { realizarLogin, salvarToken, limparStartupAtiva } from '../servicos/servicoAutenticacao.js';

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

    try {
        const resposta = await realizarLogin({ email, password: senha });
        salvarToken(resposta.token);
        limparStartupAtiva();
        window.location.href = 'selecionar-startup.html';
    } catch (erro) {
        exibirErro(erro.message);
    } finally {
        botaoConfirmar.disabled = false;
    }
});
