/**
 * Componente Global de Header (Menu Customizado de Startups com Engrenagem + Criar Startup + Perfil do Consultor)
 * Padroniza o cabeçalho em todas as páginas da plataforma
 */
import {
    obterStartupAtiva,
    salvarStartupAtiva,
    obterUsuarioLogado,
    obterPerfilUsuario,
    atualizarPerfilUsuario,
    cancelarContaDefinitivamente,
    removerToken
} from '../servicos/servicoAutenticacao.js';
import { requisicaoApi } from '../servicos/api.js';
import { listarStartupsDaConsultoria } from '../servicos/servicoStartups.js';
import {
    verificarAlertasMatch,
    listarAlertasMatch,
    marcarAlertaComoLido,
    marcarTodosAlertasComoLidos,
    verificarAlertasPrazosCriticos,
    listarAlertasPrazosCriticos,
    marcarAlertaPrazoComoLido,
    marcarTodosAlertasPrazoComoLidos,
    dispararEmailAlertaPrazo
} from '../servicos/servicoAlertas.js';

function injetarEstilosHeaderGlobal() {
    if (document.getElementById('estilos-header-global')) return;

    const style = document.createElement('style');
    style.id = 'estilos-header-global';
    style.textContent = `
        @keyframes deslizarNotificacaoEntrada {
            from {
                opacity: 0;
                transform: translateY(-24px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        @keyframes deslizarNotificacaoSaida {
            from {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
            to {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
        }
        @media (max-width: 640px) {
            .anjo-toast-notificacao {
                top: 12px !important;
                right: 12px !important;
                left: 12px !important;
                width: auto !important;
                max-width: none !important;
            }
        }

        .top-navbar {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            padding: 0.85rem 2rem !important;
            background-color: #0d0f12 !important;
            border-bottom: 1px solid #222 !important;
            min-height: 70px !important;
            width: 100% !important;
            box-sizing: border-box !important;
            z-index: 100 !important;
        }

        .header-left-slot {
            display: flex;
            align-items: center;
        }

        .header-right-group {
            margin-left: auto;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 1.5rem;
        }

        /* Menu Customizado de Startups no Header */
        .seletor-startup-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid #262930;
            padding: 6px 12px;
            border-radius: 10px;
            cursor: pointer;
            user-select: none;
            transition: border-color 0.2s, background-color 0.2s;
        }

        .seletor-startup-wrapper:hover,
        .seletor-startup-wrapper.open {
            border-color: #3b82f6;
            background: rgba(59, 130, 246, 0.05);
        }

        .startup-trigger-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            background: #181b20;
            color: #f8fafc;
            border: 1px solid #334155;
            border-radius: 6px;
            padding: 5px 10px;
            font-size: 13px;
            font-weight: 600;
            min-width: 185px;
            max-width: 260px;
        }

        .startup-trigger-nome {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .startup-trigger-seta {
            font-size: 10px;
            color: #94a3b8;
            transition: transform 0.2s;
        }

        .seletor-startup-wrapper.open .startup-trigger-seta {
            transform: rotate(180deg);
        }

        .startup-dropdown-menu {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            width: 310px;
            background: #14171c;
            border: 1px solid #2e3440;
            border-radius: 12px;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.75);
            display: none;
            flex-direction: column;
            z-index: 1000;
            overflow: hidden;
        }

        .startup-dropdown-menu.active {
            display: flex;
        }

        .startup-dropdown-header {
            padding: 10px 14px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #94a3b8;
            border-bottom: 1px solid #22262e;
            background: #101317;
        }

        .startup-dropdown-lista {
            max-height: 260px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .startup-dropdown-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 10px 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            transition: background-color 0.15s;
            cursor: pointer;
        }

        .startup-dropdown-item:hover {
            background-color: rgba(255, 255, 255, 0.06);
        }

        .startup-dropdown-item.ativa {
            background-color: rgba(59, 130, 246, 0.12);
            border-left: 3px solid #3b82f6;
        }

        .startup-item-info {
            display: flex;
            flex-direction: column;
            min-width: 0;
            flex: 1;
        }

        .startup-item-nome {
            font-size: 0.88rem;
            font-weight: 600;
            color: #f1f5f9;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .startup-item-status {
            font-size: 0.72rem;
            color: #10b981;
            font-weight: 600;
            margin-top: 2px;
        }

        .btn-engrenagem-startup {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid #334155;
            color: #cbd5e1;
            width: 32px;
            height: 32px;
            min-width: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.18s;
            padding: 0;
        }

        .btn-engrenagem-startup:hover {
            background: #2563eb;
            border-color: #3b82f6;
            color: #ffffff;
            transform: rotate(25deg);
        }

        .startup-dropdown-criar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 14px;
            background: linear-gradient(135deg, rgba(27, 54, 255, 0.18), rgba(139, 92, 246, 0.18));
            border-top: 1px solid #2e3440;
            color: #93c5fd;
            font-size: 0.88rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
        }

        .startup-dropdown-criar:hover {
            background: linear-gradient(135deg, #1b36ff, #8b5cf6);
            color: #ffffff;
        }

        .user-section-global {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
            cursor: pointer;
            padding: 6px 10px;
            border-radius: 10px;
            transition: background-color 0.2s;
            user-select: none;
        }

        .user-section-global:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }

        .user-section-global .user-name {
            font-weight: 500;
            font-size: 0.95rem;
            color: #ffffff;
            white-space: nowrap;
        }

        .user-section-global .avatar {
            width: 38px;
            height: 38px;
            background-color: #d9d9d9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #555;
            flex-shrink: 0;
        }

        .user-dropdown-global {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background-color: #f5f5f5;
            color: #333333;
            border-radius: 10px;
            width: 210px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.6);
            display: none;
            flex-direction: column;
            z-index: 999;
            padding: 0.4rem 0;
            overflow: hidden;
        }

        .user-dropdown-global.active {
            display: flex;
        }

        .user-dropdown-item {
            padding: 10px 18px;
            font-size: 0.92rem;
            font-weight: 500;
            color: #222;
            cursor: pointer;
            transition: background-color 0.15s;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .user-dropdown-item:hover {
            background-color: #e2e8f0;
        }

        .user-dropdown-item.logoff-item {
            margin-top: 4px;
            border-top: 1px solid #dcdcdc;
            padding-top: 12px;
            color: #dc2626;
            font-weight: 600;
        }

        .user-dropdown-item.logoff-item:hover {
            background-color: #fee2e2;
        }

        /* Modal Editar Perfil do Consultor */
        .modal-perfil-consultor-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(6px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            padding: 1rem;
        }

        .modal-perfil-consultor-overlay.active {
            display: flex !important;
        }

        .modal-perfil-consultor-card {
            background: #16191e;
            border: 1px solid #2e3440;
            border-radius: 14px;
            width: 100%;
            max-width: 460px;
            padding: 1.75rem;
            display: flex;
            flex-direction: column;
            gap: 1.1rem;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.8);
            color: #fff;
        }

        .modal-perfil-consultor-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1px solid #262930;
            padding-bottom: 0.9rem;
        }

        .modal-perfil-consultor-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #fff;
        }

        .modal-perfil-consultor-subtitle {
            font-size: 0.82rem;
            color: #94a3b8;
            margin-top: 4px;
        }

        .modal-perfil-close {
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 1.4rem;
            cursor: pointer;
            line-height: 1;
            padding: 2px 6px;
            border-radius: 6px;
        }

        .modal-perfil-close:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.08);
        }

        .perfil-form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .perfil-form-label {
            font-size: 0.82rem;
            font-weight: 600;
            color: #cbd5e1;
        }

        .perfil-form-input {
            background: #0d0f12;
            border: 1px solid #334155;
            color: #fff;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 0.92rem;
            outline: none;
            width: 100%;
            transition: border-color 0.2s;
        }

        .perfil-form-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
        }

        .perfil-feedback {
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            display: none;
        }

        .perfil-feedback.erro {
            display: block;
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
        }

        .perfil-feedback.sucesso {
            display: block;
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.4);
            color: #6ee7b7;
        }

        .modal-perfil-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 0.5rem;
            border-top: 1px solid #262930;
            padding-top: 1rem;
        }

        .btn-perfil-cancelar {
            background: transparent;
            border: 1px solid #475569;
            color: #cbd5e1;
            padding: 9px 16px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            width: auto;
        }

        .btn-perfil-cancelar:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
        }

        .btn-perfil-salvar {
            background: linear-gradient(135deg, #1b36ff, #8b5cf6);
            border: none;
            color: #fff;
            padding: 9px 18px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            width: auto;
        }

        .btn-perfil-salvar:hover {
            opacity: 0.92;
        }

        /* Zona de Perigo (Perfil do Consultor Admin) */
        .zona-perigo-consultor {
            margin-top: 0.4rem;
            padding-top: 1rem;
            border-top: 1px dashed rgba(239, 68, 68, 0.35);
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }

        .zona-perigo-titulo {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #f87171;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .zona-perigo-descricao {
            font-size: 0.8rem;
            color: #94a3b8;
            line-height: 1.4;
        }

        .btn-zona-perigo-cancelar {
            background: rgba(239, 68, 68, 0.14);
            border: 1px solid rgba(239, 68, 68, 0.5);
            color: #fca5a5;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-zona-perigo-cancelar:hover {
            background: #dc2626;
            border-color: #ef4444;
            color: #ffffff;
            box-shadow: 0 4px 16px rgba(239, 68, 68, 0.35);
        }

        /* Modal Customizado de Cancelamento de Conta + Conformidade LGPD */
        .modal-cancelar-conta-overlay {
            position: fixed;
            inset: 0;
            background: rgba(4, 6, 12, 0.85);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10050;
            padding: 1rem;
        }

        .modal-cancelar-conta-overlay.active {
            display: flex !important;
        }

        .modal-cancelar-conta-card {
            background: #12151e;
            border: 1px solid rgba(239, 68, 68, 0.45);
            border-radius: 16px;
            width: 100%;
            max-width: 520px;
            padding: 1.75rem;
            display: flex;
            flex-direction: column;
            gap: 1.15rem;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.9), 0 0 24px rgba(239, 68, 68, 0.15);
            color: #f8fafc;
        }

        .alerta-lgpd-irreversivel {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.4);
            border-left: 4px solid #ef4444;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 0.84rem;
            line-height: 1.55;
            color: #fecaca;
        }

        .alerta-lgpd-irreversivel strong {
            color: #ffffff;
        }

        .checkbox-lgpd-wrapper {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid #2e3440;
            border-radius: 10px;
            padding: 11px 13px;
            cursor: pointer;
            user-select: none;
        }

        .checkbox-lgpd-wrapper input[type="checkbox"] {
            width: 18px !important;
            height: 18px !important;
            min-width: 18px !important;
            margin-top: 2px;
            accent-color: #ef4444;
            cursor: pointer;
            padding: 0 !important;
        }

        .checkbox-lgpd-label {
            font-size: 0.83rem;
            color: #e2e8f0;
            line-height: 1.45;
            cursor: pointer;
        }

        .btn-confirmar-encerramento {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            border: 1px solid #ef4444;
            color: #ffffff;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            width: auto;
        }

        .btn-confirmar-encerramento:hover:not(:disabled) {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            box-shadow: 0 4px 18px rgba(239, 68, 68, 0.45);
        }

        .btn-confirmar-encerramento:disabled {
            opacity: 0.42 !important;
            cursor: not-allowed !important;
            filter: grayscale(0.4);
            box-shadow: none !important;
        }

        /* Modal Minha Assinatura (EDTAN-160 - Dark Mode B2B SaaS) */
        .modal-assinatura-overlay {
            position: fixed;
            inset: 0;
            background: rgba(4, 6, 12, 0.82);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10020;
            padding: 1rem;
        }

        .modal-assinatura-overlay.active {
            display: flex !important;
        }

        .modal-assinatura-card {
            background: #131722;
            border: 1px solid #2a3142;
            border-radius: 16px;
            width: 100%;
            max-width: 520px;
            padding: 1.75rem;
            display: flex;
            flex-direction: column;
            gap: 1.15rem;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.88);
            color: #f8fafc;
        }

        .assinatura-bloco-plano {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.14), rgba(124, 58, 237, 0.14));
            border: 1px solid rgba(99, 102, 241, 0.35);
            border-radius: 12px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .assinatura-plano-nome {
            font-size: 1.1rem;
            font-weight: 700;
            color: #ffffff;
        }

        .assinatura-plano-preco {
            font-size: 1.05rem;
            font-weight: 700;
            color: #60a5fa;
        }

        .assinatura-grid-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .assinatura-info-item {
            background: #0d1017;
            border: 1px solid #222836;
            border-radius: 10px;
            padding: 11px 13px;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .assinatura-info-label {
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94a3b8;
            font-weight: 600;
        }

        .assinatura-info-valor {
            font-size: 0.92rem;
            font-weight: 700;
            color: #f1f5f9;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .badge-metodo-pix {
            background: rgba(16, 185, 129, 0.16);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.45);
            font-size: 0.75rem;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 999px;
            letter-spacing: 0.6px;
        }

        .badge-status-assinatura {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .badge-status-assinatura.ativa {
            background: rgba(16, 185, 129, 0.16);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.45);
        }

        .badge-status-assinatura.proxima {
            background: rgba(245, 158, 11, 0.16);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.45);
        }

        .badge-status-assinatura.vencida {
            background: rgba(239, 68, 68, 0.16);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.45);
        }

        .assinatura-consumo-box {
            background: #0d1017;
            border: 1px solid #222836;
            border-radius: 10px;
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .assinatura-consumo-linha {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: #cbd5e1;
            padding: 4px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        }

        .assinatura-consumo-linha:last-child {
            border-bottom: none;
        }

        .assinatura-aviso-renovacao {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 10px;
            padding: 10px 13px;
            font-size: 0.82rem;
            line-height: 1.45;
            color: #bfdbfe;
        }

        .btn-renovar-assinatura-pix {
            background: linear-gradient(135deg, #10b981, #059669);
            border: 1px solid #34d399;
            color: #ffffff;
            padding: 11px 18px;
            border-radius: 10px;
            font-size: 0.92rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-renovar-assinatura-pix:hover:not(:disabled) {
            background: linear-gradient(135deg, #059669, #047857);
            box-shadow: 0 4px 18px rgba(16, 185, 129, 0.38);
        }

        .btn-renovar-assinatura-pix:disabled {
            background: #1e2433 !important;
            border-color: #334155 !important;
            color: #64748b !important;
            cursor: not-allowed !important;
            box-shadow: none !important;
        }
    `;
    document.head.appendChild(style);
}

function atualizarVisibilidadeZonaPerigo(usuario) {
    const secaoZonaPerigo = document.getElementById('secaoZonaPerigoConsultor');
    if (!secaoZonaPerigo) return;

    const perfil = usuario?.perfil_acesso;
    if (perfil === 'consultor_admin') {
        secaoZonaPerigo.style.display = 'flex';
    } else {
        secaoZonaPerigo.style.display = 'none';
    }
}

function garantirModalCancelarConta() {
    if (document.getElementById('modalCancelarContaOverlay')) return;

    const modalCancelamentoHtml = `
        <div class="modal-cancelar-conta-overlay" id="modalCancelarContaOverlay">
            <div class="modal-cancelar-conta-card" role="dialog" aria-modal="true" aria-labelledby="tituloModalCancelarConta">
                <div class="modal-perfil-consultor-header">
                    <div>
                        <div class="modal-perfil-consultor-title" id="tituloModalCancelarConta" style="color: #f87171;">
                            ⚠️ Cancelar Conta e Assinatura
                        </div>
                        <div class="modal-perfil-consultor-subtitle">
                            Confirmação de segurança e exclusão definitiva de dados (LGPD)
                        </div>
                    </div>
                    <button type="button" class="modal-perfil-close" id="btnFecharModalCancelarConta">&times;</button>
                </div>

                <div class="alerta-lgpd-irreversivel">
                    Em conformidade com a Lei Geral de Proteção de Dados (LGPD - Lei nº 13.709/2018), todos os dados da consultoria, membros da equipe, startups cadastradas, documentos do Kanban e minutas geradas serão permanentemente eliminados dos nossos servidores, sendo <strong>impossível a recuperação</strong> após a confirmação. Sua assinatura mensal também será encerrada imediatamente.
                </div>

                <form id="formCancelarContaLgpd" novalidate style="display: flex; flex-direction: column; gap: 1rem;">
                    <div class="perfil-form-group">
                        <label class="perfil-form-label" for="senha-confirmar-cancelamento">Confirme sua Senha Atual</label>
                        <input
                            type="password"
                            id="senha-confirmar-cancelamento"
                            class="perfil-form-input"
                            placeholder="Digite sua senha atual para confirmar"
                            required
                        >
                    </div>

                    <label class="checkbox-lgpd-wrapper" for="checkbox-ciente-lgpd">
                        <input type="checkbox" id="checkbox-ciente-lgpd">
                        <span class="checkbox-lgpd-label">
                            Estou ciente de que todos os meus dados serão permanentemente excluídos de acordo com a LGPD e que esta ação é irreversível.
                        </span>
                    </label>

                    <div id="feedbackCancelamentoConta" class="perfil-feedback"></div>

                    <div class="modal-perfil-actions" style="justify-content: space-between; flex-wrap: wrap;">
                        <button type="button" class="btn-perfil-cancelar" id="btnVoltarManterConta">
                            Voltar / Manter Conta
                        </button>
                        <button type="submit" class="btn-confirmar-encerramento" id="btnConfirmarEncerramentoConta" disabled>
                            Sim, Encerrar Minha Conta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalCancelamentoHtml);

    const overlay = document.getElementById('modalCancelarContaOverlay');
    const btnFechar = document.getElementById('btnFecharModalCancelarConta');
    const btnVoltar = document.getElementById('btnVoltarManterConta');
    const inputSenha = document.getElementById('senha-confirmar-cancelamento');
    const checkboxLgpd = document.getElementById('checkbox-ciente-lgpd');
    const btnConfirmar = document.getElementById('btnConfirmarEncerramentoConta');
    const formCancelamento = document.getElementById('formCancelarContaLgpd');
    const feedback = document.getElementById('feedbackCancelamentoConta');

    const atualizarEstadoBotao = () => {
        const senhaPreenchida = inputSenha && inputSenha.value.trim().length > 0;
        const cienteMarcado = checkboxLgpd && checkboxLgpd.checked === true;
        if (btnConfirmar) {
            btnConfirmar.disabled = !(senhaPreenchida && cienteMarcado);
        }
    };

    const limparEFecharModal = () => {
        overlay.classList.remove('active');
        if (inputSenha) inputSenha.value = '';
        if (checkboxLgpd) checkboxLgpd.checked = false;
        if (feedback) {
            feedback.className = 'perfil-feedback';
            feedback.textContent = '';
        }
        atualizarEstadoBotao();
    };

    inputSenha.addEventListener('input', atualizarEstadoBotao);
    checkboxLgpd.addEventListener('change', atualizarEstadoBotao);

    btnFechar.addEventListener('click', limparEFecharModal);
    btnVoltar.addEventListener('click', limparEFecharModal);
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) limparEFecharModal();
    });

    formCancelamento.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (btnConfirmar.disabled) return;

        feedback.className = 'perfil-feedback';
        feedback.textContent = '';

        const password = inputSenha.value;
        const ciente_lgpd = checkboxLgpd.checked;

        if (!password || !ciente_lgpd) {
            feedback.textContent = 'Preencha sua senha atual e marque a confirmação LGPD.';
            feedback.className = 'perfil-feedback erro';
            return;
        }

        btnConfirmar.disabled = true;
        btnVoltar.disabled = true;
        const textoOriginal = btnConfirmar.textContent;
        btnConfirmar.textContent = 'Encerrando conta...';

        try {
            const resposta = await requisicaoApi('/conta/cancelar', {
                method: 'DELETE',
                body: JSON.stringify({
                    password,
                    ciente_lgpd: true
                })
            });

            localStorage.clear();
            sessionStorage.clear();

            feedback.textContent = resposta?.message || 'Sua conta, assinatura e todos os dados vinculados foram permanentemente excluídos conforme a LGPD.';
            feedback.className = 'perfil-feedback sucesso';

            setTimeout(() => {
                window.location.href = 'login.html';
            }, 1200);
        } catch (erro) {
            feedback.textContent = erro.message || 'Não foi possível encerrar a conta. Verifique sua senha.';
            feedback.className = 'perfil-feedback erro';
            btnVoltar.disabled = false;
            btnConfirmar.textContent = textoOriginal;
            atualizarEstadoBotao();
        }
    });
}

function abrirModalCancelarConta() {
    garantirModalCancelarConta();
    const overlayPerfil = document.getElementById('modalEditarPerfilConsultorOverlay');
    if (overlayPerfil) overlayPerfil.classList.remove('active');

    const inputSenha = document.getElementById('senha-confirmar-cancelamento');
    const checkboxLgpd = document.getElementById('checkbox-ciente-lgpd');
    const btnConfirmar = document.getElementById('btnConfirmarEncerramentoConta');
    const btnVoltar = document.getElementById('btnVoltarManterConta');
    const feedback = document.getElementById('feedbackCancelamentoConta');

    if (inputSenha) inputSenha.value = '';
    if (checkboxLgpd) checkboxLgpd.checked = false;
    if (btnConfirmar) {
        btnConfirmar.disabled = true;
        btnConfirmar.textContent = 'Sim, Encerrar Minha Conta';
    }
    if (btnVoltar) btnVoltar.disabled = false;
    if (feedback) {
        feedback.className = 'perfil-feedback';
        feedback.textContent = '';
    }

    const overlayCancelamento = document.getElementById('modalCancelarContaOverlay');
    if (overlayCancelamento) overlayCancelamento.classList.add('active');
}

function garantirModalEditarPerfil() {
    if (document.getElementById('modalEditarPerfilConsultorOverlay')) return;

    const modalHtml = `
        <div class="modal-perfil-consultor-overlay" id="modalEditarPerfilConsultorOverlay">
            <div class="modal-perfil-consultor-card">
                <div class="modal-perfil-consultor-header">
                    <div>
                        <div class="modal-perfil-consultor-title">Editar Perfil do Consultor</div>
                        <div class="modal-perfil-consultor-subtitle">Atualize suas informações de cadastro (nome, e-mail e senha)</div>
                    </div>
                    <button type="button" class="modal-perfil-close" id="btnFecharModalPerfilConsultor">&times;</button>
                </div>

                <form id="formEditarPerfilConsultor" novalidate style="display: flex; flex-direction: column; gap: 0.95rem;">
                    <div class="perfil-form-group">
                        <label class="perfil-form-label" for="inputPerfilConsultorNome">Nome do Consultor</label>
                        <input type="text" id="inputPerfilConsultorNome" class="perfil-form-input" placeholder="Seu nome completo" required>
                    </div>

                    <div class="perfil-form-group">
                        <label class="perfil-form-label" for="inputPerfilConsultorEmail">E-mail de Cadastro</label>
                        <input type="email" id="inputPerfilConsultorEmail" class="perfil-form-input" placeholder="seu@email.com" required>
                    </div>

                    <div class="perfil-form-group">
                        <label class="perfil-form-label" for="inputPerfilConsultorSenha">Nova Senha (opcional)</label>
                        <input type="password" id="inputPerfilConsultorSenha" class="perfil-form-input" placeholder="Deixe em branco para manter a senha atual">
                    </div>

                    <div id="feedbackPerfilConsultor" class="perfil-feedback"></div>

                    <div class="modal-perfil-actions">
                        <button type="button" class="btn-perfil-cancelar" id="btnCancelarPerfilConsultor">Cancelar</button>
                        <button type="submit" class="btn-perfil-salvar" id="btnSalvarPerfilConsultor">Salvar Alterações</button>
                    </div>
                </form>

                <!-- Zona de Perigo (Exclusiva para consultor_admin) -->
                <div class="zona-perigo-consultor" id="secaoZonaPerigoConsultor" style="display: none;">
                    <div class="zona-perigo-titulo">⚠️ Zona de Perigo</div>
                    <div class="zona-perigo-descricao">
                        Encerre imediatamente sua assinatura e exclua permanentemente todos os dados da consultoria em conformidade com a LGPD.
                    </div>
                    <button type="button" class="btn-zona-perigo-cancelar" id="btnAbrirModalCancelarConta">
                        Cancelar Conta e Assinatura
                    </button>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    garantirModalCancelarConta();

    const overlay = document.getElementById('modalEditarPerfilConsultorOverlay');
    const btnFechar = document.getElementById('btnFecharModalPerfilConsultor');
    const btnCancelar = document.getElementById('btnCancelarPerfilConsultor');
    const btnAbrirCancelarConta = document.getElementById('btnAbrirModalCancelarConta');
    const form = document.getElementById('formEditarPerfilConsultor');
    const feedback = document.getElementById('feedbackPerfilConsultor');
    const btnSalvar = document.getElementById('btnSalvarPerfilConsultor');

    if (btnAbrirCancelarConta) {
        btnAbrirCancelarConta.addEventListener('click', () => {
            abrirModalCancelarConta();
        });
    }

    const fecharModal = () => {
        overlay.classList.remove('active');
        feedback.className = 'perfil-feedback';
        feedback.textContent = '';
    };

    btnFechar.addEventListener('click', fecharModal);
    btnCancelar.addEventListener('click', fecharModal);
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) fecharModal();
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        feedback.className = 'perfil-feedback';
        feedback.textContent = '';

        const nome = document.getElementById('inputPerfilConsultorNome').value.trim();
        const email = document.getElementById('inputPerfilConsultorEmail').value.trim();
        const senha = document.getElementById('inputPerfilConsultorSenha').value;

        if (!nome || !email) {
            feedback.textContent = 'Nome e e-mail são obrigatórios.';
            feedback.className = 'perfil-feedback erro';
            return;
        }

        if (senha && senha.length < 8) {
            feedback.textContent = 'A nova senha deve ter no mínimo 8 caracteres.';
            feedback.className = 'perfil-feedback erro';
            return;
        }

        btnSalvar.disabled = true;
        btnSalvar.textContent = 'Salvando...';

        try {
            const payload = { name: nome, email };
            if (senha) {
                payload.password = senha;
            }

            await atualizarPerfilUsuario(payload);

            const nomeHeaderEl = document.getElementById('headerUserName');
            if (nomeHeaderEl) {
                nomeHeaderEl.textContent = nome;
            }

            document.getElementById('inputPerfilConsultorSenha').value = '';
            feedback.textContent = 'Informações do perfil atualizadas com sucesso!';
            feedback.className = 'perfil-feedback sucesso';

            setTimeout(() => {
                fecharModal();
            }, 900);
        } catch (err) {
            feedback.textContent = err.message || 'Erro ao atualizar perfil.';
            feedback.className = 'perfil-feedback erro';
        } finally {
            btnSalvar.disabled = false;
            btnSalvar.textContent = 'Salvar Alterações';
        }
    });
}

function abrirModalEditarPerfil() {
    garantirModalEditarPerfil();
    const usuario = obterUsuarioLogado() || {};
    const inputNome = document.getElementById('inputPerfilConsultorNome');
    const inputEmail = document.getElementById('inputPerfilConsultorEmail');
    const inputSenha = document.getElementById('inputPerfilConsultorSenha');
    const feedback = document.getElementById('feedbackPerfilConsultor');

    if (inputNome) inputNome.value = usuario.name || '';
    if (inputEmail) inputEmail.value = usuario.email || '';
    if (inputSenha) inputSenha.value = '';
    if (feedback) {
        feedback.className = 'perfil-feedback';
        feedback.textContent = '';
    }

    atualizarVisibilidadeZonaPerigo(usuario);

    const overlay = document.getElementById('modalEditarPerfilConsultorOverlay');
    if (overlay) overlay.classList.add('active');
}

function formatarDataPtBr(valorIsoOuData) {
    if (!valorIsoOuData) return '--/--/----';
    if (/^\d{2}\/\d{2}\/\d{4}$/.test(String(valorIsoOuData))) {
        return String(valorIsoOuData);
    }
    const data = new Date(valorIsoOuData);
    if (Number.isNaN(data.getTime())) return String(valorIsoOuData);
    return data.toLocaleDateString('pt-BR');
}

function formatarMoedaBrl(valor) {
    const numero = Number(valor ?? 0);
    if (Number.isNaN(numero)) return 'R$ 0,00';
    return numero.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function garantirModalMinhaAssinatura() {
    if (document.getElementById('modalMinhaAssinaturaOverlay')) return;

    const modalHtml = `
        <div class="modal-assinatura-overlay" id="modalMinhaAssinaturaOverlay">
            <div class="modal-assinatura-card" role="dialog" aria-modal="true" aria-labelledby="tituloModalMinhaAssinatura">
                <div class="modal-perfil-consultor-header">
                    <div>
                        <div class="modal-perfil-consultor-title" id="tituloModalMinhaAssinatura">Minha Assinatura</div>
                        <div class="modal-perfil-consultor-subtitle">Detalhes do plano atual, limites da consultoria e ciclo de renovação</div>
                    </div>
                    <button type="button" class="modal-perfil-close" id="btnFecharModalMinhaAssinatura">&times;</button>
                </div>

                <!-- Plano Atual -->
                <div class="assinatura-bloco-plano">
                    <div>
                        <div style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.5px; color: #93c5fd; font-weight: 700;">Plano Atual</div>
                        <div class="assinatura-plano-nome" id="assinaturaPlanoNome">Carregando...</div>
                    </div>
                    <div class="assinatura-plano-preco" id="assinaturaPlanoPreco">R$ --,--/mês</div>
                </div>

                <!-- Forma de Pagamento + Vencimento e Ciclo -->
                <div class="assinatura-grid-info">
                    <div class="assinatura-info-item">
                        <span class="assinatura-info-label">Forma de Pagamento Atual</span>
                        <div class="assinatura-info-valor">
                            <span class="badge-metodo-pix" id="assinaturaFormaPagamentoBadge">PIX</span>
                        </div>
                    </div>
                    <div class="assinatura-info-item">
                        <span class="assinatura-info-label">Status do Ciclo</span>
                        <div class="assinatura-info-valor">
                            <span class="badge-status-assinatura ativa" id="assinaturaStatusBadge">Ativa</span>
                        </div>
                    </div>
                    <div class="assinatura-info-item">
                        <span class="assinatura-info-label">Vencimento da Assinatura</span>
                        <div class="assinatura-info-valor" id="assinaturaDataVencimento">--/--/----</div>
                    </div>
                    <div class="assinatura-info-item">
                        <span class="assinatura-info-label">Dias Restantes</span>
                        <div class="assinatura-info-valor" id="assinaturaDiasRestantes">-- dias</div>
                    </div>
                </div>

                <!-- Consumo Atual vs Limites -->
                <div class="assinatura-consumo-box">
                    <div style="font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; font-weight: 700;">Consumo Atual vs Limites do Plano</div>
                    <div class="assinatura-consumo-linha">
                        <span>Startups Cadastradas</span>
                        <strong id="assinaturaUsoStartups">0 / 0</strong>
                    </div>
                    <div class="assinatura-consumo-linha">
                        <span>Membros de Equipe</span>
                        <strong id="assinaturaUsoMembros">0 / 0</strong>
                    </div>
                    <div class="assinatura-consumo-linha">
                        <span>Minutas IA (Ciclo Mensal)</span>
                        <strong id="assinaturaUsoMinutas">0 / 0</strong>
                    </div>
                </div>

                <!-- Aviso da Janela de Renovação -->
                <div class="assinatura-aviso-renovacao" id="assinaturaAvisoRenovacao" style="display: none;"></div>

                <div id="feedbackMinhaAssinatura" class="perfil-feedback"></div>

                <!-- Ação de Renovação Mensal -->
                <div class="modal-perfil-actions" style="justify-content: space-between; align-items: center; gap: 12px;">
                    <button type="button" class="btn-perfil-cancelar" id="btnFecharRodapeAssinatura">Fechar</button>
                    <button type="button" class="btn-renovar-assinatura-pix" id="btnAcaoRenovarAssinaturaPix" disabled style="flex: 1;">
                        Renovar Assinatura via PIX
                    </button>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);

    const overlay = document.getElementById('modalMinhaAssinaturaOverlay');
    const btnFechar = document.getElementById('btnFecharModalMinhaAssinatura');
    const btnFecharRodape = document.getElementById('btnFecharRodapeAssinatura');
    const btnRenovar = document.getElementById('btnAcaoRenovarAssinaturaPix');
    const feedback = document.getElementById('feedbackMinhaAssinatura');

    const fecharModal = () => {
        overlay.classList.remove('active');
        if (feedback) {
            feedback.className = 'perfil-feedback';
            feedback.textContent = '';
        }
    };

    btnFechar.addEventListener('click', fecharModal);
    btnFecharRodape.addEventListener('click', fecharModal);
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) fecharModal();
    });

    btnRenovar.addEventListener('click', async () => {
        if (btnRenovar.disabled) return;

        feedback.className = 'perfil-feedback';
        feedback.textContent = '';

        const usuarioAtual = obterUsuarioLogado();
        const perfilAcesso = btnRenovar.dataset.perfilAcesso || usuarioAtual?.perfil_acesso || 'consultor_admin';

        if (perfilAcesso === 'consultor_padrao') {
            feedback.textContent = 'Apenas o administrador da consultoria pode gerar uma cobrança de renovação da assinatura.';
            feedback.className = 'perfil-feedback erro';
            return;
        }

        const textoOriginal = btnRenovar.textContent;
        btnRenovar.disabled = true;
        btnRenovar.textContent = 'Gerando pagamento PIX...';

        try {
            const resposta = await requisicaoApi('/assinatura/renovar', {
                method: 'POST'
            });

            const urlCheckout = resposta?.url_checkout;
            if (urlCheckout) {
                btnRenovar.textContent = 'Abrir PIX de Renovação Pendente';
                btnRenovar.disabled = false;
                feedback.textContent = resposta?.message || 'Cobrança PIX pronta! Redirecionando para o pagamento...';
                feedback.className = 'perfil-feedback sucesso';

                const novaJanela = window.open(urlCheckout, '_blank');
                if (!novaJanela) {
                    window.location.href = urlCheckout;
                }
            } else {
                throw new Error('Não foi possível obter o link de pagamento PIX.');
            }
        } catch (erro) {
            feedback.textContent = erro.message || 'Erro ao gerar cobrança PIX de renovação.';
            feedback.className = 'perfil-feedback erro';
            btnRenovar.textContent = textoOriginal;
            btnRenovar.disabled = false;
        }
    });
}

function preencherDadosModalAssinatura(dados) {
    const plano = dados?.plano || {};
    const uso = dados?.uso_atual || {};
    const diasRestantes = Number(dados?.dias_restantes ?? 0);
    const podeRenovar = Boolean(dados?.pode_renovar);
    const cobrancaAberta = Boolean(dados?.cobranca_aberta);
    const statusPagamento = dados?.status_pagamento || 'ativo';
    const dataVencimentoFmt = dados?.assinatura_expira_em_formatada || formatarDataPtBr(dados?.assinatura_expira_em);
    const dataLiberacaoFmt = dados?.data_liberacao_renovacao || '--/--/----';
    const perfilAcesso = dados?.perfil_acesso || obterUsuarioLogado()?.perfil_acesso || 'consultor_admin';

    const elNome = document.getElementById('assinaturaPlanoNome');
    const elPreco = document.getElementById('assinaturaPlanoPreco');
    const elMetodo = document.getElementById('assinaturaFormaPagamentoBadge');
    const elStatus = document.getElementById('assinaturaStatusBadge');
    const elVencimento = document.getElementById('assinaturaDataVencimento');
    const elDias = document.getElementById('assinaturaDiasRestantes');
    const elUsoStartups = document.getElementById('assinaturaUsoStartups');
    const elUsoMembros = document.getElementById('assinaturaUsoMembros');
    const elUsoMinutas = document.getElementById('assinaturaUsoMinutas');
    const elAviso = document.getElementById('assinaturaAvisoRenovacao');
    const btnRenovar = document.getElementById('btnAcaoRenovarAssinaturaPix');

    if (elNome) elNome.textContent = plano.nome || 'Plano Anjo Nexus';
    if (elPreco) {
        const valorFmt = plano.preco_formatado
            ? `R$ ${plano.preco_formatado}/mês`
            : `${formatarMoedaBrl(plano.preco)}/mês`;
        elPreco.textContent = valorFmt;
    }
    if (elMetodo) elMetodo.textContent = dados?.forma_pagamento || 'PIX';

    if (elStatus) {
        const vencida = statusPagamento !== 'ativo' || (dados?.assinatura_expira_em && diasRestantes <= 0 && !dados?.assinatura_ativa);
        if (vencida) {
            elStatus.textContent = 'Vencida';
            elStatus.className = 'badge-status-assinatura vencida';
        } else if (diasRestantes <= 5) {
            elStatus.textContent = 'Próxima do Vencimento';
            elStatus.className = 'badge-status-assinatura proxima';
        } else {
            elStatus.textContent = 'Ativa';
            elStatus.className = 'badge-status-assinatura ativa';
        }
    }

    if (elVencimento) elVencimento.textContent = dataVencimentoFmt || 'Sem vencimento';
    if (elDias) elDias.textContent = `${diasRestantes} ${diasRestantes === 1 ? 'dia restante' : 'dias restantes'}`;

    if (elUsoStartups) elUsoStartups.textContent = `${uso.startups_cadastradas ?? 0} / ${plano.limite_startups ?? 0}`;
    if (elUsoMembros) elUsoMembros.textContent = `${uso.membros_equipe ?? 0} / ${plano.limite_membros_equipe ?? 0}`;
    if (elUsoMinutas) elUsoMinutas.textContent = `${uso.minutas_ia ?? 0} / ${plano.limite_minutas_ia ?? 5}`;

    if (btnRenovar) {
        btnRenovar.dataset.perfilAcesso = perfilAcesso;

        if (!podeRenovar) {
            btnRenovar.disabled = true;
            btnRenovar.textContent = 'Renovar Assinatura via PIX';
            if (elAviso) {
                elAviso.style.display = 'block';
                elAviso.textContent = `A geração do próximo pagamento PIX será liberada 5 dias antes do vencimento (em ${dataLiberacaoFmt}).`;
            }
        } else {
            btnRenovar.disabled = false;
            btnRenovar.textContent = cobrancaAberta
                ? 'Abrir PIX de Renovação Pendente'
                : 'Gerar Novo Pagamento PIX';
            if (elAviso) {
                if (cobrancaAberta) {
                    elAviso.style.display = 'block';
                    elAviso.textContent = 'Você já possui uma cobrança PIX aberta dentro da validade de 60 minutos. Clique abaixo para concluir o pagamento (+30 dias acumulativos).';
                } else {
                    elAviso.style.display = 'block';
                    elAviso.textContent = 'Renovação liberada! Ao confirmar o pagamento PIX, serão somados +30 dias ao seu ciclo atual sem perda dos dias restantes.';
                }
            }
        }
    }
}

async function abrirModalMinhaAssinatura() {
    garantirModalMinhaAssinatura();
    const overlay = document.getElementById('modalMinhaAssinaturaOverlay');
    const feedback = document.getElementById('feedbackMinhaAssinatura');
    const btnRenovar = document.getElementById('btnAcaoRenovarAssinaturaPix');

    if (feedback) {
        feedback.className = 'perfil-feedback';
        feedback.textContent = '';
    }
    if (btnRenovar) {
        btnRenovar.disabled = true;
        btnRenovar.textContent = 'Carregando assinatura...';
    }
    if (overlay) {
        overlay.classList.add('active');
    }

    try {
        const dadosAssinatura = await requisicaoApi('/assinatura/atual', {
            method: 'GET'
        });
        preencherDadosModalAssinatura(dadosAssinatura);
    } catch (erro) {
        if (feedback) {
            feedback.textContent = erro.message || 'Não foi possível carregar os dados da assinatura.';
            feedback.className = 'perfil-feedback erro';
        }
        if (btnRenovar) {
            btnRenovar.disabled = true;
            btnRenovar.textContent = 'Renovar Assinatura via PIX';
        }
    }
}

export async function inicializarSeletorGlobal(containerId, options = {}) {
    injetarEstilosHeaderGlobal();
    garantirModalEditarPerfil();
    garantirModalMinhaAssinatura();

    const container = document.getElementById(containerId);
    if (!container) return;

    let startupAtiva = obterStartupAtiva();
    if (!startupAtiva) {
        startupAtiva = { id: '1', nome: 'Startup Piloto (Demonstração)' };
        salvarStartupAtiva('1', 'Startup Piloto (Demonstração)');
    }

    const siglaInicial = (startupAtiva.nome || 'AN').slice(0, 2).toUpperCase();
    const usuarioLocal = obterUsuarioLogado();
    const nomeInicialUsuario = usuarioLocal?.name || 'Consultor';

    // Padroniza o container à direita do header com o Menu de Startups à esquerda do Perfil do Consultor
    container.className = 'header-right-group';
    container.innerHTML = `
        <div class="seletor-startup-wrapper" id="seletorStartupWrapper">
            <div style="width: 34px; height: 34px; min-width: 34px; border-radius: 8px; background: linear-gradient(135deg, #2563eb, #7c3aed); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.3); flex-shrink: 0;" id="avatar-startup-sigla">
                ${siglaInicial}
            </div>
            <div style="display: flex; flex-direction: column; gap: 2px; min-width: 0;">
                <span style="font-size: 9px; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.5px; white-space: nowrap;">Startup Assessorada:</span>
                <div class="startup-trigger-box" id="startupTriggerBox">
                    <span class="startup-trigger-nome" id="startupTriggerNome">${startupAtiva.nome}</span>
                    <span class="startup-trigger-seta">▼</span>
                </div>
            </div>

            <div class="startup-dropdown-menu" id="startupDropdownMenu">
                <div class="startup-dropdown-header">Selecionar ou Configurar Startup</div>
                <div class="startup-dropdown-lista" id="startupDropdownLista"></div>
                <a href="nova-startup-painel.html" class="startup-dropdown-criar" id="btnHeaderCriarStartup">
                    <span>+ Criar Startup</span>
                </a>
            </div>
        </div>

        <!-- Central de Alertas: Match Inteligente (EDTAN-172) & Prazos Críticos (EDTAN-171) -->
        <div class="alerta-notificacoes-wrapper" id="alertaNotificacoesWrapper" style="position: relative;">
            <button id="btnSinoAlertas" class="btn-sino-alertas" title="Central de Alertas e Prazos" style="background: rgba(255,255,255,0.04); border: 1px solid #2a2e39; width: 38px; height: 38px; border-radius: 10px; color: #94a3b8; cursor: pointer; display: flex; align-items: center; justify-content: center; position: relative; transition: 0.2s;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2zm-2 1H8v-6c0-2.48 1.51-4.5 4-4.5s4 2.02 4 4.5v6z"/>
                </svg>
                <span id="badgeContadorAlertas" style="display: none; position: absolute; top: -4px; right: -4px; background: #ef4444; color: #fff; font-size: 10px; font-weight: 700; width: 18px; height: 18px; border-radius: 50%; align-items: center; justify-content: center; box-shadow: 0 0 8px rgba(239,68,68,0.6);">0</span>
            </button>
            <div class="alertas-dropdown-menu" id="alertasDropdownMenu" style="display: none; position: absolute; right: 0; top: 46px; width: 370px; background: #0f131a; border: 1px solid #262b36; border-radius: 12px; box-shadow: 0 12px 30px rgba(0,0,0,0.85); z-index: 1000; overflow: hidden; font-family: inherit;">
                <div style="padding: 10px 14px; border-bottom: 1px solid #1e2430; display: flex; justify-content: space-between; align-items: center; background: #131822;">
                    <span style="font-weight: 700; font-size: 13px; color: #f8fafc;">🔔 Central de Alertas</span>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button id="btnTestarNotificacaoPopUp" title="Testar Pop-up na Web e Mobile" style="background: rgba(99,102,241,0.15); border: 1px solid rgba(99,102,241,0.35); color: #a5b4fc; font-size: 10px; cursor: pointer; font-weight: 600; padding: 3px 8px; border-radius: 6px;">🔔 Pop-up</button>
                        <button id="btnMarcarTodosLidos" style="background: none; border: none; color: #38bdf8; font-size: 11px; cursor: pointer; font-weight: 600;">Marcar lidos</button>
                    </div>
                </div>
                <!-- Filtros por tipo de alerta -->
                <div id="abasFiltroAlertas" style="display: flex; border-bottom: 1px solid #1e2430; background: #0b0e14; padding: 4px 8px; gap: 4px;">
                    <button class="aba-alerta-btn ativa" data-tipo="todos" style="flex: 1; padding: 5px 8px; font-size: 11px; font-weight: 600; border-radius: 6px; border: none; background: #1e293b; color: #f1f5f9; cursor: pointer;">Todos</button>
                    <button class="aba-alerta-btn" data-tipo="prazos" style="flex: 1; padding: 5px 8px; font-size: 11px; font-weight: 600; border-radius: 6px; border: none; background: transparent; color: #94a3b8; cursor: pointer;">⏳ Prazos</button>
                    <button class="aba-alerta-btn" data-tipo="match" style="flex: 1; padding: 5px 8px; font-size: 11px; font-weight: 600; border-radius: 6px; border: none; background: transparent; color: #94a3b8; cursor: pointer;">🎯 Matches</button>
                </div>
                <div id="listaAlertasDropdown" style="max-height: 380px; overflow-y: auto; padding: 8px; display: flex; flex-direction: column; gap: 8px;"></div>
            </div>
        </div>

        <div class="user-section-global" id="userMenuBtn">
            <span class="user-name" id="headerUserName">${nomeInicialUsuario}</span>
            <div class="avatar">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
            </div>
            <div class="user-dropdown-global" id="mainDropdown">
                <div class="user-dropdown-item" id="btnMenuEditarPerfil">Editar Perfil</div>
                <div class="user-dropdown-item" id="btnMenuVisualizarAssinatura">Visualizar Assinatura</div>
                <div class="user-dropdown-item" id="btnMenuConfiguracoesFake">Configurações</div>
                <div class="user-dropdown-item logoff-item" id="btnMenuLogoff">Logoff</div>
            </div>
        </div>
    `;

    const seletorWrapper = document.getElementById('seletorStartupWrapper');
    const startupDropdownMenu = document.getElementById('startupDropdownMenu');
    const startupDropdownLista = document.getElementById('startupDropdownLista');
    const startupTriggerNome = document.getElementById('startupTriggerNome');
    const avatarSigla = document.getElementById('avatar-startup-sigla');

    const userMenuBtn = document.getElementById('userMenuBtn');
    const mainDropdown = document.getElementById('mainDropdown');
    const btnEditarPerfil = document.getElementById('btnMenuEditarPerfil');
    const btnVisualizarAssinatura = document.getElementById('btnMenuVisualizarAssinatura');
    const btnConfigFake = document.getElementById('btnMenuConfiguracoesFake');
    const btnLogoff = document.getElementById('btnMenuLogoff');

    const fecharDropdownStartups = () => {
        if (startupDropdownMenu) startupDropdownMenu.classList.remove('active');
        if (seletorWrapper) seletorWrapper.classList.remove('open');
    };

    if (seletorWrapper && startupDropdownMenu) {
        seletorWrapper.addEventListener('click', (e) => {
            if (e.target.closest('.btn-engrenagem-startup') || e.target.closest('.startup-dropdown-criar')) {
                return;
            }
            if (e.target.closest('.startup-dropdown-item')) {
                return;
            }
            const abriu = startupDropdownMenu.classList.toggle('active');
            seletorWrapper.classList.toggle('open', abriu);
            if (abriu && mainDropdown) {
                mainDropdown.classList.remove('active');
            }
        });
    }

    // Eventos do menu do Perfil do Consultor
    if (userMenuBtn && mainDropdown) {
        userMenuBtn.addEventListener('click', (e) => {
            if (e.target.closest('.user-dropdown-global')) return;
            const abriu = mainDropdown.classList.toggle('active');
            if (abriu) {
                fecharDropdownStartups();
            }
        });
    }

    document.addEventListener('click', (e) => {
        if (userMenuBtn && !userMenuBtn.contains(e.target) && mainDropdown) {
            mainDropdown.classList.remove('active');
        }
        if (seletorWrapper && !seletorWrapper.contains(e.target)) {
            fecharDropdownStartups();
        }
    });

    if (btnEditarPerfil) {
        btnEditarPerfil.addEventListener('click', (e) => {
            e.stopPropagation();
            if (mainDropdown) mainDropdown.classList.remove('active');
            abrirModalEditarPerfil();
        });
    }

    if (btnVisualizarAssinatura) {
        btnVisualizarAssinatura.addEventListener('click', (e) => {
            e.stopPropagation();
            if (mainDropdown) mainDropdown.classList.remove('active');
            abrirModalMinhaAssinatura();
        });
    }

    if (btnConfigFake) {
        btnConfigFake.addEventListener('click', (e) => {
            e.stopPropagation();
            if (mainDropdown) mainDropdown.classList.remove('active');
        });
    }

    if (btnLogoff) {
        btnLogoff.addEventListener('click', (e) => {
            e.stopPropagation();
            removerToken();
            window.location.href = 'login.html';
        });
    }

    // Sincroniza dados reais do usuário cadastrado a partir da API
    obterPerfilUsuario().then((usuarioApi) => {
        if (usuarioApi && usuarioApi.name) {
            const nomeHeaderEl = document.getElementById('headerUserName');
            if (nomeHeaderEl) {
                nomeHeaderEl.textContent = usuarioApi.name;
            }
        }
        atualizarVisibilidadeZonaPerigo(usuarioApi);
    });

    function renderizarItensDropdown(listaStartups) {
        if (!startupDropdownLista) return;
        startupDropdownLista.innerHTML = '';

        const ativaAtual = obterStartupAtiva() || startupAtiva;

        listaStartups.forEach((st) => {
            const nomeExibicao = st.nome_fantasia || st.razao_social || st.nome || 'Startup';
            const ehAtiva = String(st.id) === String(ativaAtual.id);

            const itemEl = document.createElement('div');
            itemEl.className = `startup-dropdown-item ${ehAtiva ? 'ativa' : ''}`;

            const infoEl = document.createElement('div');
            infoEl.className = 'startup-item-info';
            infoEl.innerHTML = `
                <span class="startup-item-nome">${nomeExibicao}</span>
                ${ehAtiva ? '<span class="startup-item-status">● Em uso no momento</span>' : ''}
            `;

            const btnGear = document.createElement('button');
            btnGear.type = 'button';
            btnGear.className = 'btn-engrenagem-startup';
            btnGear.title = `Configurar dados de ${nomeExibicao}`;
            btnGear.innerHTML = `
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
            `;

            // Clicar na engrenagem vai para a página dedicada de configuração daquela startup
            btnGear.addEventListener('click', (e) => {
                e.stopPropagation();
                fecharDropdownStartups();
                window.location.href = `perfil-startup.html?id=${encodeURIComponent(st.id)}`;
            });

            // Clicar no item seleciona a startup como ativa
            itemEl.addEventListener('click', (e) => {
                if (e.target.closest('.btn-engrenagem-startup')) return;
                e.stopPropagation();
                salvarStartupAtiva(st.id, nomeExibicao);
                startupAtiva = { id: String(st.id), nome: nomeExibicao };
                if (startupTriggerNome) startupTriggerNome.textContent = nomeExibicao;
                if (avatarSigla) avatarSigla.textContent = (nomeExibicao || 'AN').slice(0, 2).toUpperCase();
                fecharDropdownStartups();
                renderizarItensDropdown(listaStartups);

                if (options.onTrocarStartup) {
                    options.onTrocarStartup(st.id, nomeExibicao);
                } else {
                    window.location.reload();
                }
            });

            itemEl.appendChild(infoEl);
            itemEl.appendChild(btnGear);
            startupDropdownLista.appendChild(itemEl);
        });
    }

    // Renderiza inicialmente com a startup ativa em cache
    renderizarItensDropdown([{ id: startupAtiva.id, nome_fantasia: startupAtiva.nome }]);

    // Tenta obter lista atualizada de startups da API sem bloquear o fluxo visual
    try {
        const startups = await listarStartupsDaConsultoria();

        if (Array.isArray(startups) && startups.length > 0) {
            const encontrada = startups.find(st => String(st.id) === String(startupAtiva.id)) || startups[0];
            if (encontrada) {
                const nomeEncontrado = encontrada.nome_fantasia || encontrada.razao_social;
                salvarStartupAtiva(encontrada.id, nomeEncontrado);
                startupAtiva = { id: String(encontrada.id), nome: nomeEncontrado };
                if (startupTriggerNome) startupTriggerNome.textContent = nomeEncontrado;
                if (avatarSigla) avatarSigla.textContent = (nomeEncontrado || 'AN').slice(0, 2).toUpperCase();
            }
            renderizarItensDropdown(startups);
        }
    } catch (err) {
        console.warn('Operando com startup em cache local.');
    }

    // ==========================================
    // CENTRAL DE ALERTAS (MATCH EDTAN-172 & PRAZOS CRÍTICOS EDTAN-171 / RF-47)
    // ==========================================
    const btnSinoAlertas = document.getElementById('btnSinoAlertas');
    const badgeContador = document.getElementById('badgeContadorAlertas');
    const alertasDropdown = document.getElementById('alertasDropdownMenu');
    const listaAlertasDropdown = document.getElementById('listaAlertasDropdown');
    const btnMarcarTodosLidos = document.getElementById('btnMarcarTodosLidos');
    const btnTestarPopUp = document.getElementById('btnTestarNotificacaoPopUp');
    const abasFiltroAlertas = document.querySelectorAll('.aba-alerta-btn');

    let alertasMatch = [];
    let alertasPrazos = [];
    let filtroTipoAtivo = 'todos';

    function exibirPopUpNotificacao(alerta, opcoes = {}) {
        if (!alerta) return;

        // Remove qualquer popup flutuante anterior
        const antigo = document.getElementById('anjo-toast-notificacao');
        if (antigo) antigo.remove();

        // Tenta acionar vibração tátil no mobile
        try {
            if ('vibrate' in navigator) {
                navigator.vibrate([70, 50, 70]);
            }
        } catch (_) {}

        // Tenta disparar Notification nativa do navegador/dispositivo se houver permissão
        try {
            if (window.Notification && Notification.permission === 'granted') {
                new Notification(alerta.titulo || 'Alerta Anjo Nexus', {
                    body: alerta.mensagem || 'Confira os detalhes no Radar de Editais.',
                    icon: '../favicon.ico'
                });
            }
        } catch (_) {}

        const ehPrazo = alerta._tipo === 'prazos' || alerta.dias_restantes !== undefined;
        const isCritico = ehPrazo ? (alerta.nivel_urgencia === 'critico') : ((alerta.score_match || 0) >= 85);
        const corBorda = isCritico ? '#ef4444' : '#f59e0b';
        const icone = ehPrazo ? '🚨' : '⚡';
        const tagTexto = ehPrazo
            ? (alerta.nivel_urgencia === 'critico' ? `CRÍTICO • ${alerta.dias_restantes}d` : `PRAZO • ${alerta.dias_restantes}d`)
            : `${alerta.score_match || 80}% MATCH`;

        const titulo = alerta.edital?.title || alerta.titulo || 'Alerta de Oportunidade';
        const editalId = alerta.edital_id;
        const mensagem = alerta.mensagem || 'Atenção aos requisitos e prazos do edital.';

        const toast = document.createElement('div');
        toast.id = 'anjo-toast-notificacao';
        toast.className = 'anjo-toast-notificacao';
        toast.style.cssText = `
            position: fixed;
            top: 24px;
            right: 24px;
            max-width: 410px;
            width: calc(100% - 32px);
            background: rgba(15, 19, 26, 0.96);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid ${corBorda};
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.85), 0 0 20px ${corBorda}33;
            border-radius: 12px;
            padding: 14px 16px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-family: inherit;
            animation: deslizarNotificacaoEntrada 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        `;

        toast.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="font-size: 16px;">${icone}</span>
                    <span style="background: ${corBorda}22; color: ${corBorda}; border: 1px solid ${corBorda}55; font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 999px; text-transform: uppercase;">
                        ${tagTexto}
                    </span>
                </div>
                <button id="btnFecharToast" style="background: none; border: none; color: #94a3b8; font-size: 20px; cursor: pointer; padding: 0 4px; line-height: 1;">&times;</button>
            </div>
            <div style="font-size: 13px; font-weight: 600; color: #f8fafc; line-height: 1.35;">
                ${titulo}
            </div>
            <div style="font-size: 12px; color: #cbd5e1; line-height: 1.4;">
                ${mensagem}
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 4px;">
                <button id="btnDispensarToast" style="background: transparent; border: 1px solid #334155; color: #94a3b8; font-size: 11px; font-weight: 600; padding: 5px 10px; border-radius: 6px; cursor: pointer;">
                    Dispensar
                </button>
                <button id="btnVerEditalToast" style="background: #2563eb; border: none; color: #ffffff; font-size: 11px; font-weight: 600; padding: 5px 12px; border-radius: 6px; cursor: pointer;">
                    Ver no Radar →
                </button>
            </div>
        `;

        document.body.appendChild(toast);

        const fechar = () => {
            toast.style.animation = 'deslizarNotificacaoSaida 0.25s ease forwards';
            setTimeout(() => toast.remove(), 260);
        };

        toast.querySelector('#btnFecharToast')?.addEventListener('click', fechar);
        toast.querySelector('#btnDispensarToast')?.addEventListener('click', () => {
            if (alerta.id) {
                if (ehPrazo) marcarAlertaPrazoComoLido(alerta.id);
                else marcarAlertaComoLido(alerta.id);
            }
            fechar();
        });

        toast.querySelector('#btnVerEditalToast')?.addEventListener('click', () => {
            fechar();
            if (editalId) {
                if (window.selectEdital) {
                    window.selectEdital(editalId);
                } else {
                    window.location.href = `RadarEditais.html?edital_id=${editalId}`;
                }
            }
        });

        if (!opcoes.persistente) {
            setTimeout(() => {
                if (document.getElementById('anjo-toast-notificacao') === toast) {
                    fechar();
                }
            }, 8000);
        }
    }

    async function atualizarTodosAlertas(stId) {
        if (!stId) return;
        try {
            const [resMatch, resPrazos] = await Promise.allSettled([
                verificarAlertasMatch(stId),
                verificarAlertasPrazosCriticos(stId, 15)
            ]);

            if (resMatch.status === 'fulfilled' && resMatch.value?.sucesso) {
                alertasMatch = (resMatch.value.dados || []).map(a => ({ ...a, _tipo: 'match' }));
            }

            if (resPrazos.status === 'fulfilled' && resPrazos.value?.sucesso) {
                alertasPrazos = (resPrazos.value.dados || []).map(a => ({ ...a, _tipo: 'prazos' }));
            }

            renderizarCentralAlertas();

            // Disparo automático do Pop-up Mobile / Web para o alerta mais crítico na sessão
            const alertaMaisCritico = alertasPrazos.find(a => !a.lido && a.nivel_urgencia === 'critico')
                || alertasPrazos.find(a => !a.lido && a.nivel_urgencia === 'urgente')
                || alertasMatch.find(a => !a.lido && (a.score_match || 0) >= 85);

            if (alertaMaisCritico) {
                const chaveSessao = `anjo_notif_visto_${alertaMaisCritico._tipo}_${alertaMaisCritico.id}`;
                if (!sessionStorage.getItem(chaveSessao)) {
                    sessionStorage.setItem(chaveSessao, '1');
                    setTimeout(() => {
                        exibirPopUpNotificacao(alertaMaisCritico);
                    }, 1200);
                }
            }
        } catch (e) {
            console.debug('Central de alertas operando em fallback offline.');
        }
    }

    function renderizarCentralAlertas() {
        if (!badgeContador || !listaAlertasDropdown) return;

        const naoLidosMatch = alertasMatch.filter(a => !a.lido).length;
        const naoLidosPrazos = alertasPrazos.filter(a => !a.lido).length;
        const totalNaoLidos = naoLidosMatch + naoLidosPrazos;

        if (totalNaoLidos > 0) {
            badgeContador.textContent = totalNaoLidos > 9 ? '9+' : String(totalNaoLidos);
            badgeContador.style.display = 'flex';
        } else {
            badgeContador.style.display = 'none';
        }

        let listaFiltrada = [];
        if (filtroTipoAtivo === 'match') {
            listaFiltrada = [...alertasMatch];
        } else if (filtroTipoAtivo === 'prazos') {
            listaFiltrada = [...alertasPrazos];
        } else {
            // Todos: prioriza prazos críticos não lidos, depois matches não lidos, depois lidos
            listaFiltrada = [...alertasPrazos, ...alertasMatch].sort((a, b) => {
                if (a.lido !== b.lido) return a.lido ? 1 : -1;
                if (a._tipo === 'prazos' && a.nivel_urgencia === 'critico') return -1;
                if (b._tipo === 'prazos' && b.nivel_urgencia === 'critico') return 1;
                return 0;
            });
        }

        if (listaFiltrada.length === 0) {
            listaAlertasDropdown.innerHTML = `
                <div style="padding: 24px 16px; text-align: center; color: #64748b; font-size: 12px;">
                    <div style="font-size: 24px; margin-bottom: 6px;">🎯</div>
                    Nenhuma notificação encontrada no momento.
                </div>
            `;
            return;
        }

        listaAlertasDropdown.innerHTML = '';
        listaFiltrada.forEach(alerta => {
            const ehPrazo = alerta._tipo === 'prazos';
            const cardEl = document.createElement('div');

            let corBordaEsquerda = '#3b82f6';
            let badgeTagHtml = '';

            if (ehPrazo) {
                if (alerta.nivel_urgencia === 'critico') {
                    corBordaEsquerda = '#ef4444';
                    badgeTagHtml = `<span style="background: rgba(239,68,68,0.15); color: #ef4444; border: 1px solid rgba(239,68,68,0.4); font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 999px;">🚨 Crítico (${alerta.dias_restantes}d)</span>`;
                } else if (alerta.nivel_urgencia === 'urgente') {
                    corBordaEsquerda = '#f59e0b';
                    badgeTagHtml = `<span style="background: rgba(245,158,11,0.15); color: #f59e0b; border: 1px solid rgba(245,158,11,0.4); font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 999px;">⚠️ Urgente (${alerta.dias_restantes}d)</span>`;
                } else {
                    corBordaEsquerda = '#3b82f6';
                    badgeTagHtml = `<span style="background: rgba(59,130,246,0.15); color: #3b82f6; border: 1px solid rgba(59,130,246,0.4); font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 999px;">📅 Atenção (${alerta.dias_restantes}d)</span>`;
                }
            } else {
                const score = alerta.score_match || 70;
                corBordaEsquerda = score >= 85 ? '#10b981' : '#6366f1';
                badgeTagHtml = `<span style="background: rgba(16,185,129,0.15); color: #10b981; font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 999px;">⚡ ${score}% Match</span>`;
            }

            cardEl.style.cssText = `
                background: ${alerta.lido ? 'rgba(255,255,255,0.02)' : 'rgba(30,41,59,0.5)'};
                border: 1px solid ${alerta.lido ? '#1e2430' : '#334155'};
                border-left: 3px solid ${corBordaEsquerda};
                border-radius: 8px;
                padding: 10px 12px;
                display: flex;
                flex-direction: column;
                gap: 6px;
                transition: 0.2s;
            `;

            const tituloEdital = alerta.edital?.title || alerta.titulo;
            const editalId = alerta.edital_id;

            cardEl.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    ${badgeTagHtml}
                    ${!alerta.lido ? `<button class="btn-marcar-um-lido" data-id="${alerta.id}" data-tipo="${alerta._tipo}" style="background: none; border: none; color: #94a3b8; font-size: 11px; cursor: pointer; padding: 2px 6px;">✓ Lido</button>` : ''}
                </div>
                <div style="font-weight: 600; font-size: 12px; color: #f1f5f9; line-height: 1.3;">${tituloEdital}</div>
                <div style="font-size: 11px; color: #94a3b8; line-height: 1.35;">${alerta.mensagem}</div>
                <div style="display: flex; justify-content: flex-end; align-items: center; gap: 6px; margin-top: 4px;">
                    ${ehPrazo ? `<button class="btn-email-alerta" data-id="${alerta.id}" title="Disparar e-mail de notificação" style="background: rgba(255,255,255,0.04); border: 1px solid #334155; color: #94a3b8; font-size: 11px; font-weight: 500; padding: 4px 8px; border-radius: 6px; cursor: pointer;">✉️ E-mail</button>` : ''}
                    <button class="btn-ver-edital-alerta" data-edital-id="${editalId}" style="background: #2563eb; color: #fff; border: none; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; cursor: pointer;">Ver no Radar →</button>
                </div>
            `;

            cardEl.querySelector('.btn-ver-edital-alerta')?.addEventListener('click', (e) => {
                e.stopPropagation();
                fecharDropdownAlertas();
                if (window.selectEdital) {
                    window.selectEdital(editalId);
                } else {
                    window.location.href = `RadarEditais.html?edital_id=${editalId}`;
                }
            });

            if (ehPrazo) {
                cardEl.querySelector('.btn-email-alerta')?.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    const btn = e.currentTarget;
                    const original = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = 'Enviando...';
                    try {
                        const res = await dispararEmailAlertaPrazo(alerta.id);
                        if (res && res.sucesso) {
                            btn.innerHTML = '✓ Enviado!';
                            btn.style.color = '#10b981';
                            btn.style.borderColor = '#10b981';
                        } else {
                            btn.innerHTML = 'Falha';
                            setTimeout(() => { btn.innerHTML = original; btn.disabled = false; }, 2000);
                        }
                    } catch (err) {
                        btn.innerHTML = 'Erro';
                        setTimeout(() => { btn.innerHTML = original; btn.disabled = false; }, 2000);
                    }
                });
            }

            cardEl.querySelector('.btn-marcar-um-lido')?.addEventListener('click', async (e) => {
                e.stopPropagation();
                try {
                    if (ehPrazo) {
                        await marcarAlertaPrazoComoLido(alerta.id);
                    } else {
                        await marcarAlertaComoLido(alerta.id);
                    }
                    alerta.lido = true;
                    renderizarCentralAlertas();
                } catch (err) {
                    console.error('Falha ao marcar lido:', err);
                }
            });

            listaAlertasDropdown.appendChild(cardEl);
        });
    }

    // Configura botões de abas de filtro
    if (abasFiltroAlertas) {
        abasFiltroAlertas.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                abasFiltroAlertas.forEach(b => {
                    b.classList.remove('ativa');
                    b.style.background = 'transparent';
                    b.style.color = '#94a3b8';
                });
                btn.classList.add('ativa');
                btn.style.background = '#1e293b';
                btn.style.color = '#f1f5f9';
                filtroTipoAtivo = btn.dataset.tipo || 'todos';
                renderizarCentralAlertas();
            });
        });
    }

    function fecharDropdownAlertas() {
        if (alertasDropdown) alertasDropdown.style.display = 'none';
    }

    if (btnSinoAlertas) {
        btnSinoAlertas.addEventListener('click', (e) => {
            e.stopPropagation();
            fecharDropdownStartups();
            fecharMenuUsuario();
            if (alertasDropdown) {
                alertasDropdown.style.display = alertasDropdown.style.display === 'block' ? 'none' : 'block';
            }
        });
    }

    if (btnTestarPopUp) {
        btnTestarPopUp.addEventListener('click', (e) => {
            e.stopPropagation();
            fecharDropdownAlertas();
            const amostra = alertasPrazos[0] || alertasMatch[0] || {
                titulo: 'Edital FINEP Mulheres Inovadoras - Fase Final',
                mensagem: '🚨 Encerramento em 2 dias (11/11/2026)! Revise proposta e documentação técnica.',
                dias_restantes: 2,
                nivel_urgencia: 'critico',
                _tipo: 'prazos'
            };
            exibirPopUpNotificacao(amostra, { persistente: false });
        });
    }

    if (btnMarcarTodosLidos) {
        btnMarcarTodosLidos.addEventListener('click', async (e) => {
            e.stopPropagation();
            try {
                await Promise.allSettled([
                    marcarTodosAlertasComoLidos(startupAtiva.id),
                    marcarTodosAlertasPrazoComoLidos(startupAtiva.id)
                ]);
                alertasMatch.forEach(a => a.lido = true);
                alertasPrazos.forEach(a => a.lido = true);
                renderizarCentralAlertas();
            } catch (err) {
                console.error('Falha ao marcar todos lidos:', err);
            }
        });
    }

    document.addEventListener('click', (e) => {
        if (!e.target.closest('#alertaNotificacoesWrapper') && !e.target.closest('#anjo-toast-notificacao')) {
            fecharDropdownAlertas();
        }
    });

    // Inicia verificação automática de alertas de match e prazos críticos para a startup ativa
    atualizarTodosAlertas(startupAtiva.id);
}
