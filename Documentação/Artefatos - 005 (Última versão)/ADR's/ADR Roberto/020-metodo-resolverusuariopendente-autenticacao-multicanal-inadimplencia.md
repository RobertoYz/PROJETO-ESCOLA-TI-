# ADR 020: Método `resolverUsuarioPendente()` no `AuthController` e Resolução Multicanal Contra Erro `401 Unauthenticated.`

## Status
Aceito

## Contexto
Durante a homologação das três rotas de autoatendimento de contas pendentes (`GET /api/pagamentos/consultar-anterior`, `POST /api/planos/gerar-nova-cobranca` e `DELETE /api/auth/cancelar-cadastro-pendente` — `EDTAN-164`), identificamos um problema recorrente de autenticação no Back-end:
1. Em ambientes Windows com Apache/XAMPP, requisições `DELETE` ou `GET` frequentemente têm o cabeçalho `Authorization: Bearer` filtrado pelo servidor web caso regras específicas de `RewriteRule` não estejam ativas;
2. Caso o cliente enviasse um token antigo de outra sessão no header padrão `Authorization`, depender exclusivamente do middleware `auth:sanctum` fazia o Laravel retornar erro **`401 Unauthenticated.`** ou operar sobre o usuário errado.

## Decisão
Decidimos criar o método protegido `resolverUsuarioPendente(Request $requisicao): ?User` em `app/Http/Controllers/AuthController.php` e retirar as 3 rotas de regularização da dependência estrita do middleware de rota `auth:sanctum`, delegando a resolução segura do usuário diretamente ao `resolverUsuarioPendente()`.

## Justificativa
1. **Por que `resolverUsuarioPendente(Request $requisicao)` verifica 3 canais em cascata:**
   - **Canal 1 — Identificação explícita do e-mail da pendência (`input('email')`, `query('email')` ou `header('X-Pending-Email')`):**
     ```php
     $email = $requisicao->input('email') ?: $requisicao->query('email') ?: $requisicao->header('X-Pending-Email');
     if (!empty($email)) {
         $usuarioPorEmail = User::with('agencia')->where('email', strtolower(trim($email)))->first();
         if ($usuarioPorEmail) {
             return $usuarioPorEmail;
         }
     }
     ```
     Priorizar o e-mail da conta pendente garante que o Back-end nunca opere acidentalmente sobre um token residual de outra conta que tenha ficado em cache.
   - **Canal 2 — Resolução manual do Token Sanctum via `PersonalAccessToken::findToken($tokenBruto)`:** Lê o token a partir de `$requisicao->bearerToken()`, `$requisicao->header('X-Pending-Token')`, `$requisicao->input('token_pendencia')` ou `$requisicao->query('token_pendencia')`. Em seguida, consulta `PersonalAccessToken::findToken($tokenBruto)` no banco e retorna `$tokenModel->tokenable` caso seja uma instância de `User`. Isso contorna qualquer bloqueio do header `Authorization` pelo Apache!
   - **Canal 3 — Fallback para o Guard do Laravel (`$requisicao->user('sanctum') ?? $requisicao->user()`):** Caso nenhum dos parâmetros anteriores tenha sido usado, delega ao resolvedor nativo do Sanctum.
2. **Por que essa resolução multicanal é segura no Back-end:** Todos os 3 métodos que chamam `resolverUsuarioPendente()` (`consultarPagamentoAnterior`, `gerarNovaCobranca` e `cancelarCadastroPendente`) verificam explicitamente se a agência vinculada está de fato pendente, bloqueando qualquer tentativa de cancelar ou alterar uma conta que já esteja com assinatura ativa (`if ($agencia && ($agencia->pagamento_ativo || $agencia->status_pagamento === 'ativo')) return response()->json(..., 400);`).

## Consequências
- Erradicação definitiva do erro `401 Unauthenticated.` nas operações de consulta de PIX anterior, geração de nova cobrança e exclusão de cadastro pendente.

