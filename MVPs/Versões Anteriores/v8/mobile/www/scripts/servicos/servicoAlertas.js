import { requisicaoApi } from './api.js';

/**
 * RF-26 (EDTAN-172): Módulo de Alertas de Match Inteligente de Editais Compatíveis
 */

export async function listarAlertasMatch(startupId = null, apenasNaoLidos = false) {
    const params = new URLSearchParams();
    if (startupId) params.append('startup_id', startupId);
    if (apenasNaoLidos) params.append('apenas_nao_lidos', '1');
    const qs = params.toString() ? `?${params.toString()}` : '';
    return await requisicaoApi(`/alertas/match${qs}`);
}

export async function verificarAlertasMatch(startupId) {
    return await requisicaoApi('/alertas/verificar', {
        method: 'POST',
        body: JSON.stringify({ startup_id: startupId })
    });
}

export async function obterContadorAlertas(startupId = null) {
    const qs = startupId ? `?startup_id=${startupId}` : '';
    return await requisicaoApi(`/alertas/contador${qs}`);
}

export async function marcarAlertaComoLido(alertaId) {
    return await requisicaoApi(`/alertas/${alertaId}/lido`, {
        method: 'PATCH'
    });
}

export async function marcarTodosAlertasComoLidos(startupId = null) {
    return await requisicaoApi('/alertas/marcar-todos-lidos', {
        method: 'POST',
        body: JSON.stringify({ startup_id: startupId })
    });
}

/**
 * RF-47 (EDTAN-171): Módulo de Alertas de Prazos Críticos de Editais
 */

export async function listarAlertasPrazosCriticos(startupId = null, apenasNaoLidos = false, nivelUrgencia = null) {
    const params = new URLSearchParams();
    if (startupId) params.append('startup_id', startupId);
    if (apenasNaoLidos) params.append('apenas_nao_lidos', '1');
    if (nivelUrgencia) params.append('nivel_urgencia', nivelUrgencia);
    const qs = params.toString() ? `?${params.toString()}` : '';
    return await requisicaoApi(`/alertas/prazos-criticos${qs}`);
}

export async function verificarAlertasPrazosCriticos(startupId = null, diasMaximos = 15) {
    return await requisicaoApi('/alertas/prazos-criticos/verificar', {
        method: 'POST',
        body: JSON.stringify({
            startup_id: startupId,
            dias_maximos: diasMaximos
        })
    });
}

export async function obterContadorAlertasPrazos(startupId = null) {
    const qs = startupId ? `?startup_id=${startupId}` : '';
    return await requisicaoApi(`/alertas/prazos-criticos/contador${qs}`);
}

export async function marcarAlertaPrazoComoLido(alertaId) {
    return await requisicaoApi(`/alertas/prazos-criticos/${alertaId}/lido`, {
        method: 'PATCH'
    });
}

export async function marcarTodosAlertasPrazoComoLidos(startupId = null) {
    return await requisicaoApi('/alertas/prazos-criticos/marcar-todos-lidos', {
        method: 'POST',
        body: JSON.stringify({ startup_id: startupId })
    });
}

export async function dispararEmailAlertaPrazo(alertaId) {
    return await requisicaoApi(`/alertas/prazos-criticos/${alertaId}/enviar-email`, {
        method: 'POST'
    });
}
