# Matriz de Registros de Decisões Arquiteturais (ADRs) - Pedro Emanuel - Versão 005

Este catálogo reúne as **7 Decisões Arquiteturais** sob a responsabilidade de **Pedro Emanuel** no ecossistema **Anjo Nexus**, detalhando o contexto, alternativas descartadas, decisões técnicas de implementação e a fundamentação acadêmica para a banca avaliadora.

O escopo de engenharia divide-se em duas frentes centrais do produto:
1. **Pipeline Kanban B2B de Editais e Propostas** (Jira EDTAN-91)
2. **Módulo de Busca, Validação e Enriquecimento por CNPJ vinculado ao Onboarding e Login** (Jira EDTAN-134 e EDTAN-41)

---

## 📊 Matriz Consolidada de Decisões

| ADR | Decisão Arquitetural | Problema Resolvido | Justificativa para a Banca |
| :--- | :--- | :--- | :--- |
| [**001**](./001-modelagem-hierarquica-kanban-multitenant.md) | **Modelagem Relacional Hierárquica do Funil Kanban B2B Multi-tenant** | Estruturação de fluxo de submissão por startup sem acoplamento rígido | Modelo normalizado `Board ➔ Column ➔ Card` associado à `Startup` com isolamento estrito de dados entre consultorias e prevenção de N+1 via *Eager Loading*. |
| [**002**](./002-fractional-indexing-concorrencia-kanban.md) | **Algoritmo de Fractional Indexing para Concorrência O(1) no Drag-and-Drop** | *Deadlocks* e lentidão causados por reordenação sequencial $O(N)$ em banco | Ponto médio flutuante com gap inicial de $65536.0$, atualizando apenas 1 registro no banco por movimentação em tempo constante $O(1)$ sem contenção de travas. |
| [**003**](./003-eventos-dominio-ciclo-vida-card-kanban.md) | **Arquitetura Orientada a Eventos no Ciclo de Vida do Card (`CardMovedToProposal`)** | Acoplamento rígido entre mover o card físico e disparar preparação de proposta | Padrão *Domain Events / Observer*: listeners assíncronos desacoplados para pré-aquecer IA e registrar auditoria sem poluir o serviço de movimentação (OCP/SRP). |
| [**004**](./004-drag-and-drop-reativo-vanilla-kanban.md) | **Interface Reativa do Kanban em Vanilla JS com Gestão de Estado Local e Drag-and-Drop Otimizado** | Conflitos entre clique/arrasto e travamentos por falta de feedback instantâneo | Manipulação atômica de DOM com *Optimistic UI Update* (latência percebida de 0ms), supressão de falsos cliques via flag temporal e *Rollback* seguro em falhas. |
| [**005**](./005-fallback-cascata-resiliencia-cnpj.md) | **Padrão Fallback em Cascata de Resiliência para Consulta de Dados de CNPJ** | Instabilidade frequente, quotas restritas e quedas nos servidores da Receita Federal | RNF-02 (Tolerância a Falhas): cascata automática BrasilAPI (primário) ➔ ReceitaWS (fallback) com timeout de 8s e normalização semântica via Adapter Pattern. |
| [**006**](./006-cache-validacao-algoritmica-dados-cnpj.md) | **Cache Distribuído com TTL de 7 Dias e Validação Algorítmica Matemática de CNPJ** | Estouro de *Rate Limits* externos, lentidão de rede e envio de CNPJs inválidos | Validação matemática dos dígitos verificadores (Módulo 11) em <1ms antes de qualquer tráfego externo e cache local de 7 dias com resposta em <5ms (>99% mais rápido). |
| [**007**](./007-enriquecimento-cadastral-onboarding-login.md) | **Enriquecimento Cadastral Reativo no Fluxo de Onboarding e Login** | Fricção de cadastro manual de 15 campos burocráticos e erros que quebram o match de IA | Redução do tempo de cadastro de 4 min para <10s: usuário informa apenas o CNPJ e o sistema popula e bloqueia automaticamente dados fiscais oficiais. |

---

## 🛠️ Tecnologias e Padrões Aplicados

- **Arquitetura de Dados:** MySQL InnoDB Relacional (3FN), Foreign Keys com `cascadeOnDelete` e `nullOnDelete`, Índices Fracionários com precisão IEEE 754 (`DOUBLE`).
- **Arquitetura Backend:** Laravel 11 / PHP 8.2, Eloquent ORM, Service-Controller Pattern, Domain Events (`CardMovedToProposal`), Cache API, HTTP Client com Timeouts defensivos.
- **Arquitetura Frontend:** HTML5 Drag and Drop API, Vanilla JavaScript puro (Zero-Build), CSS3 Flexbox/Grid, *Optimistic UI Pattern*, *Event Debouncing*.
- **Padrões de Projeto (GoF / Enterprise):** *Observer/Publish-Subscribe*, *Adapter*, *Circuit Breaker/Fallback*, *Optimistic Offline/UI Update*, *Fail-Fast*.
