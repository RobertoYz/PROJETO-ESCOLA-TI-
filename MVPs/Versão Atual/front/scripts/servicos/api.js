// Detecta automaticamente se está rodando localmente no navegador ou em produção/APK
const ehAmbienteLocal = typeof window !== 'undefined' &&
    (window.location.hostname === '127.0.0.1' || window.location.hostname === 'localhost') &&
    !window.Capacitor && window.location.protocol !== 'capacitor:';

const URL_PADRAO = ehAmbienteLocal
    ? 'http://127.0.0.1:8000/api'
    : 'https://anjonexus.intalenti.com.br/api';

const URL_BASE_API = localStorage.getItem('api_base_url_custom') || URL_PADRAO;

if (typeof window !== 'undefined') {
    const marcarCapacitor = () => {
        if (window.Capacitor || window.location.protocol === 'capacitor:' || window.navigator.userAgent.includes('wv')) {
            document.body?.classList.add('is-capacitor');
        }
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', marcarCapacitor);
    } else {
        marcarCapacitor();
    }
}

export async function requisicaoApi(endpoint, opcoes = {}) {
    const tokenPendencia = sessionStorage.getItem('token_pendencia') || localStorage.getItem('token_pendencia');
    const tokenAutenticacao = localStorage.getItem('token_autenticacao');
    const emailPendencia = sessionStorage.getItem('email_pendencia') || localStorage.getItem('email_pendencia');

    const ehFluxoPendencia = endpoint.includes('/pagamentos/consultar-anterior')
        || endpoint.includes('/planos/gerar-nova-cobranca')
        || endpoint.includes('/auth/cancelar-cadastro-pendente')
        || (typeof window !== 'undefined' && (
            window.location.pathname.includes('pagamento-pendente')
            || window.location.search.includes('modo=renovacao')
        ));

    const token = ehFluxoPendencia
        ? (tokenPendencia || tokenAutenticacao)
        : (tokenAutenticacao || tokenPendencia);

    const startupAtivaId = localStorage.getItem('startup_ativa_id');

    const cabecalhos = {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        ...(opcoes.headers || {})
    };

    if (token && !cabecalhos['Authorization']) {
        cabecalhos['Authorization'] = `Bearer ${token}`;
    }

    if (ehFluxoPendencia && tokenPendencia && !cabecalhos['X-Pending-Token']) {
        cabecalhos['X-Pending-Token'] = tokenPendencia;
    }

    if (ehFluxoPendencia && emailPendencia && !cabecalhos['X-Pending-Email']) {
        cabecalhos['X-Pending-Email'] = emailPendencia;
    }

    if (startupAtivaId && !cabecalhos['X-Startup-Id']) {
        cabecalhos['X-Startup-Id'] = startupAtivaId;
    }

    if (typeof window !== 'undefined' && window.location?.href) {
        cabecalhos['X-Frontend-Url'] = window.location.href;
    }

    const resposta = await fetch(`${URL_BASE_API}${endpoint}`, {
        ...opcoes,
        headers: cabecalhos
    });

    let dados = null;
    try {
        dados = await resposta.json();
    } catch (erro) {
        dados = {};
    }

    if (!resposta.ok) {
        let mensagemErro = dados.mensagem || dados.message || 'Ocorreu um erro na comunicação com o servidor.';
        if (dados.errors) {
            const primeiroErro = Object.values(dados.errors)[0];
            if (Array.isArray(primeiroErro) && primeiroErro.length > 0) {
                mensagemErro = primeiroErro[0];
            }
        }
        const erroPersonalizado = new Error(mensagemErro);
        erroPersonalizado.status = resposta.status;
        erroPersonalizado.dados = dados;
        throw erroPersonalizado;
    }

    return dados;
}

export const requisitarApi = requisicaoApi;
