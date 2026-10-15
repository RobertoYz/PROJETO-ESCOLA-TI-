import { cadastrarUsuario, salvarToken } from '../servicos/servicoAutenticacao.js';

document.addEventListener('DOMContentLoaded', () => {
    const formularioCadastro = document.getElementById('formulario-cadastro');
    const campoNomeAgencia = document.getElementById('campo-nome-agencia');
    const campoNomeUsuario = document.getElementById('campo-nome-usuario');
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
        botaoConfirmar.textContent = carregando ? 'Processando cadastro...' : 'Cadastrar';
    }

    formularioCadastro.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        limparErro();

        const valorNomeAgencia = campoNomeAgencia.value.trim();
        const valorNomeUsuario = campoNomeUsuario.value.trim();
        const valorEmail = campoEmail.value.trim();
        const valorSenha = campoSenha.value;

        // Validação front-end de campos obrigatórios
        if (!valorNomeAgencia || !valorNomeUsuario || !valorEmail || !valorSenha) {
            exibirErro('Todos os campos são obrigatórios. Por favor, preencha todos os dados.');
            return;
        }

        // Validação de formato de e-mail
        const padraoEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!padraoEmail.test(valorEmail)) {
            exibirErro('Por favor, informe um endereço de e-mail válido.');
            return;
        }

        // Validação de tamanho mínimo da senha
        if (valorSenha.length < 8) {
            exibirErro('A senha deve conter no mínimo 8 caracteres.');
            return;
        }

        definirCarregando(true);

        try {
            const respostaApi = await cadastrarUsuario({
                nomeAgencia: valorNomeAgencia,
                nomeUsuario: valorNomeUsuario,
                email: valorEmail,
                senha: valorSenha
            });

            // Se a API retornar token, salva na sessão
            const tokenRetornado = respostaApi.dados?.token || respostaApi.token;
            if (tokenRetornado) {
                salvarToken(tokenRetornado);
            }

            // Identifica se há URL de checkout para pagamento
            const urlCheckout = respostaApi.checkout_url ||
                                respostaApi.dados?.checkout_url ||
                                respostaApi.dados?.link_pagamento ||
                                respostaApi.link_pagamento;

            if (urlCheckout) {
                window.location.href = urlCheckout;
            } else {
                window.location.href = 'login.html';
            }
        } catch (erro) {
            exibirErro(erro.message || 'Falha ao realizar o cadastro. Tente novamente mais tarde.');
            definirCarregando(false);
        }
    });
});
