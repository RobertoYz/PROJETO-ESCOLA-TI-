import { cadastrarUsuario, salvarToken } from '../servicos/servicoAutenticacao.js';

const formulario = document.getElementById('formulario-cadastro');
const campoNomeUsuario = document.getElementById('campo-nome-usuario');
const campoEmail = document.getElementById('campo-email');
const campoSenha = document.getElementById('campo-senha');
const divMensagemErro = document.getElementById('mensagem-erro');
const botaoConfirmar = document.getElementById('botao-confirmar');

function exibirErro(mensagem) {
    divMensagemErro.textContent = mensagem;
    divMensagemErro.hidden = false;
}

function limparErro() {
    divMensagemErro.textContent = '';
    divMensagemErro.hidden = true;
}

formulario.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    limparErro();

    const nomeUsuario = campoNomeUsuario.value.trim();
    const email = campoEmail.value.trim();
    const senha = campoSenha.value;

    if (!nomeUsuario || !email || !senha) {
        exibirErro('Preencha todos os campos obrigatórios.');
        return;
    }

    if (senha.length < 8) {
        exibirErro('A senha deve ter no mínimo 8 caracteres.');
        return;
    }

    botaoConfirmar.disabled = true;

    try {
        const resposta = await cadastrarUsuario({
            name: nomeUsuario,
            email: email,
            password: senha
        });

        if (resposta.token) {
            salvarToken(resposta.token);
        }

        window.location.href = 'selecionar-plano.html';
    } catch (erro) {
        exibirErro(erro.message);
    } finally {
        botaoConfirmar.disabled = false;
    }
});
