import { requisicaoApi } from './api.js';

export async function listarEquipeConsultoria() {
    return await requisicaoApi('/equipe', {
        method: 'GET'
    });
}

export async function convidarMembroEquipe(dados) {
    return await requisicaoApi('/equipe', {
        method: 'POST',
        body: JSON.stringify(dados)
    });
}

export async function atualizarPermissoesMembro(id, permissoes) {
    return await requisicaoApi(`/equipe/${encodeURIComponent(id)}/permissoes`, {
        method: 'PUT',
        body: JSON.stringify({ permissoes })
    });
}

export async function alternarStatusMembro(id, status = null) {
    const body = status ? JSON.stringify({ status }) : JSON.stringify({});
    return await requisicaoApi(`/equipe/${encodeURIComponent(id)}/status`, {
        method: 'PATCH',
        body
    });
}

export async function reenviarConviteMembro(id) {
    return await requisicaoApi(`/equipe/${encodeURIComponent(id)}/reenviar-convite`, {
        method: 'POST'
    });
}

export async function removerMembroEquipe(id) {
    return await requisicaoApi(`/equipe/${encodeURIComponent(id)}`, {
        method: 'DELETE'
    });
}

export async function validarTokenConvitePublico(token) {
    return await requisicaoApi(`/convites/${encodeURIComponent(token)}`, {
        method: 'GET'
    });
}

export async function aceitarConvitePublico(dados) {
    return await requisicaoApi('/convites/aceitar', {
        method: 'POST',
        body: JSON.stringify(dados)
    });
}
