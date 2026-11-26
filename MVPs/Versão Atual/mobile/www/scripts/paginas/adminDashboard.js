import { requisicaoApi } from '../servicos/api.js';
import { obterUsuarioLogado, removerToken, limparDadosPendencia } from '../servicos/servicoAutenticacao.js';

// Verificação de Autorização Global
const usuarioLogado = obterUsuarioLogado();
if (!usuarioLogado || usuarioLogado.perfil_acesso !== 'admin_global') {
    alert('Acesso restrito ao Administrador Global.');
    window.location.replace('login.html');
}

// Elementos da UI
const adminUserInfo = document.getElementById('admin-user-info');
const btnLogoutAdmin = document.getElementById('btn-logout-admin');

if (adminUserInfo && usuarioLogado) {
    adminUserInfo.textContent = `${usuarioLogado.name || 'Admin'} (${usuarioLogado.email})`;
}

btnLogoutAdmin?.addEventListener('click', () => {
    removerToken();
    limparDadosPendencia();
    sessionStorage.clear();
    localStorage.removeItem('usuario_logado');
    window.location.replace('login.html');
});

// KPIs Elements
const kpiTotalAgencias = document.getElementById('kpi-total-agencias');
const kpiAgenciasStatus = document.getElementById('kpi-agencias-status');
const kpiTotalUsuarios = document.getElementById('kpi-total-usuarios');
const kpiUsuariosStatus = document.getElementById('kpi-usuarios-status');
const kpiMrr = document.getElementById('kpi-mrr');
const kpiPagamentos = document.getElementById('kpi-pagamentos');
const kpiTotalEditais = document.getElementById('kpi-total-editais');
const kpiEditaisAbertos = document.getElementById('kpi-editais-abertos');

const containerRadares = document.getElementById('container-radares');
const btnSyncTodosRadares = document.getElementById('btn-sync-todos-radares');

const inputBuscaAgencia = document.getElementById('input-busca-agencia');
const selectStatusAgencia = document.getElementById('select-status-agencia');
const btnFiltrarAgencias = document.getElementById('btn-filtrar-agencias');
const tabelaAgenciasCorpo = document.getElementById('tabela-agencias-corpo');

// Modal Elements
const modalAjustePlano = document.getElementById('modal-ajuste-plano');
const modalAgenciaNome = document.getElementById('modal-agencia-nome');
const modalAgenciaId = document.getElementById('modal-agencia-id');
const modalSelectPlano = document.getElementById('modal-select-plano');
const modalDiasExtras = document.getElementById('modal-dias-extras');
const btnCancelarModal = document.getElementById('btn-cancelar-modal');
const formAjustePlano = document.getElementById('form-ajuste-plano');

/**
 * Carrega as métricas consolidadas do dashboard.
 */
async function carregarDashboard() {
    try {
        const resposta = await requisicaoApi('/admin/dashboard');
        const dados = resposta.dados;

        if (kpiTotalAgencias) kpiTotalAgencias.textContent = dados.agencias.total;
        if (kpiAgenciasStatus) {
            kpiAgenciasStatus.textContent = `${dados.agencias.status.ativas} ativas • ${dados.agencias.status.suspensas} suspensas • ${dados.agencias.status.pendentes} pendentes`;
        }

        if (kpiTotalUsuarios) kpiTotalUsuarios.textContent = dados.usuarios.total;
        if (kpiUsuariosStatus) {
            kpiUsuariosStatus.textContent = `${dados.usuarios.status.ativos} ativos • ${dados.usuarios.status.inativos} inativos`;
        }

        if (kpiMrr) {
            kpiMrr.textContent = `R$ ${Number(dados.financeiro.mrr_estimado).toFixed(2).replace('.', ',')}`;
        }
        if (kpiPagamentos) {
            kpiPagamentos.textContent = `${dados.financeiro.formas_pagamento.pix} PIX • ${dados.financeiro.formas_pagamento.card} Cartão`;
        }

        if (kpiTotalEditais) kpiTotalEditais.textContent = dados.editais.total;
        if (kpiEditaisAbertos) {
            kpiEditaisAbertos.textContent = `${dados.editais.abertos} com inscrições abertas`;
        }
    } catch (e) {
        console.error('Erro ao carregar dashboard:', e);
    }
}

/**
 * Carrega a saúde e status dos radares de editais.
 */
async function carregarRadares() {
    if (!containerRadares) return;
    try {
        const resposta = await requisicaoApi('/admin/radares/status');
        const radares = resposta.dados;

        containerRadares.innerHTML = '';

        radares.forEach((radar) => {
            const card = document.createElement('div');
            card.className = 'radar-card';

            const statusClass = radar.status_servidor.toLowerCase().includes('online')
                ? 'online'
                : (radar.status_servidor.toLowerCase().includes('instável') ? 'instavel' : 'offline');

            card.innerHTML = `
                <div class="radar-card-top">
                    <div class="radar-card-title">
                        <h3>${radar.fonte}</h3>
                        <span>${radar.orgao}</span>
                    </div>
                    <span class="status-badge ${statusClass}">
                        ● ${radar.status_servidor} (${radar.latencia_ms}ms)
                    </span>
                </div>

                <div class="radar-metrics">
                    <div>
                        <span>Editais Totais</span>
                        <strong>${radar.total_editais}</strong>
                    </div>
                    <div>
                        <span>Em Aberto</span>
                        <strong style="color: #34d399;">${radar.editais_abertos}</strong>
                    </div>
                </div>

                <div style="font-size: 0.78rem; color: var(--text-subtle);">
                    <strong>Método:</strong> ${radar.metodo_extracao}
                </div>

                <div class="radar-card-bottom">
                    <div class="radar-last-sync">
                        Última extração: <strong>${radar.ultima_sincronizacao}</strong>
                    </div>
                    <button class="btn-sync-radar" data-fonte="${radar.fonte}">
                        Sincronizar
                    </button>
                </div>
            `;

            const btnSync = card.querySelector('.btn-sync-radar');
            btnSync.addEventListener('click', () => dispararSincronizacao(radar.fonte, btnSync));

            containerRadares.appendChild(card);
        });
    } catch (e) {
        containerRadares.innerHTML = `<p style="color: #f87171;">Erro ao carregar radares: ${e.message}</p>`;
    }
}

/**
 * Dispara a sincronização manual de um radar.
 */
async function dispararSincronizacao(fonte, botao) {
    if (botao.disabled) return;
    botao.disabled = true;
    const textoOriginal = botao.textContent;
    botao.textContent = 'Extraindo...';

    try {
        const resposta = await requisicaoApi(`/admin/radares/${encodeURIComponent(fonte)}/sincronizar`, {
            method: 'POST'
        });
        alert(`Sincronização do radar ${fonte} concluída em ${resposta.dados.duracao_segundos}s! Total de editais: ${resposta.dados.total_editais_banco}`);
        carregarRadares();
        carregarDashboard();
    } catch (e) {
        alert(`Erro na sincronização: ${e.message}`);
    } finally {
        botao.disabled = false;
        botao.textContent = textoOriginal;
    }
}

btnSyncTodosRadares?.addEventListener('click', () => dispararSincronizacao('TODOS', btnSyncTodosRadares));

/**
 * Carrega a lista de agências/empresas com filtros.
 */
async function carregarAgencias() {
    if (!tabelaAgenciasCorpo) return;
    tabelaAgenciasCorpo.innerHTML = `<tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 24px;">Buscando empresas...</td></tr>`;

    try {
        const busca = inputBuscaAgencia?.value?.trim() || '';
        const status = selectStatusAgencia?.value || '';
        const params = new URLSearchParams();
        if (busca) params.append('busca', busca);
        if (status) params.append('status', status);

        const resposta = await requisicaoApi(`/admin/agencias?${params.toString()}`);
        const agencias = resposta.dados;

        if (!agencias || agencias.length === 0) {
            tabelaAgenciasCorpo.innerHTML = `<tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 24px;">Nenhuma consultoria encontrada com esses filtros.</td></tr>`;
            return;
        }

        tabelaAgenciasCorpo.innerHTML = '';

        agencias.forEach((agencia) => {
            const tr = document.createElement('tr');
            const ehAtiva = agencia.status_pagamento === 'ativo';

            tr.innerHTML = `
                <td style="color: var(--text-subtle); font-weight: 600;">#${agencia.id}</td>
                <td>
                    <strong style="color: #fff; font-size: 0.95rem;">${agencia.nome}</strong>
                </td>
                <td>
                    <div>${agencia.owner?.name || 'Sem titular'}</div>
                    <small style="color: var(--text-muted);">${agencia.owner?.email || '--'}</small>
                </td>
                <td>
                    <span style="font-weight: 600;">Plano ${agencia.plano?.nome || 'N/A'}</span>
                </td>
                <td>
                    <span class="badge-status-agencia ${agencia.status_pagamento}">
                        ${agencia.status_pagamento}
                    </span>
                </td>
                <td>
                    <strong>${agencia.startups_count ?? 0}</strong> startups
                </td>
                <td class="acoes-td">
                    <button class="btn-acao ${ehAtiva ? 'btn-acao-suspender' : 'btn-acao-ativar'}" data-id="${agencia.id}" data-action="toggle-status">
                        ${ehAtiva ? 'Desativar' : 'Ligar Acesso'}
                    </button>
                    <button class="btn-acao btn-acao-plano" data-id="${agencia.id}" data-nome="${agencia.nome}" data-plano="${agencia.plano_id}" data-action="ajustar-plano">
                        Plano
                    </button>
                    <button class="btn-acao btn-acao-excluir" data-id="${agencia.id}" data-nome="${agencia.nome}" data-action="excluir">
                        Excluir
                    </button>
                </td>
            `;

            // Event Listeners das Ações
            const btnToggle = tr.querySelector('[data-action="toggle-status"]');
            btnToggle.addEventListener('click', () => toggleStatusAgencia(agencia.id, ehAtiva ? 'suspenso' : 'ativo'));

            const btnPlano = tr.querySelector('[data-action="ajustar-plano"]');
            btnPlano.addEventListener('click', () => abrirModalAjustePlano(agencia.id, agencia.nome, agencia.plano_id));

            const btnExcluir = tr.querySelector('[data-action="excluir"]');
            btnExcluir.addEventListener('click', () => excluirAgencia(agencia.id, agencia.nome));

            tabelaAgenciasCorpo.appendChild(tr);
        });
    } catch (e) {
        tabelaAgenciasCorpo.innerHTML = `<tr><td colspan="7" style="text-align: center; color: #f87171; padding: 24px;">Erro ao carregar consultorias: ${e.message}</td></tr>`;
    }
}

/**
 * Alterna status da agência (Ligar / Desligar).
 */
async function toggleStatusAgencia(agenciaId, novoStatus) {
    const acaoTexto = novoStatus === 'ativo' ? 'ativar e liberar o acesso de' : 'suspender e cortar o acesso de';
    if (!confirm(`Deseja realmente ${acaoTexto} esta consultoria?`)) return;

    try {
        await requisicaoApi(`/admin/agencias/${agenciaId}/status`, {
            method: 'PATCH',
            body: JSON.stringify({ status: novoStatus })
        });
        carregarAgencias();
        carregarDashboard();
    } catch (e) {
        alert(`Erro ao alterar status: ${e.message}`);
    }
}

/**
 * Exclui consultoria.
 */
async function excluirAgencia(agenciaId, nome) {
    if (!confirm(`ATENÇÃO: Deseja excluir permanentemente a consultoria "${nome}" e todos os seus recursos (startups, cards e membros)? Esta ação não pode ser desfeita.`)) return;

    try {
        await requisicaoApi(`/admin/agencias/${agenciaId}`, {
            method: 'DELETE'
        });
        alert(`Consultoria "${nome}" excluída com sucesso.`);
        carregarAgencias();
        carregarDashboard();
    } catch (e) {
        alert(`Erro ao excluir consultoria: ${e.message}`);
    }
}

/**
 * Modal de Ajuste de Plano
 */
function abrirModalAjustePlano(id, nome, planoId) {
    if (!modalAjustePlano) return;
    modalAgenciaId.value = id;
    modalAgenciaNome.textContent = `Consultoria: ${nome}`;
    if (planoId && modalSelectPlano) modalSelectPlano.value = planoId;
    if (modalDiasExtras) modalDiasExtras.value = '';
    modalAjustePlano.hidden = false;
}

btnCancelarModal?.addEventListener('click', () => {
    if (modalAjustePlano) modalAjustePlano.hidden = true;
});

formAjustePlano?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = modalAgenciaId.value;
    const plano_id = modalSelectPlano.value;
    const dias_extras = modalDiasExtras.value ? Number(modalDiasExtras.value) : null;

    try {
        await requisicaoApi(`/admin/agencias/${id}/ajustar-plano`, {
            method: 'POST',
            body: JSON.stringify({ plano_id, dias_extras })
        });
        alert('Plano atualizado com sucesso!');
        if (modalAjustePlano) modalAjustePlano.hidden = true;
        carregarAgencias();
        carregarDashboard();
    } catch (erro) {
        alert(`Erro ao ajustar plano: ${erro.message}`);
    }
});

btnFiltrarAgencias?.addEventListener('click', carregarAgencias);
inputBuscaAgencia?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') carregarAgencias();
});

// Inicialização
carregarDashboard();
carregarRadares();
carregarAgencias();
