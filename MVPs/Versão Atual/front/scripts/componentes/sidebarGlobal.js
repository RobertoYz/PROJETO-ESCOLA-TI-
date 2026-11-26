import { obterUsuarioLogado, usuarioEhTitular, usuarioTemPermissao } from '../servicos/servicoAutenticacao.js';

/**
 * Componente Sidebar Global
 * Mantém navegação padronizada e unificada entre Radar, Kanban, Minhas Startups e Equipe.
 * Para o Administrador Global, isola a navegação exclusivamente nas ferramentas
 * de Governança, Catálogo Geral de Editais e App Mobile, protegendo as rotas de consultoria.
 */
export function inicializarSidebarGlobal(paginaAtiva = 'radar') {
    const sidebarEl = document.getElementById('sidebar');
    if (!sidebarEl) return;

    const usuario = obterUsuarioLogado();
    const ehAdminGlobal = usuario?.perfil_acesso === 'admin_global';

    // Guarda de Rotas do Front-end: se o Admin Global tentar acessar abas restritas de consultoria,
    // redireciona imediatamente para o painel de governança oficial.
    if (ehAdminGlobal) {
        const paginasRestritasAdmin = ['kanban', 'startups', 'equipe'];
        if (paginasRestritasAdmin.includes(paginaAtiva)) {
            window.location.replace('admin-dashboard.html');
            return;
        }

        sidebarEl.innerHTML = `
            <div class="sidebar-header" style="padding: 1.5rem; display: flex; flex-direction: column; gap: 0.8rem;">
                <button class="toggle-btn" id="toggleSidebar" title="Recolher / Expandir menu" style="background: none; border: none; color: #fff; cursor: pointer; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 6px;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
                </button>
                <div class="logo" style="display: flex; align-items: center; gap: 10px;">
                    <img src="../imagens/icone-192.png" alt="Logo AnjoNexus" style="height: 32px; width: 32px; border-radius: 8px; object-fit: contain;">
                    <span style="font-size: 1.35rem; font-weight: 700; color: #fff; letter-spacing: -0.5px;">Anjo<span style="font-weight: 400; color: #38bdf8;">Nexus</span></span>
                </div>
                <div style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #0f172a; font-size: 0.68rem; font-weight: 800; padding: 4px 10px; border-radius: 9999px; text-transform: uppercase; text-align: center; letter-spacing: 0.6px; box-shadow: 0 2px 8px rgba(245, 158, 11, 0.25);">
                    Super Admin Global
                </div>
            </div>

            <nav class="nav-menu" style="flex: 1; display: flex; flex-direction: column; padding: 1rem 0;">
                <a href="admin-dashboard.html" class="nav-item ${paginaAtiva === 'admin' ? 'active' : ''}" style="padding: 0.9rem 1.5rem; color: #e2e8f0; text-decoration: none; font-size: 0.95rem; display: flex; align-items: center; gap: 14px; transition: 0.2s;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
                    <span class="nav-text">Painel de Governança</span>
                </a>

                <a href="busca-editais.html" class="nav-item ${paginaAtiva === 'busca' || paginaAtiva === 'radar' ? 'active' : ''}" style="padding: 0.9rem 1.5rem; color: #e2e8f0; text-decoration: none; font-size: 0.95rem; display: flex; align-items: center; gap: 14px; transition: 0.2s;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                    <span class="nav-text">Catálogo de Editais</span>
                </a>

                <a href="baixar-app.html" class="nav-item ${paginaAtiva === 'mobile' ? 'active' : ''}" style="padding: 0.9rem 1.5rem; color: #a78bfa; text-decoration: none; font-size: 0.95rem; display: flex; align-items: center; justify-content: space-between; transition: 0.2s;">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17 1.01L7 1c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-1.99-2-1.99zM17 19H7V5h10v14z"/></svg>
                        <span class="nav-text">📱 App Mobile</span>
                    </div>
                    <span class="nav-text" style="background: rgba(167, 139, 250, 0.2); color: #c4b5fd; font-size: 0.72rem; padding: 2px 7px; border-radius: 999px; font-weight: 600;">Baixar APK</span>
                </a>
            </nav>

            <div class="sidebar-footer" style="padding: 1rem 0; border-top: 1px solid #222;">
                <a href="login.html" onclick="localStorage.clear(); sessionStorage.clear();" class="nav-item" style="padding: 0.9rem 1.5rem; color: #ef4444; text-decoration: none; font-size: 0.95rem; display: flex; align-items: center; gap: 14px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M10.09 15.59L11.5 17l5-5-5-5-1.41 1.41L12.67 11H3v2h9.67l-2.58 2.59zM19 3H5c-1.11 0-2 .9-2 2v4h2V5h14v14H5v-4H3v4c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
                    <span class="nav-text">Sair do Sistema</span>
                </a>
            </div>
        `;

        configurarComportamentoSidebar(sidebarEl);
        return;
    }

    // Configuração para Consultores e Usuários normais
    const podeGerenciarEquipe = usuarioEhTitular(usuario) || usuarioTemPermissao('equipe.gerenciar', usuario);

    sidebarEl.innerHTML = `
        <div class="sidebar-header" style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
            <button class="toggle-btn" id="toggleSidebar" title="Recolher / Expandir menu" style="background: none; border: none; color: #fff; cursor: pointer; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 6px;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
            </button>
            <div class="logo" style="display: flex; align-items: center; gap: 10px;">
                <img src="../imagens/icone-192.png" alt="Logo AnjoNexus" style="height: 32px; width: 32px; border-radius: 8px; object-fit: contain;">
                <span style="font-size: 1.35rem; font-weight: 700; color: #fff; letter-spacing: -0.5px;">Anjo<span style="font-weight: 400; color: #3b82f6;">Nexus</span></span>
            </div>
        </div>

        <nav class="nav-menu" style="flex: 1; display: flex; flex-direction: column; padding: 1rem 0;">
            <a href="busca-editais.html" class="nav-item ${paginaAtiva === 'busca' || paginaAtiva === 'radar' ? 'active' : ''}" style="padding: 0.9rem 1.5rem; color: #e2e8f0; text-decoration: none; font-size: 0.95rem; display: flex; align-items: center; gap: 14px; transition: 0.2s;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <span class="nav-text">Busca de Editais</span>
            </a>

            <a href="kanban.html" class="nav-item ${paginaAtiva === 'kanban' ? 'active' : ''}" style="padding: 0.9rem 1.5rem; color: #e2e8f0; text-decoration: none; font-size: 0.95rem; display: flex; align-items: center; gap: 14px; transition: 0.2s;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7V7h2v10zm4-5h-2V7h2v5zm4 3h-2V7h2v8z"/></svg>
                <span class="nav-text">Pipeline Kanban</span>
            </a>

            <a href="minhas-startups.html" class="nav-item ${paginaAtiva === 'startups' ? 'active' : ''}" style="padding: 0.9rem 1.5rem; color: #e2e8f0; text-decoration: none; font-size: 0.95rem; display: flex; align-items: center; gap: 14px; transition: 0.2s;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/></svg>
                <span class="nav-text">Minhas Startups</span>
            </a>

            <a href="equipe.html" id="navItemSidebarEquipe" class="nav-item ${paginaAtiva === 'equipe' ? 'active' : ''}" style="padding: 0.9rem 1.5rem; color: #e2e8f0; text-decoration: none; font-size: 0.95rem; display: ${podeGerenciarEquipe ? 'flex' : 'none'}; align-items: center; gap: 14px; transition: 0.2s;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                <span class="nav-text">Equipe</span>
            </a>

            <a href="baixar-app.html" class="nav-item ${paginaAtiva === 'mobile' ? 'active' : ''}" style="padding: 0.9rem 1.5rem; color: #a78bfa; text-decoration: none; font-size: 0.95rem; display: flex; align-items: center; justify-content: space-between; transition: 0.2s;">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17 1.01L7 1c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-1.99-2-1.99zM17 19H7V5h10v14z"/></svg>
                    <span class="nav-text">📱 App Mobile</span>
                </div>
                <span class="nav-text" style="background: rgba(167, 139, 250, 0.2); color: #c4b5fd; font-size: 0.72rem; padding: 2px 7px; border-radius: 999px; font-weight: 600;">Baixar APK</span>
            </a>
        </nav>

        <div class="sidebar-footer" style="padding: 1rem 0; border-top: 1px solid #222;">
            <a href="login.html" onclick="localStorage.removeItem('token_autenticacao'); localStorage.removeItem('usuario_logado'); localStorage.removeItem('usuario_nome'); localStorage.removeItem('usuario_email'); localStorage.removeItem('startup_ativa_id'); localStorage.removeItem('startup_ativa_nome');" class="nav-item" style="padding: 0.9rem 1.5rem; color: #ef4444; text-decoration: none; font-size: 0.95rem; display: flex; align-items: center; gap: 14px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M10.09 15.59L11.5 17l5-5-5-5-1.41 1.41L12.67 11H3v2h9.67l-2.58 2.59zM19 3H5c-1.11 0-2 .9-2 2v4h2V5h14v14H5v-4H3v4c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
                <span class="nav-text">Sair do Sistema</span>
            </a>
        </div>
    `;

    configurarComportamentoSidebar(sidebarEl);
}

function configurarComportamentoSidebar(sidebarEl) {
    let backdrop = document.getElementById('sidebarBackdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.id = 'sidebarBackdrop';
        backdrop.className = 'sidebar-backdrop';
        document.body.appendChild(backdrop);
    }

    const fecharMenuMobile = () => {
        sidebarEl.classList.remove('mobile-open');
        backdrop.classList.remove('active');
    };

    const abrirOuAlternarMenuMobile = () => {
        const isOpen = sidebarEl.classList.toggle('mobile-open');
        if (isOpen) {
            backdrop.classList.add('active');
        } else {
            backdrop.classList.remove('active');
        }
    };

    const toggleBtn = document.getElementById('toggleSidebar');
    if (toggleBtn) {
        const novoToggleBtn = toggleBtn.cloneNode(true);
        toggleBtn.parentNode.replaceChild(novoToggleBtn, toggleBtn);

        novoToggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (window.innerWidth <= 900) {
                abrirOuAlternarMenuMobile();
            } else {
                sidebarEl.classList.toggle('collapsed');
            }
        });
    }

    if (window.Capacitor || window.location.protocol === 'capacitor:' || (window.location.hostname === 'localhost' && /Android|iPhone|iPad|iPod/i.test(navigator.userAgent))) {
        document.body.classList.add('is-capacitor');
    }

    const btnMobileMenu = document.getElementById('btnMobileMenu') || document.getElementById('mobileMenuBtn');
    if (btnMobileMenu) {
        const novoBtnMobile = btnMobileMenu.cloneNode(true);
        btnMobileMenu.parentNode.replaceChild(novoBtnMobile, btnMobileMenu);

        novoBtnMobile.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            abrirOuAlternarMenuMobile();
        });
    }

    backdrop.onclick = fecharMenuMobile;

    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 900 && sidebarEl.classList.contains('mobile-open')) {
            if (!sidebarEl.contains(e.target) && e.target !== toggleBtn && (!btnMobileMenu || !btnMobileMenu.contains(e.target))) {
                fecharMenuMobile();
            }
        }
    });
}

export function atualizarVisibilidadeSidebarEquipe(usuario = null) {
    const itemEquipe = document.getElementById('navItemSidebarEquipe');
    if (!itemEquipe) return;
    const u = usuario || obterUsuarioLogado();
    const visivel = usuarioEhTitular(u) || usuarioTemPermissao('equipe.gerenciar', u);
    itemEquipe.style.display = visivel ? 'flex' : 'none';
}
