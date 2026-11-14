# ADR 011: Arquitetura do `PlanoController` (`listarPlanos` e `selecionarPlano`) e Projeção de Aliases SQL para Compatibilidade de Contrato

## Status
Aceito

## Contexto
Durante a implementação do módulo de catálogo de planos e associação de assinatura (`EDTAN-148` e `EDTAN-208`), precisávamos expor no Back-end um endpoint público para listagem das faixas de assinatura (`GET /api/planos`) e um endpoint autenticado para vinculação de plano (`POST /api/planos/selecionar`). Havia duas exigências arquiteturais importantes:
1. O endpoint público de listagem não deveria expor o campo interno `id_plataforma_pagamento` (identificador de produto do gateway) na resposta pública;
2. Diferentes consumidores da API esperavam ler o preço como `preco` ou `preco_mensal` e a cota de equipe como `limite_membros_equipe` ou `limite_usuarios`.

## Decisão
Decidimos criar o controlador dedicado `app/Http/Controllers/PlanoController.php` contendo os métodos `listarPlanos()` (com projeção explícita `Plano::select(...)` e aliases SQL) e `selecionarPlano(Request $requisicao, AbacatePayService $servicoAbacatePay)`.

## Justificativa
1. **Por que isolar `PlanoController.php` fora do `AuthController.php`:** Segue o Princípio da Responsabilidade Única (*Single Responsibility Principle*): a consulta ao catálogo comercial de planos (`Plano`) pertence ao domínio de faturamento/assinatura, e não ao domínio de credenciais de autenticação.
2. **Por que usar `Plano::select(...)` com aliases SQL em `listarPlanos()`:**
   ```php
   $planos = Plano::select(
       'id',
       'nome',
       'preco',
       'preco as preco_mensal',
       'limite_startups',
       'limite_membros_equipe',
       'limite_membros_equipe as limite_usuarios'
   )->get();
   ```
   Essa query faz duas coisas simultaneamente no próprio MySQL: oculta colunas sensíveis/internas da tabela `planos` (como `id_plataforma_pagamento`, `created_at` e `updated_at`) e entrega os aliases `preco_mensal` e `limite_usuarios` no JSON HTTP `200` sem custo adicional de processamento em PHP.
3. **Por que implementar `selecionarPlano(Request $requisicao, AbacatePayService $servicoAbacatePay)` (`POST /api/planos/selecionar`):** Permite que uma agência já autenticada (`$requisicao->user()->agencia`) valide `'plano_id' => 'required|exists:planos,id'`, atualize seu `$agencia->plano_id`, invoque `$servicoAbacatePay->gerarCobranca($agencia, $usuario, $plano)` dentro de um bloco `try / catch`, atualize as colunas de controle (`checkout_url`, `url_checkout`, `billing_id`, `id_cobranca`, `id_plataforma_pagamento` e `pagamento_expira_em = Carbon::now()->addMinutes(60)`) e receba o link de pagamento com HTTP `200`.

## Consequências
- Exposição segura e limpa do catálogo de planos em `GET /api/planos`.
- Compatibilidade integral de nomes de campos JSON sem precisar alterar a estrutura da tabela `planos`.

