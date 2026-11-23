import { validarTokenConvitePublico, aceitarConvitePublico } from '../servicos/servicoEquipe.js';

document.addEventListener('DOMContentLoaded', async () => {
    const params = new URLSearchParams(window.location.search);
    const token = (params.get('token') || '').trim();

    const subtituloAgencia = document.getElementById('subtituloConviteAgencia');
    const boxResumoConvidado = document.getElementById('boxResumoConvidado');
    const lblNomeAgencia = document.getElementById('lblNomeAgenciaConvite');
    const lblNomeConvidado = document.getElementById('lblNomeConvidado');
    const lblEmailMascarado = document.getElementById('lblEmailMascarado');
    const caixaMensagem = document.getElementById('mensagem-feedback-convite');
    const formulario = document.getElementById('form-aceitar-convite');
    const campoPin = document.getElementById('codigo-pin');
    const campoSenha = document.getElementById('nova-senha');
    const campoConfirmar = document.getElementById('confirmar-senha');
    const botaoAtivar = document.getElementById('botao-ativar-convite');
    const boxSucessoAtivacao = document.getElementById('boxSucessoAtivacao');

    function exibirMensagem(texto, tipo = 'erro') {
        if (!caixaMensagem) return;
        caixaMensagem.textContent = texto;
        caixaMensagem.classList.remove('alert-box--error', 'alert-box--success');
        caixaMensagem.classList.add(tipo === 'sucesso' ? 'alert-box--success' : 'alert-box--error');
        caixaMensagem.hidden = false;
    }

    function definirFormularioDesabilitado(desabilitado) {
        if (campoPin) campoPin.disabled = desabilitado;
        if (campoSenha) campoSenha.disabled = desabilitado;
        if (campoConfirmar) campoConfirmar.disabled = desabilitado;
        if (botaoAtivar) botaoAtivar.disabled = desabilitado;
    }

    campoPin?.addEventListener('input', (e) => {
        e.target.value = e.target.value.replace(/\D/g, '').slice(0, 6);
    });

    if (!token) {
        if (subtituloAgencia) subtituloAgencia.textContent = 'Link de convite ausente ou incompleto.';
        exibirMensagem('O link de convite é inválido. Solicite um novo link ao administrador da consultoria.', 'erro');
        definirFormularioDesabilitado(true);
        return;
    }

    try {
        const dadosConvite = await validarTokenConvitePublico(token);
        if (subtituloAgencia) {
            subtituloAgencia.textContent = `Você foi convidado para a consultoria ${dadosConvite.agencia_nome}`;
        }
        if (lblNomeAgencia) lblNomeAgencia.textContent = dadosConvite.agencia_nome || 'Consultoria';
        if (lblNomeConvidado) lblNomeConvidado.textContent = dadosConvite.convidado_nome || 'Consultor';
        if (lblEmailMascarado) lblEmailMascarado.textContent = dadosConvite.email_mascarado || '';
        if (boxResumoConvidado) boxResumoConvidado.style.display = 'block';
    } catch (erro) {
        if (subtituloAgencia) subtituloAgencia.textContent = 'Convite indisponível';
        exibirMensagem(erro.message || 'Este convite é inválido ou expirou.', 'erro');
        definirFormularioDesabilitado(true);
        return;
    }

    formulario?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (caixaMensagem) caixaMensagem.hidden = true;

        const codigo = (campoPin?.value || '').replace(/\D/g, '');
        const password = campoSenha?.value || '';
        const password_confirmation = campoConfirmar?.value || '';

        if (codigo.length !== 6) {
            exibirMensagem('Informe o código de verificação numérico de 6 dígitos fornecido pelo titular.', 'erro');
            campoPin?.focus();
            return;
        }

        if (password.length < 8) {
            exibirMensagem('A nova senha deve conter no mínimo 8 caracteres.', 'erro');
            campoSenha?.focus();
            return;
        }

        if (password !== password_confirmation) {
            exibirMensagem('A confirmação da senha não coincide.', 'erro');
            campoConfirmar?.focus();
            return;
        }

        const textoOriginal = botaoAtivar.textContent;
        botaoAtivar.disabled = true;
        botaoAtivar.textContent = 'Ativando conta...';

        try {
            const resposta = await aceitarConvitePublico({
                token,
                codigo,
                password,
                password_confirmation
            });

            exibirMensagem(resposta.message || 'Conta ativada com sucesso!', 'sucesso');
            if (formulario) formulario.style.display = 'none';
            if (boxSucessoAtivacao) boxSucessoAtivacao.style.display = 'flex';
        } catch (erro) {
            exibirMensagem(erro.message || 'Não foi possível ativar o convite. Verifique o código PIN.', 'erro');
            botaoAtivar.disabled = false;
            botaoAtivar.textContent = textoOriginal;
        }
    });
});
