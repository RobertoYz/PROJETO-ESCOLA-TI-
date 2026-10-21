import { usuarioEstaAutenticado } from '../servicos/servicoAutenticacao.js';
import { criarNovaStartup, consultarDadosCnpj } from '../servicos/servicoStartups.js';

if (!usuarioEstaAutenticado()) {
    window.location.href = 'login.html';
}

const formulario = document.getElementById('formulario-cadastro-startup');
const divMensagemErro = document.getElementById('mensagem-erro');
const botaoConfirmar = document.getElementById('botao-confirmar');
const campoCnpj = document.getElementById('campo-cnpj');
const botaoBuscarCnpj = document.getElementById('botao-buscar-cnpj');
const avisoStatusCnpj = document.getElementById('aviso-status-cnpj');

function exibirErro(mensagem) {
    divMensagemErro.textContent = mensagem;
    divMensagemErro.hidden = false;
}

function formatarCnpj(valor) {
    const digitos = valor.replace(/\D/g, '').slice(0, 14);
    if (digitos.length <= 2) return digitos;
    if (digitos.length <= 5) return `${digitos.slice(0, 2)}.${digitos.slice(2)}`;
    if (digitos.length <= 8) return `${digitos.slice(0, 2)}.${digitos.slice(2, 5)}.${digitos.slice(5)}`;
    if (digitos.length <= 12) return `${digitos.slice(0, 2)}.${digitos.slice(2, 5)}.${digitos.slice(5, 8)}/${digitos.slice(8)}`;
    return `${digitos.slice(0, 2)}.${digitos.slice(2, 5)}.${digitos.slice(5, 8)}/${digitos.slice(8, 12)}-${digitos.slice(12, 14)}`;
}

// Máscara dinâmica de CNPJ
campoCnpj.addEventListener('input', (e) => {
    e.target.value = formatarCnpj(e.target.value);
    const digitos = e.target.value.replace(/\D/g, '');
    if (digitos.length === 14) {
        realizarBuscaCnpj(digitos);
    }
});

// Ação do botão manual "Puxar Dados"
botaoBuscarCnpj.addEventListener('click', () => {
    const digitos = campoCnpj.value.replace(/\D/g, '');
    if (digitos.length !== 14) {
        exibirErro('Digite um CNPJ válido com 14 dígitos antes de buscar.');
        return;
    }
    realizarBuscaCnpj(digitos);
});

async function realizarBuscaCnpj(cnpj) {
    divMensagemErro.hidden = true;
    avisoStatusCnpj.className = 'aviso-status carregando';
    avisoStatusCnpj.textContent = '⏳ Consultando Receita Federal e bases públicas...';

    botaoBuscarCnpj.disabled = true;

    try {
        const resposta = await consultarDadosCnpj(cnpj);
        const dados = resposta.dados || resposta;

        // Preenchimento automático dos dados fiscais e cadastrais
        if (dados.razao_social) {
            document.getElementById('campo-razao-social').value = dados.razao_social;
        }
        if (dados.nome_fantasia) {
            document.getElementById('campo-nome-fantasia').value = dados.nome_fantasia;
        }
        if (dados.data_abertura) {
            document.getElementById('campo-data-abertura').value = dados.data_abertura;
        }
        if (dados.porte) {
            document.getElementById('campo-porte').value = dados.porte;
        }
        if (dados.regime_tributario) {
            document.getElementById('campo-regime-tributario').value = dados.regime_tributario;
        }
        if (dados.nicho_atuacao) {
            document.getElementById('campo-nicho-atuacao').value = dados.nicho_atuacao;
        }

        // Endereço
        const end = dados.endereco || {};
        if (end.cep) document.getElementById('campo-cep').value = end.cep;
        if (end.logradouro) document.getElementById('campo-logradouro').value = end.logradouro;
        if (end.numero) document.getElementById('campo-numero').value = end.numero;
        if (end.bairro) document.getElementById('campo-bairro').value = end.bairro;
        if (end.cidade) document.getElementById('campo-cidade').value = end.cidade;
        if (end.estado) document.getElementById('campo-estado').value = end.estado;

        avisoStatusCnpj.className = 'aviso-status sucesso';
        avisoStatusCnpj.textContent = `✅ Dados de "${dados.razao_social || 'Empresa'}" carregados com sucesso!`;

        // Foca no campo de Pitch / Nicho para o consultor preencher o essencial
        document.getElementById('campo-pitch').focus();
    } catch (erro) {
        avisoStatusCnpj.className = 'aviso-status';
        avisoStatusCnpj.style.display = 'none';
        exibirErro(`Não foi possível auto-preencher: ${erro.message}`);
    } finally {
        botaoBuscarCnpj.disabled = false;
    }
}

// Submissão do Formulário
formulario.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    divMensagemErro.hidden = true;

    const cnpj = campoCnpj.value.trim();
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
    botaoConfirmar.textContent = 'Gravando Startup...';

    try {
        await criarNovaStartup(dadosStartup);
        window.location.href = 'selecionar-startup.html';
    } catch (erro) {
        exibirErro(erro.message);
        botaoConfirmar.disabled = false;
        botaoConfirmar.textContent = 'Concluir Cadastro da Startup';
    }
});
