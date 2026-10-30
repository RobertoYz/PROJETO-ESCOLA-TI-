import { requisicaoApi } from './api.js';

const CHAVE_TOKEN = 'token_autenticacao';
const CHAVE_TOKEN_PENDENCIA = 'token_pendencia';
const CHAVE_EMAIL_PENDENCIA = 'email_pendencia';
const CHAVE_STARTUP_ID = 'startup_ativa_id';
const CHAVE_STARTUP_NOME = 'startup_ativa_nome';

export async function verificarEmail(email) {
    return await requisicaoApi('/auth/verificar-email', {
        method: 'POST',
        body: JSON.stringify({ email })
    });
}

export async function registrarComPlano(dados) {
    return await requisicaoApi('/auth/registrar-com-plano', {
        method: 'POST',
        body: JSON.stringify(dados)
    });
}

export async function cadastrarUsuario(dadosCadastro) {
    return await requisicaoApi('/auth/registro', {
        method: 'POST',
        body: JSON.stringify(dadosCadastro)
    });
}

export async function realizarLogin(credenciais) {
    return await requisicaoApi('/auth/login', {
        method: 'POST',
        body: JSON.stringify(credenciais)
    });
}

export async function consultarPagamentoAnterior() {
    return await requisicaoApi('/pagamentos/consultar-anterior', {
        method: 'GET'
    });
}

export async function cancelarCadastroPendente() {
    return await requisicaoApi('/auth/cancelar-cadastro-pendente', {
        method: 'DELETE'
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
}

export function salvarTokenPendencia(token) {
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

export function limparDadosPendencia() {
    sessionStorage.removeItem(CHAVE_TOKEN_PENDENCIA);
    sessionStorage.removeItem(CHAVE_EMAIL_PENDENCIA);
    localStorage.removeItem(CHAVE_TOKEN_PENDENCIA);
    localStorage.removeItem(CHAVE_EMAIL_PENDENCIA);
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
