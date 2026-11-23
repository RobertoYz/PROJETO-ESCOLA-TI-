import { requisicaoApi } from './api.js';

export async function buscarPlanos() {
    return await requisicaoApi('/planos', {
        method: 'GET'
    });
}

export async function confirmarSelecaoPlano(planoId, formaPagamento = 'PIX') {
    return await requisicaoApi('/planos/selecionar', {
        method: 'POST',
        body: JSON.stringify({
            plano_id: planoId,
            forma_pagamento: formaPagamento
        })
    });
}

export async function gerarNovaCobranca(planoId, formaPagamento = 'PIX') {
    const email = sessionStorage.getItem('email_pendencia') || localStorage.getItem('email_pendencia') || undefined;
    const tokenPend = sessionStorage.getItem('token_pendencia') || localStorage.getItem('token_pendencia') || undefined;
    return await requisicaoApi('/planos/gerar-nova-cobranca', {
        method: 'POST',
        body: JSON.stringify({
            plano_id: planoId,
            forma_pagamento: formaPagamento || 'PIX',
            email,
            token_pendencia: tokenPend
        })
    });
}

export async function obterAssinaturaAtual() {
    return await requisicaoApi('/assinatura/atual', {
        method: 'GET'
    });
}

export async function renovarAssinaturaPix(formaPagamento) {
    const opcoes = {
        method: 'POST'
    };
    if (formaPagamento) {
        opcoes.body = JSON.stringify({ forma_pagamento: formaPagamento });
    }
    return await requisicaoApi('/assinatura/renovar', opcoes);
}

export async function atualizarFormaPagamento(formaPagamento) {
    return await requisicaoApi('/assinatura/atualizar-forma-pagamento', {
        method: 'POST',
        body: JSON.stringify({
            forma_pagamento: formaPagamento
        })
    });
}
