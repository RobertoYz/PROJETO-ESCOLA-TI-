/**
 * Gerenciador de Push Notifications no Dispositivo Móvel / Desktop (PWA)
 * Registra o Service Worker e solicita permissão nativa de notificação
 */

class NotificacaoMobileManager {
    constructor() {
        this.swRegistration = null;
        this.temSuporte = ('serviceWorker' in navigator) && ('Notification' in window);
    }

    async inicializar() {
        if (!this.temSuporte) {
            console.log('[PushMobile] Este navegador não suporta Web Notifications / Service Worker.');
            return false;
        }

        try {
            // Registra o Service Worker na raiz do front
            this.swRegistration = await navigator.serviceWorker.register('/sw.js');
            console.log('[PushMobile] Service Worker registrado com sucesso!');

            // Se a permissão já foi concedida antes, já estamos prontos
            if (Notification.permission === 'granted') {
                this.atualizarStatusVisual(true);
            }

            return true;
        } catch (error) {
            console.warn('[PushMobile] Falha ao registrar Service Worker:', error.message);
            return false;
        }
    }

    async solicitarPermissao() {
        if (!this.temSuporte) {
            alert('Seu navegador não oferece suporte para notificações nativas.');
            return false;
        }

        const permissao = await Notification.requestPermission();

        if (permissao === 'granted') {
            this.atualizarStatusVisual(true);
            this.dispararNotificacaoLocal(
                '🔔 Notificações Ativadas!',
                'Você receberá alertas de prazos críticos (24h e 72h) direto na barra do seu celular.',
                '/paginas/kanban.html'
            );
            return true;
        } else {
            this.atualizarStatusVisual(false);
            console.warn('[PushMobile] Permissão negada pelo usuário.');
            return false;
        }
    }

    dispararNotificacaoLocal(titulo, mensagem, url = '/paginas/kanban.html') {
        if (!this.temSuporte || Notification.permission !== 'granted') return;

        if (this.swRegistration && this.swRegistration.showNotification) {
            this.swRegistration.showNotification(titulo, {
                body: mensagem,
                icon: '/favicon.ico',
                badge: '/favicon.ico',
                vibrate: [200, 100, 200],
                data: { url }
            });
        } else {
            new Notification(titulo, { body: mensagem, icon: '/favicon.ico' });
        }
    }

    atualizarStatusVisual(ativo) {
        const btnAtivar = document.getElementById('btnAtivarNotificacoesMobile');
        if (btnAtivar) {
            if (ativo) {
                btnAtivar.innerHTML = '✅ Notificações Mobile Ativas';
                btnAtivar.style.background = '#10b981';
                btnAtivar.style.borderColor = '#10b981';
            } else {
                btnAtivar.innerHTML = '📲 Ativar Notificações no Celular';
            }
        }
    }
}

export const notificacaoMobile = new NotificacaoMobileManager();

