import { inicializarSidebarGlobal } from '../componentes/sidebarGlobal.js';
import { inicializarSeletorGlobal } from '../componentes/seletorGlobalStartup.js';
import { usuarioEstaAutenticado, obterStartupAtiva, salvarStartupAtiva } from '../servicos/servicoAutenticacao.js';
import { listarStartupsDaConsultoria, obterStartupPorId, atualizarStartup, consultarDadosCnpj } from '../servicos/servicoStartups.js';

if (!usuarioEstaAutenticado()) {
    window.location.href = 'login.html';
}

const secaoLista = document.getElementById('secaoListaStartups');
const secaoDetalhes = document.getElementById('secaoDetalhesStartup');
const containerLista = document.getElementById('containerListaStartups');
const campoBusca = document.getElementById('campoBuscaMinhasStartups');
const alertaLista = document.getElementById('alertaListaStartups');
const alertaEdicao = document.getElementById('alertaFeedbackEdicao');

const btnVoltarLista = document.getElementById('btnVoltarListaStartups');
const formEdicao = document.getElementById('formEditarStartupMesmaPagina');
const btnSalvar = document.getElementById('btnSalvarEdicaoMesmaPagina');
const btnCancelar = document.getElementById('btnCancelarEdicaoMesmaPagina');
const btnSyncReceita = document.getElementById('btnSincronizarReceita');
const tituloStartupSelecionada = document.getElementById('tituloStartupSelecionada');
const badgeStatusStartupAberta = document.getElementById('badgeStatusStartupAberta');

let listaStartupsCache = [];
let startupAbertaId = null;
let dadosOriginaisStartupAberta = null;

function exibirAlertaEdicao(msg, tipo = 'sucesso') {
    if (!alertaEdicao) return;
    alertaEdicao.textContent = msg;
    alertaEdicao.className = `alerta-feedback ${tipo}`;
    alertaEdicao.style.display = 'block';
    alertaEdicao.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function ocultarAlertaEdicao() {
    if (!alertaEdicao) return;
    alertaEdicao.style.display = 'none';
    alertaEdicao.textContent = '';
}

function preencherFormularioStartup(startup) {
    if (!startup) return;
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

    const nomeExibicao = startup.nome_fantasia || startup.razao_social || 'Startup';
    if (tituloStartupSelecionada) {
        tituloStartupSelecionada.textContent = `Informações de ${nomeExibicao}`;
    }

    const ativa = obterStartupAtiva();
    const ehAtiva = ativa && String(ativa.id) === String(startup.id);
    if (badgeStatusStartupAberta) {
        badgeStatusStartupAberta.innerHTML = ehAtiva
            ? `<span style="background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; color: #10b981; padding: 6px 14px; border-radius: 999px; font-size: 0.82rem; font-weight: 700;">● Esta startup está sendo utilizada no momento</span>`
            : '';
    }
}

async function abrirDetalhesStartup(id) {
    startupAbertaId = id;
    ocultarAlertaEdicao();

    secaoLista.style.display = 'none';
    secaoDetalhes.style.display = 'flex';

    try {
        const startup = await obterStartupPorId(id);
        dadosOriginaisStartupAberta = { ...startup };
        preencherFormularioStartup(startup);
    } catch (erro) {
        const emCache = listaStartupsCache.find(s => String(s.id) === String(id));
        if (emCache) {
            dadosOriginaisStartupAberta = { ...emCache };
            preencherFormularioStartup(emCache);
        } else {
            exibirAlertaEdicao(`Não foi possível carregar os dados da startup: ${erro.message}`, 'erro');
        }
    }
}

function voltarParaLista() {
    secaoDetalhes.style.display = 'none';
    secaoLista.style.display = 'flex';
    startupAbertaId = null;
    ocultarAlertaEdicao();
    carregarListaStartups(campoBusca?.value.trim() || '');
}

function renderizarListaStartups(startups) {
    if (!containerLista) return;
    containerLista.innerHTML = '';

    if (!Array.isArray(startups) || startups.length === 0) {
        containerLista.innerHTML = `
            <div style="background: #14171c; border: 1px solid #282c34; border-radius: 12px; padding: 2rem; text-align: center; color: #94a3b8;">
                Nenhuma startup encontrada.
            </div>
        `;
        return;
    }

    const ativa = obterStartupAtiva();

    // Ordena para que a startup em uso apareça no topo ou mantém destacada
    const startupsOrdenadas = [...startups].sort((a, b) => {
        const aAtiva = ativa && String(a.id) === String(ativa.id) ? -1 : 0;
        const bAtiva = ativa && String(b.id) === String(ativa.id) ? -1 : 0;
        return aAtiva - bAtiva;
    });

    startupsOrdenadas.forEach((st) => {
        const nomeExibicao = st.nome_fantasia || st.razao_social || 'Startup';
        const sigla = nomeExibicao.slice(0, 2).toUpperCase();
        const ehEmUso = ativa && String(st.id) === String(ativa.id);

        const card = document.createElement('div');
        card.className = `card-item-startup ${ehEmUso ? 'startup-em-uso' : ''}`;

        const cnpjTexto = st.cnpj ? `CNPJ: ${st.cnpj}` : 'CNPJ não informado';
        const nichoTexto = st.nicho_atuacao ? `• ${st.nicho_atuacao}` : '';
        const localTexto = (st.cidade || st.estado) ? `• ${[st.cidade, st.estado].filter(Boolean).join('/')}` : '';

        card.innerHTML = `
            <div class="card-startup-esquerda">
                <div class="card-startup-sigla">${sigla}</div>
                <div class="card-startup-dados">
                    <div class="card-startup-nome">${nomeExibicao}</div>
                    <div class="card-startup-subinfo">
                        <span>${cnpjTexto}</span>
                        <span>${nichoTexto}</span>
                        <span>${localTexto}</span>
                    </div>
                    ${ehEmUso ? `<div class="texto-startup-em-uso">● Sendo utilizada no momento</div>` : ''}
                </div>
            </div>
            <div class="card-startup-direita">
                <span class="badge-ver-informacoes">Ver e Alterar Informações →</span>
            </div>
        `;

        card.addEventListener('click', () => {
            abrirDetalhesStartup(st.id);
        });

        containerLista.appendChild(card);
    });
}

async function carregarListaStartups(termo = '') {
    if (alertaLista) alertaLista.style.display = 'none';

    try {
        const startups = await listarStartupsDaConsultoria(termo);
        listaStartupsCache = Array.isArray(startups) ? startups : [];
        renderizarListaStartups(listaStartupsCache);
    } catch (erro) {
        if (alertaLista) {
            alertaLista.textContent = erro.message || 'Erro ao carregar lista de startups.';
            alertaLista.className = 'alerta-feedback erro';
            alertaLista.style.display = 'block';
        }
    }
}

// Botão superior para voltar à lista
btnVoltarLista?.addEventListener('click', () => {
    voltarParaLista();
});

// Busca na lista
campoBusca?.addEventListener('input', (e) => {
    carregarListaStartups(e.target.value.trim());
});

// Sincronizar Receita Federal na mesma página
btnSyncReceita?.addEventListener('click', async () => {
    const cnpj = document.getElementById('campoCnpj').value;
    if (!cnpj) {
        exibirAlertaEdicao('CNPJ inválido para consulta.', 'erro');
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

        exibirAlertaEdicao('✅ Dados sincronizados com a Receita Federal! Clique em Salvar para confirmar.', 'sucesso');
    } catch (err) {
        exibirAlertaEdicao(`Falha ao sincronizar com a Receita Federal: ${err.message}`, 'erro');
    } finally {
        btnSyncReceita.disabled = false;
        btnSyncReceita.textContent = textoOriginal;
    }
});

// Botão Cancelar (mantém na mesma página de edição, restaurando os valores originais)
btnCancelar?.addEventListener('click', () => {
    if (dadosOriginaisStartupAberta) {
        preencherFormularioStartup(dadosOriginaisStartupAberta);
    }
    exibirAlertaEdicao('Alterações descartadas. As informações originais foram restauradas.', 'sucesso');
});

// Botão Salvar (mantém na mesma página de edição)
formEdicao?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!startupAbertaId) return;

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
        await atualizarStartup(startupAbertaId, payload);

        dadosOriginaisStartupAberta = {
            ...(dadosOriginaisStartupAberta || {}),
            ...payload,
            id: startupAbertaId
        };

        const novoNome = payload.nome_fantasia || payload.razao_social;
        if (tituloStartupSelecionada && novoNome) {
            tituloStartupSelecionada.textContent = `Informações de ${novoNome}`;
        }

        // Se for a startup ativa, atualiza também o header e a sessão local
        const ativa = obterStartupAtiva();
        if (ativa && String(ativa.id) === String(startupAbertaId)) {
            salvarStartupAtiva(startupAbertaId, novoNome);
            const triggerNome = document.getElementById('startupTriggerNome');
            const avatarSigla = document.getElementById('avatar-startup-sigla');
            if (triggerNome) triggerNome.textContent = novoNome;
            if (avatarSigla) avatarSigla.textContent = (novoNome || 'AN').slice(0, 2).toUpperCase();
        }

        exibirAlertaEdicao('✅ Informações da startup salvas com sucesso!', 'sucesso');
    } catch (err) {
        exibirAlertaEdicao(`Erro ao salvar alterações: ${err.message}`, 'erro');
    } finally {
        btnSalvar.disabled = false;
        btnSalvar.textContent = '💾 Salvar Alterações da Startup';
    }
});

document.addEventListener('DOMContentLoaded', () => {
    inicializarSidebarGlobal('startups');

    inicializarSeletorGlobal('container-seletor-global', {
        onTrocarStartup: () => {
            if (secaoDetalhes.style.display === 'flex' && startupAbertaId) {
                preencherFormularioStartup(dadosOriginaisStartupAberta);
            } else {
                carregarListaStartups(campoBusca?.value.trim() || '');
            }
        }
    });

    carregarListaStartups();
});

