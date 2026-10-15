/**
 * Configuração base para comunicação com a API do backend
 */

export const URL_BASE_API = 'http://127.0.0.1:8000/api';

/**
 * Função utilitária para realizar requisições HTTP padronizadas à API
 * @param {string} endpoint - Caminho do endpoint (ex: '/auth/registro')
 * @param {object} configuracoes - Opções do fetch (método, corpo, etc.)
 * @returns {Promise<any>}
 */
export async function requisitarApi(endpoint, configuracoes = {}) {
    const urlCompleta = `${URL_BASE_API}${endpoint.startsWith('/') ? endpoint : `/${endpoint}`}`;

    const cabecalhosPadrao = {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
    };

    const tokenArmazenado = localStorage.getItem('token_autenticacao');
    if (tokenArmazenado) {
        cabecalhosPadrao['Authorization'] = `Bearer ${tokenArmazenado}`;
    }

    const opcoesRequisicao = {
        ...configuracoes,
        headers: {
            ...cabecalhosPadrao,
            ...(configuracoes.headers || {})
        }
    };

    try {
        const resposta = await fetch(urlCompleta, opcoesRequisicao);
        const dadosResposta = await resposta.json().catch(() => null);

        if (!resposta.ok) {
            let mensagemErro = 'Ocorreu um erro ao processar a requisição.';

            if (dadosResposta) {
                if (dadosResposta.errors && typeof dadosResposta.errors === 'object') {
                    // Captura a primeira mensagem de validação retornada pelo Laravel
                    const primeiraChave = Object.keys(dadosResposta.errors)[0];
                    if (primeiraChave && dadosResposta.errors[primeiraChave].length > 0) {
                        mensagemErro = dadosResposta.errors[primeiraChave][0];
                    }
                } else if (dadosResposta.mensagem) {
                    mensagemErro = dadosResposta.mensagem;
                } else if (dadosResposta.message) {
                    mensagemErro = dadosResposta.message;
                }
            }

            const erroCustomizado = new Error(mensagemErro);
            erroCustomizado.status = resposta.status;
            erroCustomizado.dados = dadosResposta;
            throw erroCustomizado;
        }

        return dadosResposta;
    } catch (erro) {
        if (!erro.status) {
            const erroConexao = new Error('Falha de conexão com o servidor. Verifique se o backend está rodando.');
            erroConexao.status = 0;
            throw erroConexao;
        }
        throw erro;
    }
}
