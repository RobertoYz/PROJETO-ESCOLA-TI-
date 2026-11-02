// Endereço oficial da API em nuvem (pode ser sobrescrito para testes via localStorage)
const URL_BASE_API = localStorage.getItem('api_base_url_custom') || 'https://anjonexus.intalenti.com.br/api';

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
    const token = localStorage.getItem('token_autenticacao');
    const startupAtivaId = localStorage.getItem('startup_ativa_id');

    const cabecalhos = {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        ...(opcoes.headers || {})
    };

    if (token) {
        cabecalhos['Authorization'] = `Bearer ${token}`;
    }

    if (startupAtivaId) {
        cabecalhos['X-Startup-Id'] = startupAtivaId;
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
        throw new Error(mensagemErro);
    }

    return dados;
}

export const requisitarApi = requisicaoApi;
