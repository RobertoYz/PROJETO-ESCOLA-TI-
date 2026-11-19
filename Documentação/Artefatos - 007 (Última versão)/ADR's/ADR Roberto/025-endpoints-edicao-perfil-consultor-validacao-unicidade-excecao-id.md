# ADR 025: Endpoints de Perfil do Consultor (`AuthController@obterPerfil` e `atualizarPerfil`), Regra `unique` com Exceção do Próprio ID e Hash Condicional

## Status
Aceito

## Contexto
Para atender ao requisito de gestão e edição da própria conta pelo Consultor autenticado (`RF-95` / `UC-08` — `EDTAN-157`), precisávamos criar no Back-end os endpoints para leitura e atualização dos dados cadastrais do usuário logado (`name`, `email` e `password`). Dois cuidados técnicos eram essenciais na construção do método de atualização:
1. Quando o consultor altera apenas o seu Nome (ou a sua Senha) mantendo o mesmo endereço de e-mail atual no payload, uma regra simples `'unique:users,email'` falharia dizendo que o e-mail já está em uso (por ele mesmo!);
2. O campo de nova senha deveria ser opcional (`nullable`), não podendo sobrescrever a senha atual no banco caso viesse em branco.

## Decisão
Decidimos implementar em `app/Http/Controllers/AuthController.php` os métodos `obterPerfil(Request $requisicao): JsonResponse` (`GET /api/auth/perfil`) e `atualizarPerfil(Request $requisicao): JsonResponse` (`PUT /api/auth/perfil`) dentro do grupo protegido por `auth:sanctum` em `routes/api.php`.

## Justificativa
1. **Por que posicionar `GET /api/auth/perfil` e `PUT /api/auth/perfil` dentro de `Route::middleware('auth:sanctum')`, mas fora de `check.pagamento`:** Permite que qualquer usuário autenticado via token Sanctum consulte ou corrija seus dados cadastrais (como nome ou e-mail) a partir de `$requisicao->user()`, retornando HTTP `401` apenas se o token for inválido.
2. **Por que utilizar a regra `'email' => 'required|string|email|max:255|unique:users,email,' . $usuario->id` em `atualizarPerfil()`:**
   ```php
   $dadosValidados = $requisicao->validate([
       'name' => 'required|string|max:255',
       'email' => 'required|string|email|max:255|unique:users,email,' . $usuario->id,
       'password' => 'nullable|string|min:8',
   ]);
   ```
   Ao concatenar `',' . $usuario->id` como terceiro parâmetro da regra `unique:tabela,coluna,id_ignorado`, o Query Builder do validador do Laravel executa `SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?`. Isso garante duas coisas: se o consultor mantiver seu próprio e-mail, a validação passa sem erro; porém, se ele tentar trocar seu e-mail para o endereço de *outro* usuário já cadastrado no banco, o Back-end bloqueia com HTTP `422` (`'Este e-mail já está em uso por outra conta.'`).
3. **Por que aplicar higienização (`trim` / `strtolower`) e atualização condicional de senha (`if (!empty($dadosValidados['password']))`):**
   - `$usuario->name = trim($dadosValidados['name'])` e `$usuario->email = strtolower(trim($dadosValidados['email']))` evitam espaços acidentais ou letras maiúsculas no endereço de login.
   - A verificação `if (!empty($dadosValidados['password'])) { $usuario->password = Hash::make($dadosValidados['password']); }` garante que o hash `bcrypt` da senha atual na tabela `users` permaneça intacto quando o consultor atualiza apenas seu nome ou e-mail.

## Consequências
- Atualização segura de Nome, E-mail e Senha do Consultor diretamente na tabela `users` sem falsos positivos de e-mail duplicado.

