import { inicializarSeletorGlobal } from '../componentes/seletorGlobalStartup.js';
import { inicializarSidebarGlobal } from '../componentes/sidebarGlobal.js';
import { obterStartupAtiva, usuarioEstaAutenticado } from '../servicos/servicoAutenticacao.js';
import { exibirModalAlerta, exibirModalConfirmacao } from '../componentes/modalGlobal.js';
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
} from '../servicos/servicoEditais.js';

if (!usuarioEstaAutenticado()) {
    window.location.replace('login.html');
}

let catalogoEditais = [];
let resultadosFiltrados = [];
let drawerEditalId = null;

const estadoFiltros = {
    termo: '',
    fontes: new Set(),
    apenasFavoritos: false,
    perfilBeneficiario: '',
    matchMinimo: 0,
    orcamentoMinimo: 0,
    abrangencia: '',
    ordenacao: 'match_desc'
};

function inicializarBuscaEditais() {
    inicializarAlternadorTema();
    lerParametrosIniciaisUrl();
    configurarEventos();
    inicializarSidebarGlobal('busca');
    inicializarSeletorGlobal('container-seletor-global', {
        onTrocarStartup: () => {
            carregarCatalogo();
        }
    });
    carregarCatalogo();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inicializarBuscaEditais);
} else {
    inicializarBuscaEditais();
}

function lerParametrosIniciaisUrl() {
    const params = new URLSearchParams(window.location.search);
    const q = params.get('q');
    const fonte = params.get('fonte');

    if (q) {
        estadoFiltros.termo = q;
        const input = document.getElementById('searchKeywordInput');
        if (input) input.value = q;
    }

    if (fonte) {
        const sigla = fonte.toUpperCase();
        estadoFiltros.fontes.add(sigla);
        document.querySelectorAll('.filter-fonte-check').forEach((chk) => {
            if (chk.value.toUpperCase() === sigla) chk.checked = true;
        });
    }
}

function configurarEventos() {
    const inputBusca = document.getElementById('searchKeywordInput');
    const btnLimparBusca = document.getElementById('btnLimparBusca');
    const btnResetFilters = document.getElementById('btnResetFilters');
    const sortSelect = document.getElementById('searchSortSelect');
    const btnToggleFiltros = document.getElementById('btnToggleFiltrosMobile');
    const filterRail = document.getElementById('searchFilterRail');

    const atualizarBotaoLimpar = () => {
        if (!btnLimparBusca || !inputBusca) return;
        btnLimparBusca.classList.toggle('visible', inputBusca.value.trim().length > 0);
    };

    inputBusca?.addEventListener('input', () => {
        estadoFiltros.termo = inputBusca.value;
        atualizarBotaoLimpar();
        aplicarFiltrosERenderizar();
    });

    btnLimparBusca?.addEventListener('click', () => {
        if (!inputBusca) return;
        inputBusca.value = '';
        estadoFiltros.termo = '';
        atualizarBotaoLimpar();
        aplicarFiltrosERenderizar();
        inputBusca.focus();
    });

    document.querySelectorAll('.filter-fonte-check').forEach((chk) => {
        chk.addEventListener('change', () => {
            if (chk.checked) {
                estadoFiltros.fontes.add(chk.value);
            } else {
                estadoFiltros.fontes.delete(chk.value);
            }
            aplicarFiltrosERenderizar();
        });
    });

    document.getElementById('filterPerfilBeneficiario')?.addEventListener('change', (e) => {
        estadoFiltros.perfilBeneficiario = e.target.value || '';
        aplicarFiltrosERenderizar();
    });

    document.getElementById('filterApenasFavoritos')?.addEventListener('change', (e) => {
        estadoFiltros.apenasFavoritos = e.target.checked;
        aplicarFiltrosERenderizar();
    });

    document.getElementById('filterMatchMinimo')?.addEventListener('change', (e) => {
        estadoFiltros.matchMinimo = Number(e.target.value || 0);
        aplicarFiltrosERenderizar();
    });

    document.getElementById('filterOrcamentoMinimo')?.addEventListener('change', (e) => {
        estadoFiltros.orcamentoMinimo = Number(e.target.value || 0);
        aplicarFiltrosERenderizar();
    });

    document.getElementById('filterAbrangencia')?.addEventListener('change', (e) => {
        estadoFiltros.abrangencia = e.target.value || '';
        aplicarFiltrosERenderizar();
    });

    sortSelect?.addEventListener('change', (e) => {
        estadoFiltros.ordenacao = e.target.value;
        aplicarFiltrosERenderizar();
    });

    btnResetFilters?.addEventListener('click', limparTodosOsFiltros);

    btnToggleFiltros?.addEventListener('click', () => {
        const aberto = filterRail?.classList.toggle('mobile-open');
        btnToggleFiltros.setAttribute('aria-expanded', String(Boolean(aberto)));
    });

    // Atalhos de teclado: '/' foca a busca e 'Escape' fecha o drawer ou limpa o campo
    document.addEventListener('keydown', (e) => {
        if (e.key === '/' && document.activeElement !== inputBusca && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) {
            e.preventDefault();
            inputBusca?.focus();
        } else if (e.key === 'Escape') {
            const backdrop = document.getElementById('searchDrawerBackdrop');
            if (backdrop?.classList.contains('open')) {
                fecharDrawer();
            } else if (document.activeElement === inputBusca && inputBusca.value) {
                inputBusca.value = '';
                estadoFiltros.termo = '';
                atualizarBotaoLimpar();
                aplicarFiltrosERenderizar();
            }
        }
    });

    document.getElementById('btnCloseDrawer')?.addEventListener('click', fecharDrawer);
    document.getElementById('searchDrawerBackdrop')?.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) fecharDrawer();
    });

    document.getElementById('drawerBtnFav')?.addEventListener('click', () => {
        if (drawerEditalId) alternarFavoritoNaBusca(drawerEditalId);
    });

    document.getElementById('drawerBtnSendKanban')?.addEventListener('click', async (e) => {
        if (!drawerEditalId) return;
        await enviarParaKanbanComFeedback(drawerEditalId, e.currentTarget);
    });

    atualizarBotaoLimpar();
}

async function carregarCatalogo() {
    renderizarCarregamento();
    try {
        const startupAtiva = obterStartupAtiva();
        catalogoEditais = await listarEditais({
            startupId: startupAtiva?.id || null
        });

        atualizarContadoresLaterais();
        aplicarFiltrosERenderizar();

        const editalUrlId = new URLSearchParams(window.location.search).get('edital_id');
        if (editalUrlId) {
            abrirDrawerEdital(editalUrlId);
        }
    } catch (err) {
        console.error('Erro ao carregar catálogo de editais:', err);
        renderizarErro();
    }
}

function atualizarContadoresLaterais() {
    const porFonte = { FINEP: 0, FAPESC: 0, FAPESP: 0, FAPEMIG: 0, FAPERJ: 0, FAPERGS: 0 };
    let totalFav = 0;

    catalogoEditais.forEach((ed) => {
        const org = String(ed.org || '').toUpperCase();
        if (org.includes('FINEP')) porFonte.FINEP += 1;
        else if (org.includes('FAPESC')) porFonte.FAPESC += 1;
        else if (org.includes('FAPESP')) porFonte.FAPESP += 1;
        else if (org.includes('FAPEMIG')) porFonte.FAPEMIG += 1;
        else if (org.includes('FAPERJ')) porFonte.FAPERJ += 1;
        else if (org.includes('FAPERGS')) porFonte.FAPERGS += 1;
        if (ed.favorite) totalFav += 1;
    });

    const elFinep = document.getElementById('countFonteFINEP');
    const elFapesc = document.getElementById('countFonteFAPESC');
    const elFapesp = document.getElementById('countFonteFAPESP');
    const elFapemig = document.getElementById('countFonteFAPEMIG');
    const elFaperj = document.getElementById('countFonteFAPERJ');
    const elFapergs = document.getElementById('countFonteFAPERGS');
    const elFav = document.getElementById('countFavoritosBusca');

    if (elFinep) elFinep.textContent = String(porFonte.FINEP);
    if (elFapesc) elFapesc.textContent = String(porFonte.FAPESC);
    if (elFapesp) elFapesp.textContent = String(porFonte.FAPESP);
    if (elFapemig) elFapemig.textContent = String(porFonte.FAPEMIG);
    if (elFaperj) elFaperj.textContent = String(porFonte.FAPERJ);
    if (elFapergs) elFapergs.textContent = String(porFonte.FAPERGS);
    if (elFav) elFav.textContent = String(totalFav);
}

function aplicarFiltrosERenderizar() {
    const termoNorm = normalizarTextoBusca(estadoFiltros.termo);

    resultadosFiltrados = catalogoEditais.filter((edital) => {
        if (!editalCorrespondeATermo(edital, termoNorm)) return false;

        if (estadoFiltros.fontes.size > 0) {
            const orgUpper = String(edital.org || '').toUpperCase();
            const bateFonte = Array.from(estadoFiltros.fontes).some((f) => orgUpper.includes(f));
            if (!bateFonte) return false;
        }

        if (estadoFiltros.apenasFavoritos && !edital.favorite) return false;

        if (estadoFiltros.matchMinimo > 0 && Number(edital.match || 0) < estadoFiltros.matchMinimo) {
            return false;
        }

        if (estadoFiltros.orcamentoMinimo > 0 && extrairValorOrcamento(edital.budget) < estadoFiltros.orcamentoMinimo) {
            return false;
        }

        if (estadoFiltros.abrangencia) {
            const regiaoNorm = normalizarTextoBusca(edital.region || '');
            const orgUpper = String(edital.org || '').toUpperCase();
            if (estadoFiltros.abrangencia === 'mg') {
                const ehMg = regiaoNorm === 'mg' || regiaoNorm.includes('minas gerais') || orgUpper.includes('FAPEMIG');
                if (!ehMg) return false;
            } else if (estadoFiltros.abrangencia === 'estadual') {
                const ehEstadual = regiaoNorm.includes('estadual')
                    || ['mg', 'sp', 'sc'].includes(regiaoNorm)
                    || orgUpper.includes('FAPEMIG')
                    || orgUpper.includes('FAPESC')
                    || orgUpper.includes('FAPESP');
                if (!ehEstadual) return false;
            } else if (!regiaoNorm.includes(estadoFiltros.abrangencia)) {
                return false;
            }
        }

        if (estadoFiltros.perfilBeneficiario) {
            const ctx = `${edital.name || ''} ${edital.target || ''} ${edital.objetivo || ''}`.toLowerCase();
            const perfil = estadoFiltros.perfilBeneficiario;

            if (perfil === 'empresa_sozinha') {
                const exigeIctObrig = (ctx.includes('obrigatoriamente') || ctx.includes('apenas')) && (ctx.includes('ict') || ctx.includes('pesquisador'));
                const temEmpresa = ctx.includes('empresa') || ctx.includes('startup') || ctx.includes('sociedade') || ctx.includes('pdi');
                if (exigeIctObrig || !temEmpresa) return false;
            } else if (perfil === 'pesquisador_empresa') {
                const ehContratacaoPesquisador = ctx.includes('inova talentos') 
                    || ctx.includes('pesquisador na empresa') 
                    || ctx.includes('fixação de doutor')
                    || ctx.includes('rhae')
                    || (ctx.includes('bolsa') && ctx.includes('empresa'))
                    || ctx.includes('atracao') || ctx.includes('atração');
                if (!ehContratacaoPesquisador) return false;
            } else if (perfil === 'apenas_pesquisador') {
                const ehApenasPesquisador = ctx.includes('pesquisador visitante')
                    || ctx.includes('bolsa nota 10')
                    || ctx.includes('recém-doutor')
                    || ctx.includes('recém doutor')
                    || ctx.includes('apenas ict')
                    || ctx.includes('somente pesquisador')
                    || (ctx.includes('pesquisador') && !ctx.includes('empresa') && !ctx.includes('startup'));
                if (!ehApenasPesquisador) return false;
            } else if (perfil === 'consorcio') {
                const ehConsorcio = ctx.includes('consórcio') 
                    || ctx.includes('consorcio') 
                    || ctx.includes('cooperação internacional')
                    || ctx.includes('eureka')
                    || ctx.includes('chamada bilateral')
                    || ctx.includes('parceria obrigatória');
                if (!ehConsorcio) return false;
            }
        }

        return true;
    });

    resultadosFiltrados.sort((a, b) => {
        if (estadoFiltros.ordenacao === 'prazo_asc') {
            const ta = extrairTimestampPrazo(a.deadline) ?? Number.MAX_SAFE_INTEGER;
            const tb = extrairTimestampPrazo(b.deadline) ?? Number.MAX_SAFE_INTEGER;
            return ta - tb;
        }
        if (estadoFiltros.ordenacao === 'orcamento_desc') {
            return extrairValorOrcamento(b.budget) - extrairValorOrcamento(a.budget);
        }
        if (estadoFiltros.ordenacao === 'titulo_asc') {
            return String(a.name || '').localeCompare(String(b.name || ''), 'pt-BR');
        }
        return Number(b.match || 0) - Number(a.match || 0);
    });

    renderizarChipsAtivos();
    renderizarListaResultados();
}

function renderizarChipsAtivos() {
    const counterEl = document.getElementById('searchResultsCounter');
    const chipsWrap = document.getElementById('activeFilterChips');

    if (counterEl) {
        const total = catalogoEditais.length;
        const exibidos = resultadosFiltrados.length;
        counterEl.textContent = exibidos === total
            ? `${exibidos} ${exibidos === 1 ? 'edital disponível' : 'editais disponíveis'}`
            : `Mostrando ${exibidos} de ${total} editais`;
    }

    if (!chipsWrap) return;
    chipsWrap.innerHTML = '';

    const adicionarChip = (label, onRemover) => {
        const span = document.createElement('span');
        span.className = 'active-chip';
        span.innerHTML = `${escapeHtml(label)} <button type="button" aria-label="Remover filtro ${escapeHtml(label)}">&times;</button>`;
        span.querySelector('button')?.addEventListener('click', onRemover);
        chipsWrap.appendChild(span);
    };

    if (estadoFiltros.termo.trim()) {
        adicionarChip(`Busca: "${estadoFiltros.termo.trim()}"`, () => {
            estadoFiltros.termo = '';
            const input = document.getElementById('searchKeywordInput');
            if (input) input.value = '';
            document.getElementById('btnLimparBusca')?.classList.remove('visible');
            aplicarFiltrosERenderizar();
        });
    }

    estadoFiltros.fontes.forEach((fonte) => {
        adicionarChip(`Agência: ${fonte}`, () => {
            estadoFiltros.fontes.delete(fonte);
            document.querySelectorAll('.filter-fonte-check').forEach((chk) => {
                if (chk.value === fonte) chk.checked = false;
            });
            aplicarFiltrosERenderizar();
        });
    });

    if (estadoFiltros.apenasFavoritos) {
        adicionarChip('Somente favoritos', () => {
            estadoFiltros.apenasFavoritos = false;
            const chk = document.getElementById('filterApenasFavoritos');
            if (chk) chk.checked = false;
            aplicarFiltrosERenderizar();
        });
    }

    if (estadoFiltros.matchMinimo > 0) {
        adicionarChip(`Match ${estadoFiltros.matchMinimo}%+`, () => {
            estadoFiltros.matchMinimo = 0;
            const sel = document.getElementById('filterMatchMinimo');
            if (sel) sel.value = '0';
            aplicarFiltrosERenderizar();
        });
    }

    if (estadoFiltros.orcamentoMinimo > 0) {
        adicionarChip(`Orçamento >= R$ ${(estadoFiltros.orcamentoMinimo / 1000).toLocaleString('pt-BR')} mil`, () => {
            estadoFiltros.orcamentoMinimo = 0;
            const sel = document.getElementById('filterOrcamentoMinimo');
            if (sel) sel.value = '0';
            aplicarFiltrosERenderizar();
        });
    }

    if (estadoFiltros.abrangencia) {
        const rotuloAbrangencia = estadoFiltros.abrangencia === 'mg'
            ? 'Minas Gerais (MG)'
            : estadoFiltros.abrangencia;
        adicionarChip(`Abrangência: ${rotuloAbrangencia}`, () => {
            estadoFiltros.abrangencia = '';
            const sel = document.getElementById('filterAbrangencia');
            if (sel) sel.value = '';
            aplicarFiltrosERenderizar();
        });
    }

    if (estadoFiltros.perfilBeneficiario) {
        const nomesPerfil = {
            'empresa_sozinha': 'Empresa pode concorrer sozinha',
            'pesquisador_empresa': 'Bolsas/Atração para Empresas (Inova Talentos/RHAE)',
            'apenas_pesquisador': 'Apenas Pesquisadores / ICTs',
            'consorcio': 'Exige Parceria / Consórcio'
        };
        const rotulo = nomesPerfil[estadoFiltros.perfilBeneficiario] || estadoFiltros.perfilBeneficiario;
        adicionarChip(`Perfil: ${rotulo}`, () => {
            estadoFiltros.perfilBeneficiario = '';
            const sel = document.getElementById('filterPerfilBeneficiario');
            if (sel) sel.value = '';
            aplicarFiltrosERenderizar();
        });
    }
}

function renderizarListaResultados() {
    const container = document.getElementById('searchResultsList');
    if (!container) return;

    if (resultadosFiltrados.length === 0) {
        container.innerHTML = `
            <div class="empty-state-box">
                <div class="empty-state-title">Nenhum edital corresponde a esses filtros</div>
                <p class="empty-state-desc">Tente ampliar a faixa de orçamento, reduzir o percentual mínimo de match ou remover termos da busca.</p>
                <button type="button" class="btn-outline" id="btnEmptyLimpar">Limpar todos os filtros</button>
            </div>
        `;
        document.getElementById('btnEmptyLimpar')?.addEventListener('click', limparTodosOsFiltros);
        return;
    }

    container.innerHTML = '';

    resultadosFiltrados.forEach((edital) => {
        const card = document.createElement('article');
        card.className = 'search-result-item';
        if (String(edital.id) === String(drawerEditalId)) {
            card.classList.add('is-selected');
        }

        const org = edital.org || 'FINEP';
        const matchVal = Number(edital.match || 0);
        const starIcon = edital.favorite ? '#icon-star-filled' : '#icon-star';
        const starActive = edital.favorite ? 'active' : '';
        const dias = calcularDiasRestantes(edital.deadline);
        const prazoBadge = resolverBadgePrazo(edital.status, edital.statusClass, dias);
        const excerpt = edital.objetivo || 'Consulte os detalhes completos do edital para verificar critérios e cronograma.';

        card.innerHTML = `
            <div class="result-top-row">
                <div class="result-badges-left">
                    <span class="agency-chip ${obterClasseAgencia(org)}">${escapeHtml(org)}</span>
                    <span class="badge ${prazoBadge.classe}">${prazoBadge.texto}</span>
                    <span class="match-pill ${obterClasseMatch(matchVal)}">${matchVal}% match</span>
                </div>
                <button type="button" class="btn-icon btn-fav-result ${starActive}" title="Favoritar edital" aria-label="Favoritar edital">
                    <svg width="19" height="19"><use href="${starIcon}"></use></svg>
                </button>
            </div>

            <h3 class="result-title">${escapeHtml(edital.name || 'Edital sem título')}</h3>
            <p class="result-excerpt">${escapeHtml(excerpt)}</p>

            <div class="result-footer-row">
                <div class="result-specs">
                    <div class="result-spec">
                        <span class="result-spec-label">Orçamento</span>
                        <span class="result-spec-val">${escapeHtml(edital.budget || 'A definir')}</span>
                    </div>
                    <div class="result-spec">
                        <span class="result-spec-label">Submissão</span>
                        <span class="result-spec-val">${escapeHtml(edital.deadline || 'Sem data')}</span>
                    </div>
                    <div class="result-spec">
                        <span class="result-spec-label">Público-alvo</span>
                        <span class="result-spec-val">${escapeHtml(truncarTexto(edital.target || '--', 32))}</span>
                    </div>
                    <div class="result-spec">
                        <span class="result-spec-label">Abrangência</span>
                        <span class="result-spec-val">${escapeHtml(edital.region || 'Nacional')}</span>
                    </div>
                </div>

                <div class="result-actions">
                    <button type="button" class="btn-sm-outline btn-ver-detalhes">Ver detalhes</button>
                    <button type="button" class="btn-sm-primary btn-enviar-kanban">Enviar para o Kanban</button>
                </div>
            </div>
        `;

        card.querySelector('.result-title')?.addEventListener('click', () => abrirDrawerEdital(edital.id));
        card.querySelector('.btn-ver-detalhes')?.addEventListener('click', () => abrirDrawerEdital(edital.id));
        card.querySelector('.btn-fav-result')?.addEventListener('click', () => alternarFavoritoNaBusca(edital.id));
        card.querySelector('.btn-enviar-kanban')?.addEventListener('click', (e) => {
            enviarParaKanbanComFeedback(edital.id, e.currentTarget);
        });

        container.appendChild(card);
    });
}

function abrirDrawerEdital(id) {
    const edital = catalogoEditais.find((e) => String(e.id) === String(id));
    if (!edital) return;

    drawerEditalId = edital.id;

    const setTexto = (elId, txt) => {
        const el = document.getElementById(elId);
        if (el) el.textContent = txt || '--';
    };

    setTexto('drawerDetOrg', edital.org);
    setTexto('drawerDetName', edital.name);
    // Formata o resumo do edital para texto limpo e natural
    const formatarResumoLimpo = (texto) => {
        if (!texto) return '<p style="color: var(--text-muted); font-style: italic; margin: 0;">Sem descrição disponível.</p>';
        
        let t = String(texto);
        // Remove lixo técnico extraído de cabeçalhos/rodapés de PDF
        t = t.replace(/^RESUMO EXTRA[ÍI]DO DO PDF OFICIAL:\s*/i, '');
        t = t.replace(/---\s*OBJETIVOS\s*---\s*/gi, '');
        t = t.replace(/ESPEC[ÍI]FICOS/gi, '');
        t = t.replace(/P[áa]g\.\s*\d+\s*de\s*\d+[^.]*?\./gi, '');
        t = t.replace(/Documento assinado digitalmente[^.]*?\./gi, '');
        t = t.replace(/Para confer[êe]ncia,?\s*acesse o site[^.]*?\./gi, '');
        t = t.replace(/https?:\/\/[^\s]+/gi, '');
        t = t.replace(/e informe o processo[^.]*?\./gi, '');
        t = t.replace(/portal\.sgpe\.sea\.sc\.gov\.br[^\s]*/gi, '');
        t = t.replace(/c[óo]digo\s+[A-Z0-9]{5,}/gi, '');
        t = t.replace(/\b\d{3}\s+2\.1\./gi, '2.1.');

        // Normaliza espaços em branco
        t = t.replace(/\s+/g, ' ').trim();

        // Quebra em parágrafos limpos baseado em pontuação final e início de novos tópicos
        const sentencas = t.split(/(?<=[.!?])\s+(?=[A-Z0-9])/);
        const paragrafos = [];
        let blocoAtual = '';

        sentencas.forEach((s) => {
            const limpa = s.trim();
            if (!limpa || limpa.length < 4) return;
            // Se for apenas número solto (ex: "2.", "163"), ignora
            if (/^\d+\.?$/.test(limpa)) return;

            if (blocoAtual.length + limpa.length > 250) {
                paragrafos.push(blocoAtual.trim());
                blocoAtual = limpa;
            } else {
                blocoAtual += (blocoAtual ? ' ' : '') + limpa;
            }
        });
        if (blocoAtual) {
            paragrafos.push(blocoAtual.trim());
        }

        if (paragrafos.length === 0) {
            paragrafos.push(t);
        }

        return paragrafos
            .map(p => `<p style="margin: 0 0 0.85rem 0; line-height: 1.65; color: var(--text-main); font-size: 0.92rem; text-align: justify;">${escapeHtml(p)}</p>`)
            .join('');
    };

    const objetivoEl = document.getElementById('drawerDetObjetivo');
    if (objetivoEl) {
        objetivoEl.innerHTML = formatarResumoLimpo(edital.objetivo);
    }
    setTexto('drawerDetTarget', edital.target);
    setTexto('drawerDetRegion', edital.region);
    setTexto('drawerDetNicho', edital.nicho);
    setTexto('drawerDetFaturamento', edital.faturamento);
    setTexto('drawerDetTrl', edital.trl);
    setTexto('drawerDetOpen', edital.openDate);
    const closeStr = String(edital.closeDate || edital.deadline || '').trim();
    if (closeStr.includes('1970') || closeStr.includes('0000') || !closeStr || closeStr === '--') {
        setTexto('drawerDetClose', 'Fluxo contínuo');
    } else {
        setTexto('drawerDetClose', closeStr);
    }
    setTexto('drawerDetResult', edital.resultDate);

    // Análise determinística de Elegibilidade e Parcerias (Custo Zero de IA)
    const textoContexto = `${edital.name || ''} ${edital.target || ''} ${edital.objetivo || ''}`.toLowerCase();
    
    // 1. Exigência de ICT
    let exigeIct = 'Não obrigatório';
    if (textoContexto.includes('obrigatoriamente') && (textoContexto.includes('ict') || textoContexto.includes('instituição de pesquisa') || textoContexto.includes('universidade'))) {
        exigeIct = 'Sim, obrigatória';
    } else if (textoContexto.includes('com ict') || textoContexto.includes('parceria com ict') || textoContexto.includes('em conjunto com ict') || textoContexto.includes('cooperação com ict')) {
        exigeIct = 'Exigida na chamada';
    } else if (textoContexto.includes('apenas ict') || textoContexto.includes('somente ict')) {
        exigeIct = 'Sim (proponente ICT)';
    }

    // 2. Empresa pode concorrer sozinha?
    let empresaSozinha = 'Sim (Executora única)';
    if (exigeIct === 'Sim, obrigatória' || exigeIct === 'Sim (proponente ICT)' || textoContexto.includes('consórcio obrigatório') || textoContexto.includes('obrigatoriamente em parceria')) {
        empresaSozinha = 'Não (Exige parceria)';
    } else if (textoContexto.includes('apenas empresas') || textoContexto.includes('startups') || textoContexto.includes('microempresa') || textoContexto.includes('subvenção individual')) {
        empresaSozinha = 'Sim (Independente)';
    }

    // 3. Parceria / Consórcio
    let parceria = 'Opcional';
    if (textoContexto.includes('cooperação internacional') || textoContexto.includes('rede eureka') || textoContexto.includes('chamada bilateral') || textoContexto.includes('consórcio')) {
        parceria = 'Obrigatória / Consórcio';
    } else if (textoContexto.includes('parceria recomendada') || textoContexto.includes('pontuação extra') || textoContexto.includes('cooperação')) {
        parceria = 'Opcional (bonifica)';
    }

    setTexto('drawerDetExigeIct', exigeIct);
    setTexto('drawerDetEmpresaSozinha', empresaSozinha);
    setTexto('drawerDetParceria', parceria);

    configurarBotaoUrlOficial(document.getElementById('drawerDetUrl'), edital.url);

    const matchVal = Number(edital.match || 0);
    const matchValEl = document.getElementById('drawerDetMatchVal');
    if (matchValEl) {
        matchValEl.textContent = `${matchVal}%`;
        matchValEl.className = `info-value ${obterClasseMatch(matchVal)}`;
    }
    const matchBar = document.getElementById('drawerDetMatchBar');
    if (matchBar) {
        matchBar.style.width = `${matchVal}%`;
        matchBar.style.backgroundColor = matchVal >= 75 ? 'var(--success-color)' : matchVal >= 50 ? 'var(--warning-color)' : 'var(--danger-color)';
    }

    atualizarBotaoFavoritoDrawer(edital);
    renderizarDocumentosNoPainel(document.getElementById('drawerDetDocs'), edital.documentos);
    renderizarDiagnosticoNoPainel(document.getElementById('drawerDetDiag'), edital.diagnosis);

    const backdrop = document.getElementById('searchDrawerBackdrop');
    if (backdrop) {
        backdrop.classList.add('open');
        backdrop.setAttribute('aria-hidden', 'false');
    }

    renderizarListaResultados();
}

window.selectEdital = abrirDrawerEdital;

function fecharDrawer() {
    drawerEditalId = null;
    const backdrop = document.getElementById('searchDrawerBackdrop');
    if (backdrop) {
        backdrop.classList.remove('open');
        backdrop.setAttribute('aria-hidden', 'true');
    }
    renderizarListaResultados();
}

function atualizarBotaoFavoritoDrawer(edital) {
    const btn = document.getElementById('drawerBtnFav');
    if (!btn || !edital) return;
    const useTag = btn.querySelector('use');
    btn.classList.toggle('active', Boolean(edital.favorite));
    if (useTag) useTag.setAttribute('href', edital.favorite ? '#icon-star-filled' : '#icon-star');
}

async function alternarFavoritoNaBusca(id) {
    const edital = catalogoEditais.find((e) => String(e.id) === String(id));
    if (!edital) return;

    const estadoAnterior = !!edital.favorite;
    edital.favorite = !estadoAnterior;
    atualizarContadoresLaterais();
    aplicarFiltrosERenderizar();
    if (String(drawerEditalId) === String(id)) atualizarBotaoFavoritoDrawer(edital);

    try {
        const startupAtiva = obterStartupAtiva();
        const json = await alternarFavoritoEdital(id, startupAtiva?.id || null);
        edital.favorite = !!json.favorito;
        atualizarContadoresLaterais();
        aplicarFiltrosERenderizar();
        if (String(drawerEditalId) === String(id)) atualizarBotaoFavoritoDrawer(edital);
    } catch (err) {
        console.error('Erro ao atualizar favorito:', err);
        edital.favorite = estadoAnterior;
        atualizarContadoresLaterais();
        aplicarFiltrosERenderizar();
        if (String(drawerEditalId) === String(id)) atualizarBotaoFavoritoDrawer(edital);
        exibirToastFeedback('Não foi possível salvar o favorito.', { tipo: 'erro' });
    }
}

async function enviarParaKanbanComFeedback(editalId, botao) {
    const textoOriginal = botao ? botao.textContent : '';
    if (botao) {
        botao.disabled = true;
        botao.textContent = 'Enviando...';
    }

    const startupAtiva = obterStartupAtiva();
    const nomeStartup = startupAtiva ? startupAtiva.nome : 'Startup Assessorada';

    try {
        await enviarEditalParaKanban(editalId, nomeStartup);
        const irParaKanban = await exibirModalConfirmacao({
            titulo: 'Edital enviado com sucesso',
            mensagem: `O edital foi vinculado à <strong>${escapeHtml(nomeStartup)}</strong> e adicionado na coluna <strong>Backlog</strong> do Pipeline Kanban. Deseja abrir o quadro agora?`,
            textoConfirmar: 'Abrir Kanban',
            textoCancelar: 'Continuar na Busca',
            corConfirmar: '#10b981',
            icone: '📋'
        });

        if (irParaKanban) {
            window.location.href = 'kanban.html';
        }
    } catch (err) {
        console.error(err);
        await exibirModalAlerta({
            titulo: 'Não foi possível enviar',
            mensagem: err.message || 'Falha ao enviar edital para o Kanban.',
            tipo: 'erro'
        });
    } finally {
        if (botao) {
            botao.disabled = false;
            botao.textContent = textoOriginal;
        }
    }
}

function limparTodosOsFiltros() {
    estadoFiltros.termo = '';
    estadoFiltros.fontes.clear();
    estadoFiltros.apenasFavoritos = false;
    estadoFiltros.matchMinimo = 0;
    estadoFiltros.orcamentoMinimo = 0;
    estadoFiltros.abrangencia = '';

    const input = document.getElementById('searchKeywordInput');
    if (input) input.value = '';
    document.getElementById('btnLimparBusca')?.classList.remove('visible');
    document.querySelectorAll('.filter-fonte-check').forEach((chk) => { chk.checked = false; });

    const chkFav = document.getElementById('filterApenasFavoritos');
    if (chkFav) chkFav.checked = false;

    const selMatch = document.getElementById('filterMatchMinimo');
    if (selMatch) selMatch.value = '0';

    const selOrc = document.getElementById('filterOrcamentoMinimo');
    if (selOrc) selOrc.value = '0';

    const selAbr = document.getElementById('filterAbrangencia');
    if (selAbr) selAbr.value = '';

    aplicarFiltrosERenderizar();
}

function renderizarCarregamento() {
    const container = document.getElementById('searchResultsList');
    if (container) {
        container.innerHTML = '<div class="empty-state-box"><div class="empty-state-title">Carregando catálogo de editais...</div></div>';
    }
}

function renderizarErro() {
    const container = document.getElementById('searchResultsList');
    if (container) {
        container.innerHTML = `
            <div class="empty-state-box">
                <div class="empty-state-title" style="color: var(--danger-color);">Não foi possível consultar os editais</div>
                <p class="empty-state-desc">Verifique a conexão com a API e tente novamente.</p>
                <button type="button" class="btn-outline" id="btnRetrySearch">Tentar novamente</button>
            </div>
        `;
        document.getElementById('btnRetrySearch')?.addEventListener('click', carregarCatalogo);
    }
}

function truncarTexto(texto, limite) {
    const str = String(texto || '');
    return str.length > limite ? `${str.slice(0, limite)}...` : str;
}
