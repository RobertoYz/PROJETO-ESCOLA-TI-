import { inicializarSeletorGlobal } from '../scripts/componentes/seletorGlobalStartup.js';
import { inicializarSidebarGlobal } from '../scripts/componentes/sidebarGlobal.js';
import { obterStartupAtiva, usuarioEstaAutenticado } from '../scripts/servicos/servicoAutenticacao.js';
import {
    listarEditais,
    alternarFavoritoEdital,
    enviarEditalParaKanban,
    normalizarTextoBusca,
    extrairValorOrcamento,
    extrairTimestampPrazo,
    calcularDiasRestantes,
    obterClasseAgencia,
    obterClasseMatch,
    editalCorrespondeATermo,
    inicializarAlternadorTema,
    escapeHtml,
    resolverBadgePrazo,
    renderizarDocumentosNoPainel,
    renderizarDiagnosticoNoPainel,
    configurarBotaoUrlOficial,
    exibirToastFeedback
} from '../scripts/servicos/servicoEditais.js';

if (!usuarioEstaAutenticado()) {
    window.location.replace('login.html');
}

let editaisCarregados = [];
let editaisExibidos = [];
let selectedId = null;

function inicializarAplicacao() {
    inicializarAlternadorTema();
    initUI();
    inicializarSidebarGlobal('radar');
    inicializarSeletorGlobal('container-seletor-global', {
        onTrocarStartup: () => {
            atualizarSubtituloStartup();
            fetchEditais();
        }
    });
    atualizarSubtituloStartup();
    fetchEditais();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inicializarAplicacao);
} else {
    inicializarAplicacao();
}

function atualizarSubtituloStartup() {
    const subEl = document.getElementById('radarStartupSub');
    if (!subEl) return;
    const startupAtiva = obterStartupAtiva();
    subEl.textContent = startupAtiva?.nome
        ? `Monitoramento ativo para ${startupAtiva.nome} — FINEP, FAPESC e FAPESP`
        : 'Monitoramento contínuo das chamadas da FINEP, FAPESC e FAPESP';
}

function initUI() {
    const btnFavDetail = document.getElementById('btnFavDetail');
    btnFavDetail?.addEventListener('click', () => {
        if (selectedId) toggleFavorite(selectedId);
    });

    const radarFilterEl = document.getElementById('radarFilter');
    const lensButtons = document.querySelectorAll('.lens-pill');
    lensButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const valor = btn.dataset.lens ?? '';
            lensButtons.forEach((b) => b.classList.toggle('active', b === btn));
            if (radarFilterEl) {
                radarFilterEl.value = valor;
            }
            fetchEditais();
        });
    });

    document.getElementById('radarSearchInput')?.addEventListener('input', () => {
        aplicarFiltrosLocaisEOrdenacao();
    });

    document.getElementById('radarSortSelect')?.addEventListener('change', () => {
        aplicarFiltrosLocaisEOrdenacao();
    });

    const btnSendToKanban = document.getElementById('btnSendToKanban');
    btnSendToKanban?.addEventListener('click', async () => {
        if (!selectedId) {
            exibirToastFeedback('Selecione um edital antes de enviar para o Kanban.', { tipo: 'erro' });
            return;
        }

        const originalText = btnSendToKanban.textContent;
        btnSendToKanban.disabled = true;
        btnSendToKanban.textContent = 'Enviando para o Kanban...';

        const startupAtiva = obterStartupAtiva();
        const nomeStartup = startupAtiva ? startupAtiva.nome : 'Startup Assessorada';

        try {
            await enviarEditalParaKanban(selectedId, nomeStartup);
            exibirToastFeedback('Edital adicionado ao Backlog do Kanban.', {
                linkHref: 'kanban.html',
                linkTexto: 'Abrir Kanban'
            });
        } catch (err) {
            console.error(err);
            exibirToastFeedback(err.message || 'Não foi possível enviar o edital ao Kanban.', { tipo: 'erro' });
        } finally {
            btnSendToKanban.textContent = originalText;
            btnSendToKanban.disabled = false;
        }
    });
}

export async function fetchEditais() {
    showLoadingState();

    try {
        const radarFilterEl = document.getElementById('radarFilter');
        const filtroValor = radarFilterEl ? radarFilterEl.value : '';
        const startupAtiva = obterStartupAtiva();

        sincronizarPillsComSelect(filtroValor);

        editaisCarregados = await listarEditais({
            fonte: filtroValor === 'FAVORITOS' ? '' : filtroValor,
            apenasFavoritos: filtroValor === 'FAVORITOS',
            startupId: startupAtiva?.id || null
        });

        atualizarMetricasPulso();
        aplicarFiltrosLocaisEOrdenacao(true);
    } catch (error) {
        console.error('Falha ao buscar os editais da API:', error);
        showErrorState();
    }
}

function sincronizarPillsComSelect(valor) {
    document.querySelectorAll('.lens-pill').forEach((btn) => {
        btn.classList.toggle('active', (btn.dataset.lens ?? '') === valor);
    });
}

function atualizarMetricasPulso() {
    const totalEl = document.getElementById('metricTotalRadar');
    const matchEl = document.getElementById('metricAltoMatch');
    const favEl = document.getElementById('metricFavoritos');

    const total = editaisCarregados.length;
    const altoMatch = editaisCarregados.filter((e) => Number(e.match || 0) >= 75).length;
    const favoritos = editaisCarregados.filter((e) => Boolean(e.favorite)).length;

    if (totalEl) totalEl.textContent = String(total);
    if (matchEl) matchEl.textContent = String(altoMatch);
    if (favEl) favEl.textContent = String(favoritos);
}

function aplicarFiltrosLocaisEOrdenacao(verificarUrl = false) {
    const termo = normalizarTextoBusca(document.getElementById('radarSearchInput')?.value || '');
    const ordenacao = document.getElementById('radarSortSelect')?.value || 'match_desc';

    editaisExibidos = editaisCarregados.filter((edital) => editalCorrespondeATermo(edital, termo));

    editaisExibidos.sort((a, b) => {
        if (ordenacao === 'prazo_asc') {
            const ta = extrairTimestampPrazo(a.deadline) ?? Number.MAX_SAFE_INTEGER;
            const tb = extrairTimestampPrazo(b.deadline) ?? Number.MAX_SAFE_INTEGER;
            return ta - tb;
        }
        if (ordenacao === 'orcamento_desc') {
            return extrairValorOrcamento(b.budget) - extrairValorOrcamento(a.budget);
        }
        return Number(b.match || 0) - Number(a.match || 0);
    });

    const countEl = document.getElementById('radarListCount');
    if (countEl) {
        countEl.textContent = editaisExibidos.length === 1 ? '1 edital' : `${editaisExibidos.length} editais`;
    }

    if (editaisExibidos.length === 0) {
        showEmptyState(termo);
        return;
    }

    renderTable();

    if (verificarUrl) {
        const urlParams = new URLSearchParams(window.location.search);
        const editalUrlId = urlParams.get('edital_id');
        const editalAlvo = editalUrlId
            ? editaisExibidos.find((e) => String(e.id) === String(editalUrlId))
            : null;

        if (editalAlvo) {
            selectEdital(editalAlvo.id);
            return;
        }
    }

    const selecionadoAindaExiste = editaisExibidos.some((e) => String(e.id) === String(selectedId));
    if (selecionadoAindaExiste) {
        selectEdital(selectedId);
    } else if (window.innerWidth > 900) {
        selectEdital(editaisExibidos[0].id);
    } else {
        fecharDetalhesMobile();
    }
}

window.fetchEditais = fetchEditais;
window.toggleFavorite = toggleFavorite;
window.selectEdital = selectEdital;

function renderTable() {
    const tbody = document.getElementById('tableBody');
    if (!tbody) return;

    tbody.innerHTML = '';

    editaisExibidos.forEach((edital) => {
        const tr = document.createElement('tr');
        tr.id = `row-${edital.id}`;
        if (String(edital.id) === String(selectedId)) tr.classList.add('selected');

        tr.addEventListener('click', (e) => {
            if (!e.target.closest('.btn-icon')) selectEdital(edital.id);
        });

        const starIcon = edital.favorite ? '#icon-star-filled' : '#icon-star';
        const starActive = edital.favorite ? 'active' : '';

        const name = edital.name || '--';
        const org = edital.org || 'FINEP';
        const budget = edital.budget || 'A definir';
        const deadline = edital.deadline || 'Sem data';
        const matchValue = Number(edital.match || 0);
        const diasRestantes = calcularDiasRestantes(deadline);
        const statusInfo = resolverBadgePrazo(edital.status, edital.statusClass, diasRestantes);

        tr.innerHTML = `
            <td class="cell-main">
                <div class="card-mobile-top">
                    <span class="badge ${statusInfo.classe}">${statusInfo.texto}</span>
                    <span class="match-pill ${obterClasseMatch(matchValue)}">${matchValue}% match</span>
                    <button type="button" class="btn-icon btn-fav-card ${starActive}" onclick="toggleFavorite(${edital.id})" title="Favoritar" aria-label="Favoritar edital">
                        <svg width="19" height="19"><use href="${starIcon}"></use></svg>
                    </button>
                </div>
                <div class="edital-name">${escapeHtml(name)}</div>
                <div class="edital-org-row">
                    <span class="agency-chip ${obterClasseAgencia(org)}">${escapeHtml(org)}</span>
                    ${edital.nicho ? `<span class="edital-org">${escapeHtml(edital.nicho)}</span>` : ''}
                </div>
            </td>
            <td class="budget cell-budget"><span class="meta-label">Orçamento: </span><span class="meta-val">${escapeHtml(budget)}</span></td>
            <td class="deadline cell-deadline"><span class="meta-label">Prazo: </span><span class="meta-val">${escapeHtml(deadline)}</span></td>
            <td class="cell-status-desktop"><span class="badge ${statusInfo.classe}">${statusInfo.texto}</span></td>
            <td class="cell-match-desktop tabular ${obterClasseMatch(matchValue)}">${matchValue}%</td>
            <td class="cell-fav-desktop">
                <button type="button" class="btn-icon ${starActive}" onclick="toggleFavorite(${edital.id})" title="Favoritar" aria-label="Favoritar edital">
                    <svg width="19" height="19"><use href="${starIcon}"></use></svg>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function selectEdital(id) {
    selectedId = id;

    document.querySelectorAll('#tableBody tr').forEach((row) => row.classList.remove('selected'));
    const selectedRow = document.getElementById(`row-${id}`);
    if (selectedRow) selectedRow.classList.add('selected');

    const edital = editaisCarregados.find((e) => String(e.id) === String(id));
    if (!edital) return;

    const detailPanel = document.getElementById('detailPanel');
    if (detailPanel) {
        detailPanel.style.display = 'flex';
        if (window.innerWidth <= 900) {
            detailPanel.scrollTop = 0;
        }
    }

    updateElementText('detOrg', edital.org);
    updateElementText('detName', edital.name);
    updateElementText('detObjetivo', edital.objetivo);
    updateElementText('detTarget', edital.target);
    updateElementText('detRegion', edital.region);
    updateElementText('detNicho', edital.nicho);
    updateElementText('detFaturamento', edital.faturamento);
    updateElementText('detTrl', edital.trl);
    updateElementText('detOpen', edital.openDate);
    updateElementText('detClose', edital.closeDate);
    updateElementText('detResult', edital.resultDate);

    configurarBotaoUrlOficial(document.getElementById('detUrl'), edital.url);

    const matchVal = Number(edital.match || 0);
    const matchValEl = document.getElementById('detMatchVal');
    if (matchValEl) {
        matchValEl.textContent = `${matchVal}%`;
        matchValEl.className = `info-value ${obterClasseMatch(matchVal)}`;
    }

    const matchBar = document.getElementById('detMatchBar');
    if (matchBar) {
        matchBar.style.width = `${matchVal}%`;
        matchBar.style.backgroundColor = getMatchHexColor(matchVal);
    }

    atualizarBotaoFavoritoPainel(edital);
    renderizarDocumentosNoPainel(document.getElementById('detDocs'), edital.documentos);
    renderizarDiagnosticoNoPainel(document.getElementById('detDiag'), edital.diagnosis);
}

function updateElementText(elementId, text) {
    const el = document.getElementById(elementId);
    if (el) el.textContent = text || '--';
}

function getMatchHexColor(match) {
    const value = Number(match || 0);
    if (value >= 75) return 'var(--success-color)';
    if (value >= 50) return 'var(--warning-color)';
    return 'var(--danger-color)';
}

function atualizarBotaoFavoritoPainel(edital) {
    if (!edital || String(edital.id) !== String(selectedId)) return;
    const btnFavDetail = document.getElementById('btnFavDetail');
    if (!btnFavDetail) return;

    const useTag = btnFavDetail.querySelector('use');
    btnFavDetail.classList.toggle('active', Boolean(edital.favorite));
    if (useTag) useTag.setAttribute('href', edital.favorite ? '#icon-star-filled' : '#icon-star');
}

async function toggleFavorite(id) {
    const edital = editaisCarregados.find((e) => String(e.id) === String(id));
    if (!edital) return;

    const estadoAnterior = !!edital.favorite;
    edital.favorite = !estadoAnterior;
    atualizarMetricasPulso();
    renderTable();
    atualizarBotaoFavoritoPainel(edital);

    try {
        const startupAtiva = obterStartupAtiva();
        const json = await alternarFavoritoEdital(id, startupAtiva?.id || null);
        edital.favorite = !!json.favorito;
        atualizarMetricasPulso();

        const radarFilterEl = document.getElementById('radarFilter');
        if (radarFilterEl && radarFilterEl.value === 'FAVORITOS' && !edital.favorite) {
            editaisCarregados = editaisCarregados.filter((e) => String(e.id) !== String(id));
            atualizarMetricasPulso();
            aplicarFiltrosLocaisEOrdenacao();
            return;
        }

        renderTable();
        atualizarBotaoFavoritoPainel(edital);
    } catch (err) {
        console.error('Falha ao persistir favorito no backend:', err);
        edital.favorite = estadoAnterior;
        atualizarMetricasPulso();
        renderTable();
        atualizarBotaoFavoritoPainel(edital);
        exibirToastFeedback('Não foi possível salvar o favorito agora.', { tipo: 'erro' });
    }
}

function showLoadingState() {
    const tbody = document.getElementById('tableBody');
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 48px 20px; color: var(--text-muted); cursor: default;">Carregando oportunidades...</td></tr>';
    }
}

function showErrorState() {
    const tbody = document.getElementById('tableBody');
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 48px 20px; color: var(--danger-color); cursor: default;">Não foi possível carregar os editais. Verifique sua conexão e tente novamente.</td></tr>';
    }
}

function showEmptyState(termo = '') {
    const tbody = document.getElementById('tableBody');
    if (tbody) {
        const msg = termo
            ? 'Nenhum edital corresponde ao termo digitado nesta lente.'
            : 'Nenhum edital encontrado para este filtro.';
        tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 48px 20px; color: var(--text-muted); cursor: default;">${msg}</td></tr>`;
    }
    const detailPanel = document.getElementById('detailPanel');
    if (detailPanel) detailPanel.style.display = 'none';
}

function fecharDetalhesMobile() {
    const detailPanel = document.getElementById('detailPanel');
    if (detailPanel) {
        detailPanel.style.display = 'none';
        document.querySelectorAll('#tableBody tr').forEach((row) => row.classList.remove('selected'));
    }
}
window.fecharDetalhesMobile = fecharDetalhesMobile;

if (window.Capacitor || window.location.protocol === 'capacitor:' || (window.location.hostname === 'localhost' && /Android|iPhone|iPad|iPod/i.test(navigator.userAgent))) {
    document.body.classList.add('is-capacitor');
}
