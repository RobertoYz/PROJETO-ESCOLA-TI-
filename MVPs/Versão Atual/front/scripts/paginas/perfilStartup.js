import { inicializarSidebarGlobal } from '../componentes/sidebarGlobal.js';
import { inicializarSeletorGlobal } from '../componentes/seletorGlobalStartup.js';
import { obterStartupAtiva } from '../servicos/servicoAutenticacao.js';
import { obterStartupPorId, atualizarStartup, consultarDadosCnpj } from '../servicos/servicoStartups.js';

let startupIdAtual = null;

const form = document.getElementById('formPerfilStartup');
const alertaFeedback = document.getElementById('alertaFeedback');
const btnSalvar = document.getElementById('btnSalvarPerfil');
const btnSyncReceita = document.getElementById('btnSincronizarReceita');

function exibirAlerta(msg, tipo = 'sucesso') {
    alertaFeedback.textContent = msg;
    alertaFeedback.className = `alerta-feedback ${tipo}`;
    alertaFeedback.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(() => {
        alertaFeedback.style.display = 'none';
    }, 6000);
}

async function carregarDadosStartup(id) {
    if (!id) return;
    startupIdAtual = id;

    try {
        const startup = await obterStartupPorId(id);

        document.getElementById('campoCnpj').value = startup.cnpj || '';
        document.getElementById('campoRazaoSocial').value = startup.razao_social || '';
        document.getElementById('campoNomeFantasia').value = startup.nome_fantasia || '';
        document.getElementById('campoDataAbertura').value = startup.data_abertura ? startup.data_abertura.substring(0, 10) : '';
        document.getElementById('campoPorte').value = startup.porte || '';
        document.getElementById('campoRegimeTributario').value = startup.regime_tributario || '';

        document.getElementById('campoCep').value = startup.cep || '';
        document.getElementById('campoLogradouro').value = startup.logradouro || '';
        document.getElementById('campoNumero').value = startup.numero || '';
        document.getElementById('campoBairro').value = startup.bairro || '';
        document.getElementById('campoCidade').value = startup.cidade || '';
        document.getElementById('campoEstado').value = startup.estado || '';
        document.getElementById('campoEmail').value = startup.email || '';
        document.getElementById('campoTelefone').value = startup.telefone || '';

        document.getElementById('campoNichoAtuacao').value = startup.nicho_atuacao || '';
        document.getElementById('campoPitch').value = startup.pitch || '';

    } catch (err) {
        console.error('Erro ao carregar dados da startup:', err);
        exibirAlerta(`Não foi possível carregar os dados da startup: ${err.message}`, 'erro');
    }
}

// Sincronizar dados frescos diretamente da Receita Federal
btnSyncReceita.addEventListener('click', async () => {
    const cnpj = document.getElementById('campoCnpj').value;
    if (!cnpj) {
        exibirAlerta('CNPJ inválido para consulta.', 'erro');
        return;
    }

    const textoOriginal = btnSyncReceita.textContent;
    btnSyncReceita.disabled = true;
    btnSyncReceita.textContent = '⏳ Consultando Receita...';

    try {
        const resposta = await consultarDadosCnpj(cnpj);
        const dados = resposta.dados || resposta;

        if (dados.razao_social) document.getElementById('campoRazaoSocial').value = dados.razao_social;
        if (dados.nome_fantasia) document.getElementById('campoNomeFantasia').value = dados.nome_fantasia;
        if (dados.data_abertura) document.getElementById('campoDataAbertura').value = dados.data_abertura;
        if (dados.porte) document.getElementById('campoPorte').value = dados.porte;
        if (dados.regime_tributario) document.getElementById('campoRegimeTributario').value = dados.regime_tributario;

        const end = dados.endereco || {};
        if (end.cep) document.getElementById('campoCep').value = end.cep;
        if (end.logradouro) document.getElementById('campoLogradouro').value = end.logradouro;
        if (end.numero) document.getElementById('campoNumero').value = end.numero;
        if (end.bairro) document.getElementById('campoBairro').value = end.bairro;
        if (end.cidade) document.getElementById('campoCidade').value = end.cidade;
        if (end.estado) document.getElementById('campoEstado').value = end.estado;

        exibirAlerta('✅ Dados atualizados com as bases oficiais da Receita Federal! Clique em Salvar para consolidar.', 'sucesso');
    } catch (err) {
        exibirAlerta(`Falha ao sincronizar com a Receita Federal: ${err.message}`, 'erro');
    } finally {
        btnSyncReceita.disabled = false;
        btnSyncReceita.textContent = textoOriginal;
    }
});

// Salvar alterações
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!startupIdAtual) return;

    const payload = {
        razao_social: document.getElementById('campoRazaoSocial').value.trim(),
        nome_fantasia: document.getElementById('campoNomeFantasia').value.trim() || null,
        data_abertura: document.getElementById('campoDataAbertura').value || null,
        porte: document.getElementById('campoPorte').value.trim() || null,
        regime_tributario: document.getElementById('campoRegimeTributario').value.trim() || null,
        cep: document.getElementById('campoCep').value.trim() || null,
        logradouro: document.getElementById('campoLogradouro').value.trim() || null,
        numero: document.getElementById('campoNumero').value.trim() || null,
        bairro: document.getElementById('campoBairro').value.trim() || null,
        cidade: document.getElementById('campoCidade').value.trim() || null,
        estado: document.getElementById('campoEstado').value.trim().toUpperCase() || null,
        email: document.getElementById('campoEmail').value.trim() || null,
        telefone: document.getElementById('campoTelefone').value.trim() || null,
        nicho_atuacao: document.getElementById('campoNichoAtuacao').value.trim() || null,
        pitch: document.getElementById('campoPitch').value.trim() || null
    };

    btnSalvar.disabled = true;
    btnSalvar.textContent = 'Salvando alterações...';

    try {
        const resp = await atualizarStartup(startupIdAtual, payload);
        exibirAlerta('✅ Perfil da startup e diferenciais tecnológicos atualizados com sucesso!', 'sucesso');
        
        // Atualiza nome na sessão local se mudou
        const novoNome = payload.nome_fantasia || payload.razao_social;
        localStorage.setItem('startup_ativa_nome', novoNome);

    } catch (err) {
        exibirAlerta(`Erro ao salvar perfil: ${err.message}`, 'erro');
    } finally {
        btnSalvar.disabled = false;
        btnSalvar.textContent = '💾 Salvar Alterações da Startup';
    }
});

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    inicializarSidebarGlobal('perfil-startup');
    
    inicializarSeletorGlobal('container-seletor-global', {
        onTrocarStartup: (id, nome) => {
            carregarDadosStartup(id);
        }
    });

    const ativa = obterStartupAtiva();
    if (ativa && ativa.id) {
        carregarDadosStartup(ativa.id);
    }
});
