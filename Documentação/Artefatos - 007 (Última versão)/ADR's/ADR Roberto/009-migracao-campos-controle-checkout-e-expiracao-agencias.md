# ADR 009: Migration Incremental de Campos de Controle de Checkout e Janela de Expiração na Tabela `agencias`

## Status
Aceito

## Contexto
Na modelagem inicial da tabela `agencias` (`2026_09_15_000002_create_agencias_table.php` — `EDTAN-80`), existiam apenas as colunas `status_pagamento` e `id_plataforma_pagamento`. Contudo, para implementar no Back-end o controle de janela de expiração de 60 minutos do PIX, a reabertura do link de pagamento anterior sem regerar cobrança e a verificação booleana rápida de ativação (`EDTAN-208`), faltavam colunas na tabela `agencias` para persistir a URL do checkout, o ID da cobrança e o timestamp de expiração.

## Decisão
Decidimos criar a migration incremental `database/migrations/2026_10_29_000001_adicionar_campos_pagamento_tabela_agencias.php`, adicionando à tabela `agencias` as colunas `checkout_url` (`text`), `url_checkout` (`text`), `billing_id` (`string`), `id_cobranca` (`string`), `pagamento_expira_em` (`timestamp`) e `pagamento_ativo` (`boolean`), protegidas por verificações `Schema::hasColumn()`.

## Justificativa
1. **Por que usar o tipo `TEXT` (`$tabela->text()->nullable()`) para `checkout_url` e `url_checkout`:** URLs de checkout de gateways de pagamento podem conter tokens longos e parâmetros de query que ultrapassam os 255 caracteres de um `VARCHAR` padrão. O tipo `TEXT` garante que nenhuma URL gerada pelo AbacatePay sofra truncamento no MySQL.
2. **Por que criar a coluna `pagamento_expira_em` (`timestamp()->nullable()`) e configurar `$casts` em `Agencia.php`:** Ao definir `'pagamento_expira_em' => 'datetime'` na propriedade `$casts` de `Agencia.php`, o Eloquent converte automaticamente o valor do MySQL em uma instância `Carbon`, permitindo comparar a validade da janela de 60 minutos diretamente no PHP (`Carbon::parse($agencia->pagamento_expira_em)->isPast()`).
3. **Por que criar a coluna booleana `pagamento_ativo` (`boolean()->default(false)`) junto com `status_pagamento`:** Permite checagens booleanas diretas (`'pagamento_ativo' => 'boolean'` no `$casts`), mantendo sincronia total com a máquina de estados textual `status_pagamento` (`'pendente'`, `'ativo'`, `'suspenso'`, `'cancelado'`).
4. **Por que proteger cada alteração com `Schema::hasColumn('agencias', ...)` no `up()` e no `down()`:** Garante idempotência de esquema entre os bancos locais dos diferentes integrantes da equipe, evitando falhas de *Duplicate column name* durante merges de branches.

## Consequências
- Rastreabilidade completa dos metadados de cada checkout PIX diretamente na tabela `agencias`.
- Reversão segura (`php artisan migrate:rollback`) garantida pelo array `$colunasParaRemover` no método `down()`.

