import { requisicaoApi } from './api.js';

export async function listarStartupsDaConsultoria(termoBusca = '') {
    const sufixo = termoBusca ? `?busca=${encodeURIComponent(termoBusca)}` : '';
    return await requisicaoApi(`/startups${sufixo}`, {
        method: 'GET'
    });
}

export async function criarNovaStartup(dadosStartup) {
    return await requisicaoApi('/startups', {
        method: 'POST',
        body: JSON.stringify(dadosStartup)
    });
}

export async function obterStartupPorId(id) {
    return await requisicaoApi(`/startups/${id}`, {
        method: 'GET'
    });
}

export async function atualizarStartup(id, dados) {
    return await requisicaoApi(`/startups/${id}`, {
        method: 'PUT',
        body: JSON.stringify(dados)
    });
}

export async function consultarDadosCnpj(cnpj) {
    const apenasDigitos = cnpj.replace(/\D/g, '');
    return await requisicaoApi(`/cnpj/${apenasDigitos}`, {
        method: 'GET'
    });
}
