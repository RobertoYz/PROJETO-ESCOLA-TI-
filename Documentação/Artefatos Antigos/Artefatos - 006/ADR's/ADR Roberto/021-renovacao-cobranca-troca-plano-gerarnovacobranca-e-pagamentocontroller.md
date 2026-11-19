# ADR 021: Regeneração de Checkout e Troca de Plano em Contas Pendentes (`AuthController@gerarNovaCobranca` e `PagamentoController@recuperarLinkPagamento`)

## Status
Aceito

## Contexto
Quando a janela de 60 minutos de uma cobrança PIX expira ou quando uma agência com pagamento pendente decide contratar um plano diferente daquele escolhido inicialmente (`UC-46` — `EDTAN-104`, `EDTAN-121` e `EDTAN-208`), o Back-end precisa fornecer mecanismos para emitir uma nova cobrança no AbacatePay atualizando os metadados financeiros da `Agencia` sem exigir que o usuário recrie sua conta.

## Decisão
Decidimos implementar dois endpoints complementares no Back-end:
1. **`PagamentoController@recuperarLinkPagamento(Request $requisicao, AbacatePayService $servicoAbacatePay)` (`GET /api/pagamento/recuperar`):** Regera rapidamente uma cobrança PIX mantendo o `$agencia->plano` atual da agência;
2. **`AuthController@gerarNovaCobranca(Request $requisicao, AbacatePayService $servicoAbacatePay)` (`POST /api/planos/gerar-nova-cobranca`):** Permite alterar o `plano_id` da agência pendente e emitir uma nova cobrança PIX com renovação da janela de 60 minutos.

## Justificativa
1. **Por que criar `app/Http/Controllers/PagamentoController.php` (`recuperarLinkPagamento`) na `EDTAN-104`:** Caso o usuário queira apenas um novo QR Code para o mesmo plano já vinculado à sua agência (`$plano = $agencia->plano`), o método verifica primeiro se a conta já está ativa (`if ($agencia->pagamento_ativo || $agencia->status_pagamento === 'ativo') return response()->json(['erro' => 'Sua assinatura já está ativa.'], 400);`), impedindo cobranças duplicadas acidentais em clientes adimplentes. Se estiver pendente, aciona `$servicoAbacatePay->gerarCobranca($agencia, $usuario, $plano)` e atualiza `checkout_url`, `url_checkout`, `billing_id`, `id_cobranca` e `id_plataforma_pagamento`.
2. **Por que implementar `AuthController@gerarNovaCobranca` (`POST /api/planos/gerar-nova-cobranca`) na `EDTAN-208`:** Na central de regularização financeira, o usuário pode querer trocar do plano *Standard* para o *Pro* (ou vice-versa) antes de pagar. O método:
   - Valida `'plano_id' => 'required|exists:planos,id'`;
   - Identifica o usuário e a agência via `$this->resolverUsuarioPendente($requisicao)` (retornando HTTP `404` caso não exista agência vinculada);
   - Carrega o novo plano (`$plano = Plano::findOrFail($requisicao->plano_id)`) e invoca `$servicoAbacatePay->gerarCobranca($agencia, $usuario, $plano)`;
   - Valida se `$urlCheckout` é uma URL HTTPS válida do domínio `abacatepay.com`;
   - Atualiza atomicamente as 9 propriedades de estado da agência (`plano_id`, `checkout_url`, `url_checkout`, `billing_id`, `id_cobranca`, `id_plataforma_pagamento`, `pagamento_expira_em = Carbon::now()->addMinutes(60)`, `status_pagamento = 'pendente'` e `pagamento_ativo = false`) e retorna HTTP `200`.

## Consequências
- Flexibilidade total no Back-end tanto para regenerar o link do plano atual (`GET /api/pagamento/recuperar`) quanto para trocar de plano e renovar o prazo de 60 minutos (`POST /api/planos/gerar-nova-cobranca`).

