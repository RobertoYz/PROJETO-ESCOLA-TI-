# ADR 016: Gestão Multi-tenant B2B e Seletor Global Reativo de Startups

## Status
Aceito

## Contexto (Jira EDTAN-164 / EDTAN-157)
O modelo de negócio do Anjo Nexus prevê que uma Agência ou Consultor de Inovação atenda simultaneamente dezenas de startups clientes. Cada startup possui seu próprio CNPJ, porte fiscal (ME, EPP), enquadramento de faturamento, CNAEs primários e secundários, além de um portfólio de editais e minutas em tramitação.

Surgiram os seguintes desafios de engenharia:
1. **Isolamento de Dados Multi-tenant:** Como garantir que o consultor alterne entre startups clientes sem que cartões do Kanban, diagnósticos de aderência ou minutas técnicas de uma empresa vazem para outra?
2. **Reatividade no Frontend:** Como propagar a alteração da startup ativa para todos os módulos (Radar de Editais, Kanban, Minutas, Perfil) sem forçar um logout ou requisições redundantes de autenticação?
3. **Gestão de Carteira:** Como estruturar a listagem, cadastro e edição de empresas no painel de controle do consultor?

---

## Decisão

### 1. Modelo de Domínio B2B no Banco de Dados
A entidade `startups` mantém relacionamento direto com o consultor/agência (`user_id` / `agencia_id`):
- Uma `Agencia` ou `User` possui muitas `Startups` (`hasMany`).
- Cada `KanbanBoard` e `KanbanCard` pertence a uma `Startup` específica (`belongsTo`).
- Cada `Minuta` vincula-se obrigatoriamente a uma `Startup` e a um `Edital`.

### 2. Seletor Global Reativo no Frontend (`seletorGlobalStartup.js`)
Para gerenciar o estado da startup ativa no cliente (Web e Mobile):
- O componente injeta um menu dropdown dinâmico no cabeçalho e na barra lateral de todas as páginas autenticadas.
- A startup selecionada é persistida no `localStorage` sob a chave `anjo_startup_ativa_id`.
- Ao selecionar uma nova startup, o componente emite um evento customizado no navegador:
  ```javascript
  window.dispatchEvent(new CustomEvent('startupAlterada', { 
      detail: { startupId: novaId, startup: dadosStartup } 
  }));
  ```
- Todas as páginas escutam o evento e recarregam seus dados (editais do Kanban, scores do Radar) sem necessidade de recarregar a página inteira (*Zero Page Reload*).

### 3. Telas Dedicadas de Gestão de Carteira
- **`minhas-startups.html`:** Visão consolidada da carteira de startups do consultor, com cartões informando CNPJ, Porte, Nicho e atalhos rápidos para editar perfil ou selecionar como ativa.
- **`nova-startup-painel.html`:** Formulário ágil integrado à API pública de CNPJ para onboarding de novas startups clientes a partir do painel já autenticado.

---

## Justificativa Acadêmica para a Banca
- **Atendimento ao Padrão Observer:** O uso de eventos customizados desacoplados no Vanilla JS replica os princípios de arquitetura reativa sem o peso de frameworks como Vuex ou Redux.
- **Segurança e Isolamento Lógico:** O backend valida em middleware (`VerificarStartupAtiva`) se a startup informada no cabeçalho ou parâmetro realmente pertence à agência autenticada, impedindo ataques de IDOR (*Insecure Direct Object References*).

