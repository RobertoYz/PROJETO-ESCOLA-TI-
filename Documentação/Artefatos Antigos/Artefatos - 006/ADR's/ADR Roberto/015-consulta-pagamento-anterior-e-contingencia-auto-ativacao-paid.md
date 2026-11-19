# ADR 015: Endpoint `GET /api/pagamentos/consultar-anterior` (`AuthController@consultarPagamentoAnterior`) e Contingência de Auto-Ativação (`PAID`)

## Status
Aceito

## Contexto
Quando um usuário com pagamento pendente solicita ao Back-end a reabertura de sua janela de pagamento anterior (`GET /api/pagamentos/consultar-anterior` — `EDTAN-208` e `EDTAN-164`), três situações reais podem ocorrer no servidor:
1. O pagamento já foi confirmado segundos antes (via webhook ou diretamente no gateway while o túnel local de webhook estava offline);
2. A cobrança anterior já expirou (> 60 minutos ou status `EXPIRED`/`CANCELLED`);
3. A cobrança anterior continua aberta (`PENDING`) dentro da janela de 60 minutos.

## Decisão
Decidimos implementar o método `consultarPagamentoAnterior(Request $requisicao, AbacatePayService $servicoAbacatePay): JsonResponse` em `app/Http/Controllers/AuthController.php`, incorporando além da devolução do link válido uma rotina de **auto-ativação ativa (*self-healing*)** caso a consulta em tempo real ao AbacatePay retorne o status `'PAID'`.

## Justificativa
1. **Por que verificar primeiro se a agência já está ativa no banco local (`$agencia->pagamento_ativo || $agencia->status_pagamento === 'ativo'`):** Se o `WebhookController` já tiver processado o pagamento em background, o método responde imediatamente com HTTP `200` e `['expirado' => false, 'pago' => true, 'mensagem' => 'O pagamento já foi confirmado!']`.
2. **Por que consultar `$servicoAbacatePay->consultarStatusCobranca($idCobranca)` e ativar a agência se `$statusGateway === 'PAID'`:**
   ```php
   $idCobranca = $agencia->billing_id ?? $agencia->id_cobranca ?? $agencia->id_plataforma_pagamento;
   if (!empty($idCobranca)) {
       $statusGateway = $servicoAbacatePay->consultarStatusCobranca($idCobranca);
       if ($statusGateway === 'PAID') {
           $agencia->pagamento_ativo = true;
           $agencia->status_pagamento = 'ativo';
           $agencia->save();
           return response()->json(['expirado' => false, 'pago' => true, ...], 200);
       }
   }
   ```
   Essa decisão cria uma **contingência ativa ao Webhook**: se o usuário acabou de pagar o PIX no AbacatePay, mas o disparo do webhook sofreu atraso de rede ou o desenvolvedor local não estava com o `ngrok` aberto no momento do pagamento, basta acionar `consultarPagamentoAnterior` para que o próprio Back-end consulte `/v2/checkouts/get`, constate o status `PAID`, grave `pagamento_ativo = true` e `status_pagamento = 'ativo'` no MySQL e libere a conta!
3. **Por que retornar HTTP `400` com `expirado: true` quando `$servicoAbacatePay->estaExpirado($agencia)` é verdadeiro:** Informa explicitamente ao consumidor da API que o link antigo não deve mais ser aberto e que o usuário deve ser direcionado para o fluxo de geração de nova cobrança (`POST /api/planos/gerar-nova-cobranca`). Quando válido, retorna HTTP `200` com `checkout_url` e `url_checkout`.

## Consequências
- Resiliência financeira dupla: as contas são ativadas tanto de forma passiva (`WebhookController`) quanto de forma ativa (`consultarPagamentoAnterior`).
- Nenhum usuário é redirecionado para um QR Code PIX vencido.

