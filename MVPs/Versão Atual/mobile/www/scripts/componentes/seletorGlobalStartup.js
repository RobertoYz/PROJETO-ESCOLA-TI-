/**
 * Componente Seletor Global de Startup Ativa
 * Blindado com fallback automático para modo demonstração
 */
import { obterStartupAtiva, salvarStartupAtiva } from '../servicos/servicoAutenticacao.js';
import { listarStartupsDaConsultoria } from '../servicos/servicoStartups.js';

export async function inicializarSeletorGlobal(containerId, options = {}) {
    const container = document.getElementById(containerId);
    if (!container) return;

    let startupAtiva = obterStartupAtiva();
    if (!startupAtiva) {
        startupAtiva = { id: '1', nome: 'Startup Piloto (Demonstração)' };
        salvarStartupAtiva('1', 'Startup Piloto (Demonstração)');
    }

    const siglaInicial = (startupAtiva.nome || 'AN').slice(0, 2).toUpperCase();

    // Renderiza imediatamente com layout polido e sem travar
    container.innerHTML = `
        <div class="seletor-startup-wrapper" style="display: flex; align-items: center; gap: 8px; flex: 1; min-width: 0;">
            <div style="width: 34px; height: 34px; min-width: 34px; border-radius: 8px; background: linear-gradient(135deg, #2563eb, #7c3aed); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.3); flex-shrink: 0;" id="avatar-startup-sigla">
                ${siglaInicial}
            </div>
            <div style="display: flex; flex-direction: column; gap: 2px; flex: 1; min-width: 0;">
                <span style="font-size: 9px; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Startup Assessorada:</span>
                <select id="select-global-startup" style="background: #181b20; color: #f8fafc; border: 1px solid #334155; border-radius: 6px; padding: 4px 8px; font-size: 13px; font-weight: 600; cursor: pointer; outline: none; width: 100%; min-width: 0; text-overflow: ellipsis;">
                    <option value="${startupAtiva.id}" selected>${startupAtiva.nome}</option>
                </select>
            </div>
            <a href="cadastrar-startup.html" class="btn-nova-startup-topo" title="Cadastrar nova startup via CNPJ" style="background: rgba(255,255,255,0.06); color: #cbd5e1; text-decoration: none; padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; border: 1px solid #334155; transition: 0.2s; white-space: nowrap; flex-shrink: 0;" onmouseover="this.style.background='rgba(255,255,255,0.15)'; this.style.color='#fff';" onmouseout="this.style.background='rgba(255,255,255,0.06)'; this.style.color='#cbd5e1';">+ Nova Startup</a>
        </div>
    `;

    const selectEl = document.getElementById('select-global-startup');
    const avatarSigla = document.getElementById('avatar-startup-sigla');

    // Tenta obter lista atualizada da API sem bloquear o fluxo visual
    try {
        const startups = await listarStartupsDaConsultoria();

        if (Array.isArray(startups) && startups.length > 0) {
            selectEl.innerHTML = '';
            startups.forEach(st => {
                const nomeExibicao = st.nome_fantasia || st.razao_social;
                const opt = document.createElement('option');
                opt.value = st.id;
                opt.textContent = nomeExibicao;
                if (String(st.id) === String(startupAtiva.id)) {
                    opt.selected = true;
                }
                selectEl.appendChild(opt);
            });
        }
    } catch (err) {
        // Fallback silencioso mantendo a startup ativa em tela sem quebrar
        console.warn('Operando com startup em cache local.');
    }

    // Listener de troca
    selectEl.addEventListener('change', (e) => {
        const idSelecionado = e.target.value;
        const optSelecionada = e.target.options[e.target.selectedIndex];
        const nome = optSelecionada ? optSelecionada.textContent : '';

        salvarStartupAtiva(idSelecionado, nome);
        avatarSigla.textContent = (nome || 'AN').slice(0, 2).toUpperCase();

        if (options.onTrocarStartup) {
            options.onTrocarStartup(idSelecionado, nome);
        } else {
            window.location.reload();
        }
    });
}
