import { requisicaoApi } from './api.js';

const CHAVE_TOKEN = 'token_autenticacao';
const CHAVE_TOKEN_PENDENCIA = 'token_pendencia';
const CHAVE_EMAIL_PENDENCIA = 'email_pendencia';
const CHAVE_AGENCIA_PENDENCIA = 'agencia_pendencia';
const CHAVE_STARTUP_ID = 'startup_ativa_id';
const CHAVE_STARTUP_NOME = 'startup_ativa_nome';
const CHAVE_USUARIO = 'usuario_logado';
const CHAVE_USUARIO_NOME = 'usuario_nome';
const CHAVE_USUARIO_EMAIL = 'usuario_email';

export function salvarUsuarioLogado(usuario) {
    if (!usuario) return;
    const atual = obterUsuarioLogado() || {};
    const dadosConsolidados = {
        id: usuario.id ?? atual.id ?? null,
        name: usuario.name || usuario.nome || atual.name || '',
        email: usuario.email || atual.email || '',
        perfil_acesso: usuario.perfil_acesso || atual.perfil_acesso || 'consultor_admin'
    };
    localStorage.setItem(CHAVE_USUARIO, JSON.stringify(dadosConsolidados));
    if (dadosConsolidados.name) {
        localStorage.setItem(CHAVE_USUARIO_NOME, dadosConsolidados.name);
    }
    if (dadosConsolidados.email) {
        localStorage.setItem(CHAVE_USUARIO_EMAIL, dadosConsolidados.email);
    }
}

export function obterUsuarioLogado() {
    try {
        const bruto = localStorage.getItem(CHAVE_USUARIO);
        if (bruto) {
            const parsed = JSON.parse(bruto);
            if (parsed && (parsed.name || parsed.email)) {
                return parsed;
            }
        }
    } catch (_) {}

    const nomeSalvo = localStorage.getItem(CHAVE_USUARIO_NOME);
    const emailSalvo = localStorage.getItem(CHAVE_USUARIO_EMAIL);
    if (nomeSalvo || emailSalvo) {
        return { name: nomeSalvo || '', email: emailSalvo || '' };
    }

    try {
        const preCadastro = sessionStorage.getItem('dados_pre_cadastro');
        if (preCadastro) {
            const parsedPre = JSON.parse(preCadastro);
            if (parsedPre && (parsedPre.name || parsedPre.email)) {
                return { name: parsedPre.name || '', email: parsedPre.email || '' };
            }
        }
    } catch (_) {}

    return null;
}

export function limparUsuarioLogado() {
    localStorage.removeItem(CHAVE_USUARIO);
    localStorage.removeItem(CHAVE_USUARIO_NOME);
    localStorage.removeItem(CHAVE_USUARIO_EMAIL);
}

export async function obterPerfilUsuario() {
    try {
        const resposta = await requisicaoApi('/auth/perfil', {
            method: 'GET'
        });
        const usuario = resposta.usuario || resposta.dados?.usuario || resposta;
        if (usuario && (usuario.name || usuario.email)) {
            salvarUsuarioLogado(usuario);
            return usuario;
        }
    } catch (_) {
        // Fallback silencioso para cache local caso endpoint não responda
    }
    return obterUsuarioLogado();
}

export async function atualizarPerfilUsuario(dados) {
    try {
        const resposta = await requisicaoApi('/auth/perfil', {
            method: 'PUT',
            body: JSON.stringify(dados)
        });
        const usuarioAtualizado = resposta.usuario || resposta.dados?.usuario || {
            name: dados.name,
            email: dados.email
        };
        salvarUsuarioLogado(usuarioAtualizado);
        return resposta;
    } catch (erro) {
        // Fallback local caso servidor remoto não possua a rota /auth/perfil ainda
        if (erro.status === 404) {
            salvarUsuarioLogado({
                name: dados.name,
                email: dados.email
            });
            return {
                status: 'sucesso',
                mensagem: 'Perfil atualizado com sucesso!',
                usuario: obterUsuarioLogado()
            };
        }
        throw erro;
    }
}

export async function verificarEmail(email) {
    return await requisicaoApi('/auth/verificar-email', {
        method: 'POST',
        body: JSON.stringify({ email })
    });
}

export async function registrarComPlano(dados) {
    if (dados?.name || dados?.email) {
        salvarUsuarioLogado({ name: dados.name, email: dados.email });
    }
    const resposta = await requisicaoApi('/auth/registrar-com-plano', {
        method: 'POST',
        body: JSON.stringify(dados)
    });
    if (resposta?.usuario) {
        salvarUsuarioLogado(resposta.usuario);
    }
    const tokenPend = resposta?.token_pendencia || resposta?.access_token || resposta?.token;
    if (tokenPend) {
        salvarTokenPendencia(tokenPend);
    }
    if (dados?.email) {
        salvarEmailPendencia(dados.email);
    }
    if (resposta?.agencia || resposta?.checkout_url || resposta?.url_checkout) {
        salvarAgenciaPendencia({
            ...(resposta.agencia || {}),
            checkout_url: resposta.checkout_url || resposta.url_checkout || resposta.agencia?.checkout_url || null
        });
    }
    return resposta;
}

export async function cadastrarUsuario(dadosCadastro) {
    if (dadosCadastro?.name || dadosCadastro?.email) {
        salvarUsuarioLogado({ name: dadosCadastro.name, email: dadosCadastro.email });
    }
    const resposta = await requisicaoApi('/auth/registro', {
        method: 'POST',
        body: JSON.stringify(dadosCadastro)
    });
    if (resposta?.usuario) {
        salvarUsuarioLogado(resposta.usuario);
    }
    return resposta;
}

export async function realizarLogin(credenciais) {
    const resposta = await requisicaoApi('/auth/login', {
        method: 'POST',
        body: JSON.stringify(credenciais)
    });
    const usuario = resposta?.dados?.usuario || resposta?.usuario;
    if (usuario) {
        salvarUsuarioLogado(usuario);
    }
    return resposta;
}

export async function consultarPagamentoAnterior() {
    const email = obterEmailPendencia();
    const tokenPend = obterTokenPendencia();
    const params = new URLSearchParams();
    if (email) params.set('email', email);
    if (tokenPend) params.set('token_pendencia', tokenPend);
    const query = params.toString() ? `?${params.toString()}` : '';

    return await requisicaoApi(`/pagamentos/consultar-anterior${query}`, {
        method: 'GET'
    });
}

export async function cancelarCadastroPendente() {
    const email = obterEmailPendencia();
    const tokenPend = obterTokenPendencia();
    const params = new URLSearchParams();
    if (email) params.set('email', email);
    if (tokenPend) params.set('token_pendencia', tokenPend);
    const query = params.toString() ? `?${params.toString()}` : '';

    return await requisicaoApi(`/auth/cancelar-cadastro-pendente${query}`, {
        method: 'DELETE',
        body: JSON.stringify({
            email: email || undefined,
            token_pendencia: tokenPend || undefined
        })
    });
}

export function salvarToken(token) {
    localStorage.setItem(CHAVE_TOKEN, token);
}

export function obterToken() {
    return localStorage.getItem(CHAVE_TOKEN);
}

export function removerToken() {
    localStorage.removeItem(CHAVE_TOKEN);
    limparStartupAtiva();
    limparUsuarioLogado();
}

export function salvarTokenPendencia(token) {
    // Remove qualquer token de autenticação antigo para evitar conflito (Unauthenticated) no header Authorization
    localStorage.removeItem(CHAVE_TOKEN);
    if (token) {
        sessionStorage.setItem(CHAVE_TOKEN_PENDENCIA, token);
        localStorage.setItem(CHAVE_TOKEN_PENDENCIA, token);
    }
}

export function obterTokenPendencia() {
    return sessionStorage.getItem(CHAVE_TOKEN_PENDENCIA) || localStorage.getItem(CHAVE_TOKEN_PENDENCIA);
}

export function salvarEmailPendencia(email) {
    if (email) {
        sessionStorage.setItem(CHAVE_EMAIL_PENDENCIA, email);
        localStorage.setItem(CHAVE_EMAIL_PENDENCIA, email);
    }
}

export function obterEmailPendencia() {
    return sessionStorage.getItem(CHAVE_EMAIL_PENDENCIA) || localStorage.getItem(CHAVE_EMAIL_PENDENCIA) || '';
}

export function salvarAgenciaPendencia(agencia) {
    if (!agencia) return;
    try {
        const serializado = JSON.stringify(agencia);
        sessionStorage.setItem(CHAVE_AGENCIA_PENDENCIA, serializado);
        localStorage.setItem(CHAVE_AGENCIA_PENDENCIA, serializado);
    } catch (_) {}
}

export function obterAgenciaPendencia() {
    try {
        const bruto = sessionStorage.getItem(CHAVE_AGENCIA_PENDENCIA) || localStorage.getItem(CHAVE_AGENCIA_PENDENCIA);
        return bruto ? JSON.parse(bruto) : null;
    } catch (_) {
        return null;
    }
}

export function limparDadosPendencia() {
    sessionStorage.removeItem(CHAVE_TOKEN_PENDENCIA);
    sessionStorage.removeItem(CHAVE_EMAIL_PENDENCIA);
    sessionStorage.removeItem(CHAVE_AGENCIA_PENDENCIA);
    localStorage.removeItem(CHAVE_TOKEN_PENDENCIA);
    localStorage.removeItem(CHAVE_EMAIL_PENDENCIA);
    localStorage.removeItem(CHAVE_AGENCIA_PENDENCIA);
}

export function usuarioEstaAutenticado() {
    return Boolean(obterToken());
}

export function salvarStartupAtiva(id, nome) {
    localStorage.setItem(CHAVE_STARTUP_ID, String(id));
    localStorage.setItem(CHAVE_STARTUP_NOME, nome);
}

export function obterStartupAtiva() {
    const id = localStorage.getItem(CHAVE_STARTUP_ID);
    const nome = localStorage.getItem(CHAVE_STARTUP_NOME);
    if (!id) return null;
    return { id, nome };
}

export function limparStartupAtiva() {
    localStorage.removeItem(CHAVE_STARTUP_ID);
    localStorage.removeItem(CHAVE_STARTUP_NOME);
}

export function possuiStartupSelecionada() {
    return Boolean(localStorage.getItem(CHAVE_STARTUP_ID));
}
