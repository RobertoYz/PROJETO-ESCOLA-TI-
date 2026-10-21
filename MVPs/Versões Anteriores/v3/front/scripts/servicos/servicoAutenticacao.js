import { requisitarApi } from './api.js';

const CHAVE_TOKEN_STORAGE = 'token_autenticacao';
const CHAVE_USUARIO_STORAGE = 'usuario_autenticado';

/**
 * Envia os dados para o endpoint de cadastro/registro da agência e usuário
 * @param {object} dadosCadastro
 * @param {string} dadosCadastro.nomeAgencia
 * @param {string} dadosCadastro.nomeUsuario
 * @param {string} dadosCadastro.email
 * @param {string} dadosCadastro.senha
 * @returns {Promise<object>}
 */
export async function cadastrarUsuario({ nomeAgencia, nomeUsuario, email, senha }) {
    const payload = {
        nome_agencia: nomeAgencia,
        name: nomeUsuario,
        email: email,
        password: senha
    };

    return await requisitarApi('/auth/registro', {
        method: 'POST',
        body: JSON.stringify(payload)
    });
}

/**
 * Envia as credenciais para o endpoint de login
 * @param {string} email
 * @param {string} senha
 * @returns {Promise<object>}
 */
export async function realizarLogin(email, senha) {
    const payload = {
        email: email,
        password: senha
    };

    return await requisitarApi('/auth/login', {
        method: 'POST',
        body: JSON.stringify(payload)
    });
}

/**
 * Salva o token de autenticação no localStorage
 * @param {string} token
 */
export function salvarToken(token) {
    if (token) {
        localStorage.setItem(CHAVE_TOKEN_STORAGE, token);
    }
}

/**
 * Retorna o token de autenticação armazenado
 * @returns {string|null}
 */
export function obterToken() {
    return localStorage.getItem(CHAVE_TOKEN_STORAGE);
}

/**
 * Remove o token e dados salvos no logout
 */
export function removerToken() {
    localStorage.removeItem(CHAVE_TOKEN_STORAGE);
    localStorage.removeItem(CHAVE_USUARIO_STORAGE);
}

/**
 * Verifica se existe uma sessão com token
 * @returns {boolean}
 */
export function usuarioEstaAutenticado() {
    const token = obterToken();
    return Boolean(token && token.trim() !== '');
}
