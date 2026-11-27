// Detecta se o aplicativo está rodando em ambiente Capacitor (APK Android)
const ehCapacitorApp = typeof window !== 'undefined' && (
    window.Capacitor !== undefined ||
    window.location.protocol === 'capacitor:' ||
    (window.location.hostname === 'localhost' && window.location.protocol === 'https:') ||
    (typeof navigator !== 'undefined' && navigator.userAgent && (navigator.userAgent.includes('wv') || navigator.userAgent.includes('Capacitor')))
);

// Se for Capacitor (APK no celular) ou domínio de produção, SEMPRE consome o servidor oficial na nuvem!
// Só usa localhost se for explicitamente navegador desktop de desenvolvimento
const ehDevNavegadorLocal = typeof window !== 'undefined' &&
    !ehCapacitorApp &&
    (
        window.location.hostname === '127.0.0.1' ||
        window.location.hostname === 'localhost' ||
        window.location.protocol === 'file:' ||
        window.location.hostname === ''
    );

const URL_PADRAO = ehDevNavegadorLocal
    ? 'http://127.0.0.1:8000/api'
    : 'https://anjonexus.com.br/api';

const customApiSalva = localStorage.getItem('api_base_url_custom');
if (customApiSalva && customApiSalva.includes('intalenti.com.br')) {
    localStorage.removeItem('api_base_url_custom');
}

const URL_BASE_API = (customApiSalva && !customApiSalva.includes('intalenti.com.br'))
    ? customApiSalva
    : URL_PADRAO;

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
        let mensagemErro = dados.message || 'Ocorreu um erro na comunicação com o servidor.';
        if (dados.errors) {
            const primeiroErro = Object.values(dados.errors)[0];
            if (Array.isArray(primeiroErro) && primeiroErro.length > 0) {
                mensagemErro = primeiroErro[0];
            }
        }

        // Intercepta bloqueio por Pagamento Pendente em qualquer chamada da API
        if (resposta.status === 403 && (dados.codigo === 'PAGAMENTO_PENDENTE' || dados.codigo_erro === 'PAGAMENTO_PENDENTE')) {
            const tokenPend = dados.token_pendencia || dados.access_token || dados.token;
            if (tokenPend) {
                sessionStorage.setItem('token_pendencia', tokenPend);
                localStorage.setItem('token_pendencia', tokenPend);
            }
            if (dados.usuario?.email) {
                sessionStorage.setItem('email_pendencia', dados.usuario.email);
                localStorage.setItem('email_pendencia', dados.usuario.email);
            }
            if (dados.agencia) {
                try {
                    sessionStorage.setItem('agencia_pendencia', JSON.stringify(dados.agencia));
                    localStorage.setItem('agencia_pendencia', JSON.stringify(dados.agencia));
                } catch (_) {}
            }

            // Se o usuário estiver navegando em páginas internas protegidas, redireciona para a tela de pendência
            if (typeof window !== 'undefined' && window.location) {
                try {
                    const usuarioSalvo = JSON.parse(sessionStorage.getItem('usuario_logado') || localStorage.getItem('usuario_logado') || '{}');
                    if (usuarioSalvo?.perfil_acesso === 'admin_global') {
                        return dados;
                    }
                } catch (_) {}

                const caminho = window.location.pathname.toLowerCase();
                const ehTelaPublicaOuPendencia = caminho.includes('login')
                    || caminho.includes('cadastro')
                    || caminho.includes('esqueci-senha')
                    || caminho.includes('redefinir-senha')
                    || caminho.includes('aceitar-convite')
                    || caminho.includes('selecionar-plano')
                    || caminho.includes('admin-dashboard')
                    || caminho.includes('pagamento-pendente');

                if (!ehTelaPublicaOuPendencia) {
                    window.location.href = 'pagamento-pendente.html';
                }
            }
        }

        if (resposta.status === 401 && typeof window !== 'undefined' && window.location) {
            const caminho = window.location.pathname.toLowerCase();
            const ehTelaPublica = caminho.includes('login')
                || caminho.includes('cadastro')
                || caminho.includes('esqueci-senha')
                || caminho.includes('redefinir-senha')
                || caminho.includes('aceitar-convite')
                || caminho.includes('selecionar-plano')
                || caminho.includes('escolher-pagamento')
                || caminho.includes('pagamento-pendente')
                || caminho.includes('baixar-app')
                || caminho.endsWith('/index.html')
                || caminho.endsWith('/front/');

            if (!ehTelaPublica && !endpoint.includes('/auth/login')) {
                localStorage.removeItem('token_autenticacao');
                localStorage.removeItem('usuario_logado');
                window.location.href = 'login.html';
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
