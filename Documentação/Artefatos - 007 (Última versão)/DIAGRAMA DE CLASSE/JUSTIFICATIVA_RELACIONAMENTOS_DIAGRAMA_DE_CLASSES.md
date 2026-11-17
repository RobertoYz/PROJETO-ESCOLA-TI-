# Justificativa Técnica de Relacionamentos: Diagrama de Classes UML

Este documento formaliza a justificativa técnica e arquitetural da modelagem orientada a objetos do sistema **Anjo Nexus** (Versão 007), abrangendo as relações estruturais entre os **Models Eloquent**, o encapsulamento por **Adapters** e o desacoplamento promovido pela **Camada de Serviços (Service Layer)**.

---

## 1. Arquitetura em Camadas e Clean Architecture

A estrutura de classes do Anjo Nexus foi organizada para respeitar o princípio da responsabilidade única (**SRP**) e a inversão de dependência (**DIP**):

```
┌────────────────────────────────────────────────────────┐
│                   Controllers / API                    │
└───────────────────────────┬────────────────────────────┘
                            │ solicita operações
                            ▼
┌────────────────────────────────────────────────────────┐
│               Camada de Serviços (Services)            │
│  Orquestração transacional, regras de negócio e cotas  │
└──────────────┬──────────────────────────┬──────────────┘
               │ consome                  │ interage com
               ▼                          ▼
┌──────────────────────────────┐ ┌──────────────────────────────┐
│     Adapters de Integração   │ │    Modelos de Domínio        │
│   Isolamento de APIs externa │ │    (Eloquent / Multi-Tenant) │
└──────────────────────────────┘ └──────────────────────────────┘
```

---

## 2. Modelos de Domínio (Eloquent Models) e Associações

O diagrama contempla 12 classes de domínio centrais:

### 2.1 Núcleo de Faturamento, Autenticação e Multi-Tenancy
* **`Plano "1" -- "*" Agencia`**: Associação simples. O plano define as diretrizes de faturamento e as cotas contratuais máximas (`limite_startups`, `limite_membros_equipe`, `limite_minutas_ia`), enquanto a `Agencia` consome e gerencia o status operacional da assinatura.
* **`Agencia "1" *-- "*" User`**: Composição forte. Os usuários pertencem ao Tenant da agência. Métodos como `+isConsultorAdmin()` e `+isStartup()` implementam a lógica de controle de acesso baseada em papéis (RBAC).
* **`Agencia "1" *-- "*" Startup`**: Composição forte. Toda startup cliente é criada e gerenciada dentro do escopo fechado de uma agência de consultoria.
* **`Agencia "1" *-- "*" KanbanBoard`**: Composição forte. O pipeline de fomento da consultoria é vinculado à agência titular.

### 2.2 Pipeline Kanban e Processamento
* **`KanbanBoard "1" *-- "*" KanbanColumn`**: Composição estrutural. O quadro gerencia a ordenação e as transições das 6 etapas oficiais do fluxo.
* **`KanbanColumn "1" *-- "*" KanbanCard`**: Composição. Uma coluna agrupa os cartões de processos que estão naquele estágio específico.
* **`KanbanCard "*" -- "0..1" Edital`**: Associação fraca. Um cartão de processo pode referenciar um edital minerado da base ou representar uma oportunidade customizada criada livremente pelo consultor.

### 2.3 Propostas Técnicas (Minutas) e Versionamento
* **`Startup "1" *-- "*" Minuta`**: Composição. A minuta técnica é o artefato jurídico/intelectual vinculado à empresa solicitante de fomento.
* **`Edital "1" *-- "*" Minuta`**: Associação. A proposta orienta-se pelos critérios e requisitos normativos de um edital de fomento.
* **`User "0..1" -- "*" Minuta`**: Associação com multiplicidade opcional. Permite auditoria de autoria (`elaborada_por`) sem inviabilizar a integridade do documento caso o usuário autor venha a ser desvinculado da consultoria.
* **`Minuta "0..1" *-- "*" Minuta`**: Auto-composição recursiva (`parent_minuta_id`). Implementa o padrão de versionamento encadeado imutável, permitindo criar novas revisões (v2, v3...) mantendo o histórico de versões anteriores acessível para auditoria.

### 2.4 Notificações e Favoritos
* **`User "1" *-- "*" EditalFavorito`**, **`Edital "1" *-- "*" EditalFavorito`** e **`Startup "0..1" -- "*" EditalFavorito`**: Modelo associativo que registra o interesse em um edital com rastreabilidade da startup em foco no momento da ação.
* **`User "1" *-- "*" AlertaMatch`**, **`Startup "1" *-- "*" AlertaMatch`** e **`Edital "1" *-- "*" AlertaMatch`**: Triangulação que materializa a correspondência entre startup e oportunidade com Score $\ge 70\%$.
* **`User "1" *-- "*" AlertaPrazo`**, **`Edital "1" *-- "*" AlertaPrazo`** e **`KanbanCard "0..1" -- "*" AlertaPrazo`**: Notificações escalonadas de prazos críticos (Atenção, Urgente, Crítico) vinculadas ao edital ou marco do Kanban.

---

## 3. Padrão Adapter (`App\Adapters`)

A camada de adaptação isola o núcleo de domínio contra alterações e particularidades de fornecedores externos:

1. **`AbacatePayAdapter`**:
   * *Responsabilidade:* Mapeia os dados do domínio (`Agencia`, `Plano`) no formato esperado pela API REST do AbacatePay v2 e converte respostas JSON e webhooks para as estruturas do domínio interno (`status_pagamento`).
   * *Benefício:* Caso o gateway financeiro seja substituído no futuro, apenas o Adapter precisará ser ajustado, mantendo controllers, services e models 100% inalterados.
2. **`BrasilApiCnpjAdapter`**:
   * *Responsabilidade:* Traduz os dados fiscais heterogêneos retornados pela BrasilAPI e pela ReceitaWS (como porte, CNAE principal e endereço) em formatos padronizados para persistência nos atributos do Model `Startup`.
   * *Benefício:* Uniformiza a camada de dados de pessoas jurídicas e abstrai contingências entre múltiplos provedores públicos de consulta.

---

## 4. Camada de Serviços (`App\Services`) e Padrões Comportamentais

Os serviços encapsulam a lógica de negócio transacional e orquestram a cooperação entre múltiplos modelos:

1. **`AbacatePayService`**:
   * Implementa a gestão de cobranças via PIX e Cartão de Crédito com controle de idempotência (evitando duplicidade de requisições) e ciclo de vigência mensal de 30 dias com acúmulo de dias restantes (+30d).
2. **`CnpjService`**:
   * Implementa algoritmo de validação matemática de dígitos verificadores (Módulo 11) e orquestra a consulta com estratégia de **Cache Local de 7 dias**, consultando serviços externos somente quando o cache expira ou sob solicitação expressa de ressincronização.
3. **`MinutaLimitService`**:
   * Responsável pela governança de cotas contratuais. Verifica o saldo de minutas geradas por IA no mês vigente frente ao plano contratado da agência (`Standard: 3`, `Pro: 15`, `Enterprise: 50`), bloqueando a operação antes de qualquer chamada a provedores externos de IA.
4. **`MinutaAiService`**:
   * Implementa o padrão **Chain of Responsibility / Cascata Quádrupla de Resiliência** (1º Gemini ➔ 2º DeepSeek ➔ 3º Groq ➔ 4º Motor Determinístico Local), garantindo 100% de disponibilidade operacional na geração das 11 seções técnicas homologadas.
5. **`DocumentExportService`**:
   * Compila nativamente o documento para arquivos Microsoft Word (`.docx`) via biblioteca pura OpenXML e para PDF A4, sem dependência de ferramentas externas pesadas.
6. **`PdfExtractorService`**:
   * Executa extração de texto de anexos normativos de editais e sintetiza o checklist interativo de obrigações documentais.
7. **`AlertaMatchService` e `AlertaPrazoService`**:
   * Processam regras de disparo multicanal de notificações (Painel, E-mail e WhatsApp), garantindo unicidade e evitando disparos redundantes.
8. **`KanbanService` e `CardMovementService`**:
   * Gerenciam o quadro de processos aplicando o algoritmo de **Ordenação Fracionária Contínua** (`Double position = (ant + post) / 2`), permitindo movimentar cartões livremente sem necessidade de reindexar todas as linhas da tabela no banco de dados.
