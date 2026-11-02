import { requisicaoApi } from './api.js';

const CHAVE_TOKEN = 'token_autenticacao';
const CHAVE_STARTUP_ID = 'startup_ativa_id';
const CHAVE_STARTUP_NOME = 'startup_ativa_nome';

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
