# ADR 001: Modelagem Relacional Multi-Tenant (Tabelas `users`, `agencias` e `planos`) e Adoção do Eloquent ORM

## Status
Aceito

## Contexto
No início do desenvolvimento do Back-end (`EDTAN-80`), precisávamos traduzir o modelo de negócios SaaS B2B do Anjo Nexus para o banco de dados relacional MySQL. Uma única conta contratante (Agência de Consultoria) pode possuir múltiplos usuários vinculados e múltiplos perfis de startups assessoradas, além de estar submetida aos limites de um Plano de Assinatura. Se concentrássemos dados de faturamento, plano e credenciais de login em uma única tabela `users`, inviabilizaríamos a hierarquia Multi-Tenant (onde uma consultoria gerencia vários acessos e startups sob a mesma assinatura) e acoplaríamos regras financeiras à entidade de autenticação.

## Decisão
Decidimos dividir a base estrutural de autenticação e assinatura em três Migrations independentes (`2026_09_15_000001_create_planos_table.php`, `2026_09_15_000002_create_agencias_table.php` e `2026_09_15_000003_create_users_table.php`) mapeadas através das classes de modelo do **Eloquent ORM** (`app/Models/Plano.php`, `app/Models/Agencia.php` e `app/Models/User.php`).

## Justificativa
1. **Por que utilizar Migrations do Laravel em vez de scripts `.sql` manuais:** As Migrations transformam a criação das tabelas do MySQL em código PHP versionável no Git (`up()` e `down()`). Isso permite que qualquer desenvolvedor da equipe rode `php artisan migrate:fresh --seed` no XAMPP local ou no servidor de produção e obtenha exatamente a mesma estrutura relacional e chaves estrangeiras na ordem correta (`planos` ➔ `agencias` ➔ `users`).
2. **Por que adotar o Eloquent ORM (`User.php`, `Agencia.php`, `Plano.php`):** Em vez de escrever queries SQL brutas espalhadas pelo sistema, o Eloquent representa cada tabela como uma Classe e cada linha como um Objeto, facilitando a navegação pelos relacionamentos (`$user->agencia`, `$agencia->plano`, `$agencia->usuarios`, `$agencia->startups`) e protegendo nativamente contra *SQL Injection* via *Prepared Statements* do PDO.
3. **Por que criar a tabela `agencias` separada da tabela `users`:** A `Agencia` é a unidade central do *Multi-Tenancy* (inquilino do SaaS). É nela que residem a chave estrangeira `plano_id` (`foreignId('plano_id')->constrained('planos')`), o identificador externo de cobrança (`id_plataforma_pagamento`), o status financeiro da assinatura (`status_pagamento` com `enum('pendente', 'ativo', 'suspenso', 'cancelado')->default('pendente')`) e os relacionamentos `HasMany` com `usuarios()` e `startups()`. Já a tabela `users` armazena as credenciais individuais (`name`, `email` com índice `unique()`, `password`), a chave estrangeira `agencia_id` (`foreignId('agencia_id')->nullable()->constrained('agencias')->onDelete('cascade')`) e o nível de permissão (`perfil_acesso` com `enum('admin_global', 'consultor_admin', 'consultor_padrao', 'startup')->default('consultor_admin')`).
4. **Por que criar a tabela `planos` isolada:** Permite armazenar os parâmetros de negócio de cada faixa de assinatura (`nome`, `preco` em `decimal(8, 2)`, `id_plataforma_pagamento`, `limite_startups` e `limite_membros_equipe`) sem engessar valores no código PHP, relacionando-se com as consultorias via `hasMany(Agencia::class, 'plano_id')`.

## Consequências
- Isolamento Multi-Tenant nativo no Back-end: todas as consultas de negócio filtram os dados pelo `agencia_id` do usuário autenticado.
- Facilidade para expandir a hierarquia de usuários secundários (`consultor_padrao` e `startup`) vinculados à mesma `Agencia` matriz.

