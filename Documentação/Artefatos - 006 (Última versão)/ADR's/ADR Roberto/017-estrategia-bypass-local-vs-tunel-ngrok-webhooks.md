# ADR 017: Evolução do Ambiente de Testes de Pagamento no Back-end: Do Bypass Inicial (`EDTAN-104`) ao Túnel Fixo `ngrok` (`EDTAN-208`)

## Status
Aceito

## Contexto
Durante a `EDTAN-104` (Sprint 005), quando o `WebhookController` e o middleware `CheckPagamentoAtivo` foram implementados no Back-end, os demais integrantes da equipe ainda não possuíam túneis de rede configurados localmente para receber requisições HTTP externas do AbacatePay em `http://127.0.0.1:8000`. Se toda agência criada no Back-end nascesse bloqueada (`status_pagamento = 'pendente'`) sem que o webhook chegasse ao `localhost`, a equipe ficaria impedida de testar os endpoints protegidos de Kanban, Editais e Minutas. Já na `EDTAN-208` (Sprint 008), era obrigatório remover qualquer bypass no Back-end para entregar o ciclo financeiro real de ponta a ponta.

## Decisão
Decidimos conduzir a configuração do Back-end em duas fases evolutivas:
1. **Fase de Desenvolvimento Paralelo (`EDTAN-104`):** Adoção temporária de criação com status `'ativo'` em `RegistroService.php` para destravar os testes locais das demais branches;
2. **Fase de Integração Final e Produção (`EDTAN-148` e `EDTAN-208`):** Remoção total de mocks/bypasses em `RegistroService.php`, `AbacatePayService.php` e `AuthController.php`, forçando toda conta nova a nascer com `status_pagamento = 'pendente'` e `pagamento_ativo = false`, e padronizando a recepção local de webhooks via túnel fixo do **ngrok** (`ngrok http --domain=esophagus-swifter-cultivate.ngrok-free.dev 8000`).

## Justificativa
1. **Por que o Bypass temporário na `EDTAN-104` evitou gargalos na equipe:** Permitiu que as APIs internas fossem desenvolvidas e testadas localmente nas Sprints intermediárias sem exigir aprovação manual no banco ou pagamento PIX a cada `php artisan migrate:fresh`.
2. **Por que remover 100% dos Bypasses no Back-end na `EDTAN-208` e padronizar o domínio estático do `ngrok`:** Garante que o código de `AbacatePayService.php` e `AuthController.php` em desenvolvimento seja exatamente o mesmo de produção, sem desvios condicionais. Com o túnel fixo apontando para a porta `8000` do `php artisan serve`, quando o PIX de teste é pago no AbacatePay, os servidores do gateway enviam o POST real para `https://esophagus-swifter-cultivate.ngrok-free.dev/api/webhook/abacatepay`, acionando o `WebhookController@abacatePayWebhook` localmente sem precisar reconfigurar a URL no painel do AbacatePay a cada reinicialização.

## Consequências
- Paridade de 100% no Back-end entre o ambiente de desenvolvimento local e o ambiente de produção.
- Fluxo real de geração de PIX, retenção por pendência e ativação via webhook testável de ponta a ponta.

