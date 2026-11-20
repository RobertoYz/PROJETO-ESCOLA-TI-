# ADR 036: Carência de 2 Dias, Ciclo de Vida de Inadimplência via Scheduler (3 Dias / 30 Dias) e Proteção contra Estorno/Chargeback

## Status
Implementado

## Data
Novembro de 2026

## Contexto e Problema (Jira EDTAN-311)
Contas pendentes abandonadas acumulavam registros no MySQL retendo e-mails indefinidamente (contrariando o Princípio da Necessidade da LGPD), enquanto clientes pagantes precisavam de uma janela segura de tolerância caso houvesse falha na cobrança do cartão antes de perderem o acesso ou os dados operacionais da consultoria. Além disso, estornos (`checkout.refunded`) e disputas perdidas (`checkout.lost`) exigiam revogação imediata de acesso.

### Alternativas Consideradas e Rejeitadas
- **Bloqueio imediato no segundo seguinte ao vencimento (`assinatura_expira_em->isFuture()` sem carência):** Rejeitado por interromper abruptamente a operação de consultorias B2B ativas em caso de falha momentânea de limite ou renovação no cartão de crédito, sem oferecer tempo hábil para troca de cartão ou pagamento via PIX.
- **Prazo único de expurgo igual para contas nunca pagas e contas já clientes:** Rejeitado porque reter por 30 dias um cadastro que nunca concluiu o checkout inicial trava desnecessariamente o e-mail do usuário e viola a minimização de dados (LGPD), enquanto excluir em apenas 3 dias uma consultoria que já pagou e cadastrou startups, cards no Kanban e minutas IA violaria a boa-fé contratual e a segurança jurídica empresarial.

---

## Decisão Arquitetural
1. **Período de Carência (Grace Period) de 2 Dias:** O método `Agencia::isPagamentoAtivo()` concede 2 dias de tolerância após `assinatura_expira_em` quando `status_pagamento === 'ativo'`. Durante essa carência, um alerta único por ciclo (`alerta_falha_pagamento_enviado_em`) é enviado via SMTP ao `consultor_admin` orientando-o a usar *"Minha Assinatura" ➔ "Atualizar Forma de Pagamento"*.
2. **Separação de Ciclo de Vida via Laravel Scheduler (`assinaturas:processar-ciclo` às `03:00` com `chunkById(100)`):**
   - **Contas que nunca pagaram (`status_pagamento = 'pendente'` e `assinatura_expira_em IS NULL`):** Exclusão física (*Hard Delete*) automática após **3 dias** (`ASSINATURA_DIAS_EXPURGO_NUNCA_PAGO=3`), em aderência à minimização de dados da LGPD (Art. 6º, III) e liberação rápida do e-mail.
   - **Contas que já pagaram (`assinatura_expira_em IS NOT NULL`):** Pausa/suspensão de acesso (`status_pagamento = 'suspenso'`, preservando os dados) após os **2 dias de carência**, e *Hard Delete* em cascata somente após **30 dias** de inadimplência (`ASSINATURA_DIAS_EXPURGO_SUSPENSA=30`), garantindo segurança jurídica frente ao Código Civil e boa-fé contratual no Brasil.
3. **Revogação Imediata por Reembolso/Chargeback:** Os eventos de webhook `checkout.refunded` e `checkout.lost` alteram `status_pagamento` para `'cancelado'`/`'suspenso'`, bloqueando imediatamente `isPagamentoAtivo()`.

---

## Justificativa
1. **Continuidade Operacional B2B e Retenção Proativa:** A janela de carência de 2 dias aliada ao disparo automático de e-mail HTML (`Mail::html`) reduz o *churn* involuntário, permitindo que o administrador troque o cartão ou gere um PIX sem interrupção do trabalho da equipe.
2. **Equilíbrio entre LGPD e Direito Empresarial Brasileiro:** A distinção pelo histórico financeiro (`assinatura_expira_em IS NULL` vs `IS NOT NULL`) aplica a higienização rápida de 3 dias para cadastros abandonados e preserva por 30 dias o patrimônio digital de clientes recorrentes suspensos antes do expurgo definitivo nas 9 camadas do banco.
3. **Proteção Antifraude Instantânea:** Como `Agencia::isPagamentoAtivo()` exige `status_pagamento === 'ativo'` como premissa primária, qualquer webhook de estorno (`checkout.refunded` ➔ `'cancelado'`) ou chargeback (`checkout.lost` ➔ `'suspenso'`) corta o acesso no ato, independentemente da data de `assinatura_expira_em`.

---

## Consequências

### Positivas
- Execução leve em segundo plano fora de horário de pico (baixo impacto em CPU/RAM), conformidade jurídica plena com a LGPD e o ordenamento brasileiro, e proteção contra fraudes por estorno/chargeback;
- Liberação automática de chaves únicas (`users.email` e `UNIQUE(agencia_id, cnpj)`) de contas abandonadas após 3 dias.

### Negativas / Trade-offs
- Requer que o processo agendador (`php artisan schedule:run` via Cron do servidor) esteja ativo no ambiente de produção para executar a transição diária das 03:00.

---

## Rastreabilidade
- **Demanda Jira:** `EDTAN-311` (Período de Carência de 2 Dias, Notificação SMTP e Scheduler Diário de Ciclo de Vida de Contas)
- **Requisitos Funcionais e Legais:** `RF-08` (Ciclo de Vida da Assinatura), `RF-11` (Conformidade com LGPD — Art. 6º, III e Art. 18, VI), `RNF-01` (Processamento em Lote via `chunkById(100)`)
- **Artefatos Vinculados:** `app/Models/Agencia.php`, `app/Console/Commands/ProcessarCicloAssinaturas.php`, `routes/console.php`, `app/Http/Controllers/WebhookController.php`
