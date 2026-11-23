import { inicializarSidebarGlobal } from '../componentes/sidebarGlobal.js';
import { inicializarSeletorGlobal } from '../componentes/seletorGlobalStartup.js';
import { usuarioEstaAutenticado, salvarStartupAtiva } from '../servicos/servicoAutenticacao.js';
import { criarNovaStartup, consultarDadosCnpj } from '../servicos/servicoStartups.js';

if (!usuarioEstaAutenticado()) {
    window.location.href = 'login.html';
}

const formulario = document.getElementById('formNovaStartupPainel');
const alertaStatus = document.getElementById('alertaStatusPainel');
const botaoConfirmar = document.getElementById('botao-confirmar');
const botaoCancelar = document.getElementById('botao-cancelar-cadastro');
const campoCnpj = document.getElementById('campo-cnpj');
const botaoBuscarCnpj = document.getElementById('botao-buscar-cnpj');

function exibirAlerta(mensagem, tipo = 'erro') {
    if (!alertaStatus) return;
    alertaStatus.textContent = mensagem;
    alertaStatus.className = `alerta-feedback ${tipo}`;
    alertaStatus.style.display = 'block';
    alertaStatus.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function formatarCnpj(valor) {
    const digitos = valor.replace(/\D/g, '').slice(0, 14);
    if (digitos.length <= 2) return digitos;
    if (digitos.length <= 5) return `${digitos.slice(0, 2)}.${digitos.slice(2)}`;
    if (digitos.length <= 8) return `${digitos.slice(0, 2)}.${digitos.slice(2, 5)}.${digitos.slice(5)}`;
    if (digitos.length <= 12) return `${digitos.slice(0, 2)}.${digitos.slice(2, 5)}.${digitos.slice(5, 8)}/${digitos.slice(8)}`;
    return `${digitos.slice(0, 2)}.${digitos.slice(2, 5)}.${digitos.slice(5, 8)}/${digitos.slice(8, 12)}-${digitos.slice(12, 14)}`;
}

campoCnpj?.addEventListener('input', (e) => {
    e.target.value = formatarCnpj(e.target.value);
    const digitos = e.target.value.replace(/\D/g, '');
    if (digitos.length === 14) {
        realizarBuscaCnpj(digitos);
    }
});

botaoBuscarCnpj?.addEventListener('click', () => {
    const digitos = campoCnpj.value.replace(/\D/g, '');
    if (digitos.length !== 14) {
        exibirAlerta('Digite um CNPJ válido com 14 dígitos antes de buscar.', 'erro');
        return;
    }
    realizarBuscaCnpj(digitos);
});

async function realizarBuscaCnpj(cnpj) {
    exibirAlerta('⏳ Consultando Receita Federal e bases públicas...', 'carregando');
    botaoBuscarCnpj.disabled = true;

    try {
        const resposta = await consultarDadosCnpj(cnpj);
        const dados = resposta.dados || resposta;

        if (dados.razao_social) document.getElementById('campo-razao-social').value = dados.razao_social;
        if (dados.nome_fantasia) document.getElementById('campo-nome-fantasia').value = dados.nome_fantasia;
        if (dados.data_abertura) document.getElementById('campo-data-abertura').value = dados.data_abertura;
        if (dados.porte) document.getElementById('campo-porte').value = dados.porte;
        if (dados.regime_tributario) document.getElementById('campo-regime-tributario').value = dados.regime_tributario;
        if (dados.nicho_atuacao) document.getElementById('campo-nicho-atuacao').value = dados.nicho_atuacao;

        const end = dados.endereco || {};
        if (end.cep) document.getElementById('campo-cep').value = end.cep;
        if (end.logradouro) document.getElementById('campo-logradouro').value = end.logradouro;
        if (end.numero) document.getElementById('campo-numero').value = end.numero;
        if (end.bairro) document.getElementById('campo-bairro').value = end.bairro;
        if (end.cidade) document.getElementById('campo-cidade').value = end.cidade;
        if (end.estado) document.getElementById('campo-estado').value = end.estado;

        exibirAlerta(`✅ Dados de "${dados.razao_social || 'Empresa'}" carregados com sucesso!`, 'sucesso');
        document.getElementById('campo-pitch')?.focus();
    } catch (erro) {
        exibirAlerta(`Não foi possível auto-preencher: ${erro.message}`, 'erro');
    } finally {
        botaoBuscarCnpj.disabled = false;
    }
}

botaoCancelar?.addEventListener('click', () => {
    window.location.href = 'RadarEditais.html';
});

formulario?.addEventListener('submit', async (evento) => {
    evento.preventDefault();

    const cnpj = campoCnpj.value.trim();
    const razaoSocial = document.getElementById('campo-razao-social').value.trim();

    if (!cnpj || !razaoSocial) {
        exibirAlerta('Os campos CNPJ e Razão Social são obrigatórios.', 'erro');
        return;
    }

    const dadosStartup = {
        cnpj,
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
    botaoConfirmar.textContent = 'Gravando Startup...';

    try {
        const resposta = await criarNovaStartup(dadosStartup);
        const startupCriada = resposta?.startup || resposta?.dados || resposta;
        if (startupCriada && startupCriada.id) {
            const nomeExibicao = startupCriada.nome_fantasia || startupCriada.razao_social || razaoSocial;
            salvarStartupAtiva(startupCriada.id, nomeExibicao);
        }
        window.location.href = 'RadarEditais.html';
    } catch (erro) {
        exibirAlerta(erro.message || 'Erro ao cadastrar startup.', 'erro');
        botaoConfirmar.disabled = false;
        botaoConfirmar.textContent = '+ Concluir Cadastro da Startup';
    }
});

document.addEventListener('DOMContentLoaded', () => {
    inicializarSidebarGlobal('');
    inicializarSeletorGlobal('container-seletor-global');
});

