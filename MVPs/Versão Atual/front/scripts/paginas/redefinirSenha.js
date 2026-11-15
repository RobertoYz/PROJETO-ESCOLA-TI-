import { requisicaoApi } from '../servicos/api.js';

const ICONE_OLHO_ABERTO = `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
        <circle cx="12" cy="12" r="3"></circle>
    </svg>
`;

const ICONE_OLHO_FECHADO = `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
        <line x1="1" y1="1" x2="23" y2="23"></line>
    </svg>
`;

document.addEventListener('DOMContentLoaded', () => {
    const parametrosUrl = new URLSearchParams(window.location.search);
    const token = (parametrosUrl.get('token') || '').trim();
    const emailUrl = (parametrosUrl.get('email') || '').trim();

    const formulario = document.getElementById('form-redefinir-senha');
    const campoEmail = document.getElementById('email');
    const campoNovaSenha = document.getElementById('nova-senha');
    const campoConfirmarSenha = document.getElementById('confirmar-senha');
    const botaoRedefinir = document.getElementById('botao-redefinir');
    const caixaMensagem = document.getElementById('mensagem-feedback');
    const acoesLinkInvalido = document.getElementById('acoes-link-invalido');
    const reqTamanho = document.getElementById('req-tamanho');
    const reqCoincidencia = document.getElementById('req-coincidencia');
    const botoesVisibilidade = document.querySelectorAll('.btn-toggle-password');

    function exibirMensagem(texto, tipo = 'erro') {
        caixaMensagem.textContent = texto;
        caixaMensagem.classList.remove('alert-box--error', 'alert-box--success');
        caixaMensagem.classList.add(tipo === 'sucesso' ? 'alert-box--success' : 'alert-box--error');
        caixaMensagem.hidden = false;
    }

    function ocultarMensagem() {
        caixaMensagem.hidden = true;
    }

    function definirEstadoCampos(desabilitado) {
        campoNovaSenha.disabled = desabilitado;
        campoConfirmarSenha.disabled = desabilitado;
        botaoRedefinir.disabled = desabilitado;
        botoesVisibilidade.forEach((botao) => {
            botao.disabled = desabilitado;
        });
    }

    function atualizarRequisitos() {
        const novaSenha = campoNovaSenha.value;
        const confirmarSenha = campoConfirmarSenha.value;

        reqTamanho.classList.remove('is-valid', 'is-invalid');
        if (novaSenha.length >= 8) {
            reqTamanho.classList.add('is-valid');
        } else if (novaSenha.length > 0) {
            reqTamanho.classList.add('is-invalid');
        }

        reqCoincidencia.classList.remove('is-valid', 'is-invalid');
        if (confirmarSenha.length > 0) {
            if (novaSenha === confirmarSenha) {
                reqCoincidencia.classList.add('is-valid');
            } else {
                reqCoincidencia.classList.add('is-invalid');
            }
        }
    }

    function aoAlterarSenha() {
        if (!caixaMensagem.hidden && caixaMensagem.classList.contains('alert-box--error')) {
            ocultarMensagem();
        }
        atualizarRequisitos();
    }

    botoesVisibilidade.forEach((botao) => {
        botao.addEventListener('click', () => {
            const idAlvo = botao.getAttribute('data-target');
            const inputAlvo = document.getElementById(idAlvo);
            if (!inputAlvo || inputAlvo.disabled) return;

            const mostrando = inputAlvo.type === 'text';
            inputAlvo.type = mostrando ? 'password' : 'text';
            botao.setAttribute('aria-pressed', String(!mostrando));
            botao.setAttribute('aria-label', mostrando ? 'Mostrar senha' : 'Ocultar senha');
            botao.innerHTML = mostrando ? ICONE_OLHO_ABERTO : ICONE_OLHO_FECHADO;
        });
    });

    if (!token || !emailUrl) {
        exibirMensagem('Link de recuperação inválido ou incompleto. Solicite um novo link na tela de Esqueci Minha Senha.');
        definirEstadoCampos(true);
        if (acoesLinkInvalido) {
            acoesLinkInvalido.hidden = false;
        }
        return;
    }

    campoEmail.value = emailUrl;
    campoNovaSenha.addEventListener('input', aoAlterarSenha);
    campoConfirmarSenha.addEventListener('input', aoAlterarSenha);

    formulario.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        ocultarMensagem();

        const novaSenha = campoNovaSenha.value;
        const confirmarSenha = campoConfirmarSenha.value;

        if (novaSenha.length < 8) {
            exibirMensagem('A nova senha deve ter no mínimo 8 caracteres.');
            campoNovaSenha.focus();
            return;
        }

        if (novaSenha !== confirmarSenha) {
            exibirMensagem('As senhas digitadas não coincidem.');
            campoConfirmarSenha.focus();
            return;
        }

        const textoOriginal = botaoRedefinir.textContent.trim();
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

            definirEstadoCampos(true);
            botaoRedefinir.textContent = 'Senha atualizada!';
            exibirMensagem(resposta.message || 'Senha atualizada com sucesso!', 'sucesso');
            setTimeout(() => {
                window.location.href = 'login.html';
            }, 2000);
        } catch (erro) {
            exibirMensagem(erro.message || 'Erro ao redefinir a senha.');
            if (erro.status === 400 && acoesLinkInvalido) {
                acoesLinkInvalido.hidden = false;
            }
            botaoRedefinir.disabled = false;
            botaoRedefinir.textContent = textoOriginal;
        }
    });
});
