/**
 * ANJONEXUS MOBILE — CLIENTE CENTRAL DE API REST (CAPACITOR / VANILLA JS)
 * Suporte a ambiente híbrido Android (10.0.2.2), Wi-Fi local e navegador Web.
 */

const MobileApi = (function() {
  const STORAGE_KEY_API_URL = 'anjonexus_mobile_api_url';
  const STORAGE_KEY_TOKEN = 'token';
  const STORAGE_KEY_STARTUP = 'startup_ativa';

  /**
   * Determina a URL padrão ideal conforme a plataforma
   */
  function getDefaultApiUrl() {
    const isCapacitorAndroid = window.Capacitor && window.Capacitor.getPlatform() === 'android';
    if (isCapacitorAndroid) {
      return 'http://10.0.2.2:8000/api';
    }
    return 'http://127.0.0.1:8000/api';
  }

  function getBaseUrl() {
    return localStorage.getItem(STORAGE_KEY_API_URL) || getDefaultApiUrl();
  }

  function setBaseUrl(url) {
    let cleanUrl = (url || '').trim().replace(/\/+$/, '');
    if (!cleanUrl.endsWith('/api')) {
      cleanUrl += '/api';
    }
    localStorage.setItem(STORAGE_KEY_API_URL, cleanUrl);
    return cleanUrl;
  }

  function getToken() {
    return localStorage.getItem(STORAGE_KEY_TOKEN) || '';
  }

  function setToken(token) {
    if (token) {
      localStorage.setItem(STORAGE_KEY_TOKEN, token);
    } else {
      localStorage.removeItem(STORAGE_KEY_TOKEN);
    }
  }

  function getStartupAtiva() {
    const data = localStorage.getItem(STORAGE_KEY_STARTUP);
    if (!data) return { id: 1, nome: 'Startup Inovadora Demo', cnpj: '53.642.859/0001-44' };
    try {
      return JSON.parse(data);
    } catch {
      return { id: 1, nome: 'Startup Inovadora Demo', cnpj: '53.642.859/0001-44' };
    }
  }

  /**
   * Requisição central com suporte a timeout, token Sanctum e tratamento de erro
   */
  async function request(endpoint, options = {}) {
    const baseUrl = getBaseUrl();
    const cleanEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
    const url = `${baseUrl}${cleanEndpoint}`;

    const headers = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      ...(options.headers || {})
    };

    const token = getToken();
    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), options.timeout || 12000);

    try {
      const response = await fetch(url, {
        ...options,
        headers,
        signal: controller.signal
      });
      clearTimeout(timeoutId);

      const contentType = response.headers.get('content-type') || '';
      let data = null;
      if (contentType.includes('application/json')) {
        data = await response.json();
      } else {
        data = await response.text();
      }

      if (!response.ok) {
        throw new Error(data?.message || data?.mensagem || `Erro HTTP ${response.status}`);
      }

      return data;
    } catch (err) {
      clearTimeout(timeoutId);
      if (err.name === 'AbortError') {
        throw new Error('Tempo de requisição esgotado (Timeout). Verifique se o backend está rodando.');
      }
      throw err;
    }
  }

  // Métodos de Negócio
  return {
    getBaseUrl,
    setBaseUrl,
    getToken,
    setToken,
    getStartupAtiva,
    request,

    // Testar conectividade com o backend
    async checkHealth() {
      try {
        const res = await request('/planos', { timeout: 4000 });
        return { online: true, data: res };
      } catch (err) {
        return { online: false, error: err.message };
      }
    },

    // Buscar lista de editais com filtro opcional de fonte
    async getEditais(fonte = '') {
      let endpoint = '/editais';
      if (fonte && fonte !== 'all') {
        endpoint += `?fonte=${encodeURIComponent(fonte)}`;
      }
      return await request(endpoint);
    },

    // Buscar quadro kanban da startup ativa
    async getKanban(startupName) {
      const name = startupName || getStartupAtiva().nome;
      return await request(`/kanban?startup_name=${encodeURIComponent(name)}`);
    },

    // Mover card no pipeline
    async moveCard(cardId, targetColId) {
      return await request(`/kanban/cards/${cardId}/mover`, {
        method: 'PATCH',
        body: JSON.stringify({
          coluna_id: targetColId,
          ordem: 0
        })
      });
    },

    // Adicionar edital ao pipeline da startup
    async addEditalToPipeline(editalId, titulo, startupName) {
      const startup = startupName || getStartupAtiva().nome;
      return await request('/kanban/cards', {
        method: 'POST',
        body: JSON.stringify({
          titulo: titulo,
          startup_name: startup,
          coluna_id: 1, // 'A Fazer'
          metadata: {
            edital_id: editalId,
            origem: 'mobile_app',
            data_adicao: new Date().toISOString()
          }
        })
      });
    }
  };
})();
