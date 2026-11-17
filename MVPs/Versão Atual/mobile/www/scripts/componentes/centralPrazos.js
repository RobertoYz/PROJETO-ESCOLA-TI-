/**
2:  * Componente Central de Alertas de Prazos Críticos (EDTAN-171 / Módulo 8)
3:  * Gerencia contadores regressivos, badges visuais e modal/gaveta de alertas
4:  */
5: 
import { API_BASE_URL } from '../servicos/api.js';
import { notificacaoMobile } from './notificacaoMobile.js';

class CentralPrazosService {
    constructor() {
        this.dadosAlertas = { resumo: {}, alertas: [] };
        this.intervalo = null;
    }

    async carregarAlertas() {
        try {
            const startupAtiva = JSON.parse(localStorage.getItem('startup_ativa') || 'null');
            const headers = { 'Accept': 'application/json' };
            const token = localStorage.getItem('token_autenticacao');
            if (token) headers['Authorization'] = `Bearer ${token}`;
            if (startupAtiva && startupAtiva.id) headers['X-Startup-Id'] = startupAtiva.id;

            const res = await fetch(`${API_BASE_URL}/prazos/alertas`, { headers });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            this.dadosAlertas = data;
            this.atualizarBadgeSino();
            return data;
        } catch (e) {
            console.warn('[CentralPrazos] Não foi possível carregar alertas:', e.message);
            return { resumo: {}, alertas: [] };
        }
    }

    atualizarBadgeSino() {
        const badge = document.getElementById('badgePrazosCriticos');
        const sino = document.getElementById('btnSinoPrazos');
        if (!badge || !sino) return;

        const resumo = this.dadosAlertas.resumo || {};
        const urgentes = (resumo.criticos_24h || 0) + (resumo.alerta_72h || 0);

        if (urgentes > 0) {
            badge.textContent = urgentes;
            badge.style.display = 'inline-flex';
            sino.classList.add('alerta-pulsante');
        } else if (resumo.moderados_7d > 0) {
            badge.textContent = resumo.moderados_7d;
            badge.style.display = 'inline-flex';
            badge.style.background = '#eab308';
            sino.classList.remove('alerta-pulsante');
        } else {
            badge.style.display = 'none';
            sino.classList.remove('alerta-pulsante');
        }
    }

    renderizarPainelAlertas() {
        let container = document.getElementById('modalPrazosCriticos');
        if (!container) {
            container = document.createElement('div');
            container.id = 'modalPrazosCriticos';
            container.className = 'modal-prazos-overlay';
            container.style.display = 'none';
            document.body.appendChild(container);
        }

        const resumo = this.dadosAlertas.resumo || {};
        const itens = this.dadosAlertas.alertas || [];

        let itensHtml = '';
        if (itens.length === 0) {
            itensHtml = `
                <div style="text-align: center; padding: 2.5rem 1rem; color: #94a3b8;">
                    <div style="font-size: 2.2rem; margin-bottom: 0.5rem;">🎉</div>
                    <p style="font-weight: 600; color: #f1f5f9;">Nenhum prazo crítico pendente!</p>
                    <small>Todas as oportunidades ativas estão com prazos confortáveis (> 7 dias).</small>
                </div>
            `;
        } else {
            itensHtml = itens.map(item => {
                const crit = item.criticidade || {};
                let corBorda = '#3b82f6';
                let icone = '📅';

                if (crit.status === 'critico_24h') {
                    corBorda = '#ef4444';
                    icone = '🔥';
                } else if (crit.status === 'alerta_72h') {
                    corBorda = '#f97316';
                    icone = '⚠️';
                } else if (crit.status === 'moderado_7dias') {
                    corBorda = '#eab308';
                    icone = '⏱️';
                } else if (crit.status === 'expirado') {
                    corBorda = '#64748b';
                    icone = '🛑';
                }

                return `
                    <div class="card-alerta-item" style="border-left: 4px solid ${corBorda}; background: rgba(30, 41, 59, 0.7); border-radius: 8px; padding: 12px 14px; margin-bottom: 10px; display: flex; flex-direction: column; gap: 6px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                            <span style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: ${corBorda}; letter-spacing: 0.5px;">
                                ${icone} ${crit.rotulo || 'Atenção ao Prazo'}
                            </span>
                            <span style="font-size: 0.75rem; color: #94a3b8; background: #0f172a; padding: 2px 6px; border-radius: 4px;">
                                ${item.coluna_nome}
                            </span>
                        </div>
                        <div style="font-size: 0.92rem; font-weight: 600; color: #f8fafc; line-height: 1.3;">
                            ${item.card_titulo}
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.78rem; color: #94a3b8;">
                            <span>🏢 ${item.startup_name || 'Startup'}</span>
                            <span>📅 Encerramento: <strong>${crit.data_formatada || '--'}</strong></span>
                        </div>
                        <div style="display: flex; justify-content: flex-end; margin-top: 4px;">
                            <a href="kanban.html?card_id=${item.card_id}" style="color: #60a5fa; font-size: 0.8rem; text-decoration: none; font-weight: 600;">
                                Ver no Kanban &rarr;
                            </a>
                        </div>
                    </div>
                `;
            }).join('');
        }

        container.innerHTML = `
            <div class="modal-prazos-conteudo" style="background: #0f172a; border: 1px solid #334155; border-radius: 12px; width: 100%; max-width: 480px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); overflow: hidden; display: flex; flex-direction: column; max-height: 85vh;">
                <div style="padding: 16px 20px; border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center; background: #1e293b;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 1.3rem;">⏰</span>
                        <div>
                            <h3 style="margin: 0; font-size: 1.05rem; color: #f8fafc; font-weight: 700;">Alertas de Prazos Críticos</h3>
                            <small style="color: #94a3b8; font-size: 0.75rem;">Módulo EDTAN-171 &bull; Contagem Regressiva</small>
                        </div>
                    </div>
                    <button id="btnFecharModalPrazos" style="background: transparent; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer; line-height: 1; padding: 4px;">&times;</button>
                </div>

                <div style="padding: 12px 20px; background: rgba(15, 23, 42, 0.8); display: flex; gap: 8px; justify-content: space-around; border-bottom: 1px solid #1e293b;">
                    <div style="text-align: center;">
                        <div style="font-size: 1.1rem; font-weight: 700; color: #ef4444;">${resumo.criticos_24h || 0}</div>
                        <div style="font-size: 0.7rem; color: #94a3b8;">&le; 24h</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 1.1rem; font-weight: 700; color: #f97316;">${resumo.alerta_72h || 0}</div>
                        <div style="font-size: 0.7rem; color: #94a3b8;">&le; 72h</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 1.1rem; font-weight: 700; color: #eab308;">${resumo.moderados_7d || 0}</div>
                        <div style="font-size: 0.7rem; color: #94a3b8;">&le; 7 dias</div>
                    </div>
                </div>

                <div style="padding: 16px 20px; overflow-y: auto; flex: 1;">
                    ${itensHtml}
                </div>

                <div style="padding: 12px 20px; border-top: 1px solid #334155; background: #1e293b; display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                    <button id="btnAtivarNotificacoesMobile" style="background: rgba(30, 41, 59, 0.9); color: #e2e8f0; border: 1px solid #475569; padding: 7px 12px; border-radius: 6px; font-size: 0.78rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                        📲 Notificações no Celular
                    </button>
                    <button id="btnAtualizarPrazos" style="background: #3b82f6; color: #fff; border: none; padding: 7px 14px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; cursor: pointer;">
                        Atualizar
                    </button>
                </div>
            </div>
        `;

        document.getElementById('btnAtivarNotificacoesMobile')?.addEventListener('click', async () => {
            await notificacaoMobile.solicitarPermissao();
        });

        // Atualiza estado do botão se permissão já concedida
        if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
            notificacaoMobile.atualizarStatusVisual(true);
        }

        document.getElementById('btnFecharModalPrazos')?.addEventListener('click', () => {
            container.style.display = 'none';
        });

        document.getElementById('btnAtualizarPrazos')?.addEventListener('click', async () => {
            const btn = document.getElementById('btnAtualizarPrazos');
            if (btn) btn.textContent = 'Atualizando...';
            await this.carregarAlertas();
            this.renderizarPainelAlertas();
            container.style.display = 'flex';
        });

        container.addEventListener('click', (e) => {
            if (e.target === container) {
                container.style.display = 'none';
            }
        });
    }

    abrirModal() {
        this.renderizarPainelAlertas();
        const container = document.getElementById('modalPrazosCriticos');
        if (container) container.style.display = 'flex';
    }

    iniciar() {
        this.carregarAlertas();
        // Inicializa o Service Worker PWA para notificações nativas mobile
        notificacaoMobile.inicializar();

        // Polling a cada 2 minutos para atualizar contagem regressiva
        if (this.intervalo) clearInterval(this.intervalo);
        this.intervalo = setInterval(() => this.carregarAlertas(), 120000);
    }
}

export const centralPrazos = new CentralPrazosService();

