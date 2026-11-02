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
