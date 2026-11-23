import { requisicaoApi } from './api.js';

export async function listarStartupsDaConsultoria(termoBusca = '') {
    const sufixo = termoBusca ? `?busca=${encodeURIComponent(termoBusca)}` : '';
    return await requisicaoApi(`/startups${sufixo}`, {
        method: 'GET'
    });
}

export async function listarStartupsComCota(termoBusca = '') {
    const params = new URLSearchParams();
    params.set('incluir_cota', '1');
    if (termoBusca) {
        params.set('busca', termoBusca);
    }
    return await requisicaoApi(`/startups?${params.toString()}`, {
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

export async function excluirStartupPorId(id) {
    return await requisicaoApi(`/startups/${id}`, {
        method: 'DELETE'
    });
}

export async function consultarDadosCnpj(cnpj) {
    const apenasDigitos = cnpj.replace(/\D/g, '');
    return await requisicaoApi(`/cnpj/${apenasDigitos}`, {
        method: 'GET'
    });
}
