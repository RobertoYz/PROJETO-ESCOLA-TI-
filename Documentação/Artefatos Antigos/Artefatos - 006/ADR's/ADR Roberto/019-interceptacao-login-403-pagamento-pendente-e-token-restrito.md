# ADR 019: Interceptação de Login no `AuthController@login` com HTTP `403 PAGAMENTO_PENDENTE` e Emissão de `token_pendencia`

## Status
Aceito

## Contexto
Quando um usuário cuja agência ainda está com `status_pagamento = 'pendente'` (ou suspenso/cancelado) envia suas credenciais para `POST /api/auth/login` (`AuthController@login` — `EDTAN-208` e `EDTAN-164`), o Back-end precisa distinguir duas situações completamente diferentes:
1. O usuário digitou um e-mail inexistente ou uma senha incorreta;
2. O usuário digitou seu e-mail e senha corretamente, mas sua agência ainda não concluiu o pagamento da assinatura.
Além disso, no segundo caso, o Back-end precisa fornecer ao cliente os metadados da pendência e uma credencial restrita para que ele possa consultar a cobrança anterior, trocar de plano ou excluir a conta pendente.

## Decisão
Decidimos implementar em `AuthController@login(Request $requisicao): JsonResponse` duas barreiras sequenciais:
1. Validação criptográfica de credenciais (`!$usuario || !Hash::check($validacao['password'], $usuario->password)`), retornando HTTP **`401 Unauthorized`** se inválida;
2. Verificação financeira da agência (`if ($agencia && (!$agencia->pagamento_ativo && $agencia->status_pagamento !== 'ativo'))`), emitindo um `$tokenPendencia` e retornando HTTP **`403 Forbidden`** com o código `'PAGAMENTO_PENDENTE'` e o estado atual da agência.

## Justificativa
1. **Por que retornar HTTP `401` para senha incorreta e HTTP `403` para pagamento pendente:** Respeita a semântica oficial do protocolo HTTP (RFC 9110): `401 Unauthorized` indica falha de autenticação (credenciais inválidas), enquanto `403 Forbidden` confirma que as credenciais foram autenticadas com sucesso, mas uma regra de negócio (assinatura não paga) impede a emissão do token de acesso ao painel principal.
2. **Por que emitir `$tokenPendencia` e retornar os objetos `usuario` e `agencia` dentro do payload do `403`:**
   ```php
   $tokenPendencia = $usuario->createToken('token_pendencia', ['pagamento:gerenciar'])->plainTextToken;
   return response()->json([
       'codigo' => 'PAGAMENTO_PENDENTE',
       'codigo_erro' => 'PAGAMENTO_PENDENTE',
       'mensagem' => 'Seu cadastro possui um pagamento pendente.',
       'access_token' => $tokenPendencia,
       'token' => $tokenPendencia,
       'token_pendencia' => $tokenPendencia,
       'usuario' => ['id' => $usuario->id, 'name' => $usuario->name, 'email' => $usuario->email],
       'agencia' => [
           'id' => $agencia->id,
           'nome' => $agencia->nome,
           'status_pagamento' => $agencia->status_pagamento,
           'checkout_url' => $agencia->checkout_url,
           'url_checkout' => $agencia->checkout_url,
           'pagamento_expira_em' => $agencia->pagamento_expira_em,
       ]
   ], 403);
   ```
   Entregar esse conjunto completo de variáveis já na resposta `403` permite que as rotas subsequentes de regularização (`/pagamentos/consultar-anterior`, `/planos/gerar-nova-cobranca` e `/auth/cancelar-cadastro-pendente`) identifiquem imediatamente a conta pendente sem exigir novo login.

## Consequências
- Separação semântica clara no Back-end entre erro de credenciais (`401`) e retenção por inadimplência (`403`).
- Entrega segura de credencial restrita (`token_pendencia`) apenas após comprovação da senha do usuário via `Hash::check`.

