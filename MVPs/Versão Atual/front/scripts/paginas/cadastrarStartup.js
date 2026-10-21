import { usuarioEstaAutenticado } from '../servicos/servicoAutenticacao.js';
import { criarNovaStartup } from '../servicos/servicoStartups.js';

if (!usuarioEstaAutenticado()) {
    window.location.href = 'login.html';
}

const formulario = document.getElementById('formulario-cadastro-startup');
const divMensagemErro = document.getElementById('mensagem-erro');
const botaoConfirmar = document.getElementById('botao-confirmar');

function exibirErro(mensagem) {
    divMensagemErro.textContent = mensagem;
    divMensagemErro.hidden = false;
}

formulario.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    divMensagemErro.hidden = true;

    const cnpj = document.getElementById('campo-cnpj').value.trim();
    const razaoSocial = document.getElementById('campo-razao-social').value.trim();

    if (!cnpj || !razaoSocial) {
        exibirErro('Os campos CNPJ e Razão Social são obrigatórios.');
        return;
    }

    const dadosStartup = {
        cnpj: cnpj,
        razao_social: razaoSocial,
        nome_fantasia: document.getElementById('campo-nome-fantasia').value.trim() || null,
        data_abertura: document.getElementById('campo-data-abertura').value || null,
        cep: document.getElementById('campo-cep').value.trim() || null,
        logradouro: document.getElementById('campo-logradouro').value.trim() || null,
        numero: document.getElementById('campo-numero').value.trim() || null,
        bairro: document.getElementById('campo-bairro').value.trim() || null,
        cidade: document.getElementById('campo-cidade').value.trim() || null,
        estado: document.getElementById('campo-estado').value.trim().toUpperCase() || null,
        porte: document.getElementById('campo-porte').value.trim() || null,
        regime_tributario: document.getElementById('campo-regime-tributario').value.trim() || null,
        nicho_atuacao: document.getElementById('campo-nicho-atuacao').value.trim() || null,
        pitch: document.getElementById('campo-pitch').value.trim() || null
    };

    botaoConfirmar.disabled = true;

    try {
        await criarNovaStartup(dadosStartup);
        window.location.href = 'selecionar-startup.html';
    } catch (erro) {
        exibirErro(erro.message);
        botaoConfirmar.disabled = false;
    }
});
