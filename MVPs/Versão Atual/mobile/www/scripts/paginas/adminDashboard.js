import { requisicaoApi } from '../servicos/api.js';
import { obterUsuarioLogado, removerToken, limparDadosPendencia } from '../servicos/servicoAutenticacao.js';
import { inicializarSidebarGlobal } from '../componentes/sidebarGlobal.js';

// Verificação de Autorização Global
const usuarioLogado = obterUsuarioLogado();
if (!usuarioLogado || usuarioLogado.perfil_acesso !== 'admin_global') {
    alert('Acesso restrito ao Administrador Global.');
    window.location.replace('login.html');
}

// Inicializa Sidebar Global com aba de Governança ativa ('admin')
inicializarSidebarGlobal('admin');

const btnMobileMenu = document.getElementById('btnMobileMenu');
if (btnMobileMenu) {
    btnMobileMenu.addEventListener('click', () => {
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        const isOpen = sidebar?.classList.toggle('mobile-open');
        backdrop?.classList.toggle('active', Boolean(isOpen));
    });
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

// Elementos de Radares
const containerRadares = document.getElementById('container-radares');
const selectSyncAlvo = document.getElementById('select-sync-alvo');
const btnExecutarSyncAlvo = document.getElementById('btn-executar-sync-alvo');
const chipsFiltroRadar = document.querySelectorAll('.chip-filtro-radar');

// Modal de Detalhes do Radar
const modalDetalhesRadar = document.getElementById('modal-detalhes-radar');
const modalRadarSigla = document.getElementById('modal-radar-sigla');
const modalRadarStatusBadge = document.getElementById('modal-radar-status-badge');
const modalRadarOrgao = document.getElementById('modal-radar-orgao');
const modalRadarRegiaoBadge = document.getElementById('modal-radar-regiao-badge');
const modalRadarUfBadge = document.getElementById('modal-radar-uf-badge');
const modalRadarTotalEditais = document.getElementById('modal-radar-total-editais');
const modalRadarAbertos = document.getElementById('modal-radar-abertos');
const modalRadarFechados = document.getElementById('modal-radar-fechados');
const modalRadarLatencia = document.getElementById('modal-radar-latencia');
const modalRadarUrl = document.getElementById('modal-radar-url');
const modalRadarMetodo = document.getElementById('modal-radar-metodo');
const modalRadarResiliencia = document.getElementById('modal-radar-resiliencia');
const modalRadarUltimaSync = document.getElementById('modal-radar-ultima-sync');
const modalRadarContagemEditais = document.getElementById('modal-radar-contagem-editais');
const modalRadarEditaisLista = document.getElementById('modal-radar-editais-lista');
const modalRadarLogWrap = document.getElementById('modal-radar-log-wrap');
const modalRadarLogContent = document.getElementById('modal-radar-log-content');
const btnSyncModalRadar = document.getElementById('btn-sync-modal-radar');
const btnFecharModalRadar = document.getElementById('btn-fechar-modal-radar');
const btnFecharModalRadar2 = document.getElementById('btn-fechar-modal-radar-2');

let radarAtivoModal = null;
let cacheRadares = [];

// Elementos de Gestão de Empresas
const inputBuscaAgencia = document.getElementById('input-busca-agencia');
const selectStatusAgencia = document.getElementById('select-status-agencia');
const btnFiltrarAgencias = document.getElementById('btn-filtrar-agencias');
const tabelaAgenciasCorpo = document.getElementById('tabela-agencias-corpo');

// Modal de Ajuste de Plano
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
        cacheRadares = resposta.dados || [];

        renderizarCardsRadares(cacheRadares);
    } catch (e) {
        containerRadares.innerHTML = `<p style="color: #f87171;">Erro ao carregar radares: ${e.message}</p>`;
    }
}

/**
 * Renderiza os cards de radares no container.
 */
function renderizarCardsRadares(radares) {
    if (!containerRadares) return;
    containerRadares.innerHTML = '';

    if (!radares || radares.length === 0) {
        containerRadares.innerHTML = `<p style="color: var(--text-muted); font-size: 0.9rem;">Nenhum radar localizado para esta região.</p>`;
        return;
    }

    radares.forEach((radar) => {
        const card = document.createElement('div');
        card.className = 'radar-card';
        card.dataset.fonte = radar.fonte;
        card.dataset.macroRegiao = radar.macro_regiao;

        const statusClass = radar.status_servidor.toLowerCase().includes('online')
            ? 'online'
            : (radar.status_servidor.toLowerCase().includes('instável') ? 'instavel' : 'offline');

        card.innerHTML = `
            <div class="radar-card-top">
                <div class="radar-card-title">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <h3>${radar.fonte}</h3>
                        <span class="badge-macro-regiao">${radar.macro_regiao}</span>
                    </div>
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

            <div style="font-size: 0.78rem; color: var(--text-subtle); line-height: 1.4;">
                <div><strong>Região:</strong> ${radar.regiao}</div>
                <div><strong>Método:</strong> ${radar.metodo_extracao}</div>
            </div>

            <div class="radar-card-bottom">
                <div class="radar-last-sync">
                    Última extração: <strong>${radar.ultima_sincronizacao}</strong>
                </div>
                <div class="radar-card-acoes">
                    <button type="button" class="btn-ver-detalhes" data-fonte="${radar.fonte}" title="Ver detalhes completos e editais capturados">
                        🔍 Detalhes
                    </button>
                    <button type="button" class="btn-sync-radar" data-fonte="${radar.fonte}" title="Executar spider deste radar agora">
                        ⚡ Sincronizar
                    </button>
                </div>
            </div>
        `;

        // Clique no card abre modal de detalhes (se não clicou no botão de sync)
        card.addEventListener('click', (e) => {
            if (e.target.closest('.btn-sync-radar')) return;
            abrirModalDetalhesRadar(radar.fonte);
        });

        // Botão específico de Sincronização
        const btnSync = card.querySelector('.btn-sync-radar');
        btnSync?.addEventListener('click', (e) => {
            e.stopPropagation();
            dispararSincronizacao(radar.fonte, btnSync);
        });

        containerRadares.appendChild(card);
    });
}

/**
 * Filtro visual dos cards de radar via chips de região.
 */
chipsFiltroRadar.forEach((chip) => {
    chip.addEventListener('click', () => {
        chipsFiltroRadar.forEach((c) => c.classList.remove('active'));
        chip.classList.add('active');

        const regiao = chip.dataset.regiao;
        if (regiao === 'TODAS') {
            renderizarCardsRadares(cacheRadares);
        } else {
            const filtrados = cacheRadares.filter((r) => r.macro_regiao.toLowerCase() === regiao.toLowerCase());
            renderizarCardsRadares(filtrados);
        }
    });
});

/**
 * Abre o Modal com Detalhes Completos do Radar.
 */
async function abrirModalDetalhesRadar(fonte) {
    if (!modalDetalhesRadar) return;
    radarAtivoModal = fonte;

    // Reset de campos no modal
    if (modalRadarSigla) modalRadarSigla.textContent = fonte;
    if (modalRadarOrgao) modalRadarOrgao.textContent = 'Carregando dados oficiais...';
    if (modalRadarStatusBadge) {
        modalRadarStatusBadge.className = 'status-badge online';
        modalRadarStatusBadge.textContent = 'Verificando...';
    }
    if (modalRadarEditaisLista) {
        modalRadarEditaisLista.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 18px;">Buscando histórico do radar no banco...</td></tr>`;
    }
    if (modalRadarLogWrap) modalRadarLogWrap.hidden = true;

    modalDetalhesRadar.hidden = false;

    try {
        const resposta = await requisicaoApi(`/admin/radares/${encodeURIComponent(fonte)}/detalhes`);
        const dados = resposta.dados;

        if (modalRadarSigla) modalRadarSigla.textContent = dados.fonte;
        if (modalRadarOrgao) modalRadarOrgao.textContent = dados.orgao;
        if (modalRadarRegiaoBadge) modalRadarRegiaoBadge.textContent = dados.macro_regiao;
        if (modalRadarUfBadge) modalRadarUfBadge.textContent = dados.regiao;

        if (modalRadarStatusBadge) {
            const statusClass = dados.status_servidor.toLowerCase().includes('online')
                ? 'online'
                : (dados.status_servidor.toLowerCase().includes('instável') ? 'instavel' : 'offline');
            modalRadarStatusBadge.className = `status-badge ${statusClass}`;
            modalRadarStatusBadge.textContent = `● ${dados.status_servidor}`;
        }

        if (modalRadarTotalEditais) modalRadarTotalEditais.textContent = dados.total_editais;
        if (modalRadarAbertos) modalRadarAbertos.textContent = dados.editais_abertos;
        if (modalRadarFechados) modalRadarFechados.textContent = dados.editais_fechados;
        if (modalRadarLatencia) modalRadarLatencia.textContent = `${dados.codigo_http ?? '200'} • ${dados.latencia_ms}ms`;

        if (modalRadarUrl) {
            modalRadarUrl.href = dados.url_portal;
            modalRadarUrl.textContent = `${dados.url_portal} ↗`;
        }

        if (modalRadarMetodo) modalRadarMetodo.textContent = dados.metodo_extracao;
        if (modalRadarResiliencia) modalRadarResiliencia.textContent = dados.resiliencia;
        if (modalRadarUltimaSync) modalRadarUltimaSync.textContent = dados.ultima_sincronizacao;

        // Renderizar lista de editais capturados
        if (modalRadarEditaisLista) {
            modalRadarEditaisLista.innerHTML = '';
            const editais = dados.ultimos_editais || [];

            if (modalRadarContagemEditais) {
                modalRadarContagemEditais.textContent = `Exibindo os ${editais.length} editais mais recentes`;
            }

            if (editais.length === 0) {
                modalRadarEditaisLista.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 18px;">Nenhum edital cadastrado para este radar até o momento.</td></tr>`;
            } else {
                editais.forEach((edital) => {
                    const tr = document.createElement('tr');
                    const ehAtivo = edital.status === 'active';
                    const linkUrl = edital.source_url || '#';

                    tr.innerHTML = `
                        <td style="color: var(--text-subtle); font-weight: 600;">#${edital.id}</td>
                        <td style="max-width: 300px;">
                            <strong style="color: #fff; display: block; font-size: 0.86rem; line-height: 1.3;">${edital.title}</strong>
                        </td>
                        <td>
                            <span class="badge-status-agencia ${ehAtivo ? 'ativo' : 'suspenso'}">
                                ${ehAtivo ? 'Aberto' : 'Encerrado'}
                            </span>
                        </td>
                        <td style="font-size: 0.78rem; color: var(--text-muted);">
                            ${edital.deadline ? new Date(edital.deadline).toLocaleDateString('pt-BR') : 'Contínuo / S.D.'}
                        </td>
                        <td>
                            <a href="${linkUrl}" target="_blank" rel="noopener noreferrer" class="btn-acao btn-acao-plano" style="text-decoration: none;">
                                Acessar ↗
                            </a>
                        </td>
                    `;
                    modalRadarEditaisLista.appendChild(tr);
                });
            }
        }
    } catch (e) {
        if (modalRadarOrgao) modalRadarOrgao.textContent = `Erro ao carregar detalhes: ${e.message}`;
    }
}

// Fechamento do modal de detalhes do radar
btnFecharModalRadar?.addEventListener('click', () => {
    if (modalDetalhesRadar) modalDetalhesRadar.hidden = true;
});
btnFecharModalRadar2?.addEventListener('click', () => {
    if (modalDetalhesRadar) modalDetalhesRadar.hidden = true;
});

// Sincronizar dentro do Modal do Radar
btnSyncModalRadar?.addEventListener('click', async () => {
    if (!radarAtivoModal) return;
    await dispararSincronizacao(radarAtivoModal, btnSyncModalRadar, true);
    abrirModalDetalhesRadar(radarAtivoModal);
});

/**
 * Dispara a sincronização manual de um radar, região ou todos.
 */
async function dispararSincronizacao(alvo, botao, manterModalAberto = false) {
    if (botao && botao.disabled) return;
    if (botao) botao.disabled = true;
    const textoOriginal = botao ? botao.textContent : 'Sincronizar';
    if (botao) botao.textContent = 'Extraindo...';

    try {
        const resposta = await requisicaoApi(`/admin/radares/${encodeURIComponent(alvo)}/sincronizar`, {
            method: 'POST'
        });

        const duracao = resposta.dados.duracao_segundos;
        const total = resposta.dados.total_editais_banco;
        const log = resposta.dados.log_saida;

        // Se o modal estiver aberto e houver log, exibe no box de log
        if (manterModalAberto && modalRadarLogWrap && modalRadarLogContent && log) {
            modalRadarLogContent.textContent = log;
            modalRadarLogWrap.hidden = false;
        }

        alert(`Sincronização de [${alvo}] concluída em ${duracao}s!\nTotal de editais no banco: ${total}`);

        carregarRadares();
        carregarDashboard();
    } catch (e) {
        alert(`Erro na sincronização de [${alvo}]: ${e.message}`);
    } finally {
        if (botao) {
            botao.disabled = false;
            botao.textContent = textoOriginal;
        }
    }
}

// Sincronização Agrupada por Região / Seleção
btnExecutarSyncAlvo?.addEventListener('click', () => {
    const alvo = selectSyncAlvo ? selectSyncAlvo.value : 'TODOS';
    dispararSincronizacao(alvo, btnExecutarSyncAlvo);
});

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
