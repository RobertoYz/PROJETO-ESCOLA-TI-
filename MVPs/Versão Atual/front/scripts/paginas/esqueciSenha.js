import { solicitarRecuperacaoSenha } from '../servicos/servicoAutenticacao.js';

document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.getElementById('form-esqueci-senha');
    const campoEmail = document.getElementById('email');
    const botaoEnviar = document.getElementById('botao-enviar');
    const caixaMensagem = document.getElementById('mensagem-feedback');

    function exibirMensagem(texto, tipo = 'erro') {
        caixaMensagem.textContent = texto;
        caixaMensagem.classList.remove('is-success', 'is-error');
        caixaMensagem.classList.add(tipo === 'sucesso' ? 'is-success' : 'is-error');
        caixaMensagem.hidden = false;
    }

    campoEmail?.addEventListener('input', () => {
        if (!caixaMensagem.hidden) {
            caixaMensagem.hidden = true;
        }
    });

    formulario.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        caixaMensagem.hidden = true;

        const email = campoEmail.value.trim().toLowerCase();
        if (!email) {
            exibirMensagem('Por favor, digite o seu e-mail cadastrado.');
            return;
        }

        const regexEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!regexEmail.test(email)) {
            exibirMensagem('Informe um endereço de e-mail corporativo válido.');
            return;
        }

        const textoOriginal = botaoEnviar.textContent;
        botaoEnviar.disabled = true;
        botaoEnviar.textContent = 'Verificando e enviando...';

        try {
            const resposta = await solicitarRecuperacaoSenha(email);

            exibirMensagem(resposta.message || resposta.mensagem || 'Link enviado com sucesso! Verifique seu e-mail.', 'sucesso');
            campoEmail.value = '';
        } catch (erro) {
            exibirMensagem(erro.message || 'Não foi possível enviar o e-mail de recuperação.');
        } finally {
            botaoEnviar.disabled = false;
            botaoEnviar.textContent = textoOriginal;
        }
    });
});

