import { redefinirSenha } from '../servicos/servicoAutenticacao.js';

document.addEventListener('DOMContentLoaded', () => {
    const parametrosUrl = new URLSearchParams(window.location.search);
    const token = parametrosUrl.get('token');
    const emailUrl = parametrosUrl.get('email');

    const formulario = document.getElementById('form-redefinir-senha');
    const campoEmail = document.getElementById('email');
    const campoNovaSenha = document.getElementById('nova-senha');
    const campoConfirmarSenha = document.getElementById('confirmar-senha');
    const botaoRedefinir = document.getElementById('botao-redefinir');
    const caixaMensagem = document.getElementById('mensagem-feedback');

    function exibirMensagem(texto, tipo = 'erro') {
        caixaMensagem.textContent = texto;
        caixaMensagem.classList.remove('is-success', 'is-error');
        caixaMensagem.classList.add(tipo === 'sucesso' ? 'is-success' : 'is-error');
        caixaMensagem.hidden = false;
    }

    if (!token || !emailUrl) {
        exibirMensagem('Link de recuperação inválido ou incompleto. Solicite um novo link na tela de Esqueci Minha Senha.');
        botaoRedefinir.disabled = true;
        return;
    }

    campoEmail.value = emailUrl;

    [campoNovaSenha, campoConfirmarSenha].forEach((campo) => {
        campo?.addEventListener('input', () => {
            if (!caixaMensagem.hidden) {
                caixaMensagem.hidden = true;
            }
        });
    });

    formulario.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        caixaMensagem.hidden = true;

        const novaSenha = campoNovaSenha.value;
        const confirmarSenha = campoConfirmarSenha.value;

        if (novaSenha.length < 8) {
            exibirMensagem('A nova senha deve ter no mínimo 8 caracteres.');
            return;
        }

        if (novaSenha !== confirmarSenha) {
            exibirMensagem('As senhas digitadas não coincidem.');
            return;
        }

        const textoOriginal = botaoRedefinir.textContent;
        botaoRedefinir.disabled = true;
        botaoRedefinir.textContent = 'Salvando nova senha...';

        try {
            const resposta = await redefinirSenha({
                email: emailUrl,
                token: token,
                password: novaSenha,
                password_confirmation: confirmarSenha
            });

            exibirMensagem(resposta.message || resposta.mensagem || 'Senha atualizada com sucesso!', 'sucesso');
            setTimeout(() => {
                window.location.href = 'login.html';
            }, 2000);
        } catch (erro) {
            exibirMensagem(erro.message || 'Erro ao redefinir a senha.');
            botaoRedefinir.disabled = false;
            botaoRedefinir.textContent = textoOriginal;
        }
    });
});

