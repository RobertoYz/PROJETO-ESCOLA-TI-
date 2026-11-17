# ADR 022: Exclusão Física em Cascata (*Hard Delete*) de Contas Não Ativadas em `AuthController@cancelarCadastroPendente`

## Status
Aceito

## Contexto
Quando um usuário realiza o cadastro inicial, mas desiste da contratação na etapa de pagamento ou percebe que cadastrou o e-mail errado (`UC-47` — `EDTAN-208` e `EDTAN-164`), o Back-end precisa processar o cancelamento dessa conta pendente (`DELETE /api/auth/cancelar-cadastro-pendente`). Precisávamos decidir no Back-end entre aplicar *Soft Delete* (inativação lógica) ou *Hard Delete* (exclusão física permanente no MySQL), além de definir a ordem exata de remoção dos registros dependentes para não violar restrições de chave estrangeira (*Foreign Key Constraints*).

## Decisão
Decidimos implementar o método `cancelarCadastroPendente(Request $requisicao): JsonResponse` em `app/Http/Controllers/AuthController.php` aplicando **Hard Delete físico em cascata** (`forceDelete()` / `delete()`) dentro de uma transação `DB::transaction()`, restrito exclusivamente a contas cujo pagamento nunca foi ativado.

## Justificativa
1. **Por que aplicar Hard Delete (`forceDelete()`) em vez de Soft Delete para cadastros pendentes:** O *Soft Delete* (`RF-12`) aplica-se a clientes que já ativaram a assinatura, operaram na plataforma e encerraram o contrato posteriormente. Já uma conta com `status_pagamento = 'pendente'` nunca concluiu a contratação inicial. Se mantivéssemos o registro na tabela `users` com *Soft Delete*, o índice `UNIQUE` da coluna `email` (`users_email_unique`) continuaria ocupado no MySQL, impedindo que esse usuário voltasse a se cadastrar com seu próprio e-mail!
2. **Por que bloquear a exclusão caso a agência já esteja ativa (`HTTP 400`):** Antes de abrir a transação de exclusão, o método verifica:
   ```php
   if ($agencia && ($agencia->pagamento_ativo || $agencia->status_pagamento === 'ativo')) {
       return response()->json(['mensagem' => 'Não é permitido cancelar uma conta com assinatura ativa.'], 400);
   }
   ```
   Isso impede que o endpoint de cancelamento de pré-cadastro seja acionado indevidamente contra uma consultoria ativa.
3. **Por que executar a limpeza em cascata na ordem exata dentro de `DB::transaction()`:** Para eliminar todos os artefatos da tentativa de cadastro sem violar chaves estrangeiras no MySQL:
   - **Passo 1:** Coleta os IDs de todos os usuários vinculados à agência (`$idsDaAgencia = User::where('agencia_id', $agencia->id)->pluck('id')->toArray()`);
   - **Passo 2:** Deleta todos os tokens Sanctum na tabela `personal_access_tokens` (`PersonalAccessToken::where('tokenable_type', User::class)->whereIn('tokenable_id', $idsUsuarios)->delete()`);
   - **Passo 3:** Limpa eventuais tokens na tabela `password_reset_tokens` (`where('email', $emailUsuario)->delete()`) e sessões ativas na tabela `sessions` (`whereIn('user_id', $idsUsuarios)->delete()`);
   - **Passo 4:** Remove eventuais startups vinculadas (`$agencia->startups()->delete()`), executa `User::whereIn('id', $idsUsuarios)->forceDelete()` e finaliza com `$agencia->forceDelete()`.

## Consequências
- Liberação imediata do endereço de e-mail no índice `UNIQUE` da tabela `users` após o cancelamento.
- Banco de dados 100% limpo, sem tokens órfãos em `personal_access_tokens` nem sessões residuais.

