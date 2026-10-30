import { requisicaoApi } from '../servicos/api.js';

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

    if (!token || !emailUrl) {
        exibirMensagem('Link de recuperação inválido ou incompleto. Solicite um novo link na tela de Esqueci Minha Senha.');
        botaoRedefinir.disabled = true;
        return;
    }

    campoEmail.value = emailUrl;

    formulario.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        caixaMensagem.style.display = 'none';

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
            const resposta = await requisicaoApi('/auth/redefinir-senha', {
                method: 'POST',
                body: JSON.stringify({
                    email: emailUrl,
                    token: token,
                    password: novaSenha,
                    password_confirmation: confirmarSenha
                })
            });

            exibirMensagem(resposta.mensagem || 'Senha atualizada com sucesso!', 'sucesso');
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

