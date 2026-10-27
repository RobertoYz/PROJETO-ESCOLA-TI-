/**
 * ANJONEXUS MOBILE — LÓGICA PRINCIPAL DA APLICAÇÃO (SPA TOUCH-FRIENDLY)
 * Requisitos: RF-28 (Visualização de Editais e Pipeline), RF-47 (Central de Alertas), RNF-06 (Ergonomia Mobile)
 */

document.addEventListener('DOMContentLoaded', () => {
  // Estado Local
  let todosEditais = [];
  let editaisFiltrados = [];
  let editalSelecionado = null;
  let pipelineBoard = null;
  let colunaAtiva = 'a_fazer'; // a_fazer, em_andamento, submetido, aprovado
  
  let alertas = [
    {
      id: 1,
      tipo: 'warning',
      titulo: 'Prazo Próximo: Edital FINEP Inovação Aberta',
      mensagem: 'O prazo de submissão encerra em 5 dias corridos. Revise seus anexos!',
      tempo: 'Há 15 minutos'
    },
    {
      id: 2,
      tipo: 'success',
      titulo: 'Novo Edital Identificado: FAPESC Mulheres Inovadoras',
      mensagem: 'Aderência estimada de 92% com o perfil da sua startup. Subvenção de até R$ 250.000,00.',
      tempo: 'Há 1 hora'
    },
    {
      id: 3,
      tipo: 'blue',
      titulo: 'Minuta Gerada via IA (RF-29)',
      mensagem: 'A proposta técnica para o Edital SEIA foi estruturada com sucesso no modelo institucional.',
      tempo: 'Há 3 horas'
    }
  ];

  // Elementos do DOM
  const navItems = document.querySelectorAll('.bottom-nav .nav-item');
  const tabPanes = document.querySelectorAll('.tab-pane');
  const connBadge = document.getElementById('connStatusBadge');
  const connText = document.getElementById('connStatusText');
  const editaisContainer = document.getElementById('editaisListContainer');
  const editaisCounter = document.getElementById('editaisCounter');
  const searchInput = document.getElementById('editalSearchInput');
  const btnClearSearch = document.getElementById('btnClearSearch');
  const filterPills = document.querySelectorAll('.filter-pills-scroll .pill');
  const btnRefreshEditais = document.getElementById('btnRefreshEditais');

  // Modais
  const modalEdital = document.getElementById('modalEdital');
  const btnCloseModalEdital = document.getElementById('btnCloseModalEdital');
  const modalApiConfig = document.getElementById('modalApiConfig');
  const btnOpenApiConfig = document.getElementById('btnOpenApiConfig');
  const btnOpenApiConfigProfile = document.getElementById('btnOpenApiConfigProfile');
  const btnCloseApiConfig = document.getElementById('btnCloseApiConfig');
  const inputApiUrl = document.getElementById('inputApiUrl');
  const btnSaveApiConfig = document.getElementById('btnSaveApiConfig');
  const btnTestApiConnection = document.getElementById('btnTestApiConnection');
  const profileApiUrlDisplay = document.getElementById('profileApiUrlDisplay');

  // Inicialização
  initCapacitorNativePlugins();
  initNavigation();
  initApiConfig();
  carregarEditais();
  carregarPipeline();
  renderAlertas();

  // =========================================================================
  // 1. INICIALIZAÇÃO DE PLUGINS CAPACITOR NATIVOS
  // =========================================================================
  function initCapacitorNativePlugins() {
    if (window.Capacitor) {
      console.log('📱 Capacitor detectado rodando na plataforma:', window.Capacitor.getPlatform());
      
      // Hardware Back Button (Android)
      if (window.Capacitor.Plugins && window.Capacitor.Plugins.App) {
        window.Capacitor.Plugins.App.addListener('backButton', () => {
          if (modalEdital.classList.contains('open')) {
            fecharModalEdital();
          } else if (modalApiConfig.classList.contains('open')) {
            modalApiConfig.classList.remove('open');
          } else {
            // Se estiver na tela principal, confirma antes de sair
            if (confirm('Deseja sair do AnjoNexus Mobile?')) {
              window.Capacitor.Plugins.App.exitApp();
            }
          }
        });
      }

      // Splash Screen
      if (window.Capacitor.Plugins && window.Capacitor.Plugins.SplashScreen) {
        setTimeout(() => {
          window.Capacitor.Plugins.SplashScreen.hide();
        }, 1200);
      }
    }
  }

  // =========================================================================
  // 2. NAVEGAÇÃO ENTRE ABAS (BOTTOM NAVIGATION BAR)
  // =========================================================================
  function initNavigation() {
    navItems.forEach(item => {
      item.addEventListener('click', () => {
        const targetTab = item.getAttribute('data-tab');
        
        // Atualiza estilo dos botões da nav
        navItems.forEach(i => i.classList.remove('active'));
        item.classList.add('active');

        // Alterna o container visível
        tabPanes.forEach(pane => pane.classList.remove('active'));
        const activePane = document.getElementById(targetTab);
        if (activePane) {
          activePane.classList.add('active');
          window.scrollTo(0, 0);
        }

        // Se abriu a aba do pipeline ou perfil, atualiza os dados
        if (targetTab === 'tab-pipeline') {
          carregarPipeline();
        } else if (targetTab === 'tab-perfil') {
          atualizarDadosPerfil();
        }
      });
    });
  }

  // =========================================================================
  // 3. RADAR DE EDITAIS (BUSCA, FILTROS E RENDERIZAÇÃO)
  // =========================================================================
  async function carregarEditais(fonte = '') {
    atualizarStatusApi(true, 'Consultando API...');
    editaisContainer.innerHTML = `
      <div class="loading-skeleton">
        <div class="skeleton-card"></div>
        <div class="skeleton-card"></div>
        <div class="skeleton-card"></div>
      </div>
    `;

    try {
      const data = await MobileApi.getEditais(fonte);
      todosEditais = Array.isArray(data) ? data : (data.data || []);
      editaisFiltrados = [...todosEditais];
      renderListaEditais();
      atualizarStatusApi(true, 'API Online');
    } catch (err) {
      console.error('Erro ao buscar editais:', err);
      atualizarStatusApi(false, 'API Desconectada');
      editaisContainer.innerHTML = `
        <div class="empty-state">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="1.8">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <p style="color: #ef4444; font-weight: 600;">Falha na conexão com o servidor.</p>
          <span style="font-size: 0.75rem; color: #94a3b8;">Verifique se a API Laravel está rodando em <code>${MobileApi.getBaseUrl()}</code></span>
          <button id="btnRetryConn" class="btn-primary-action" style="margin-top: 0.5rem; width: auto; padding: 0.5rem 1.25rem;">
            Tentar Novamente
          </button>
        </div>
      `;
      document.getElementById('btnRetryConn')?.addEventListener('click', () => carregarEditais(fonte));
    }
  }

  function renderListaEditais() {
    if (editaisFiltrados.length === 0) {
      editaisContainer.innerHTML = `
        <div class="empty-state">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <p>Nenhum edital encontrado para os critérios selecionados.</p>
        </div>
      `;
      editaisCounter.textContent = '0 editais encontrados';
      return;
    }

    editaisCounter.textContent = `${editaisFiltrados.length} oportunidades encontradas`;

    editaisContainer.innerHTML = editaisFiltrados.map(edital => {
      const matchScore = edital.match || Math.floor(Math.random() * 25 + 75);
      return `
        <div class="edital-card" data-id="${edital.id}">
          <div class="edital-card-header">
            <div class="badge-row">
              <span class="badge badge-agency">${edital.org || 'FINEP'}</span>
              <span class="badge badge-budget">${edital.budget || 'A definir'}</span>
              <span class="badge badge-match">${matchScore}% Match</span>
            </div>
          </div>

          <h3 class="card-title">${edital.name || 'Edital de Inovação'}</h3>
          <p class="card-description">${edital.objetivo || 'Clique para visualizar os detalhes completos, prazos de submissão e links.'}</p>

          <div class="card-footer-meta">
            <div class="meta-item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
              </svg>
              <span>Prazo: ${edital.deadline || 'Consultar'}</span>
            </div>
            <div class="meta-item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                <circle cx="12" cy="10" r="3"></circle>
              </svg>
              <span>${edital.region || 'Nacional'}</span>
            </div>
          </div>
        </div>
      `;
    }).join('');

    // Adiciona evento de clique para abrir o Bottom Sheet
    document.querySelectorAll('.edital-card').forEach(cardEl => {
      cardEl.addEventListener('click', () => {
        const id = cardEl.getAttribute('data-id');
        const item = todosEditais.find(e => String(e.id) === String(id));
        if (item) {
          abrirModalEdital(item);
        }
      });
    });
  }

  // Filtro de Busca em Tempo Real
  searchInput.addEventListener('input', (e) => {
    const termo = e.target.value.toLowerCase().trim();
    btnClearSearch.style.display = termo ? 'block' : 'none';

    editaisFiltrados = todosEditais.filter(edital => {
      const nome = (edital.name || '').toLowerCase();
      const org = (edital.org || '').toLowerCase();
      const objetivo = (edital.objetivo || '').toLowerCase();
      const nicho = (edital.nicho || '').toLowerCase();
      return nome.includes(termo) || org.includes(termo) || objetivo.includes(termo) || nicho.includes(termo);
    });

    renderListaEditais();
  });

  btnClearSearch.addEventListener('click', () => {
    searchInput.value = '';
    btnClearSearch.style.display = 'none';
    editaisFiltrados = [...todosEditais];
    renderListaEditais();
  });

  // Filtro por Agência (Pills)
  filterPills.forEach(pill => {
    pill.addEventListener('click', () => {
      filterPills.forEach(p => p.classList.remove('active'));
      pill.classList.add('active');
      const filtro = pill.getAttribute('data-filter');
      carregarEditais(filtro === 'all' ? '' : filtro);
    });
  });

  btnRefreshEditais.addEventListener('click', () => carregarEditais());

  // =========================================================================
  // 4. MODAL DETALHES DO EDITAL (BOTTOM SHEET)
  // =========================================================================
  function abrirModalEdital(edital) {
    editalSelecionado = edital;
    document.getElementById('modalEditalAgency').textContent = edital.org || 'FINEP';
    document.getElementById('modalEditalBudget').textContent = edital.budget || 'A definir';
    document.getElementById('modalEditalMatch').textContent = `${edital.match || 90}% Match`;
    document.getElementById('modalEditalTitle').textContent = edital.name || 'Título do Edital';
    document.getElementById('modalEditalOpenDate').textContent = edital.openDate || '--';
    document.getElementById('modalEditalDeadline').textContent = edital.deadline || '--';
    document.getElementById('modalEditalTarget').textContent = edital.target || 'Startups e Empresas';
    document.getElementById('modalEditalRegion').textContent = edital.region || 'Nacional';
    document.getElementById('modalEditalObjetivo').textContent = edital.objetivo || 'Sem descrição cadastrada.';

    const linkOficial = document.getElementById('btnModalEditalLink');
    if (edital.url && edital.url !== '#') {
      linkOficial.href = edital.url;
      linkOficial.style.display = 'block';
    } else {
      linkOficial.style.display = 'none';
    }

    modalEdital.classList.add('open');
  }

  function fecharModalEdital() {
    modalEdital.classList.remove('open');
  }

  btnCloseModalEdital.addEventListener('click', fecharModalEdital);
  modalEdital.addEventListener('click', (e) => {
    if (e.target === modalEdital) fecharModalEdital();
  });

  // Enviar para o Pipeline
  document.getElementById('btnModalSendToPipeline').addEventListener('click', async () => {
    if (!editalSelecionado) return;
    try {
      showToast('Enviando edital para o seu Pipeline...', 'info');
      await MobileApi.addEditalToPipeline(editalSelecionado.id, editalSelecionado.name);
      showToast('Oportunidade adicionada ao seu Kanban com sucesso!', 'success');
      fecharModalEdital();
      carregarPipeline();
    } catch (err) {
      showToast('Erro ao adicionar no pipeline: ' + err.message, 'error');
    }
  });

  // =========================================================================
  // 5. PIPELINE KANBAN (RF-28)
  // =========================================================================
  async function carregarPipeline() {
    const pipelineContainer = document.getElementById('pipelineCardsContainer');
    try {
      const res = await MobileApi.getKanban();
      pipelineBoard = res.data || res;
      renderPipelineCards();
    } catch (err) {
      console.warn('Erro ao carregar quadro do pipeline:', err);
      pipelineContainer.innerHTML = `
        <div class="empty-state">
          <p>Não foi possível carregar as etapas do funil no momento.</p>
        </div>
      `;
    }
  }

  function renderPipelineCards() {
    const container = document.getElementById('pipelineCardsContainer');
    if (!pipelineBoard || !pipelineBoard.colunas) {
      container.innerHTML = `
        <div class="empty-state">
          <p>Nenhuma oportunidade cadastrada no pipeline desta startup.</p>
        </div>
      `;
      return;
    }

    // Mapeamento das 4 colunas padrão
    const colMap = {
      'a_fazer': pipelineBoard.colunas[0] || { cards: [] },
      'em_andamento': pipelineBoard.colunas[1] || { cards: [] },
      'submetido': pipelineBoard.colunas[2] || { cards: [] },
      'aprovado': pipelineBoard.colunas[3] || { cards: [] }
    };

    // Atualiza badges numéricos nas abas das colunas
    document.getElementById('badgeCol1').textContent = colMap['a_fazer'].cards?.length || 0;
    document.getElementById('badgeCol2').textContent = colMap['em_andamento'].cards?.length || 0;
    document.getElementById('badgeCol3').textContent = colMap['submetido'].cards?.length || 0;
    document.getElementById('badgeCol4').textContent = colMap['aprovado'].cards?.length || 0;

    const cardsDaColuna = colMap[colunaAtiva]?.cards || [];

    if (cardsDaColuna.length === 0) {
      container.innerHTML = `
        <div class="empty-state">
          <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <rect x="3" y="3" width="18" height="18" rx="2"></rect>
            <line x1="9" y1="3" x2="9" y2="21"></line>
          </svg>
          <p>Nenhum card nesta etapa do funil.</p>
        </div>
      `;
      return;
    }

    container.innerHTML = cardsDaColuna.map(card => {
      const meta = card.metadata || {};
      const valorEstimado = meta.valor_estimado ? `R$ ${parseFloat(meta.valor_estimado).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}` : 'R$ A definir';
      const notas = meta.notas_internas || 'Nenhuma nota de submissão vinculada.';
      const anexosCount = Array.isArray(meta.anexos) ? meta.anexos.length : 0;

      return `
        <div class="pipeline-card" data-card-id="${card.id}">
          <div class="pipeline-card-title">${card.titulo}</div>
          <div class="badge-row">
            <span class="badge badge-budget">${valorEstimado}</span>
            <span class="badge badge-agency">${anexosCount} anexos</span>
          </div>
          <div class="pipeline-card-notes">${notas}</div>
          <div class="pipeline-card-actions">
            <span style="font-size: 0.72rem; color: #94a3b8;">Card #${card.id}</span>
            <button class="btn-move-col" onclick="avancarEtapaCard(${card.id}, ${card.coluna_id})">
              Avançar Etapa ➔
            </button>
          </div>
        </div>
      `;
    }).join('');
  }

  // Alternador de Coluna no Pipeline
  document.querySelectorAll('.col-tab').forEach(tabBtn => {
    tabBtn.addEventListener('click', () => {
      document.querySelectorAll('.col-tab').forEach(b => b.classList.remove('active'));
      tabBtn.classList.add('active');
      colunaAtiva = tabBtn.getAttribute('data-col');
      renderPipelineCards();
    });
  });

  document.getElementById('btnRefreshPipeline').addEventListener('click', carregarPipeline);

  // Função global para avançar card de coluna
  window.avancarEtapaCard = async function(cardId, currentColId) {
    if (!pipelineBoard || !pipelineBoard.colunas) return;
    const colunas = pipelineBoard.colunas;
    const currentIndex = colunas.findIndex(c => c.id === currentColId);
    if (currentIndex >= 0 && currentIndex < colunas.length - 1) {
      const nextCol = colunas[currentIndex + 1];
      try {
        await MobileApi.moveCard(cardId, nextCol.id);
        showToast(`Card movido para "${nextCol.nome}" com sucesso!`, 'success');
        carregarPipeline();
      } catch (err) {
        showToast('Erro ao mover card: ' + err.message, 'error');
      }
    } else {
      showToast('Este card já atingiu a fase final do funil!', 'info');
    }
  };

  // =========================================================================
  // 6. CENTRAL DE ALERTAS E NOTIFICAÇÕES (RF-47)
  // =========================================================================
  function renderAlertas() {
    const container = document.getElementById('alertasListContainer');
    const badge = document.getElementById('navAlertBadge');
    badge.textContent = alertas.length;

    container.innerHTML = alertas.map(a => `
      <div class="alerta-item">
        <div class="alerta-icon-box icon-box-${a.tipo}">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
          </svg>
        </div>
        <div class="alerta-content">
          <div class="alerta-title">${a.titulo}</div>
          <div class="alerta-msg">${a.mensagem}</div>
          <div class="alerta-time">${a.tempo}</div>
        </div>
      </div>
    `).join('');
  }

  // Simular Alerta em Tempo Real (Demonstração para a Banca)
  document.getElementById('btnSimulateAlert').addEventListener('click', () => {
    const novosAlertas = [
      {
        id: Date.now(),
        tipo: 'warning',
        titulo: 'Alerta de Edital: Chamada MCTI 2026',
        mensagem: 'Novo edital de Subvenção Econômica publicado hoje pelo Ministério da Ciência e Tecnologia.',
        tempo: 'Agora mesmo'
      },
      {
        id: Date.now() + 1,
        tipo: 'success',
        titulo: 'Etapa Aprovada no Funil',
        mensagem: 'O parecer preliminar da documentação da proposta FAPESC foi validado com sucesso.',
        tempo: 'Há 1 minuto'
      }
    ];

    const sortudo = novosAlertas[Math.floor(Math.random() * novosAlertas.length)];
    alertas.unshift(sortudo);
    renderAlertas();
    showToast(`🔔 Notificação (RF-47): ${sortudo.titulo}`, 'success');

    // Vibração háptica nativa se disponível no dispositivo
    if (navigator.vibrate) {
      navigator.vibrate([100, 50, 100]);
    }
  });

  // =========================================================================
  // 7. PERFIL E DADOS DA STARTUP
  // =========================================================================
  function atualizarDadosPerfil() {
    const startup = MobileApi.getStartupAtiva();
    document.getElementById('profileStartupName').textContent = startup.nome || 'Minha Startup';
    document.getElementById('profileCnpj').textContent = startup.cnpj || '53.642.859/0001-44';
    profileApiUrlDisplay.textContent = MobileApi.getBaseUrl();
  }

  document.getElementById('btnLogout').addEventListener('click', () => {
    if (confirm('Deseja realmente sair da aplicação?')) {
      MobileApi.setToken('');
      showToast('Sessão encerrada com sucesso.', 'info');
      setTimeout(() => location.reload(), 800);
    }
  });

  // =========================================================================
  // 8. CONFIGURAÇÃO DE IP DA API (TESTES BANCA TCC / DISPOSITIVO REAL)
  // =========================================================================
  function initApiConfig() {
    inputApiUrl.value = MobileApi.getBaseUrl();
    profileApiUrlDisplay.textContent = MobileApi.getBaseUrl();

    const openConfig = () => {
      inputApiUrl.value = MobileApi.getBaseUrl();
      modalApiConfig.classList.add('open');
    };

    btnOpenApiConfig.addEventListener('click', openConfig);
    btnOpenApiConfigProfile?.addEventListener('click', openConfig);
    btnCloseApiConfig.addEventListener('click', () => modalApiConfig.classList.remove('open'));

    // Botões de Preset
    document.querySelectorAll('.btn-preset').forEach(btn => {
      btn.addEventListener('click', () => {
        inputApiUrl.value = btn.getAttribute('data-url');
      });
    });

    // Testar Conexão
    btnTestApiConnection.addEventListener('click', async () => {
      btnTestApiConnection.textContent = 'Testando...';
      const urlTemporaria = inputApiUrl.value.trim();
      try {
        const res = await fetch(`${urlTemporaria}/planos`, { method: 'GET' });
        if (res.ok) {
          showToast('✅ Conexão estabelecida com sucesso com o Laravel!', 'success');
        } else {
          showToast(`⚠️ Servidor respondeu com código HTTP ${res.status}`, 'warning');
        }
      } catch (err) {
        showToast('❌ Falha na conexão: ' + err.message, 'error');
      } finally {
        btnTestApiConnection.textContent = 'Testar Conexão';
      }
    });

    // Salvar Configuração
    btnSaveApiConfig.addEventListener('click', () => {
      const novaUrl = inputApiUrl.value.trim();
      if (!novaUrl) {
        showToast('Informe uma URL válida para a API.', 'error');
        return;
      }
      MobileApi.setBaseUrl(novaUrl);
      profileApiUrlDisplay.textContent = novaUrl;
      modalApiConfig.classList.remove('open');
      showToast('Configuração de API salva! Recarregando dados...', 'success');
      carregarEditais();
      carregarPipeline();
    });
  }

  function atualizarStatusApi(online, texto) {
    if (online) {
      connBadge.className = 'status-badge status-online';
      connText.textContent = texto || 'API Online';
    } else {
      connBadge.className = 'status-badge status-offline';
      connText.textContent = texto || 'Offline';
    }
  }

  // =========================================================================
  // 9. TOAST NOTIFICATIONS (MENSAGENS FLUTUANTES)
  // =========================================================================
  function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
      <span>${message}</span>
    `;
    const container = document.getElementById('toastContainer');
    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transition = 'opacity 0.3s ease';
      setTimeout(() => toast.remove(), 300);
    }, 3500);
  }
});
