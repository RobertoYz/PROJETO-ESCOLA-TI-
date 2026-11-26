import { URL_BASE_API, requisicaoApi } from './api.js';

export async function baixarAnexo(id, nome) {
    if (window.Capacitor?.isNativePlatform?.() && window.Capacitor.isPluginAvailable('DownloadsKanban')) {
        await window.Capacitor.Plugins.DownloadsKanban.baixar({
            url: URL_BASE_API + '/kanban/anexos/' + id,
            token: localStorage.getItem('token_autenticacao'),
            nome
        });
        return;
    }
    const blob = await requisicaoApi('/kanban/anexos/' + id, { respostaBlob: true });
    if (window.Capacitor && navigator.canShare) {
        const files = [new File([blob], nome, { type: blob.type })];
        if (navigator.canShare({ files })) {
            await navigator.share({ files });
            return;
        }
    }
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = nome;
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 60000);
}

export function excluirAnexo(id) {
    return requisicaoApi('/kanban/anexos/' + id, { method: 'DELETE' });
}
