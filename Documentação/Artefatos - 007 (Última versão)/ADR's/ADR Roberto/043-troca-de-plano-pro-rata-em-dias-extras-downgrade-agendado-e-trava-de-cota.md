# ADR 043: Troca de Plano com Pro-rata em Dias Extras, Downgrade Agendado e Trava de Cota (`EDTAN-161`)

## Status
Implementado

## Data
Novembro de 2026

## Contexto e Problema (Jira EDTAN-161)
A AbacatePay v2 cobra o preço integral de um produto fixo `ONE_TIME` via PIX ou cartão. O titular que já pagou parte do ciclo precisa conhecer o valor e a vigência da troca antes de confirmar. Na redução de plano, a agência pode ter mais startups ou membros do que a franquia de destino permite.

### Alternativas Consideradas e Rejeitadas
- Cupom dinâmico via API exigiria conciliar um desconto individual com cada checkout de produto fixo.
- Corte imediato no downgrade retiraria benefícios durante o período já pago.
- Exclusão automática de startups ou inativação automática de membros delegaria ao sistema uma escolha operacional do titular.

---

## Decisão Arquitetural
1. O titular simula a troca. O preço dos planos determina a direção da troca em uma assinatura ativa com vencimento futuro. Para os demais estados o destino é cobrado integralmente, com validação de cotas e ciclo de 30 dias.
2. O upgrade converte o saldo em dias inteiros. Com preços em centavos, dias restantes medidos entre os inícios dos dias e arredondamento ao inteiro mais próximo, o crédito estimado é `preço atual × dias restantes / 30`. Os dias bônus são `preço atual × dias restantes / preço destino`. Por exemplo, 15 dias de Pro representam R$ 49,95 de crédito estimado e 5 dias extras no Enterprise. O titular paga R$ 299,90 por 35 dias.
3. A agência mantém separadamente destino, meio de pagamento, identificador do checkout e bônus pendentes. A confirmação do identificador vigente no webhook ativa o destino e conta a vigência a partir da confirmação. Checkouts de troca levam o sufixo `_troca` no identificador externo para reconhecer notificações atrasadas mesmo após a limpeza da pendência.
4. O downgrade preserva a vigência atual. O titular pode cancelar o agendamento. Sob bloqueio da agência, o serviço verifica as startups com cadastro vigente e as vagas usadas, incluindo titular, membros ativos e convites pendentes. O comando diário e a renovação vencida repetem a verificação. O excesso cancela o agendamento e gera aviso no log, preservando os cadastros.

---

## Justificativa
O bônus em tempo permite vender o produto oficial e reaproveitar os dois meios de pagamento existentes. A simulação informa valor devido hoje, benefício, impedimentos e vigência antes da decisão do titular, apoiando o dever de informação do CDC.

---

## Consequências

### Positivas
- O plano ativo continua disponível enquanto a agência aguarda a confirmação financeira.
- A validação repetida cobre o crescimento da equipe e do portfólio entre o pedido e o vencimento.

### Negativas / Trade-offs
- O arredondamento em dias inteiros pode gerar diferença de até meio dia em relação ao crédito teórico.
- A ativação depende da entrega do webhook. Uma cobrança substituída que o cliente efetivamente pague exige conciliação financeira.
- O processamento diário pode ocorrer após o horário exato de vencimento. A renovação manual também processa o agendamento vencido sob bloqueio.

---

## Rastreabilidade
- **Demanda Jira** — `EDTAN-161`
- **ADRs relacionadas** — 020, 035, 036, 038, 040 e 042 de Roberto
- **Artefatos vinculados** — `TrocaPlanoService`, `AssinaturaController`, `WebhookController`, `ProcessarDowngradesAgendadosCommand` e `TrocaPlanoTest`
