import { verificarEmail, limparDadosPendencia, salvarUsuarioLogado, salvarEmailPendencia } from '../servicos/servicoAutenticacao.js';

const formulario = document.getElementById('formulario-cadastro');
const campoNomeUsuario = document.getElementById('campo-nome-usuario');
const campoEmail = document.getElementById('campo-email');
const campoSenha = document.getElementById('campo-senha');
const divMensagemErro = document.getElementById('mensagem-erro');
const botaoConfirmar = document.getElementById('botao-confirmar');

function exibirErro(mensagem, opcoes = {}) {
    const configuracao = typeof opcoes === 'object' && opcoes !== null
        ? opcoes
        : { exibirLinkLogin: Boolean(opcoes) };

    const { exibirLinkLogin = false, pagamentoPendente = false, email = '' } = configuracao;

    if (pagamentoPendente) {
        if (email) {
            salvarEmailPendencia(email);
        }
        divMensagemErro.innerHTML = `
            <div>${mensagem}</div>
            <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 8px;">
                <a href="pagamento-pendente.html" class="alert-link" style="font-weight: 700; color: #818cf8; text-decoration: underline;">
                    Regularizar ou consultar pagamento pendente →
                </a>
                <a href="login.html" class="alert-link" style="font-size: 0.85rem; color: var(--text-muted);">
                    Ou entrar com sua senha na tela de Login →
                </a>
            </div>
        `;
    } else if (exibirLinkLogin) {
        divMensagemErro.innerHTML = `
            <div>${mensagem}</div>
            <div style="margin-top: 8px;">
                <a href="login.html" class="alert-link">Ir para a tela de Login →</a>
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

// Limpa erro ao digitar novamente
[campoNomeUsuario, campoEmail, campoSenha].forEach((campo) => {
    campo?.addEventListener('input', () => {
        if (!divMensagemErro.hidden) {
            limparErro();
        }
    });
});

formulario.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    limparErro();

    const nomeUsuario = campoNomeUsuario.value.trim();
    const email = campoEmail.value.trim().toLowerCase();
    const senha = campoSenha.value;

    if (!nomeUsuario || !email || !senha) {
        exibirErro('Preencha todos os campos obrigatórios.');
        return;
    }

    const regexEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!regexEmail.test(email)) {
        exibirErro('Informe um endereço de e-mail corporativo válido.');
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
            const pendente = Boolean(resposta.pagamento_pendente);
            exibirErro(mensagem, { exibirLinkLogin: true, pagamentoPendente: pendente, email });
        }
    } catch (erro) {
        const dadosErro = erro.dados || {};
        const pendente = Boolean(dadosErro.pagamento_pendente) || (erro.message && erro.message.toLowerCase().includes('pagamento pendente'));
        const jaCadastrado = erro.status === 422 || (erro.message && erro.message.toLowerCase().includes('cadastrado'));
        
        exibirErro(erro.message || 'Erro ao validar e-mail. Tente novamente.', {
            exibirLinkLogin: jaCadastrado,
            pagamentoPendente: pendente,
            email
        });
    } finally {
        botaoConfirmar.disabled = false;
        botaoConfirmar.textContent = 'Continuar para Escolha de Plano';
    }
});
