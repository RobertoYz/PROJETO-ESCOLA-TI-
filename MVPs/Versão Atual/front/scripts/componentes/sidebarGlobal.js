/**
 * Componente Sidebar Global
 * Mantém navegação padronizada e unificada entre Radar, Kanban e Startups
 */
export function inicializarSidebarGlobal(paginaAtiva = 'radar') {
    const sidebarEl = document.getElementById('sidebar');
    if (!sidebarEl) return;

    sidebarEl.innerHTML = `
        <div class="sidebar-header" style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
            <button class="toggle-btn" id="toggleSidebar" title="Recolher / Expandir menu" style="background: none; border: none; color: #fff; cursor: pointer; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 6px;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
            </button>
            <div class="logo" style="font-size: 1.4rem; font-weight: 700; color: #fff; letter-spacing: -0.5px;">Anjo<span style="font-weight: 400; color: #3b82f6;">Nexus</span></div>
        </div>

        <nav class="nav-menu" style="flex: 1; display: flex; flex-direction: column; padding: 1rem 0;">
            <a href="RadarEditais.html" class="nav-item ${paginaAtiva === 'radar' ? 'active' : ''}" style="padding: 0.9rem 1.5rem; color: #e2e8f0; text-decoration: none; font-size: 0.95rem; display: flex; align-items: center; gap: 14px; transition: 0.2s;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <span class="nav-text">Radar de Editais</span>
            </a>

            <a href="kanban.html" class="nav-item ${paginaAtiva === 'kanban' ? 'active' : ''}" style="padding: 0.9rem 1.5rem; color: #e2e8f0; text-decoration: none; font-size: 0.95rem; display: flex; align-items: center; gap: 14px; transition: 0.2s;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7V7h2v10zm4-5h-2V7h2v5zm4 3h-2V7h2v8z"/></svg>
                <span class="nav-text">Pipeline Kanban</span>
            </a>

            <a href="selecionar-startup.html" class="nav-item ${paginaAtiva === 'startups' ? 'active' : ''}" style="padding: 0.9rem 1.5rem; color: #e2e8f0; text-decoration: none; font-size: 0.95rem; display: flex; align-items: center; gap: 14px; transition: 0.2s;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/></svg>
                <span class="nav-text">Minhas Startups</span>
            </a>
        </nav>

        <div class="sidebar-footer" style="padding: 1rem 0; border-top: 1px solid #222;">
            <a href="login.html" onclick="localStorage.removeItem('token_autenticacao');" class="nav-item" style="padding: 0.9rem 1.5rem; color: #ef4444; text-decoration: none; font-size: 0.95rem; display: flex; align-items: center; gap: 14px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M10.09 15.59L11.5 17l5-5-5-5-1.41 1.41L12.67 11H3v2h9.67l-2.58 2.59zM19 3H5c-1.11 0-2 .9-2 2v4h2V5h14v14H5v-4H3v4c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
                <span class="nav-text">Sair do Sistema</span>
            </a>
        </div>
    `;

    // Toggle expand/collapse
    const toggleBtn = document.getElementById('toggleSidebar');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            sidebarEl.classList.toggle('collapsed');
        });
    }
}
