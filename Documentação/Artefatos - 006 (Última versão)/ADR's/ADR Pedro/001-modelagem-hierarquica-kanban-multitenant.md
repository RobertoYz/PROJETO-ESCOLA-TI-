# ADR 001: Modelagem Relacional Hierárquica do Funil Kanban B2B Multi-tenant

## Status
Aceito

## Contexto (Jira EDTAN-91)
O ecossistema **Anjo Nexus** opera como uma plataforma SaaS B2B na qual consultores e agências de fomento gerenciam simultaneamente o ciclo de captação de recursos para dezenas de startups e empresas clientes. Cada oportunidade de fomento (edital da FINEP, FAPESC, FAPESP, etc.) transita por etapas estruturadas de triagem, elaboração técnica de proposta, anexação de certidões, revisão e submissão formal.

Para suportar essa operação, foram identificados os seguintes requisitos e desafios de engenharia:
1. **Estrutura Relacional Dinâmica:** A necessidade de modelar um funil visual em banco de dados relacional que suporte colunas dinâmicas (ex.: *Backlog*, *A fazer*, *Em elaboração*, *Bloqueado*, *Pronto para Submissão*, *Submetido*), permitindo que quadros sejam inicializados automaticamente sem intervenção manual.
2. **Isolamento de Dados Multi-tenant por Carteira:** Como assegurar que uma agência ou consultor visualize apenas os cartões das startups de sua carteira, viabilizando a filtragem cruzada por startup ativa sem risco de vazamento de informações concorrenciais?
3. **Integridade Referencial e Ciclo de Vida do Edital:** O que acontece com o cartão do Kanban se o edital de origem sofrer alterações ou for arquivado? A exclusão do edital não pode destruir o histórico de trabalho da consultoria.

### Alternativas Descartadas:
- **Tabela única com enum de status (`status = 'backlog'|'doing'|'done'`):** Descartada por engessar a evolução da esteira, inviabilizar reordenação vertical personalizada e impedir a adição futura de colunas customizadas ou quadros temáticos.
- **Banco de Dados Não-Relacional (MongoDB/NoSQL):** Descartado para não fragmentar a pilha tecnológica do projeto (que utiliza MySQL relacional com Laravel Eloquent) e por abdicar da integridade referencial nativa e das transações ACID entre agências, startups e editais.

---

## Decisão

Adotou-se uma **arquitetura relacional hierárquica normalizada em 3 níveis**, implementada através de migrações e modelos Eloquent dedicados no Laravel:

```
┌─────────────────────────────────┐
│          kanban_boards          │
│  - id                           │
│  - agencia_id (FK -> agencias)  │
│  - title                        │
└────────────────┬────────────────┘
                 │ 1:N (cascadeOnDelete)
                 ▼
┌─────────────────────────────────┐
│         kanban_columns          │
│  - id                           │
│  - board_id (FK -> boards)      │
│  - title                        │
│  - position (double)            │
│  - slug                         │
└────────────────┬────────────────┘
                 │ 1:N (cascadeOnDelete)
                 ▼
┌─────────────────────────────────┐
│          kanban_cards           │
│  - id                           │
│  - board_id (FK -> boards)      │
│  - column_id (FK -> columns)    │
│  - edital_id (FK -> editals)    │
│  - startup_name                 │
│  - title & description          │
│  - position (double: 65536.0)   │
│  - metadata (json)              │
└─────────────────────────────────┘
```

### 1. Modelagem das Tabelas de Dados
- **`kanban_boards`:** Representa o painel da agência (`agencia_id`), garantindo o escopo multi-tenant do consultor.
- **`kanban_columns`:** Modela as etapas do processo, possuindo ordenação própria (`position`) e identificador semântico (`slug`).
- **`kanban_cards`:** Representa o ticket do edital. Possui chave estrangeira para o quadro, para a coluna e relacionamento com o edital de origem:
  ```php
  $table->foreignId('edital_id')->nullable()->constrained('editals')->nullOnDelete();
  ```
  O uso de `nullOnDelete()` assegura que a integridade do histórico do card seja preservada mesmo que o edital seja descontinuado no banco.

### 2. Provisionamento Automatizado e Atômico via Transação
No serviço `KanbanService::getOrCreateBoard()`, caso a agência acesse o quadro pela primeira vez, a estrutura padrão de 6 colunas é provisionada de forma atômica através de `DB::transaction()`:
```php
$colunasPadrao = [
    ['title' => 'Backlog', 'slug' => 'backlog', 'position' => 10000.0],
    ['title' => 'A fazer', 'slug' => 'todo', 'position' => 20000.0],
    ['title' => 'Em elaboração', 'slug' => 'doing', 'position' => 30000.0],
    ['title' => 'Bloqueado', 'slug' => 'blocked', 'position' => 40000.0],
    ['title' => 'Pronto para Submissão', 'slug' => 'review', 'position' => 50000.0],
    ['title' => 'Submetido', 'slug' => 'done', 'position' => 60000.0],
];
```

### 3. Filtragem Eager Loading por Startup e Agência
Para otimizar o transporte de dados, o método carrega o quadro com uma única consulta encadeada (*Eager Loading*), filtrando por startup cliente quando solicitado:
```php
$board = $query->with([
    'columns.cards' => function ($q) use ($startupName) {
        if (!empty($startupName)) {
            $q->where('startup_name', 'like', "%{$startupName}%");
        }
        $q->orderBy('position');
    },
    'columns.cards.edital'
])->first();
```

---

## Justificativa Acadêmica (TCC / Banca Avaliadora)

- **Princípio da Responsabilidade Única (SRP - SOLID):** Cada camada de dados isola estritamente sua responsabilidade (configuração do painel, definição do fluxo e estado do item de trabalho).
- **Normalização e Integridade Referencial (3FN):** Garante eliminação de anomalias de exclusão e atualização através de restrições de chave estrangeira gerenciadas pelo próprio motor InnoDB do MySQL.
- **Resiliência e Atomicidade (ACID):** O provisionamento do quadro via transação fechada previne a criação de quadros corrompidos com colunas faltantes em caso de timeout de conexão.
- **Otimização de Consultas (Prevenção do Problema N+1):** O uso de `with(['columns.cards.edital'])` no Eloquent reduz dezenas de potenciais requisições ao banco a apenas 3 consultas indexadas na carga inicial do painel.

---

## Consequências

### Positivas:
- **Flexibilidade Total de Workflow:** Novas colunas podem ser adicionadas dinamicamente sem necessidade de migrações estruturais adicionais.
- **Isolamento de Segurança Multi-Tenant:** Cada consultor gerencia com segurança múltiplos clientes sem risco de contaminação de dados.
- **Tolerância a Alterações:** Vínculo desacoplado com editais evita quebras caso a fonte raspada sofra exclusões no catálogo.

### Negativas / Limitações Futuras:
- Aumenta ligeiramente a complexidade das consultas SQL em comparação a um modelo flat, compensada com índices dedicados nas chaves estrangeiras `board_id`, `column_id` e `agencia_id`.
