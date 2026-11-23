/**
 * Componente Global de Modais Elegantes (Dark Mode institucional Anjo Nexus)
 * Substitui os alertas e confirms nativos feios do navegador (alert / confirm)
 */

export function exibirModalAlerta({
    titulo = 'Aviso',
    mensagem = '',
    tipo = 'info', // 'sucesso', 'erro', 'aviso', 'info'
    textoBotao = 'Entendido'
} = {}) {
    return new Promise((resolve) => {
        removerModalExistente();

        const backdrop = document.createElement('div');
        backdrop.id = 'anjo-modal-backdrop';
        backdrop.style.cssText = `
            position: fixed; inset: 0; z-index: 999999;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(6px);
            display: flex; align-items: center; justify-content: center;
            padding: 20px; animation: fadeInModal 0.2s ease-out forwards;
        `;

        const coresIcone = {
            sucesso: '#10b981',
            erro: '#ef4444',
            aviso: '#f59e0b',
            info: '#6366f1'
        };

        const icones = {
            sucesso: '✔',
            erro: '✖',
            aviso: '⚠️',
            info: 'ℹ️'
        };

        const corDestaque = coresIcone[tipo] || coresIcone.info;
        const iconeSimbolo = icones[tipo] || icones.info;

        const card = document.createElement('div');
        card.style.cssText = `
            background: #181a20;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 14px;
            max-width: 440px; width: 100%;
            padding: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
            display: flex; flex-direction: column; gap: 16px;
            font-family: inherit; color: #f3f4f6;
            animation: scaleInModal 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        `;

        card.innerHTML = `
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: ${corDestaque}22; border: 1px solid ${corDestaque}55; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: ${corDestaque}; flex-shrink: 0;">
                    ${iconeSimbolo}
                </div>
                <h3 style="margin: 0; font-size: 1.15rem; font-weight: 600; color: #ffffff;">${titulo}</h3>
            </div>
            <div style="font-size: 0.95rem; line-height: 1.55; color: #cbd5e1; word-break: break-word;">
                ${mensagem}
            </div>
            <div style="display: flex; justify-content: flex-end; margin-top: 8px;">
                <button id="anjo-modal-btn-confirmar" style="
                    background: #6366f1; color: #ffffff; border: none;
                    border-radius: 8px; padding: 10px 20px; font-size: 0.95rem; font-weight: 500;
                    cursor: pointer; transition: background 0.2s;
                ">${textoBotao}</button>
            </div>
        `;

        backdrop.appendChild(card);
        document.body.appendChild(backdrop);

        const btnConfirmar = card.querySelector('#anjo-modal-btn-confirmar');
        btnConfirmar.focus();

        btnConfirmar.onmouseover = () => btnConfirmar.style.background = '#4f46e5';
        btnConfirmar.onmouseout = () => btnConfirmar.style.background = '#6366f1';

        btnConfirmar.onclick = () => {
            removerModalExistente();
            resolve(true);
        };
    });
}

export function exibirModalConfirmacao({
    titulo = 'Confirmação',
    mensagem = 'Tem certeza que deseja prosseguir?',
    textoConfirmar = 'Confirmar',
    textoCancelar = 'Cancelar',
    corConfirmar = '#6366f1',
    icone = '❓'
} = {}) {
    return new Promise((resolve) => {
        removerModalExistente();

        const backdrop = document.createElement('div');
        backdrop.id = 'anjo-modal-backdrop';
        backdrop.style.cssText = `
            position: fixed; inset: 0; z-index: 999999;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(6px);
            display: flex; align-items: center; justify-content: center;
            padding: 20px; animation: fadeInModal 0.2s ease-out forwards;
        `;

        const card = document.createElement('div');
        card.style.cssText = `
            background: #181a20;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 14px;
            max-width: 440px; width: 100%;
            padding: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
            display: flex; flex-direction: column; gap: 16px;
            font-family: inherit; color: #f3f4f6;
            animation: scaleInModal 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        `;

        card.innerHTML = `
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0;">
                    ${icone}
                </div>
                <h3 style="margin: 0; font-size: 1.15rem; font-weight: 600; color: #ffffff;">${titulo}</h3>
            </div>
            <div style="font-size: 0.95rem; line-height: 1.55; color: #cbd5e1; word-break: break-word;">
                ${mensagem}
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 8px;">
                <button id="anjo-modal-btn-cancelar" style="
                    background: transparent; color: #94a3b8; border: 1px solid rgba(255, 255, 255, 0.15);
                    border-radius: 8px; padding: 10px 18px; font-size: 0.95rem; font-weight: 500;
                    cursor: pointer; transition: all 0.2s;
                ">${textoCancelar}</button>
                <button id="anjo-modal-btn-confirmar" style="
                    background: ${corConfirmar}; color: #ffffff; border: none;
                    border-radius: 8px; padding: 10px 20px; font-size: 0.95rem; font-weight: 500;
                    cursor: pointer; transition: opacity 0.2s;
                ">${textoConfirmar}</button>
            </div>
        `;

        backdrop.appendChild(card);
        document.body.appendChild(backdrop);

        const btnCancelar = card.querySelector('#anjo-modal-btn-cancelar');
        const btnConfirmar = card.querySelector('#anjo-modal-btn-confirmar');
        btnConfirmar.focus();

        btnCancelar.onmouseover = () => btnCancelar.style.background = 'rgba(255, 255, 255, 0.06)';
        btnCancelar.onmouseout = () => btnCancelar.style.background = 'transparent';

        btnCancelar.onclick = () => {
            removerModalExistente();
            resolve(false);
        };

        btnConfirmar.onclick = () => {
            removerModalExistente();
            resolve(true);
        };
    });
}

function removerModalExistente() {
    const antigo = document.getElementById('anjo-modal-backdrop');
    if (antigo) {
        antigo.remove();
    }
}

// Injeta as keyframes de animação se não existirem
if (typeof document !== 'undefined' && !document.getElementById('anjo-modais-css')) {
    const style = document.createElement('style');
    style.id = 'anjo-modais-css';
    style.textContent = `
        @keyframes fadeInModal {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes scaleInModal {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
    `;
    document.head.appendChild(style);
}

// Expõe globalmente caso scripts tradicionais queiram chamar
if (typeof window !== 'undefined') {
    window.exibirModalAlerta = exibirModalAlerta;
    window.exibirModalConfirmacao = exibirModalConfirmacao;
}
