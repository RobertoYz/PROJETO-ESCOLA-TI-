import { requisicaoApi } from '../servicos/api.js';

document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.getElementById('form-esqueci-senha');
    const campoEmail = document.getElementById('email');
    const botaoEnviar = document.getElementById('botao-enviar');
    const caixaMensagem = document.getElementById('mensagem-feedback');

    function exibirMensagem(texto, tipo = 'erro') {
        caixaMensagem.style.display = 'block';
        caixaMensagem.textContent = texto;
        if (tipo === 'sucesso') {
            caixaMensagem.style.backgroundColor = 'rgba(16, 185, 129, 0.15)';
            caixaMensagem.style.borderColor = '#10b981';
            caixaMensagem.style.color = '#6ee7b7';
        } else {
            caixaMensagem.style.backgroundColor = 'rgba(239, 68, 68, 0.15)';
            caixaMensagem.style.borderColor = '#ef4444';
            caixaMensagem.style.color = '#fca5a5';
        }
    }

    formulario.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        caixaMensagem.style.display = 'none';

        const email = campoEmail.value.trim();
        if (!email) {
            exibirMensagem('Por favor, digite o seu e-mail cadastrado.');
            return;
        }

        const textoOriginal = botaoEnviar.textContent;
        botaoEnviar.disabled = true;
        botaoEnviar.textContent = 'Verificando e enviando...';

        try {
            const resposta = await requisicaoApi('/auth/esqueci-senha', {
                method: 'POST',
                body: JSON.stringify({ email })
            });

            exibirMensagem(resposta.mensagem || 'Link enviado com sucesso! Verifique seu Gmail.', 'sucesso');
            campoEmail.value = '';
        } catch (erro) {
            exibirMensagem(erro.message || 'Não foi possível enviar o e-mail de recuperação.');
        } finally {
            botaoEnviar.disabled = false;
            botaoEnviar.textContent = textoOriginal;
        }
    });
});

