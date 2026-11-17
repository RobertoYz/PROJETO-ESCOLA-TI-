# Justificativa Técnica de Relacionamentos: Diagrama Entidade-Relacionamento (DER)

Este documento apresenta a justificativa técnica e arquitetural de todas as chaves estrangeiras, cardinalidades, regras de integridade referencial (`ON DELETE CASCADE` vs `ON DELETE SET NULL`) e índices compostos de unicidade do modelo relacional do banco de dados da plataforma **Anjo Nexus** (Versão 007).

---

## 1. Arquitetura Multi-Tenant e Isolamento de Dados

O modelo de dados adota a abordagem **Multi-Tenant Lógico Baseado em Chave de Particionamento**, onde a tabela `agencias` figura como a entidade raiz de cada organização cliente (Tenant).

```
[planos]
   │ (1:N)
   ▼
[agencias] (Tenant Raiz)
   ├── (1:N) ──> [users]
   ├── (1:N) ──> [startups]
   └── (1:N) ──> [kanban_boards]
```

* Toda entidade operacional ou confidencial possui vínculo direto com a agência ou transita através de sua hierarquia dependente.
* Assegura-se que consultas e operações transacionais operem estritamente filtradas pelo identificador do Tenant, mitigando riscos de acesso indevido cruzado entre consultorias concorrentes.

---

## 2. Cardinalidades e Chaves Estrangeiras (FKs)

| Tabela Origem | Tabela Destino | Chave Estrangeira (FK) | Cardinalidade | Justificativa de Negócio |
| :--- | :--- | :--- | :---: | :--- |
| `planos` | `agencias` | `agencias.plano_id` | **1 : N** | Cada agência assina no máximo um plano SaaS ativo por vez. Um plano pode ser contratado por múltiplas agências. |
| `agencias` | `users` | `users.agencia_id` | **1 : N** | Uma agência possui múltiplos usuários (Consultor Titular, Consultores Padrão e Acessos de Clientes). Usuários do tipo `admin_global` possuem `agencia_id = NULL`. |
| `agencias` | `startups` | `startups.agencia_id` | **1 : N** | Uma consultoria gerencia uma carteira com N startups clientes, respeitando o teto de cotas do plano contratado. |
| `agencias` | `kanban_boards` | `kanban_boards.agencia_id` | **1 : N** | O quadro Kanban de pipeline de fomento pertence ao Tenant da agência. |
| `kanban_boards` | `kanban_columns` | `kanban_columns.board_id` | **1 : N** | Cada quadro organiza o fluxo em colunas oficiais de etapas sequenciais. |
| `kanban_columns` | `kanban_cards` | `kanban_cards.column_id` | **1 : N** | Cada coluna do Kanban agrupa os cartões de processos que estão naquele estágio específico. |
| `kanban_boards` | `kanban_cards` | `kanban_cards.board_id` | **1 : N** | Vinculação direta que permite consultas agregadas e movimentações rápidas dentro do mesmo quadro. |
| `editals` | `kanban_cards` | `kanban_cards.edital_id` | **0..1 : N** | Um cartão de processo no Kanban pode ser derivado diretamente de um edital minerado ou ser uma oportunidade manual. |
| `startups` | `minutas` | `minutas.startup_id` | **1 : N** | Toda minuta de proposta técnica é elaborada em nome de uma startup proponente específica. |
| `editals` | `minutas` | `minutas.edital_id` | **1 : N** | Toda minuta é estruturada com base nas exigências normativas de um edital de fomento. |
| `users` | `minutas` | `minutas.user_id` | **0..1 : N** | Registra o usuário responsável pela redação ou edição da minuta para fins de auditoria. |
| `kanban_cards` | `minutas` | `minutas.kanban_card_id` | **0..1 : N** | Permite o rastreamento bidirecional entre o cartão do processo no Kanban e o texto da proposta em elaboração. |
| `minutas` | `minutas` | `minutas.parent_minuta_id` | **0..1 : N** | Auto-relacionamento que viabiliza o versionamento encadeado imutável (v1, v2, v3...) preservando versões anteriores. |
| `users` | `edital_favoritos`| `edital_favoritos.user_id` | **1 : N** | Registra qual consultor realizou a ação de favoritar o edital. |
| `editals` | `edital_favoritos`| `edital_favoritos.edital_id` | **1 : N** | Identifica qual oportunidade foi salva nos favoritos. |
| `startups` | `edital_favoritos`| `edital_favoritos.startup_id` | **0..1 : N** | Contexto multi-tenant: vincula a oportunidade favoritada à startup que estava ativa na sessão de trabalho. |
| `users` | `alerta_matches` | `alerta_matches.user_id` | **1 : N** | Direciona a notificação de compatibilidade de edital ao consultor responsável. |
| `startups` | `alerta_matches` | `alerta_matches.startup_id` | **1 : N** | Identifica qual empresa obteve pontuação satisfatória de elegibilidade no edital. |
| `editals` | `alerta_matches` | `alerta_matches.edital_id` | **1 : N** | Oportunidade que gerou a compatibilidade satisfatória (Score $\ge 70\%$). |
| `users` | `alerta_prazos` | `alerta_prazos.user_id` | **1 : N** | Notificação de marcos temporais críticos direcionada ao consultor. |
| `editals` | `alerta_prazos` | `alerta_prazos.edital_id` | **1 : N** | Edital cujo prazo de submissão ou encerramento está em contagem regressiva. |
| `startups` | `alerta_prazos` | `alerta_prazos.startup_id` | **0..1 : N** | Empresa envolvida na candidatura ao edital com prazo crítico. |
| `kanban_cards` | `alerta_prazos` | `alerta_prazos.kanban_card_id` | **0..1 : N** | Cartão do Kanban cujo prazo de entrega ou marco processual requer atenção imediata. |

---

## 3. Regras de Integridade Referencial (`CASCADE` vs `SET NULL`)

A definição das cláusulas de deleção reflete estritamente as obrigações da **Lei Geral de Proteção de Dados (LGPD - Lei nº 13.709/2018)** e a preservação do valor documental das propostas.

### 3.1 Deleção em Cascata Transacional (`ON DELETE CASCADE`)
Utilizada quando a entidade filha não possui razão de existir de forma independente da entidade pai:
* **`agencias ➔ users` (`CASCADE`)**: Ao encerrar definitivamente uma conta matriz, todos os acessos dos consultores e clientes vinculados são destruídos atomicamente.
* **`agencias ➔ startups` (`CASCADE`)**: A eliminação da agência expurga integralmente todos os cadastros e dados fiscais de suas startups clientes.
* **`agencias ➔ kanban_boards` (`CASCADE`)**: O quadro de pipeline da agência é eliminado por completo.
* **`kanban_boards ➔ kanban_columns ➔ kanban_cards` (`CASCADE`)**: A hierarquia estrutural do Kanban é contínua; a exclusão do quadro remove suas colunas e respectivos cartões.
* **`startups ➔ minutas` (`CASCADE`)**: As minutas pertencem juridicamente à startup proponente; a exclusão física da empresa elimina seus rascunhos e propostas.
* **`users ➔ alerta_matches` e `startups ➔ alerta_matches` (`CASCADE`)**: Alertas de correspondência são descartados na exclusão do destinatário ou da empresa avaliada.
* **`users ➔ edital_favoritos` e `editals ➔ edital_favoritos` (`CASCADE`)**: A exclusão do usuário ou do edital remove imediatamente o registro de favorito.
* **`editals ➔ alerta_prazos` (`CASCADE`)**: Se um edital for expurgado da base, seus alertas de prazo perdem a validade e são eliminados.

### 3.2 Anulação de Chave Estrangeira (`ON DELETE SET NULL`)
Utilizada quando o registro dependente possui valor histórico, contábil ou operacional que deve ser preservado mesmo após a remoção da entidade referenciada:
* **`planos ➔ agencias` (`SET NULL`)**: Se um plano comercial for descontinuado ou removido da base cadastral, as agências que já o contrataram não são afetadas nem excluídas; o campo `plano_id` torna-se `NULL`, preservando vigência e histórico.
* **`users ➔ minutas` (`SET NULL`)**: Caso um consultor membro da equipe seja excluído da agência, as minutas técnicas redigidas por ele continuam preservadas na carteira da startup com `user_id = NULL`. O campo textual `autor_nome` mantém o registro histórico para auditoria interna.
* **`kanban_cards ➔ minutas` (`SET NULL`)**: Se um cartão de processo for removido do Kanban, a minuta técnica elaborada permanece preservada no histórico documental da empresa.
* **`minutas ➔ minutas` (`parent_minuta_id SET NULL`)**: Se uma versão intermediária for excluída pontualmente pelo usuário, o histórico das demais versões encadeadas permanece íntegro.
* **`editals ➔ kanban_cards` (`SET NULL`)**: Se um edital for retirado do ar pela agência de fomento, o cartão de processo em andamento no Kanban permanece ativo na rotina da startup com `edital_id = NULL`.
* **`startups ➔ alerta_prazos` e `kanban_cards ➔ alerta_prazos` (`SET NULL`)**: O monitoramento do prazo do edital não é interrompido caso a vinculação a uma startup ou cartão específico seja desfeita.

---

## 4. Índices Compostos e Regras de Unicidade Físicas

1. **`UNIQUE(agencia_id, cnpj)` na tabela `startups`**:
   * *Justificativa:* No modelo SaaS B2B, múltiplas consultorias concorrentes podem prestar serviços para a mesma startup. No entanto, uma mesma consultoria não pode cadastrar o mesmo CNPJ mais de uma vez em sua carteira. A chave composta garante a soberania do Tenant sem gerar colisões globais.
2. **`UNIQUE(user_id, startup_id, edital_id)` na tabela `alerta_matches`**:
   * *Justificativa:* Assegura idempotência às rotinas assíncronas de mineração e cálculo de elegibilidade (Background Queues). Evita que múltiplos alertas idênticos de compatibilidade sejam inseridos para o mesmo consultor e startup em um mesmo certame.
3. **`UNIQUE(user_id, edital_id, nivel_urgencia)` na tabela `alerta_prazos`**:
   * *Justificativa:* Garante que o sistema dispare no máximo uma notificação para cada patamar de urgência temporal (*atencao*, *urgente*, *critico*), prevenindo duplicidade de e-mails ou mensagens para o mesmo edital.
