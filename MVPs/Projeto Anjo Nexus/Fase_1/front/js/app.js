let mockEditais = [];
let selectedId = null;

// ==========================================
// INICIALIZAÇÃO DA APLICAÇÃO
// ==========================================
document.addEventListener('DOMContentLoaded', () => {
    initUI();
    fetchEditais();
});

function initUI() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('toggleSidebar');
    if (toggleBtn) toggleBtn.addEventListener('click', () => sidebar.classList.toggle('collapsed'));

    const userMenuBtn = document.getElementById('userMenuBtn');
    const mainDropdown = document.getElementById('mainDropdown');
    const manageProfilesBtn = document.getElementById('manageProfilesBtn');
    const profilesSubmenu = document.getElementById('profilesSubmenu');

    if (userMenuBtn) {
        userMenuBtn.addEventListener('click', (e) => {
            if (e.target.closest('.dropdown-menu')) return;
            mainDropdown.classList.toggle('active');
            if (!mainDropdown.classList.contains('active')) profilesSubmenu.classList.remove('active');
        });
    }

    if (manageProfilesBtn) {
        manageProfilesBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            profilesSubmenu.classList.toggle('active');
        });
    }

    document.addEventListener('click', (e) => {
        if (userMenuBtn && !userMenuBtn.contains(e.target)) {
            if (mainDropdown) mainDropdown.classList.remove('active');
            if (profilesSubmenu) profilesSubmenu.classList.remove('active');
        }
    });

    const btnFavDetail = document.getElementById('btnFavDetail');
    if (btnFavDetail) {
        btnFavDetail.addEventListener('click', () => {
            if (selectedId) toggleFavorite(selectedId);
        });
    }
}

// ==========================================
// SERVIÇOS DE API (Busca de Dados)
// ==========================================
async function fetchEditais() {
    showLoadingState();

    try {
        const response = await fetch('http://127.0.0.1:8000/api/editais');

        if (!response.ok) {
            throw new Error(`Erro HTTP: ${response.status}`);
        }

        const data = await response.json();

        // O PHP JsonResource / Collection as vezes devolve Objeto indexado quando filtramos.
        // Transformamos sempre em Array para usar map/forEach sem erros.
        mockEditais = Array.isArray(data) ? data : Object.values(data);

        if (mockEditais.length > 0) {
            renderTable();
            selectEdital(mockEditais[0].id);
        } else {
            showEmptyState();
        }

    } catch (error) {
        console.error('Falha ao buscar os editais da API:', error);
        showErrorState();
    }
}

// ==========================================
// RENDERIZAÇÃO DA INTERFACE (Componentes)
// ==========================================
function renderTable() {
    const tbody = document.getElementById('tableBody');
    if (!tbody) return;

    tbody.innerHTML = '';

    mockEditais.forEach(edital => {
        const tr = document.createElement('tr');
        tr.id = `row-${edital.id}`;
        if (edital.id === selectedId) tr.classList.add('selected');

        tr.addEventListener('click', (e) => {
            if (!e.target.closest('.btn-icon')) selectEdital(edital.id);
        });

        const starIcon = edital.favorite ? '#icon-star-filled' : '#icon-star';
        const starActive = edital.favorite ? 'active' : '';

        // Fallbacks simples para valores undefined (clean code)
        const name = edital.name || '--';
        const org = edital.org || '--';
        const budget = edital.budget || '--';
        const deadline = edital.deadline || '--';
        const status = edital.status || '--';
        const statusClass = edital.statusClass || '';
        const matchValue = edital.match || 0;

        tr.innerHTML = `
            <td><div class="edital-name">${name}</div><div class="edital-org">${org}</div></td>
            <td class="budget">${budget}</td>
            <td class="deadline">${deadline}</td>
            <td><span class="badge ${statusClass}">${status}</span></td>
            <td class="${getMatchColorClass(matchValue)}">${matchValue}%</td>
            <td>
                <button class="btn-icon ${starActive}" onclick="toggleFavorite(${edital.id})">
                    <svg width="20" height="20"><use href="${starIcon}"></use></svg>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function selectEdital(id) {
    selectedId = id;

    // Atualiza marcação visual na tabela
    document.querySelectorAll('#tableBody tr').forEach(row => row.classList.remove('selected'));
    const selectedRow = document.getElementById(`row-${id}`);
    if (selectedRow) selectedRow.classList.add('selected');

    // Recupera dados
    const edital = mockEditais.find(e => e.id === id);
    if (!edital) return;

    // Exibe painel lateral
    const detailPanel = document.getElementById('detailPanel');
    if (detailPanel) detailPanel.style.display = 'flex';

    // Preenche campos de texto simples
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

    // Atualiza o link do botão oficial
    const detUrlBtn = document.getElementById('detUrl');
    if (detUrlBtn) {
        detUrlBtn.href = edital.url || '#';
    }

    // Preenche barra de match
    const matchVal = edital.match || 0;
    const matchValEl = document.getElementById('detMatchVal');
    if (matchValEl) {
        matchValEl.textContent = `${matchVal}%`;
        matchValEl.className = `info-value ${getMatchColorClass(matchVal)}`;
    }

    const matchBar = document.getElementById('detMatchBar');
    if (matchBar) {
        matchBar.style.width = `${matchVal}%`;
        matchBar.style.backgroundColor = getMatchHexColor(matchVal);
    }
    
    // Atualiza icone de favorito no painel
    const btnFavDetail = document.getElementById('btnFavDetail');
    if (btnFavDetail) {
        const useTag = btnFavDetail.querySelector('use');
        if (edital.favorite) {
            btnFavDetail.classList.add('active');
            if(useTag) useTag.setAttribute('href', '#icon-star-filled');
        } else {
            btnFavDetail.classList.remove('active');
            if(useTag) useTag.setAttribute('href', '#icon-star');
        }
    }

    // Renderiza blocos complexos
    renderDocuments(edital.documentos);
    renderDiagnosis(edital.diagnosis);
}

function renderDocuments(documentos) {
    const docList = document.getElementById('detDocs');
    if (!docList) return;

    docList.innerHTML = '';

    if (Array.isArray(documentos) && documentos.length > 0) {
        documentos.forEach(doc => {
            const div = document.createElement('div');
            div.className = 'doc-item';
            div.innerHTML = `
                <div class="doc-info">
                    <svg width="20" height="20" style="fill: var(--accent-blue);"><use href="#icon-file"></use></svg>
                    <span class="doc-title">${doc.titulo || 'Documento'}</span>
                </div>
                <a href="${doc.link || '#'}" target="_blank" class="doc-action">
                    <svg width="16" height="16"><use href="#icon-download"></use></svg>
                </a>
            `;
            docList.appendChild(div);
        });
    } else {
        docList.innerHTML = '<div style="color: #666; font-size: 0.9rem;">Nenhum documento disponível</div>';
    }
}

function renderDiagnosis(diagnosisList) {
    const diagUl = document.getElementById('detDiag');
    if (!diagUl) return;

    diagUl.innerHTML = '';

    if (Array.isArray(diagnosisList) && diagnosisList.length > 0) {
        diagnosisList.forEach(item => {
            const li = document.createElement('li');
            li.className = `diag-item diag-${item.type}`;
            li.textContent = item.text;
            diagUl.appendChild(li);
        });
    }
}

// ==========================================
// FUNÇÕES UTILITÁRIAS E ESTADOS
// ==========================================
function updateElementText(elementId, text) {
    const el = document.getElementById(elementId);
    if (el) el.textContent = text || '--';
}

function getMatchColorClass(match) {
    const value = match || 0;
    if (value >= 80) return 'match-high';
    if (value >= 50) return 'match-medium';
    return 'match-low';
}

function getMatchHexColor(match) {
    const value = match || 0;
    if (value >= 80) return 'var(--success-color)';
    if (value >= 50) return 'var(--accent-purple)';
    return 'var(--danger-color)';
}

function toggleFavorite(id) {
    const edital = mockEditais.find(e => e.id === id);
    if (edital) {
        edital.favorite = !edital.favorite;
        renderTable();
        // TODO: Enviar requisição POST/PUT para a API atualizar o banco
    }
}

function showLoadingState() {
    const tbody = document.getElementById('tableBody');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 40px; color: #a0a0a0;">Carregando editais...</td></tr>`;
    }
}

function showErrorState() {
    const tbody = document.getElementById('tableBody');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 40px; color: #ff4d4d;">Falha ao comunicar com a API. Tente novamente mais tarde.</td></tr>`;
    }
}

function showEmptyState() {
    const tbody = document.getElementById('tableBody');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 40px; color: #a0a0a0;">Nenhum edital encontrado.</td></tr>`;
    }
}
