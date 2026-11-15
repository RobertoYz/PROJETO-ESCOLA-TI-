import { requisicaoApi } from '../servicos/api.js';

document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.getElementById('form-esqueci-senha');
    const campoEmail = document.getElementById('email');
    const botaoEnviar = document.getElementById('botao-enviar');
    const caixaMensagem = document.getElementById('mensagem-feedback');

    const TEMPO_COOLDOWN_SEGUNDOS = 30;
    const textoOriginalBotao = botaoEnviar.textContent.trim();
    let timerCooldown = null;

    function exibirMensagem(texto, tipo = 'erro') {
        caixaMensagem.textContent = texto;
        caixaMensagem.classList.remove('alert-box--error', 'alert-box--success');
        caixaMensagem.classList.add(tipo === 'sucesso' ? 'alert-box--success' : 'alert-box--error');
        caixaMensagem.hidden = false;
    }

    function ocultarMensagem() {
        caixaMensagem.hidden = true;
    }

    function emailEhValido(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function iniciarCooldown(segundos = TEMPO_COOLDOWN_SEGUNDOS) {
        if (timerCooldown) {
            clearInterval(timerCooldown);
        }

        let restante = segundos;
        botaoEnviar.disabled = true;
        botaoEnviar.textContent = `Reenviar em ${restante}s`;

        timerCooldown = setInterval(() => {
            restante -= 1;
            if (restante <= 0) {
                clearInterval(timerCooldown);
                timerCooldown = null;
                botaoEnviar.disabled = false;
                botaoEnviar.textContent = textoOriginalBotao;
                return;
            }
            botaoEnviar.textContent = `Reenviar em ${restante}s`;
        }, 1000);
    }

    campoEmail.addEventListener('input', () => {
        if (!caixaMensagem.hidden && caixaMensagem.classList.contains('alert-box--error')) {
            ocultarMensagem();
        }
    });

    formulario.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        if (timerCooldown) {
            return;
        }

        ocultarMensagem();

        const email = campoEmail.value.trim().toLowerCase();
        if (!email) {
            exibirMensagem('Por favor, digite o seu e-mail cadastrado.');
            campoEmail.focus();
            return;
        }

        if (!emailEhValido(email)) {
            exibirMensagem('Informe um endereço de e-mail válido.');
            campoEmail.focus();
            return;
        }

        botaoEnviar.disabled = true;
        botaoEnviar.textContent = 'Verificando e enviando...';

        try {
            const resposta = await requisicaoApi('/auth/esqueci-senha', {
                method: 'POST',
                body: JSON.stringify({ email })
            });

            exibirMensagem(resposta.message || 'Link enviado com sucesso! Verifique seu e-mail.', 'sucesso');
            campoEmail.value = '';
            iniciarCooldown();
        } catch (erro) {
            exibirMensagem(erro.message || 'Não foi possível enviar o e-mail de recuperação.');
            botaoEnviar.disabled = false;
            botaoEnviar.textContent = textoOriginalBotao;
        }
    });
});
