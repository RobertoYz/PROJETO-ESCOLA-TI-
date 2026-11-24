import { requisicaoApi } from '../servicos/api.js';
import { limparDadosPendencia } from '../servicos/servicoAutenticacao.js';

const formulario = document.getElementById('formulario-cadastro');
const campoNomeAgencia = document.getElementById('campo-nome-agencia');
const campoNomeUsuario = document.getElementById('campo-nome-usuario');
const campoEmail = document.getElementById('campo-email');
const campoSenha = document.getElementById('campo-senha');
const divMensagemErro = document.getElementById('mensagem-erro');
const botaoConfirmar = document.getElementById('botao-confirmar');

const verificacao = document.getElementById('formulario-verificacao');
const campoCodigo = document.getElementById('codigo');
const reenviar = document.getElementById('reenviar');
const voltar = document.getElementById('alterar-email');
const confirmar = verificacao.querySelector('[type="submit"]');
const titulo = document.querySelector('.form-title');
const subtitulo = document.querySelector('.form-subtitle');
let cadastro;
let intervalo;
let disponivelEm = 0;
let ocupado = false;

function exibirErro(mensagem) {
    divMensagemErro.textContent = mensagem;
    divMensagemErro.hidden = false;
}

function limparErro() {
    divMensagemErro.innerHTML = '';
    divMensagemErro.hidden = true;
}

try {
    const dadosSalvos = sessionStorage.getItem('dados_pre_cadastro');
    if (dadosSalvos) {
        const dados = JSON.parse(dadosSalvos);
        sessionStorage.removeItem('dados_pre_cadastro');
        if (dados.nome_agencia && campoNomeAgencia) {
            campoNomeAgencia.value = dados.nome_agencia;
        }
        if (dados.name && campoNomeUsuario) {
            campoNomeUsuario.value = dados.name;
        }
        if (dados.email && campoEmail) {
            campoEmail.value = dados.email;
        }
    }
} catch {
    sessionStorage.removeItem('dados_pre_cadastro');
}

[campoNomeAgencia, campoNomeUsuario, campoEmail, campoSenha].forEach((campo) => {
    campo?.addEventListener('input', () => {
        if (!divMensagemErro.hidden) {
            limparErro();
        }
    });
});

formulario.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    limparErro();

    const nomeAgencia = campoNomeAgencia ? campoNomeAgencia.value.trim() : '';
    const nomeUsuario = campoNomeUsuario.value.trim();
    const email = campoEmail.value.trim().toLowerCase();
    const senha = campoSenha.value;

    if (!nomeAgencia || !nomeUsuario || !email || !senha) {
        exibirErro('Preencha todos os campos obrigatórios, incluindo o nome da consultoria/agência.');
        return;
    }

    const regexEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!regexEmail.test(email)) {
        exibirErro('Informe um endereço de e-mail válido.');
        return;
    }

    if (senha.length < 8) {
        exibirErro('A senha deve ter no mínimo 8 caracteres.');
        return;
    }

    if (ocupado) return;
    cadastro = { nome_agencia: nomeAgencia, name: nomeUsuario, email, password: senha };
    await enviarCodigo();
});

function atualizarBotoes() {
    const segundos = Math.max(0, Math.ceil((disponivelEm - Date.now()) / 1000));
    reenviar.textContent = segundos ? `Reenviar código em ${segundos}s` : 'Reenviar código';
    reenviar.disabled = ocupado || segundos > 0;
    confirmar.disabled = ocupado;
    voltar.disabled = ocupado;
    botaoConfirmar.disabled = ocupado;
    if (!segundos) clearInterval(intervalo);
}

function mostrarVerificacao(segundos) {
    clearInterval(intervalo);
    disponivelEm = Date.now() + segundos * 1000;
    formulario.hidden = true;
    verificacao.hidden = false;
    titulo.textContent = 'Confirme seu e-mail';
    subtitulo.textContent = 'Informe o código para escolher seu plano.';
    document.getElementById('destino-codigo').textContent = `Enviado para ${cadastro.email}. Válido por 15 minutos.`;
    campoCodigo.value = '';
    campoCodigo.focus();
    intervalo = setInterval(atualizarBotoes, 250);
}

async function enviarCodigo() {
    ocupado = true;
    atualizarBotoes();
    limparErro();
    try {
        const resposta = await requisicaoApi('/auth/enviar-codigo-verificacao', {
            method: 'POST', body: JSON.stringify({ email: cadastro.email, nome: cadastro.name })
        });
        mostrarVerificacao(resposta.reenvio_em_segundos);
    } catch (erro) {
        if (erro.dados?.segundos_restantes) mostrarVerificacao(erro.dados.segundos_restantes);
        exibirErro(erro.message || 'Não foi possível enviar o código.');
    } finally {
        ocupado = false;
        atualizarBotoes();
    }
}

campoCodigo.addEventListener('input', () => {
    campoCodigo.value = campoCodigo.value.replace(/\D/g, '').slice(0, 6);
    limparErro();
});

reenviar.addEventListener('click', enviarCodigo);
voltar.addEventListener('click', () => {
    clearInterval(intervalo);
    verificacao.hidden = true;
    formulario.hidden = false;
    titulo.textContent = 'Criar conta de consultoria';
    subtitulo.textContent = 'Cadastre seus dados para acessar a inteligência de editais';
    cadastro = null;
    limparErro();
    campoEmail.focus();
});

verificacao.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    if (ocupado) return;
    ocupado = true;
    atualizarBotoes();
    limparErro();
    try {
        const resposta = await requisicaoApi('/auth/verificar-codigo-email', {
            method: 'POST', body: JSON.stringify({ email: cadastro.email, codigo: campoCodigo.value })
        });
        limparDadosPendencia();
        sessionStorage.setItem('dados_pre_cadastro', JSON.stringify({
            ...cadastro, email_verificado: cadastro.email, verification_token: resposta.verification_token
        }));
        window.location.replace('selecionar-plano.html');
    } catch (erro) {
        const restantes = erro.dados?.tentativas_restantes;
        let mensagem = erro.message || 'Não foi possível confirmar o código.';
        if (restantes === 0) mensagem += ' Solicite outro código.';
        else if (restantes !== undefined) mensagem += ` Tentativas restantes: ${restantes}.`;
        exibirErro(mensagem);
    } finally {
        ocupado = false;
        atualizarBotoes();
    }
});

if (new URLSearchParams(location.search).has('revalidar')) {
    exibirErro('Sua confirmação de e-mail perdeu a validade. Informe a senha e solicite um novo código.');
}
