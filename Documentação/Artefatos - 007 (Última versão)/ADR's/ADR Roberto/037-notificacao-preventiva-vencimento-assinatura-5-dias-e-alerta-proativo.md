# ADR 037: Notificação Preventiva de Vencimento de Assinatura (5 Dias Antes via SMTP) e Alerta Proativo na Interface (`EDTAN-310` / `RF-102`)

## Status
Implementado

## Data
Novembro de 2026

## Contexto e Problema (Jira EDTAN-310 / RF-102)
Com o ciclo recorrente de 30 dias e a carência de 2 dias após o vencimento (`ADR 036`), faltava um mecanismo preventivo para avisar o `consultor_admin` *antes* da data limite (atendendo integralmente ao `RF-102` / `EDTAN-310`), evitando surpresas por esquecimento no PIX ou vencimento de cartão, sem gerar spam de e-mails nem poluir o banco com tabelas extras de log.

### Alternativas Consideradas e Rejeitadas
- **Disparo diário de e-mails durante todos os 5 dias anteriores ao vencimento sem flag de controle:** Rejeitado por caracterizar *spam* transacional, degradar a reputação do domínio remetente no provedor SMTP e gerar fadiga de alertas no administrador da consultoria.
- **Criação de uma tabela dedicada de histórico de notificações de cobrança (`assinatura_notificacoes`):** Rejeitado por introduzir complexidade relacional e *joins* desnecessários para um controle que exige apenas uma trava temporal idempotente por ciclo mensal da agência.
- **Exibição de alertas financeiros para todos os membros da consultoria (`consultor_padrao` e `startup`):** Rejeitado por violar o isolamento de papéis (RBAC), uma vez que apenas o `consultor_admin` possui permissão para renovar a assinatura ou alterar a forma de pagamento.

---

## Decisão Arquitetural
1. **Idempotência via Timestamp na Entidade `agencias`:**
   - Adicionada a coluna `alerta_vencimento_proximo_enviado_em` (`TIMESTAMP NULL`) diretamente na migration original `2026_09_15_000002_create_agencias_table.php` (*Schema Squashing*);
   - Criado o helper `Agencia::emJanelaVencimentoProximo(): bool`, que retorna `true` quando `status_pagamento === 'ativo'`, `assinatura_expira_em` está no futuro e `assinatura_expira_em->lte(now()->addDays(5))`;
   - Na **Fase 0** do comando agendado `ProcessarCicloAssinaturas` (`php artisan assinaturas:processar-ciclo` às `03:00` com `chunkById(100)`), o sistema filtra agências ativas dentro da janela de `<= 5 dias` com `alerta_vencimento_proximo_enviado_em IS NULL`, dispara exatamente um e-mail HTML via SMTP (`Mail::html`) ao `consultor_admin` (contendo plano, forma de pagamento, dias restantes, data `DD/MM/AAAA` e instruções de renovação acumulativa) e grava `alerta_vencimento_proximo_enviado_em = now()`;
   - No `WebhookController` (e na auto-ativação em `AuthController@consultarPagamentoAnterior`), a confirmação de pagamento reseta `alerta_vencimento_proximo_enviado_em = null`, rearmando o monitoramento para o próximo ciclo de 30 dias.
2. **Notificação Multicanal (SMTP + Alerta Proativo na Interface Web e Mobile):**
   - O endpoint `GET /api/assinatura/atual` expõe os metadados de alerta proativo: `vencimento_proximo` (`bool`), `exibir_alerta_assinatura` (`bool`), `alerta_assinatura_nivel` (`'atencao'` para `<= 5 dias`, `'critico'` para carência ou `null`) e `alerta_assinatura_mensagem`;
   - O componente global `seletorGlobalStartup.js` (sincronizado entre Web e Mobile Capacitor) consulta silenciosamente `/api/assinatura/atual` apenas quando o usuário logado é `consultor_admin` e renderiza um badge visual discreto no menu do usuário (ao lado de *"Minha Assinatura"*) e um banner superior acionável (*"Gerenciar Assinatura"*) que abre diretamente o modal `#modalMinhaAssinaturaOverlay`.

---

## Justificativa
1. **Mitigação de Churn Involuntário:** Como pagamentos via PIX exigem ação manual a cada ciclo de 30 dias, o aviso preventivo de 5 dias (combinado à preservação acumulativa dos dias restantes) permite que a consultoria renove com antecedência sem sofrer interrupção operacional.
2. **Garantia Matemática de Zero Spam:** O par `whereNull('alerta_vencimento_proximo_enviado_em')` no Scheduler e o reset para `null` no Webhook asseguram que cada ciclo de faturamento emita no máximo um único e-mail preventivo.
3. **Baixo Custo Operacional e Resiliência:** O envio aproveita o mesmo processo agendado das 03:00 (`chunkById(100)`) e encapsula chamadas SMTP em blocos `try/catch` com `Log::error`, impedindo que falhas de rede interrompam o processamento das demais agências.

---

## Consequências

### Positivas
- Redução drástica de churn involuntário (esquecimento de renovação PIX), conformidade 100% com o `RF-102` (`EDTAN-310`), zero spam por garantia de idempotência e baixo custo operacional por reaproveitar a mesma rotina diária já agendada;
- Experiência proativa em Dark Mode exclusiva para o `consultor_admin` tanto na versão Web quanto no aplicativo Android (Capacitor).

### Negativas / Trade-offs
- A adição de uma requisição `GET /api/assinatura/atual` assíncrona no carregamento do Header Global para administradores, mitigada por execução não-bloqueante e falha silenciosa.

---

## Rastreabilidade
- **Demanda Jira:** `EDTAN-310` (Módulo de Notificação de Vencimento de Assinatura — `RF-102`)
- **Requisitos Funcionais e Não-Funcionais:** `RF-102` (Notificação Preventiva de Vencimento de Assinatura), `RF-08` (Gestão de Ciclo de Assinatura), `RNF-01` (Idempotência e Processamento em Lote)
- **Artefatos Vinculados:** `database/migrations/2026_09_15_000002_create_agencias_table.php`, `app/Models/Agencia.php`, `app/Console/Commands/ProcessarCicloAssinaturas.php`, `app/Http/Controllers/AssinaturaController.php`, `app/Http/Controllers/WebhookController.php`, `front/scripts/componentes/seletorGlobalStartup.js`
