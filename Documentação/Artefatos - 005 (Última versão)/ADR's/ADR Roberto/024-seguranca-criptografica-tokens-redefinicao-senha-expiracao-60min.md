# ADR 024: Segurança Criptográfica na Redefinição de Senha (`password_reset_tokens`), Expiração de 60 Minutos e Uso Único em `AuthController@redefinirSenha`

## Status
Aceito

## Contexto
Complementando o disparo do e-mail de recuperação (`ADR 023` / `EDTAN-156`), precisávamos definir no Back-end a arquitetura criptográfica de geração, persistência, expiração e consumo do token de redefinição de senha na tabela `password_reset_tokens` (criada na migration `2026_09_15_000003_create_users_table.php`), processada pelo endpoint `POST /api/auth/redefinir-senha` (`AuthController@redefinirSenha`).

## Decisão
Decidimos gerar um token aleatório criptograficamente seguro de 64 caracteres (`Str::random(64)`), persistir apenas o seu hash `bcrypt` (`Hash::make($tokenPuro)`) na tabela `password_reset_tokens` via `DB::table('password_reset_tokens')->updateOrInsert(...)`, e validar no método `AuthController@redefinirSenha(Request $requisicao)` a janela de expiração de 60 minutos (`Carbon`), a correspondência via `Hash::check()` e a destruição imediata do registro após o uso.

## Justificativa
1. **Por que usar `updateOrInsert(['email' => $usuario->email], ...)` na tabela `password_reset_tokens`:** Na migration `2026_09_15_000003_create_users_table.php`, a coluna `email` é a chave primária (`$table->string('email')->primary()`). Se o usuário solicitar a recuperação de senha duas vezes seguidas, um `insert()` simples lançaria erro de *Duplicate entry for key PRIMARY*. O método `updateOrInsert` substitui o hash anterior pelo novo e atualiza o timestamp `created_at` para `Carbon::now()`, invalidando automaticamente links anteriores.
2. **Por que gravar `Hash::make($tokenPuro)` no banco e enviar `$tokenPuro` somente na URL do e-mail:** Se o token fosse gravado em texto puro na tabela `password_reset_tokens`, qualquer acesso de leitura indevido ao banco de dados permitiria forjar o link de redefinição de qualquer conta. Ao gravar o hash `bcrypt` e conferir na volta com `if (!Hash::check($requisicao->token, $registroToken->token))`, mesmo um dump da tabela não revela o `$tokenPuro` necessário para aprovar a requisição em `POST /api/auth/redefinir-senha`.
3. **Por que validar a expiração de 60 minutos (`Carbon::parse($registroToken->created_at)->addMinutes(60)->isPast()`) e deletar o token expirado:** Caso o link tenha sido gerado há mais de 1 hora, o Back-end executa `DB::table('password_reset_tokens')->where('email', $email)->delete()` para limpar o registro vencido e retorna HTTP `400` (`'O link de recuperação expirou (validade de 60 minutos). Solicite um novo link.'`).
4. **Por que exigir a regra `'password' => 'required|string|min:8|confirmed'` e deletar o token após o `$usuario->save()`:** A regra `confirmed` do Laravel exige que o payload contenha o par idêntico `password` e `password_confirmation` com no mínimo 8 caracteres. Assim que a nova senha é persistida com `Hash::make($requisicao->password)`, a execução imediata de `DB::table('password_reset_tokens')->where('email', $email)->delete()` garante que o token seja estritamente de **uso único** (*One-Time Token*), impedindo ataques de *Replay*.

## Consequências
- Conformidade com os padrões OWASP para fluxos de *Password Reset* (entropia de 64 caracteres, armazenamento em hash, TTL de 60 minutos e descarte imediato pós-uso).

