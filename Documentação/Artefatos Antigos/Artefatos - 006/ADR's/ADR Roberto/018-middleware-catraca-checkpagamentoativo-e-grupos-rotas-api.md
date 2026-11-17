# ADR 018: Middleware de Catraca Financeira (`CheckPagamentoAtivo`), Registro em `bootstrap/app.php` e Segregação de Rotas em `routes/api.php`

## Status
Aceito

## Contexto
Para cumprir o requisito de bloqueio por inadimplência (`RF-27` — `EDTAN-104`, `EDTAN-121` e `EDTAN-208`), o Back-end não pode confiar apenas na verificação feita durante o login. Se uma agência tiver sua assinatura suspensa ou cancelada via webhook enquanto o usuário já possui um token emitido, o servidor precisa barrar o acesso às rotas operacionais. Por outro lado, as rotas de regularização financeira e gestão do próprio perfil não podem sofrer esse bloqueio, senão a conta pendente ficaria impossibilitada de gerar um novo PIX ou de excluir seu pré-cadastro.

## Decisão
Decidimos criar o middleware `app/Http/Middleware/CheckPagamentoAtivo.php`, registrá-lo em `bootstrap/app.php` sob o alias `'check.pagamento'` e organizar `back/routes/api.php` em camadas segregadas de proteção.

## Justificativa
1. **Por que registrar os aliases de middleware em `bootstrap/app.php`:** Seguindo a arquitetura enxuta do Laravel 11/12 (que substituiu o antigo `app/Http/Kernel.php`), o método `->withMiddleware(function (Middleware $middleware): void { ... })` em `bootstrap/app.php` registra centralmente os aliases `'check.pagamento' => \App\Http\Middleware\CheckPagamentoAtivo::class` e `'startup.ativa' => \App\Http\Middleware\VerificarStartupAtiva::class`.
2. **Por que `CheckPagamentoAtivo@handle(Request $requisicao, Closure $proximo)` verifica ambas as flags financeiras:**
   ```php
   if ($usuario && $usuario->agencia) {
       if ($usuario->agencia->pagamento_ativo || $usuario->agencia->status_pagamento === 'ativo') {
           return $proximo($requisicao);
       }
   }
   ```
   Caso nenhuma das duas condições seja satisfeita, o middleware interrompe o pipeline HTTP imediatamente e devolve status **`403 Forbidden`** com `['mensagem' => 'Acesso bloqueado. O pagamento da sua assinatura está pendente ou inativo.', 'codigo_erro' => 'PAGAMENTO_PENDENTE', 'codigo' => 'PAGAMENTO_PENDENTE']`.
3. **Por que segregar `routes/api.php` em grupos distintos:**
   - **Rotas Públicas de Entrada e Webhook:** `/planos`, `/auth/verificar-email`, `/auth/registrar-com-plano`, `/auth/registro`, `/auth/login`, `/auth/esqueci-senha`, `/auth/redefinir-senha` e `/webhook/abacatepay`.
   - **Rotas de Regularização Financeira (Isentas de `check.pagamento`):** `/pagamentos/consultar-anterior`, `/planos/gerar-nova-cobranca`, `/auth/cancelar-cadastro-pendente`, `/pagamento/recuperar`, `/planos/selecionar` e `/auth/perfil`. Manter essas rotas fora do grupo `check.pagamento` garante que contas pendentes consigam regularizar sua situação sem tomar erro `403`.
   - **Rotas de Gestão de Carteira Protegidas por `auth:sanctum` + `check.pagamento`:** `GET /startups`, `POST /startups`, `GET /startups/{id}` e `PUT /startups/{id}`.

## Consequências
- Proteção financeira centralizada na camada de Middleware, mantendo os Controllers de negócio livres de checagens repetitivas de assinatura.

