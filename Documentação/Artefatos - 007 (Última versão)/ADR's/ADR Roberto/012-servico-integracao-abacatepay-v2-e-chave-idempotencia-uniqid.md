# ADR 012: Integração Real com AbacatePay API v2 (`AbacatePayService@gerarCobranca`) e Chave de Idempotência com `uniqid()`

## Status
Aceito

## Contexto
Ao implementar e depurar o serviço de integração com o gateway de pagamentos em `app/Services/AbacatePayService.php` (`EDTAN-80`, `EDTAN-104`, `EDTAN-121` e `EDTAN-208`), identificamos problemas críticos na comunicação com o endpoint `POST https://api.abacatepay.com/v2/checkouts/create`:
1. O campo `externalId` enviado no payload para o AbacatePay era estático (`'agencia_' . $agencia->id`). Como a API v2 do AbacatePay utiliza o `externalId` como chave de idempotência, sempre que uma agência tentava gerar uma segunda cobrança (por exemplo, ao trocar de plano ou após expirar a primeira janela) ou quando o banco local era recriado com `migrate:fresh` reutilizando `id = 1`, o AbacatePay devolvia uma cobrança antiga já paga ou expirada.
2. O retorno do método precisava fornecer tanto a URL do checkout quanto o ID da transação (`data.id`) para persistência na tabela `agencias`.

## Decisão
Decidimos estruturar o método `gerarCobranca($agencia, $usuario, $plano)` em `app/Services/AbacatePayService.php` para:
1. Autenticar na API v2 (`https://api.abacatepay.com/v2`) via `Http::withToken($this->chaveApi)` lendo `env('ABACATEPAY_API_KEY')`;
2. Gerar um identificador único por tentativa concatenando `uniqid()`: `'externalId' => 'agencia_' . $agencia->id . '_' . uniqid()`;
3. Validar rigorosamente o status HTTP da resposta (`$resposta->failed()`) e o domínio da URL retornada (`str_contains($urlCheckout, 'abacatepay.com')`);
4. Retornar um array associativo padronizado com a URL e o ID da cobrança.

## Justificativa
1. **Por que o formato `'agencia_' . $agencia->id . '_' . uniqid()` resolve o problema de idempotência sem perder o vínculo com a agência:** Ao concatenar `'_' . uniqid()` ao final do `externalId`, cada chamada a `/v2/checkouts/create` torna-se uma transação inédita perante o AbacatePay, forçando a emissão de um novo QR Code PIX com status `PENDING`. Ao mesmo tempo, preservar o prefixo estruturado `agencia_{id}_` permite que o `WebhookController` identifique a qual agência o evento pertence extraindo o segundo segmento (`explode('_', $idExterno)[1]`).
2. **Por que estruturar o payload `$dadosEnvio` com `frequency`, `items`, `customer` e `methods`:** A API v2 de Checkouts do AbacatePay exige:
   - `'frequency' => 'ONE_TIME'` e `'methods' => ['PIX']`;
   - `'items' => [['id' => $idProdutoAbacatePay, 'quantity' => 1]]`, onde `$idProdutoAbacatePay` é lido de `$plano->id_plataforma_pagamento`;
   - `'customer' => ['name' => $usuario->name, 'email' => $usuario->email]`, vinculando os dados reais do titular da consultoria ao PIX gerado.
3. **Por que retornar o array `['url', 'id', 'checkout_url', 'url_checkout', 'billing_id', 'id_cobranca']`:** Permite que qualquer controlador chamador (`AuthController`, `PlanoController` ou `PagamentoController`) extraia tanto a URL quanto o ID transacional (`$dadosRetorno['data']['id']`) usando qualquer uma das chaves padronizadas.

## Consequências
- Fim definitivo do erro de cobranças recicladas/expiradas no AbacatePay, inclusive após `php artisan migrate:fresh --seed`.
- Rastreabilidade completa do `id` da transação (`bill_...` / `chk_...`) gravado no banco no exato momento da criação do checkout.

