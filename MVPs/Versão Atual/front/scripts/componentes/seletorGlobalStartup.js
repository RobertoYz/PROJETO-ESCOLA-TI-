/**
 * Componente Global de Header (Seletor de Startup Ativa + Perfil do Consultor + Modal Editar Perfil)
 * Padroniza o cabeçalho em todas as páginas da plataforma
 */
import {
    obterStartupAtiva,
    salvarStartupAtiva,
    obterUsuarioLogado,
    obterPerfilUsuario,
    atualizarPerfilUsuario,
    removerToken
} from '../servicos/servicoAutenticacao.js';
import { listarStartupsDaConsultoria } from '../servicos/servicoStartups.js';

function injetarEstilosHeaderGlobal() {
    if (document.getElementById('estilos-header-global')) return;

    const style = document.createElement('style');
    style.id = 'estilos-header-global';
    style.textContent = `
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

        .seletor-startup-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid #262930;
            padding: 6px 12px;
            border-radius: 10px;
        }

        .seletor-startup-wrapper select {
            background: #181b20;
            color: #f8fafc;
            border: 1px solid #334155;
            border-radius: 6px;
            padding: 4px 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            outline: none;
            min-width: 170px;
            max-width: 260px;
            text-overflow: ellipsis;
        }

        .seletor-startup-wrapper select:focus {
            border-color: #3b82f6;
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
    `;
    document.head.appendChild(style);
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
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);

    const overlay = document.getElementById('modalEditarPerfilConsultorOverlay');
    const btnFechar = document.getElementById('btnFecharModalPerfilConsultor');
    const btnCancelar = document.getElementById('btnCancelarPerfilConsultor');
    const form = document.getElementById('formEditarPerfilConsultor');
    const feedback = document.getElementById('feedbackPerfilConsultor');
    const btnSalvar = document.getElementById('btnSalvarPerfilConsultor');

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

    const overlay = document.getElementById('modalEditarPerfilConsultorOverlay');
    if (overlay) overlay.classList.add('active');
}

export async function inicializarSeletorGlobal(containerId, options = {}) {
    injetarEstilosHeaderGlobal();
    garantirModalEditarPerfil();

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

    // Padroniza o container à direita do header com o Seletor de Startup à esquerda do Perfil do Consultor
    container.className = 'header-right-group';
    container.innerHTML = `
        <div class="seletor-startup-wrapper">
            <div style="width: 34px; height: 34px; min-width: 34px; border-radius: 8px; background: linear-gradient(135deg, #2563eb, #7c3aed); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.3); flex-shrink: 0;" id="avatar-startup-sigla">
                ${siglaInicial}
            </div>
            <div style="display: flex; flex-direction: column; gap: 2px; min-width: 0;">
                <span style="font-size: 9px; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.5px; white-space: nowrap;">Startup Assessorada:</span>
                <select id="select-global-startup">
                    <option value="${startupAtiva.id}" selected>${startupAtiva.nome}</option>
                </select>
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
                <div class="user-dropdown-item" id="btnMenuConfiguracoesFake">Configurações</div>
                <div class="user-dropdown-item logoff-item" id="btnMenuLogoff">Logoff</div>
            </div>
        </div>
    `;

    const selectEl = document.getElementById('select-global-startup');
    const avatarSigla = document.getElementById('avatar-startup-sigla');
    const userMenuBtn = document.getElementById('userMenuBtn');
    const mainDropdown = document.getElementById('mainDropdown');
    const btnEditarPerfil = document.getElementById('btnMenuEditarPerfil');
    const btnConfigFake = document.getElementById('btnMenuConfiguracoesFake');
    const btnLogoff = document.getElementById('btnMenuLogoff');

    // Eventos do menu do Perfil do Consultor
    if (userMenuBtn && mainDropdown) {
        userMenuBtn.addEventListener('click', (e) => {
            if (e.target.closest('.user-dropdown-global')) return;
            mainDropdown.classList.toggle('active');
        });

        document.addEventListener('click', (e) => {
            if (!userMenuBtn.contains(e.target)) {
                mainDropdown.classList.remove('active');
            }
        });
    }

    if (btnEditarPerfil) {
        btnEditarPerfil.addEventListener('click', (e) => {
            e.stopPropagation();
            if (mainDropdown) mainDropdown.classList.remove('active');
            abrirModalEditarPerfil();
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
    });

    // Tenta obter lista atualizada de startups da API sem bloquear o fluxo visual
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

            // Atualiza sigla caso a startup ativa esteja na lista
            const selecionada = selectEl.options[selectEl.selectedIndex];
            if (selecionada && selecionada.textContent) {
                avatarSigla.textContent = selecionada.textContent.slice(0, 2).toUpperCase();
                salvarStartupAtiva(selectEl.value, selecionada.textContent);
            }
        }
    } catch (err) {
        console.warn('Operando com startup em cache local.');
    }

    // Listener de troca de startup
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
