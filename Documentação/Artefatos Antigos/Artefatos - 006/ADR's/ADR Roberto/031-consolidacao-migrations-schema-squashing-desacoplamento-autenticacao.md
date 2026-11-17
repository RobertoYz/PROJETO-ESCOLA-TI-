# ADR 031: Consolidação de Migrations Incrementais (Schema Squashing), Eliminação de Redundâncias PT/EN e Desacoplamento da Autenticação

## Status
Implementado

## Data
Novembro de 2026

## Contexto e Problema (Jira EDTAN-280)
Durante o desenvolvimento paralelo das sprints do projeto **Anjo Nexus** por múltiplos desenvolvedores em branches concorrentes, observou-se uma proliferação desordenada de migrations incrementais de alteração de tabelas existentes. Ao todo, acumularam-se 7 migrations de remendo (`add_campos_detalhados_to_editais`, `add_dates_to_editais`, `add_ai_fields_to_editals`, `add_faturamento_nicho_to_editals`, `add_conteudo_completo_to_editals`, `adicionar_campos_pagamento_tabela_agencias` e `add_limite_minutas_ia_to_planos`). Além disso, a migration monolítica padrão do framework Laravel acoplava as tabelas `users`, `password_reset_tokens` e `sessions` em um único arquivo de migração.

Simultaneamente, a tabela `agencias` acumulava colunas duplicadas e terminologias mistas em português e inglês para o mesmo atributo de domínio (`id_plataforma_pagamento`, `billing_id`, `id_cobranca`, `checkout_url`, `status_pagamento` e `pagamento_ativo`), exigindo *Accessors* e *Mutators* paliativos no Model `Agencia` apenas para sincronizar estados e colunas redundantes em memória.

Esse cenário gerava os seguintes problemas:
1. **Degradação de Desempenho e CI/CD:** A execução contínua de `php artisan migrate:fresh --seed` no provisionamento de ambientes e na suíte de testes automatizados tornava-se cada vez mais lenta devido ao volume excessivo de comandos `ALTER TABLE`.
2. **Divergência Arquitetural com o DER:** O esquema físico real fragmentado não refletia diretamente o Modelo Entidade-Relacionamento (DER) consolidado do artefato documental.
3. **Fragilidade de Manutenção (*Dead Code*):** A manutenção de atributos espelhados exigia lógica duplicada no Eloquent, propensa a falhas de consistência caso um atributo fosse alterado sem acionar o getter/setter correspondente.

### Alternativas Consideradas e Rejeitadas
- **Manter as 7 migrations incrementais e a migration monolítica de autenticação:** Rejeitado porque o ecossistema encontra-se em estágio de pré-produção/homologação acadêmica (sem banco legado com dados reais de clientes em produção). Manter remendos incrementais em fase de desenvolvimento ativo gera apenas ruído histórico e custos de manutenção desnecessários.
- **Utilizar o recurso nativo `php artisan schema:dump`:** Rejeitado por gerar um dump bruto em SQL puro (`schema-mysql.dump`), dificultando a visualização idiomática em código PHP/Laravel e o rastreamento das convenções de chave estrangeira e índices pelo time.

---

## Decisão Arquitetural
Decidiu-se aplicar a prática de **Schema Squashing (Consolidação Estrutural nas Migrations de Criação)** aliada ao desacoplamento estrito de tabelas e à padronização de uma Fonte Única da Verdade (*Single Source of Truth*):

1. **Schema Squashing das Migrations de Domínio:** Todas as 7 migrations incrementais foram expurgadas da árvore de arquivos e seus respectivos campos foram integrados diretamente nas migrations originais de criação (`create_*_table`):
   - `create_editals_table`: Incorporação atômica dos campos detalhados de faturamento, nicho, datas e flags de IA;
   - `create_planos_table`: Incorporação do campo `limite_minutas_ia`;
   - `create_agencias_table`: Estruturação consolidada dos atributos financeiros.
2. **Desmembramento das Migrations de Autenticação:** A migration monolítica inicial foi desmembrada em 3 arquivos atômicos com responsabilidade única:
   - `0001_01_01_000000_create_users_table.php`
   - `0001_01_01_000001_create_password_reset_tokens_table.php`
   - `0001_01_01_000002_create_sessions_table.php`
3. **Fonte Única da Verdade em `agencias` e Limpeza do Model `Agencia`:**
   - Remoção de todas as colunas espelhadas PT/EN e eliminação dos accessors/mutators redundantes (`getCheckoutUrlAttribute`, `setCheckoutUrlAttribute`, etc.);
   - Padronização dos atributos canônicos: `id_transacao`, `url_checkout`, `status_pagamento` (gerenciado como enum lógico `['pendente', 'ativo', 'suspenso', 'cancelado']`), `pagamento_expira_em` e `assinatura_expira_em`;
   - Manutenção do método semântico `isPagamentoAtivo(): bool` para encapsular as regras de liberação de acesso sem expor validações booleanas dispersas.

---

## Justificativa
1. **Aderência ao DER e Clareza do Código:** A estrutura física do banco passa a ter correlação 1:1 com os diagramas de entidade e documentações de arquitetura do projeto, permitindo que novos desenvolvedores inspecionem diretamente a migration de criação para entender a entidade completa.
2. **Redução de Overhead e Aceleração de Testes:** A consolidação elimina chamadas redundantes de redefinição de esquema do MySQL InnoDB, reduzindo sensivelmente o tempo de execução dos testes com `RefreshDatabase`.
3. **Eliminação de Código Morto (*Technical Debt*):** O Model `Agencia` teve dezenas de linhas de sincronização artificial removidas, deixando de ser um ponto frágil de acoplamento de vocabulário.

---

## Consequências

### Positivas
- Correspondência estrita e auditável entre as migrations de criação e as tabelas reais do banco de dados MySQL;
- Eliminação definitiva de divergências entre atributos espelhados (`url_checkout` vs `checkout_url`, `id_cobranca` vs `billing_id`);
- Separação clara de responsabilidades no subsistema de autenticação e sessões do Laravel.

### Negativas / Trade-offs
- Exigência de reset completo dos ambientes locais de desenvolvimento e homologação através de `php artisan migrate:fresh --seed` após o merge da entrega.

---

## Rastreabilidade
- **Demanda Jira:** `EDTAN-280` (Refatoração Arquitetural de Migrations e Desacoplamento)
- **Requisitos Não-Funcionais:** `RNF-01` (Desempenho), `RNF-04` (Manutenibilidade e Modularidade)
- **Artefatos Vinculados:** `DIAGRAMA DER` (Versão 006), `create_agencias_table`, `create_editals_table`, `Agencia.php`
