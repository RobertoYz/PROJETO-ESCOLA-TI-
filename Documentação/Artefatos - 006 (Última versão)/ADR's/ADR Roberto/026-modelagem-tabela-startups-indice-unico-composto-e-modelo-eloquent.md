# ADR 026: Modelagem da Tabela `startups` (`create_startups_table`), Índice Único Composto `['agencia_id', 'cnpj']` e Modelo Eloquent `Startup.php`

## Status
Aceito

## Contexto
Para suportar a carteira Multi-Perfil de startups gerenciadas por cada consultoria (`RF-38` a `RF-48` — `EDTAN-148`), precisávamos modelar no banco de dados MySQL a tabela `startups` e seu respectivo modelo Eloquent `app/Models/Startup.php`. Uma decisão arquitetural crítica envolvia a regra de unicidade do CNPJ: se colocássemos um índice `->unique()` simples na coluna `cnpj`, apenas uma agência em todo o SaaS poderia cadastrar aquela startup; por outro lado, se não colocássemos nenhuma restrição no banco, a mesma agência poderia cadastrar o mesmo CNPJ duplicado em sua carteira.

## Decisão
Decidimos criar a migration `database/migrations/2026_10_10_033507_create_startups_table.php` estruturando os 16 atributos de negócio da startup vinculados a `agencia_id` (`constrained('agencias')->onDelete('cascade')`), aplicando o **índice único composto `$table->unique(['agencia_id', 'cnpj'])`** e mapeando a entidade no modelo `app/Models/Startup.php`.

## Justificativa
1. **Por que usar o índice único composto `$table->unique(['agencia_id', 'cnpj'])` em vez de `unique('cnpj')` global:** Em um SaaS Multi-Tenant de consultorias de captação, a restrição de duplicidade deve ser escopada por inquilino (*tenant*). O índice composto `unique(['agencia_id', 'cnpj'])` impede no nível do motor InnoDB do MySQL que a mesma `Agencia` cadastre duas vezes o mesmo `cnpj`, mas permite que agências distintas gerenciem suas carteiras de forma totalmente independente.
2. **Por que organizar as colunas da tabela `startups` em 4 grupos funcionais:**
   - **Grupo 1 — Dados Oficiais da Receita Federal:** `cnpj` (`string(18)`), `razao_social` (`string`), `nome_fantasia` (`string()->nullable()`) e `data_abertura` (`date()->nullable()`);
   - **Grupo 2 — Contato Institucional:** `email` (`string()->nullable()`) e `telefone` (`string()->nullable()`);
   - **Grupo 3 — Endereço Detalhado (para filtro regional de editais estaduais como FAPESC/FAPESP):** `cep`, `logradouro`, `numero`, `bairro`, `cidade` e `estado` (`string(2)->nullable()`);
   - **Grupo 4 — Parâmetros para o Match Inteligente e IA de Minutas:** `porte` (`MEI, ME, EPP, Médio/Grande`), `regime_tributario` (`Simples Nacional, Lucro Presumido, etc.`), `nicho_atuacao` (`Agtech, Healthtech, IA, etc.`) e `pitch` (`text()->nullable()`).
3. **Por que configurar `onDelete('cascade')` na chave estrangeira `agencia_id` e mapear `belongsTo(Agencia::class)` em `Startup.php`:** Garante integridade referencial automática no MySQL (se uma agência for removida, todas as startups de sua carteira são limpas em cascata) e habilita a navegação bidirecional entre `$startup->agencia` (`Startup.php`) e `$agencia->startups` (`Agencia.php`).

## Consequências
- Estrutura relacional completa para armazenar tanto dados cadastrais da Receita Federal quanto insumos estratégicos para os motores de IA e elegibilidade de editais.
- Garantia de unicidade de CNPJ por consultoria diretamente no banco de dados.

