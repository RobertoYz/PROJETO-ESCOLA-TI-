import { requisicaoApi } from './api.js';

export async function buscarPlanos() {
    return await requisicaoApi('/planos', {
        method: 'GET'
    });
}

export async function confirmarSelecaoPlano(planoId) {
    return await requisicaoApi('/planos/selecionar', {
        method: 'POST',
        body: JSON.stringify({ plano_id: planoId })
    });
}

export async function gerarNovaCobranca(planoId) {
    const email = sessionStorage.getItem('email_pendencia') || localStorage.getItem('email_pendencia') || undefined;
    const tokenPend = sessionStorage.getItem('token_pendencia') || localStorage.getItem('token_pendencia') || undefined;
    return await requisicaoApi('/planos/gerar-nova-cobranca', {
        method: 'POST',
        body: JSON.stringify({
            plano_id: planoId,
            email,
            token_pendencia: tokenPend
        })
    });
}
