# ADR 006: Modelagem Polimórfica de `personal_access_tokens` e Segregação de Abilities (`auth_token` vs `token_pendencia`)

## Status
Aceito

## Contexto
No fluxo de autenticação e controle financeiro (`EDTAN-80`, `EDTAN-164` e `EDTAN-208`), o Back-end lida com dois estados distintos de usuários autenticados:
1. O consultor cuja agência está com a assinatura **ativa**, que possui permissão para operar o sistema completo;
2. O usuário recém-cadastrado (ou que retornou no login) cuja agência está com **pagamento pendente**, que precisa de um token válido para consultar sua cobrança PIX anterior, trocar de plano ou excluir seu cadastro pendente, mas que não deve ser confundido com uma sessão plena.

## Decisão
Decidimos estruturar a migration `2026_09_17_222232_create_personal_access_tokens_table.php` com suporte a escopos (`abilities`) e segregar no `AuthController.php` a emissão de dois tipos de tokens Sanctum:
- **`'auth_token'`** (com abilities `['*']`): emitido exclusivamente quando o pagamento da agência está ativo;
- **`'token_pendencia'`** (com ability restrita `['pagamento:gerenciar']`): emitido em `registrarComPlano()` e no interceptador `403` de `login()`.

## Justificativa
1. **Por que a tabela `personal_access_tokens` utiliza `morphs('tokenable')` e `string('token', 64)->unique()`:** O método `$table->morphs('tokenable')` cria as colunas `tokenable_type` (ex: `App\Models\User`) e `tokenable_id` (ID do usuário) indexadas, permitindo que o Sanctum localize rapidamente a entidade dona do token e que o método `cancelarCadastroPendente` execute deleções cirúrgicas em lote (`PersonalAccessToken::where('tokenable_type', User::class)->whereIn('tokenable_id', $idsUsuarios)->delete()`). Já a coluna `token` armazena o hash SHA-256 de 64 caracteres com índice `UNIQUE`, garantindo que o token puro nunca fique exposto no banco.
2. **Por que emitir `$usuario->createToken('token_pendencia', ['pagamento:gerenciar'])->plainTextToken` para contas pendentes:** Diferencia tanto pelo nome do registro (`name = 'token_pendencia'`) quanto pela coluna JSON `abilities` (`["pagamento:gerenciar"]`) um token emitido para regularização financeira de um token de acesso pleno (`name = 'auth_token'`).
3. **Por que retornar simultaneamente as chaves `'token_pendencia'`, `'access_token'` e `'token'` no JSON de resposta de `registrarComPlano()` e `login()` (403):** Garante compatibilidade de contrato com qualquer consumidor da API, permitindo que clientes mais novos identifiquem explicitamente a chave `token_pendencia` enquanto clientes genéricos leiam `token` ou `access_token`.

## Consequências
- Rastreabilidade clara na tabela `personal_access_tokens` entre sessões operacionais ativas e tokens temporários de regularização de inadimplência.

