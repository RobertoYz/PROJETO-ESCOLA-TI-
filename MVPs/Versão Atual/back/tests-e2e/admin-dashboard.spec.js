import { test, expect } from '@playwright/test';

test.describe('Painel do Administrador Global & Inteligência de Radares (E2E)', () => {

  test.setTimeout(90000);

  test.beforeEach(async ({ page }) => {
    page.on('console', msg => {
      if (msg.type() === 'error') {
        console.log(`[Browser Console Error]: ${msg.text()}`);
      }
    });
    page.on('pageerror', err => {
      console.log(`[Browser Page Error]: ${err.message} \n ${err.stack}`);
    });
  });

  test('Cenário 1: Login de Administrador Global e Redirecionamento Soberano', async ({ page }) => {
    // 1. Acessa a página oficial de login
    await page.goto('/paginas/login.html');
    await expect(page).toHaveTitle(/Entrar|Login|Anjo Nexus/i);

    // 2. Preenche as credenciais do Administrador Global
    await page.fill('#campo-email', 'admin@anjonexus.com.br');
    await page.fill('#campo-senha', 'AnjoNexus@2026Admin!');

    // 3. Submete o formulário
    await page.click('#botao-confirmar');

    // 4. Valida redirecionamento direto para o painel administrativo (não deve ir para pagamento pendente)
    await page.waitForURL('**/paginas/admin-dashboard.html');
    expect(page.url()).toContain('admin-dashboard.html');

    // 5. Valida identificação do Super Admin no cabeçalho
    await expect(page.locator('#admin-user-info')).toContainText('admin@anjonexus.com.br');
    await expect(page.locator('.badge-super-admin').first()).toContainText(/Super Admin Global/i);

    // 6. Valida presença da Sidebar Soberana na tela de Governança
    const sidebar = page.locator('#sidebar');
    await expect(sidebar).toBeVisible();
    await expect(sidebar).toContainText(/Painel de Governança/i);
    await expect(sidebar.locator('a.nav-item.active')).toContainText(/Painel de Governança/i);
    await expect(sidebar).toContainText(/Catálogo de Editais/i);
    await expect(sidebar.locator('a[href*="minhas-startups"]')).toHaveCount(0);
  });

  test('Cenário 2: KPIs de Governança e Métricas SaaS em Tempo Real', async ({ page }) => {
    await page.goto('/paginas/login.html');
    await page.fill('#campo-email', 'admin@anjonexus.com.br');
    await page.fill('#campo-senha', 'AnjoNexus@2026Admin!');
    await page.click('#botao-confirmar');
    await page.waitForURL('**/paginas/admin-dashboard.html');

    // Valida os 4 blocos de métricas essenciais
    const kpiAgencias = page.locator('#kpi-total-agencias');
    const kpiUsuarios = page.locator('#kpi-total-usuarios');
    const kpiMrr = page.locator('#kpi-mrr');
    const kpiEditais = page.locator('#kpi-total-editais');

    await expect(kpiAgencias).not.toHaveText('-');
    await expect(kpiUsuarios).not.toHaveText('-');
    await expect(kpiMrr).toContainText('R$');
    await expect(kpiEditais).not.toHaveText('-');
  });

  test('Cenário 3: Painel de Radares de Editais e Filtros por Região', async ({ page }) => {
    await page.goto('/paginas/login.html');
    await page.fill('#campo-email', 'admin@anjonexus.com.br');
    await page.fill('#campo-senha', 'AnjoNexus@2026Admin!');
    await page.click('#botao-confirmar');
    await page.waitForURL('**/paginas/admin-dashboard.html');

    // Aguarda o container de radares carregar todos os 6 cards
    const containerRadares = page.locator('#container-radares');
    await expect(containerRadares.locator('.radar-card')).toHaveCount(6, { timeout: 35000 });

    // Valida a presença dos principais órgãos governamentais
    await expect(page.locator('.radar-card[data-fonte="FAPDF"]')).toBeVisible();
    await expect(page.locator('.radar-card[data-fonte="FAPEG"]')).toBeVisible();
    await expect(page.locator('.radar-card[data-fonte="FINEP"]')).toBeVisible();
    await expect(page.locator('.radar-card[data-fonte="FAPESP"]')).toBeVisible();

    // Filtra pela região Centro-Oeste via chip interativo
    const chipCentroOeste = page.locator('.chip-filtro-radar[data-regiao="Centro-Oeste"]');
    await chipCentroOeste.click();

    // Radares do Centro-Oeste (FAPDF, FAPEG) devem ser os únicos 2 exibidos
    await expect(containerRadares.locator('.radar-card')).toHaveCount(2);
    await expect(page.locator('.radar-card[data-fonte="FAPDF"]')).toBeVisible();
    await expect(page.locator('.radar-card[data-fonte="FAPEG"]')).toBeVisible();

    // Filtra pela região Sudeste (FAPESP, FAPERJ, FAPEMIG)
    const chipSudeste = page.locator('.chip-filtro-radar[data-regiao="Sudeste"]');
    await chipSudeste.click();
    await expect(containerRadares.locator('.radar-card')).toHaveCount(3);
    await expect(page.locator('.radar-card[data-fonte="FAPESP"]')).toBeVisible();

    // Restaura o filtro para TODAS
    await page.locator('.chip-filtro-radar[data-regiao="TODAS"]').click();
    await expect(containerRadares.locator('.radar-card')).toHaveCount(6);
  });

  test('Cenário 4: Modal Técnico de Inspeção do Radar com Metadados e Editais', async ({ page }) => {
    await page.goto('/paginas/login.html');
    await page.fill('#campo-email', 'admin@anjonexus.com.br');
    await page.fill('#campo-senha', 'AnjoNexus@2026Admin!');
    await page.click('#botao-confirmar');
    await page.waitForURL('**/paginas/admin-dashboard.html');

    // Aguarda os radares renderizarem
    const cardFapdf = page.locator('.radar-card[data-fonte="FAPDF"]');
    await expect(cardFapdf).toBeVisible({ timeout: 35000 });

    // Clica no botão de Detalhes da FAPDF
    const btnDetalhes = cardFapdf.locator('.btn-ver-detalhes');
    await btnDetalhes.click();

    // Valida que o modal de detalhes do radar é exibido
    const modal = page.locator('#modal-detalhes-radar');
    await expect(modal).toBeVisible();

    // Valida o preenchimento dinâmico dos metadados técnicos
    await expect(page.locator('#modal-radar-sigla')).toContainText('FAPDF');
    await expect(page.locator('#modal-radar-regiao-badge')).toContainText(/centro-oeste/i);
    await expect(page.locator('#modal-radar-resiliencia')).not.toBeEmpty();

    // Fecha o modal pelo botão Fechar (✕)
    await page.click('#btn-fechar-modal-radar');
    await expect(modal).toBeHidden();
  });

  test('Cenário 5: Tabela de Consultorias e Controle de Status / Modal de Planos', async ({ page }) => {
    await page.goto('/paginas/login.html');
    await page.fill('#campo-email', 'admin@anjonexus.com.br');
    await page.fill('#campo-senha', 'AnjoNexus@2026Admin!');
    await page.click('#botao-confirmar');
    await page.waitForURL('**/paginas/admin-dashboard.html');

    // Valida se a tabela de agências carregou ao menos uma consultoria
    const tabelaAgencias = page.locator('#tabela-agencias-corpo');
    await expect(tabelaAgencias.locator('tr')).not.toHaveCount(0, { timeout: 15000 });

    // Valida presença dos botões de ação (toggle status e ajuste de plano)
    const btnPlano = tabelaAgencias.locator('button[data-action="ajustar-plano"]').first();
    const btnStatus = tabelaAgencias.locator('button[data-action="toggle-status"]').first();
    await expect(btnPlano).toBeVisible();
    await expect(btnStatus).toBeVisible();

    // Clica no botão de Ajuste de Plano para abrir o modal de plano
    await btnPlano.click();
    const modalPlano = page.locator('#modal-ajuste-plano');
    await expect(modalPlano).toBeVisible();

    // Valida campo de seleção de planos
    const selectPlano = page.locator('#modal-select-plano');
    await expect(selectPlano).toBeVisible();

    // Fecha o modal pelo botão Cancelar
    await page.click('#btn-cancelar-modal');
    await expect(modalPlano).toBeHidden();
  });

  test('Cenário 6: Logout do Administrador Global e Limpeza de Sessão', async ({ page }) => {
    await page.goto('/paginas/login.html');
    await page.fill('#campo-email', 'admin@anjonexus.com.br');
    await page.fill('#campo-senha', 'AnjoNexus@2026Admin!');
    await page.click('#botao-confirmar');
    await page.waitForURL('**/paginas/admin-dashboard.html');

    // Clica no botão de Sair
    await page.click('#btn-logout-admin');

    // Valida redirecionamento de volta para o login
    await page.waitForURL('**/paginas/login.html');
    expect(page.url()).toContain('login.html');
  });

  test('Cenário 7: Isolamento de Navegação e Guarda de Rotas para Administrador Global', async ({ page }) => {
    await page.goto('/paginas/login.html');
    await page.fill('#campo-email', 'admin@anjonexus.com.br');
    await page.fill('#campo-senha', 'AnjoNexus@2026Admin!');
    await page.click('#botao-confirmar');
    await page.waitForURL('**/paginas/admin-dashboard.html');

    // 1. Clica no botão 'Ver Radar' no cabeçalho do Admin
    await page.click('#btn-ver-radar');
    await page.waitForURL('**/paginas/busca-editais.html');
    expect(page.url()).toContain('busca-editais.html');

    // 2. Valida Sidebar Exclusiva do Super Admin (sem abas de consultoria)
    const sidebar = page.locator('#sidebar');
    await expect(sidebar).toContainText(/Super Admin Global/i);
    await expect(sidebar).toContainText(/Painel de Governança/i);
    await expect(sidebar).toContainText(/Catálogo de Editais/i);

    // Assegura que abas privadas de consultoria NÃO estão presentes na barra lateral
    await expect(sidebar.locator('a[href*="minhas-startups"]')).toHaveCount(0);
    await expect(sidebar.locator('a[href*="kanban"]')).toHaveCount(0);
    await expect(sidebar.locator('a[href*="equipe"]')).toHaveCount(0);

    // 3. Valida Topbar com Badge Soberano e Botão de Retorno ao Painel ADM
    const topbarWidget = page.locator('#container-seletor-global');
    await expect(topbarWidget).toContainText(/Super Admin/i);
    await expect(topbarWidget).toContainText(/Painel ADM/i);

    // 4. Teste de Invasão/Navegação direta: Admin tentando acessar Minhas Startups
    await page.goto('/paginas/minhas-startups.html');
    await page.waitForURL('**/paginas/admin-dashboard.html');
    expect(page.url()).toContain('admin-dashboard.html');

    // 5. Teste de Invasão/Navegação direta: Admin tentando acessar Pipeline Kanban
    await page.goto('/paginas/kanban.html');
    await page.waitForURL('**/paginas/admin-dashboard.html');
    expect(page.url()).toContain('admin-dashboard.html');
  });

});
