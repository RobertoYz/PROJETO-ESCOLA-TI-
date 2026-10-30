import { verificarEmail, limparDadosPendencia, salvarUsuarioLogado } from '../servicos/servicoAutenticacao.js';

const formulario = document.getElementById('formulario-cadastro');
const campoNomeUsuario = document.getElementById('campo-nome-usuario');
const campoEmail = document.getElementById('campo-email');
const campoSenha = document.getElementById('campo-senha');
const divMensagemErro = document.getElementById('mensagem-erro');
const botaoConfirmar = document.getElementById('botao-confirmar');

function exibirErro(mensagem, exibirLinkLogin = false) {
    if (exibirLinkLogin) {
        divMensagemErro.innerHTML = `
            <div>${mensagem}</div>
            <div style="margin-top: 8px;">
                <a href="login.html" style="color: #a5b4fc; font-weight: 600; text-decoration: underline;">Ir para a tela de Login →</a>
            </div>
        `;
    } else {
        divMensagemErro.textContent = mensagem;
    }
    divMensagemErro.hidden = false;
}

function limparErro() {
    divMensagemErro.innerHTML = '';
    divMensagemErro.hidden = true;
}

// Preenche automaticamente Nome e E-mail caso o usuário tenha retornado da seleção de plano
try {
    const dadosSalvos = sessionStorage.getItem('dados_pre_cadastro');
    if (dadosSalvos) {
        const dados = JSON.parse(dadosSalvos);
        if (dados.name && campoNomeUsuario) {
            campoNomeUsuario.value = dados.name;
        }
        if (dados.email && campoEmail) {
            campoEmail.value = dados.email;
        }
    }
} catch (e) {
    // Ignora falha ao restaurar pré-cadastro
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
    botaoConfirmar.textContent = 'Verificando e-mail...';

    try {
        const resposta = await verificarEmail(email);

        if (resposta && resposta.disponivel) {
            limparDadosPendencia();

            const dadosPreCadastro = {
                name: nomeUsuario,
                email: email,
                password: senha
            };
            sessionStorage.setItem('dados_pre_cadastro', JSON.stringify(dadosPreCadastro));
            salvarUsuarioLogado({ name: nomeUsuario, email: email });

            window.location.href = 'selecionar-plano.html';
        } else {
            const mensagem = resposta.mensagem || resposta.message || 'Este e-mail já está cadastrado.';
            exibirErro(mensagem, true);
        }
    } catch (erro) {
        const jaCadastrado = erro.status === 422 || (erro.message && erro.message.toLowerCase().includes('cadastrado'));
        exibirErro(erro.message || 'Erro ao validar e-mail. Tente novamente.', jaCadastrado);
    } finally {
        botaoConfirmar.disabled = false;
        botaoConfirmar.textContent = 'Continuar para Escolha de Plano';
    }
});
