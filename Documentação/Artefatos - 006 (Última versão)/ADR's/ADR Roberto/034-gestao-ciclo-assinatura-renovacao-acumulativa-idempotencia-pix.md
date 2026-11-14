# ADR 034: Gestão do Ciclo de Assinatura Recorrente Mensal (30 Dias), Janela de Renovação Acumulativa e Idempotência de Cobrança PIX

## Status
Implementado

## Data
Novembro de 2026

## Contexto e Problema (Jira EDTAN-160)
No modelo de monetização do **Anjo Nexus**, a cobrança de acesso aos planos SaaS é operada primordialmente via **PIX**. Por se tratar de um meio de pagamento com liquidação do tipo *push* (no qual a transferência depende do ato volitivo e manual do cliente em ler o QR Code ou copiar a chave Pix a cada ciclo mensal, diferentemente de débitos automáticos em cartão de crédito), a plataforma necessitava de um módulo de gestão de ciclo de vida da assinatura (`Minha Assinatura`).

Nesse contexto, três desafios críticos de engenharia de software precisavam ser solucionados:
1. **Canibalização de Período Pago na Renovação Antecipada:** Se um consultor com vencimento previsto para o dia 20 resolvesse pagar a renovação no dia 16, e a lógica da aplicação simplesmente definisse `now()->addDays(30)`, o cliente seria lesado perdendo os 4 dias remanescentes que já haviam sido pagos no ciclo anterior.
2. **Bloqueio Operacional Prematuro (*False Disruption*):** Caso a emissão de um QR Code de renovação alterasse o status da agência para `'pendente'`, o middleware de catraca financeira (`CheckPagamentoAtivo`) travaria sumariamente o acesso de toda a equipe de consultoria à plataforma, mesmo que a vigência contratual do mês ainda estivesse válida.
3. **Sobrecarga de Transações Órfãs no Gateway (*Billing Flooding*):** Se o consultor clicasse seguidamente no botão "Gerar Novo Pagamento PIX" dentro da mesma hora comercial, a aplicação dispararia múltiplas requisições síncronas contra a API da AbacatePay, acumulando cobranças abertas redundantes e onerando as cotas da integração.

### Alternativas Consideradas e Rejeitadas
- **Permitir renovação somente após a expiração formal da assinatura:** Rejeitado porque forçaria um intervalo obrigatório de indisponibilidade (*downtime* operacional) para a consultoria a cada 30 dias enquanto o pagamento PIX fosse gerado e processado.
- **Sobrescrever a data de vigência diretamente com `now()->addDays(30)`:** Rejeitado por penalizar financeiramente clientes adimplentes que quitam faturas de forma antecipada.
- **Gerar nova cobrança no gateway a cada clique sem validação de expiração local:** Rejeitado por degradar a performance da aplicação e poluir o painel financeiro do gateway com dezenas de checkouts descartados.

---

## Decisão Arquitetural
Desenvolveu-se um motor de controle de ciclo de vida de assinaturas distribuído entre o `AssinaturaController`, o `WebhookController` e o Model `Agencia`, alicerçado em quatro pilares de domínio:

1. **Vigência Temporal Centralizada (`assinatura_expira_em`):**
   - Criação da coluna de timestamp `assinatura_expira_em` na tabela `agencias`;
   - O método semântico `Agencia::isPagamentoAtivo(): bool` passa a atuar como árbitro único de acesso:
     ```php
     public function isPagamentoAtivo(): bool
     {
         return $this->status_pagamento === 'ativo' 
             && ($this->assinatura_expira_em === null || $this->assinatura_expira_em->isFuture());
     }
     ```
2. **Janela de Renovação de 5 Dias com Manutenção de Acesso Ativo:**
   - O endpoint de renovação (`POST /api/assinatura/renovar`) e a interface do usuário liberam o fluxo de pagamento antecipado quando faltam **$\le 5$ dias** para o término da vigência (`assinatura_expira_em`) ou quando a assinatura já está expirada;
   - Durante essa janela de tolerância prévia de 5 dias, a emissão do QR Code mantém a agência com `status_pagamento = 'ativo'`, preservando o acesso pleno dos usuários até o segundo final da vigência vigente.
3. **Idempotência de Cobrança PIX (Reaproveitamento de Checkout Aberto):**
   - Antes de acionar o `AbacatePayService`, o `AssinaturaController@renovar` valida se a agência já possui uma cobrança ativa:
     ```php
     if ($agencia->url_checkout && $agencia->pagamento_expira_em && $agencia->pagamento_expira_em->isFuture()) {
         return response()->json([
             'message' => 'Cobrança PIX já disponível.',
             'checkout_url' => $agencia->url_checkout,
             'pix_expira_em' => $agencia->pagamento_expira_em->toIso8601String(),
             'reaproveitado' => true
         ]);
     }
     ```
   - Caso exista uma URL de checkout válida dentro da janela de validade de 60 minutos do PIX, a mesma é retornada de forma idempotente, sem chamadas externas redundantes à API do gateway.
4. **Algoritmo de Renovação Acumulativa (+30 Dias):**
   - No `WebhookController`, quando o evento de liquidação (`billing.paid` ou `checkout.completed`) é recebido e autenticado, a extensão da vigência é calculada com base na data residual:
     - Se `assinatura_expira_em->isFuture()`, a nova data é calculada somando 30 dias à data futura existente (`$agencia->assinatura_expira_em->copy()->addDays(30)`);
     - Se a assinatura já estiver vencida no momento do pagamento, a nova data é ancorada na data corrente (`Carbon::now()->addDays(30)`);
     - O estado é consolidado com `status_pagamento = 'ativo'` e `pagamento_ativo = true`.

---

## Justificativa
1. **Justiça Financeira e Retenção do Cliente B2B:** Ao adotar a adição cumulativa de 30 dias sobre a vigência futura, a plataforma valoriza o bom pagador, garantindo que nenhum dia de serviço contratado seja suprimido por conveniência técnica.
2. **Resiliência e Economia de I/O de Rede:** O mecanismo de idempotência elimina requisições espúrias contra o AbacatePay, tornando a experiência de renovação instantânea e reduzindo o consumo de banda e quotas de API.
3. **Extensibilidade e Prontidão para Cartão de Crédito:** As respostas da API foram padronizadas estruturando o campo `forma_pagamento: 'PIX'`, preparando a arquitetura de persistência e os contratos de visualização para acomodar modalidades com cartão de crédito e recorrência automática programadas para a entrega técnica `EDTAN-311`.

---

## Consequências

### Positivas
- Operação ininterrupta das agências de consultoria durante a transição entre ciclos de faturamento mensal;
- Eliminação de transações duplicadas e desnecessárias na integração com a AbacatePay;
- Código desacoplado e protegido pelo middleware `CheckPagamentoAtivo`.

### Negativas / Trade-offs
- A precisão do bloqueio e da liberação depende da sincronização estrita do relógio do servidor de aplicação e banco de dados via protocolo NTP (`Carbon::now()`).

---

## Rastreabilidade
- **Demanda Jira:** `EDTAN-160` (Gestão de Assinatura Recorrente, Módulo Minha Assinatura e Idempotência PIX)
- **Requisitos Funcionais:** `RF-08` (Gestão de Assinatura e Planos), `RF-09` (Renovação de Assinatura via PIX), `RNF-01` (Performance e Idempotência)
- **Artefatos Vinculados:** `app/Http/Controllers/AssinaturaController.php`, `app/Http/Controllers/WebhookController.php`, `app/Models/Agencia.php`, `public/js/assinatura.js`
