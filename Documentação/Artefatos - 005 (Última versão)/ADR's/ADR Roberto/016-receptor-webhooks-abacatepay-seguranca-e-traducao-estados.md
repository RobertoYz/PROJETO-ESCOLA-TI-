# ADR 016: Arquitetura do `WebhookController`, Autenticação por `webhookSecret` e Máquina de Tradução de Estados (`De -> Para`)

## Status
Aceito

## Contexto
Para atender aos requisitos `RF-26`, `RF-27` e `UC-03` (`EDTAN-104`, `EDTAN-121` e `EDTAN-208`), o Back-end precisava receber e processar notificações financeiras assíncronas enviadas pelo AbacatePay para liberar, suspender ou cancelar automaticamente as agências de consultoria no MySQL. Como o endpoint de webhook precisa ser público na internet (`POST /api/webhook/abacatepay`), ele exigia proteção contra requisições falsas, além de um parser resiliente para lidar com diferentes estruturas de JSON da API do AbacatePay.

## Decisão
Decidimos criar o controlador `app/Http/Controllers/WebhookController.php` (método `abacatePayWebhook(Request $requisicao)`) implementando validação de `webhookSecret` via `.env`, extração em cascata de `externalId`, `status` e `id` da transação, parser do identificador da agência com `explode('_', $idExterno)` e Máquina de Tradução de Estados (`De -> Para`).

## Justificativa
1. **Por que autenticar via query string `webhookSecret` (`ABACATEPAY_WEBHOOK_SECRET`):** Como os servidores do AbacatePay não possuem um token de usuário do Laravel Sanctum, a rota não pode usar o middleware `auth:sanctum`. A verificação `$chaveSecretaRecebida !== $chaveSecretaEsperada` (`$requisicao->query('webhookSecret') !== env('ABACATEPAY_WEBHOOK_SECRET')`), que registra `Log::warning` e retorna HTTP `401` em caso de divergência, garante que apenas o AbacatePay consiga alterar o status financeiro de uma agência.
2. **Por que buscar `externalId`, `status` e `id` em cascata (`data.checkout.*` ➔ `data.*` ➔ `data.billing.*`):** Na API v2 do AbacatePay, os eventos de checkout enviam os atributos aninhados em `data.checkout`, enquanto outros eventos podem enviá-los em `data.billing` ou diretamente em `data`. A leitura em cascata:
   ```php
   $idExterno = $requisicao->input('data.checkout.externalId') 
       ?? $requisicao->input('data.externalId') 
       ?? $requisicao->input('data.billing.externalId');
   ```
   torna o `WebhookController` imune a variações de payload entre eventos de cobrança única e assinatura.
3. **Por que extrair o ID da agência via `$partes = explode('_', $idExterno); $idAgencia = $partes[1] ?? ...`:** Como o `AbacatePayService` envia `'externalId' => 'agencia_' . $agencia->id . '_' . uniqid()` (`ADR 012`), dividir a string pelo caractere `'_'` isola com exatidão o ID numérico da agência na posição `$partes[1]` (mantendo fallback `str_replace('agencia_', '', $idExterno)` para cobranças antigas sem sufixo), localizando o registro via `Agencia::find($idAgencia)`.
4. **Por que implementar a Máquina de Tradução de Estados (`De -> Para`) sincronizando `status_pagamento` e `pagamento_ativo`:**
   - Eventos `['checkout.completed', 'subscription.completed', 'billing.paid']` ou status `'PAID'` ➔ `$agencia->status_pagamento = 'ativo'` e `$agencia->pagamento_ativo = true`;
   - Eventos `['checkout.refunded', 'subscription.cancelled']` ou status `['REFUNDED', 'CANCELLED']` ➔ `$agencia->status_pagamento = 'cancelado'` e `$agencia->pagamento_ativo = false`;
   - Eventos `['checkout.lost', 'subscription.expired']` ou status `'EXPIRED'` ➔ `$agencia->status_pagamento = 'suspenso'` e `$agencia->pagamento_ativo = false`;
   - Status `'PENDING'` ➔ `$agencia->status_pagamento = 'pendente'` e `$agencia->pagamento_ativo = false`.

## Consequências
- Ativação ou bloqueio 100% automatizado das contas do SaaS em tempo real.
- Auditoria completa no `storage/logs/laravel.log` (`Log::info`, `Log::warning` e `Log::error`) e gravação do `$idTransacao` nas colunas `id_plataforma_pagamento`, `billing_id` e `id_cobranca`.

