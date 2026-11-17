/**
 * Service Worker - Anjo Nexus (Web Push Notifications & PWA)
 * Responsável por exibir notificações do sistema operacional e na barra de notificações mobile
 */

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

// Evento disparado quando uma notificação Push chega do servidor
self.addEventListener('push', (event) => {
    let payload = {
        title: '⏰ Alerta de Prazo Crítico - Anjo Nexus',
        body: 'Uma oportunidade no seu Kanban está prestes a expirar nas próximas horas!',
        icon: '/favicon.ico',
        badge: '/favicon.ico',
        url: '/paginas/kanban.html',
        tag: 'alerta-prazo-critico'
    };

    if (event.data) {
        try {
            payload = Object.assign(payload, event.data.json());
        } catch (e) {
            payload.body = event.data.text();
        }
    }

    const options = {
        body: payload.body,
        icon: payload.icon || '/favicon.ico',
        badge: payload.badge || '/favicon.ico',
        vibrate: [200, 100, 200, 100, 200], // Faz o celular vibrar
        tag: payload.tag || 'alerta-prazo',
        renotify: true,
        requireInteraction: true, // Mantém a notificação visível até interação
        data: {
            url: payload.url || '/paginas/kanban.html'
        },
        actions: [
            { action: 'abrir', title: 'Ver Oportunidade' },
            { action: 'fechar', title: 'Dispensar' }
        ]
    };

    event.waitUntil(
        self.registration.showNotification(payload.title, options)
    );
});

// Ao clicar na notificação na barra do mobile, abre a tela correspondente
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    if (event.action === 'fechar') return;

    const urlParaAbrir = event.notification.data?.url || '/paginas/kanban.html';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            for (let i = 0; i < windowClients.length; i++) {
                const client = windowClients[i];
                if (client.url.includes('kanban.html') && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(urlParaAbrir);
            }
        })
    );
});

