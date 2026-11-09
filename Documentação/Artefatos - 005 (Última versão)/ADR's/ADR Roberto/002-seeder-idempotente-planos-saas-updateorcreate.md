# ADR 002: População Inicial de Planos SaaS com `PlanoSeeder` Idempotente (`updateOrCreate`) e Orquestração em `DatabaseSeeder`

## Status
Aceito

## Contexto
Para que os endpoints de listagem de planos (`GET /api/planos`) e o serviço de geração de cobrança no AbacatePay (`AbacatePayService.php`) funcionassem imediatamente após a criação do banco de dados (`EDTAN-80` e `EDTAN-148`), a tabela `planos` precisava estar populada com os 3 modelos de assinatura do SaaS (*Standard*, *Pro* e *Enterprise*). Dois problemas precisavam ser evitados no Back-end:
1. O uso do método `Plano::create()` simples dentro do Seeder causaria duplicação de planos toda vez que um desenvolvedor executasse `php artisan db:seed` mais de uma vez.
2. O ID do produto no AbacatePay antes ficava engessado (*hardcoded*) dentro do código do `AbacatePayService.php`, impedindo que o serviço lesse o identificador e os parâmetros diretamente da entidade `Plano`.

## Decisão
Decidimos criar o `database/seeders/PlanoSeeder.php` (registrado em `DatabaseSeeder.php` via `$this->call([PlanoSeeder::class])`) utilizando o método idempotente **`Plano::updateOrCreate()`** do Eloquent para popular os 3 planos oficiais (*Standard*, *Pro* e *Enterprise*) e armazenar o identificador externo do gateway diretamente na coluna `id_plataforma_pagamento` da tabela `planos`.

## Justificativa
1. **Por que usar `updateOrCreate` no `PlanoSeeder.php`:** No loop `foreach ($planos as $dadosPlano)`, o Seeder extrai o `$id` (`1`, `2` ou `3`) e executa `Plano::updateOrCreate(['id' => $id], $dadosPlano)`. Se o registro já existir no MySQL, o Eloquent apenas executa um `UPDATE` nos preços (`49.90`, `99.90`, `299.90`), cotas (`limite_startups` de `2`, `10`, `50` e `limite_membros_equipe` de `1`, `5`, `15`) e `id_plataforma_pagamento`, sem duplicar linhas nem quebrar chaves estrangeiras (`plano_id`) de agências já existentes.
2. **Por que armazenar `id_plataforma_pagamento` na tabela `planos` (`EDTAN-104`):** Desacopla o `AbacatePayService.php` de códigos fixos no código-fonte. Quando uma agência contrata ou renova um plano, o serviço recebe a instância Eloquent `$plano` e lê `$plano->id_plataforma_pagamento` para montar o array `items` da cobrança PIX.
3. **Por que definir valores pagos reais (`>= 100` centavos) nos 3 planos (`EDTAN-208`):** A API v2 de Checkouts do AbacatePay exige transações financeiras reais e rejeita a criação de cobranças PIX com valor zero ("Grátis"). Manter os 3 planos com valores comerciais reais garante 100% de compatibilidade com a criação do QR Code PIX em qualquer opção enviada para o Back-end.

## Consequências
- Qualquer alteração de preço ou cota de planos é aplicada em todo o ambiente com um único comando `php artisan db:seed --class=PlanoSeeder`.
- O `AbacatePayService` torna-se totalmente dinâmico em relação ao catálogo de planos do banco.

