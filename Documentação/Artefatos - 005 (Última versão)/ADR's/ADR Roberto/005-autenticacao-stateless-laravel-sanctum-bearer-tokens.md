# ADR 005: Autenticação Stateless via Laravel Sanctum (`Bearer Token`) em `AuthController`

## Status
Aceito

## Contexto
Como o Back-end Laravel (`/back`) opera como uma API REST pura consumida por clientes externos em origens distintas (navegador web e aplicativo mobile Android) (`EDTAN-80` e `EDTAN-121`), precisávamos definir o mecanismo de autenticação em `app/Http/Controllers/AuthController.php`. A autenticação tradicional baseada em sessões de cookies (`web` guard) sofre bloqueios constantes de *CORS*, *SameSite Cookies* e *CSRF Token mismatch* em chamadas *cross-origin*.

## Decisão
Decidimos implementar a autenticação 100% *Stateless* utilizando **Laravel Sanctum** (incorporando a trait `Laravel\Sanctum\HasApiTokens` no modelo `app/Models/User.php`) através da emissão de *Personal Access Tokens* (`$usuario->createToken('auth_token')->plainTextToken`), trafegados via cabeçalho HTTP `Authorization: Bearer <token>`.

## Justificativa
1. **Por que escolher Laravel Sanctum via `Bearer Token` em vez de Cookies de Sessão:** Como o token em texto plano (`plainTextToken`) é retornado explicitamente no JSON de resposta do `AuthController` (`token` e `access_token`), o Back-end não depende de domínio compartilhado nem de estado de sessão em memória, autenticando qualquer requisição que apresente o header `Authorization: Bearer` válido no middleware `auth:sanctum`.
2. **Por que escolher Laravel Sanctum em vez de JWT (JSON Web Token) puro sem estado no banco:** Em um SaaS corporativo Multi-Tenant, o Back-end precisa ser capaz de revogar tokens instantaneamente (por exemplo, na exclusão de conta pendente `UC-47` ou no bloqueio de acessos `RF-13`). Como o Sanctum persiste o hash SHA-256 de cada token na tabela `personal_access_tokens`, basta executar `$usuario->tokens()->delete()` no servidor para invalidar o acesso em tempo real.
3. **Por que carregar ansiosamente (`Eager Loading`) a agência e o plano em `AuthController@login` (`User::with('agencia.plano')->where('email', ...)->first()`):** Evita o problema de performance *N+1 Queries* no momento do login e permite que o `AuthController` valide a senha (`Hash::check`), verifique o status financeiro da agência vinculada e monte o payload completo (`usuario` e `agencia`) em uma única consulta otimizada.

## Consequências
- Todas as rotas privadas em `routes/api.php` são protegidas de forma declarativa pelo middleware `auth:sanctum`.
- Controle total no servidor sobre a validade e revogação dos tokens emitidos.

