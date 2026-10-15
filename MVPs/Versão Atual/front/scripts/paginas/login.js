import { realizarLogin, salvarToken } from '../servicos/servicoAutenticacao.js';

document.addEventListener('DOMContentLoaded', () => {
    const formularioLogin = document.getElementById('formulario-login');
    const campoEmail = document.getElementById('campo-email');
    const campoSenha = document.getElementById('campo-senha');
    const botaoConfirmar = document.getElementById('botao-confirmar');
    const divMensagemErro = document.getElementById('mensagem-erro');

    function exibirErro(mensagem) {
        divMensagemErro.textContent = mensagem;
        divMensagemErro.hidden = false;
    }

    function limparErro() {
        divMensagemErro.textContent = '';
        divMensagemErro.hidden = true;
    }

    function definirCarregando(carregando) {
        botaoConfirmar.disabled = carregando;
        botaoConfirmar.textContent = carregando ? 'Entrando...' : 'Entrar';
    }

    formularioLogin.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        limparErro();

        const valorEmail = campoEmail.value.trim();
        const valorSenha = campoSenha.value;

        if (!valorEmail || !valorSenha) {
            exibirErro('Por favor, informe seu e-mail e senha de acesso.');
            return;
        }

        definirCarregando(true);

        try {
            const respostaApi = await realizarLogin(valorEmail, valorSenha);

            if (respostaApi.token) {
                salvarToken(respostaApi.token);
            }

            if (respostaApi.dados?.usuario) {
                localStorage.setItem('usuario_autenticado', JSON.stringify(respostaApi.dados.usuario));
            }

            window.location.href = 'inicio.html';
        } catch (erro) {
            exibirErro(erro.message || 'Falha ao autenticar. Verifique seus dados de acesso.');
            definirCarregando(false);
        }
    });
});
