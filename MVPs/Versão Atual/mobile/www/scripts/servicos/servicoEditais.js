import { requisicaoApi } from './api.js';

/**
 * Busca a listagem de editais da API com suporte a filtro por fonte, favoritos e startup ativa.
 */
export async function listarEditais({ fonte = '', apenasFavoritos = false, startupId = null } = {}) {
    const params = new URLSearchParams();

    if (apenasFavoritos || fonte === 'FAVORITOS') {
        params.append('favoritos', '1');
    } else if (fonte) {
        params.append('fonte', fonte);
    }

    if (startupId) {
        params.append('startup_id', String(startupId));
    }

    const qs = params.toString() ? `?${params.toString()}` : '';
    const dados = await requisicaoApi(`/editais${qs}`, { method: 'GET' });
    return Array.isArray(dados) ? dados : Object.values(dados || {});
}

/**
 * Alterna o estado de favorito de um edital para o usuário e startup ativos.
 */
export async function alternarFavoritoEdital(editalId, startupId = null) {
    return await requisicaoApi(`/editais/${editalId}/favoritar`, {
        method: 'POST',
        body: JSON.stringify({
            startup_id: startupId ? Number(startupId) : null
        })
    });
}

/**
 * Envia um edital selecionado para a coluna Backlog do pipeline Kanban.
 */
export async function enviarEditalParaKanban(editalId, startupName) {
    return await requisicaoApi('/kanban/cards', {
        method: 'POST',
        body: JSON.stringify({
            edital_id: Number(editalId),
            startup_name: startupName || 'Startup Assessorada'
        })
    });
}

/**
 * Normaliza strings para busca textual insensível a acentos e caixa.
 */
export function normalizarTextoBusca(valor) {
    return String(valor || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
}

/**
 * Converte a string de orçamento formatada ("R$ 1.500.000,00" ou "A definir") para número.
 */
export function extrairValorOrcamento(budgetTexto) {
    if (!budgetTexto) return 0;
    const limpo = String(budgetTexto)
        .replace(/[^\d,.-]/g, '')
        .replace(/\./g, '')
        .replace(',', '.');
    const numero = Number.parseFloat(limpo);
    return Number.isFinite(numero) ? numero : 0;
}

/**
 * Converte data no formato "dd/mm/yyyy..." em timestamp para ordenação e cálculo de urgência.
 */
export function extrairTimestampPrazo(prazoTexto) {
    if (!prazoTexto) return null;
    const match = String(prazoTexto).match(/(\d{2})\/(\d{2})\/(\d{4})/);
    if (!match) return null;
    const [, dia, mes, ano] = match;
    const data = new Date(Number(ano), Number(mes) - 1, Number(dia), 23, 59, 59);
    return Number.isNaN(data.getTime()) ? null : data.getTime();
}

/**
 * Calcula quantos dias restam até a data limite do edital.
 */
export function calcularDiasRestantes(prazoTexto) {
    const ts = extrairTimestampPrazo(prazoTexto);
    if (ts === null) return null;
    const diffMs = ts - Date.now();
    return Math.ceil(diffMs / (1000 * 60 * 60 * 24));
}

/**
 * Retorna a classe visual do badge de agência fomentadora.
 */
export function obterClasseAgencia(org) {
    const sigla = String(org || '').toUpperCase();
    if (sigla.includes('FINEP')) return 'agency-finep';
    if (sigla.includes('FAPESP')) return 'agency-fapesp';
    if (sigla.includes('FAPESC')) return 'agency-fapesc';
    if (sigla.includes('FAPEMIG')) return 'agency-fapemig';
    if (sigla.includes('FAPERJ')) return 'agency-faperj';
    if (sigla.includes('FAPERGS')) return 'agency-fapergs';
    if (sigla.includes('FAPEMAT')) return 'agency-fapemat';
    return 'agency-default';
}

/**
 * Retorna a classe CSS do nível de compatibilidade (match).
 */
export function obterClasseMatch(match) {
    const valor = Number(match || 0);
    if (valor >= 75) return 'match-high';
    if (valor >= 50) return 'match-medium';
    return 'match-low';
}

/**
 * Verifica se um edital corresponde ao termo de pesquisa textual.
 */
export function editalCorrespondeATermo(edital, termoNormalizado) {
    if (!termoNormalizado) return true;
    const docsTexto = Array.isArray(edital.documentos)
        ? edital.documentos.map((d) => d?.titulo || '').join(' ')
        : '';

    const orgUpper = String(edital.org || '').toUpperCase();
    const sinonimosRegionais = [];
    if (orgUpper.includes('FAPEMIG') || String(edital.region || '').toUpperCase() === 'MG') {
        sinonimosRegionais.push('minas gerais mg estadual');
    }
    if (orgUpper.includes('FAPESC') || String(edital.region || '').toUpperCase() === 'SC') {
        sinonimosRegionais.push('santa catarina sc estadual');
    }
    if (orgUpper.includes('FAPESP') || String(edital.region || '').toUpperCase() === 'SP') {
        sinonimosRegionais.push('sao paulo sp estadual');
    }
    if (orgUpper.includes('FAPERJ') || String(edital.region || '').toUpperCase() === 'RJ') {
        sinonimosRegionais.push('rio de janeiro rj estadual');
    }
    if (orgUpper.includes('FAPERGS') || String(edital.region || '').toUpperCase() === 'RS') {
        sinonimosRegionais.push('rio grande do sul rs gaucho estadual');
    }
    if (orgUpper.includes('FAPEMAT') || String(edital.region || '').toUpperCase() === 'MT') {
        sinonimosRegionais.push('mato grosso mt cuiaba centro oeste estadual');
    }

    const indice = normalizarTextoBusca([
        edital.name,
        edital.org,
        edital.objetivo,
        edital.target,
        edital.region,
        edital.nicho,
        edital.trl,
        edital.faturamento,
        docsTexto,
        sinonimosRegionais.join(' ')
    ].join(' '));

    const palavras = termoNormalizado.split(/\s+/).filter(Boolean);
    return palavras.every((palavra) => indice.includes(palavra));
}

/**
 * Inicializa o botão global de alternância entre tema claro e escuro.
 */
export function inicializarAlternadorTema() {
    const btnTema = document.getElementById('btnAlternarTema');
    const iconeSol = document.getElementById('iconeSol');
    const iconeLua = document.getElementById('iconeLua');

    function aplicarTema(escuro) {
        document.body.classList.toggle('dark-mode', escuro);
        if (iconeSol) iconeSol.style.display = escuro ? 'block' : 'none';
        if (iconeLua) iconeLua.style.display = escuro ? 'none' : 'block';
        localStorage.setItem('tema_preferido', escuro ? 'dark' : 'light');
    }

    const temaSalvo = localStorage.getItem('tema_preferido');
    aplicarTema(temaSalvo !== 'light');

    btnTema?.addEventListener('click', () => {
        aplicarTema(!document.body.classList.contains('dark-mode'));
    });
}

/**
 * Escapa caracteres especiais HTML para renderização segura.
 */
export function escapeHtml(texto) {
    return String(texto ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/**
 * Resolve o rótulo e a classe visual do status de prazo de um edital.
 */
export function resolverBadgePrazo(statusPadrao, classePadrao, diasRestantes) {
    if (diasRestantes !== null) {
        if (diasRestantes < 0) return { texto: 'Encerrado', classe: 'badge-encerrado' };
        if (diasRestantes <= 7) return { texto: `${diasRestantes}d restantes`, classe: 'badge-urgente' };
    }
    return {
        texto: statusPadrao || 'Aberto',
        classe: classePadrao || 'badge-aberto'
    };
}

/**
 * Renderiza a lista de documentos exigidos dentro de um container alvo.
 */
export function renderizarDocumentosNoPainel(container, documentos) {
    if (!container) return;
    container.innerHTML = '';

    if (Array.isArray(documentos) && documentos.length > 0) {
        documentos.forEach((doc) => {
            const div = document.createElement('div');
            div.className = 'doc-item';
            div.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="color: var(--success-color); font-weight: 700;">✓</span>
                    <span>${escapeHtml(doc.titulo || 'Documento exigido')}</span>
                </div>
                ${doc.link && doc.link !== '#' ? `<a href="${escapeHtml(doc.link)}" target="_blank" rel="noopener noreferrer">Ver anexo</a>` : ''}
            `;
            container.appendChild(div);
        });
    } else {
        container.innerHTML = '<div class="doc-item" style="color: var(--text-muted);">Nenhum documento específico listado no resumo.</div>';
    }
}

/**
 * Renderiza os itens de diagnóstico / análise de pré-requisitos dentro de um <ul>.
 */
export function renderizarDiagnosticoNoPainel(container, diagnosisList) {
    if (!container) return;
    container.innerHTML = '';

    if (Array.isArray(diagnosisList) && diagnosisList.length > 0) {
        diagnosisList.forEach((item) => {
            const li = document.createElement('li');
            li.className = `diag-item diag-${item.type || 'info'}`;
            li.textContent = item.text || '';
            container.appendChild(li);
        });
    } else {
        const li = document.createElement('li');
        li.className = 'diag-item diag-info';
        li.textContent = 'Análise detalhada disponível na página oficial da chamada.';
        container.appendChild(li);
    }
}

/**
 * Configura o link para a página oficial do edital desabilitando-o quando não há URL válida.
 */
export function configurarBotaoUrlOficial(linkEl, url) {
    if (!linkEl) return;
    const possuiUrl = Boolean(url && url !== '#');
    linkEl.href = possuiUrl ? url : '#';
    linkEl.style.pointerEvents = possuiUrl ? 'auto' : 'none';
    linkEl.style.opacity = possuiUrl ? '1' : '0.5';
    linkEl.setAttribute('aria-disabled', String(!possuiUrl));
}

/**
 * Exibe um toast discreto de confirmação ou erro no canto inferior direito.
 */
export function exibirToastFeedback(mensagem, opcoes = {}) {
    const anterior = document.getElementById('radarToastFeedback');
    if (anterior) anterior.remove();

    const toast = document.createElement('div');
    toast.id = 'radarToastFeedback';
    toast.className = 'radar-toast-feedback';
    if (opcoes.tipo === 'erro') {
        toast.style.borderColor = 'var(--danger-color)';
    }

    toast.innerHTML = `
        <span>${escapeHtml(mensagem)}</span>
        ${opcoes.linkHref ? `<a href="${escapeHtml(opcoes.linkHref)}">${escapeHtml(opcoes.linkTexto || 'Abrir')}</a>` : ''}
    `;

    document.body.appendChild(toast);
    setTimeout(() => {
        toast.remove();
    }, 5000);
}
