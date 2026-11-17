# ADR 030: Middleware `VerificarStartupAtiva` (`startup.ativa`), Validação Anti-IDOR via Header `X-Startup-Id` e Injeção de Contexto na Requisição

## Status
Aceito

## Contexto
Como uma agência de consultoria no Anjo Nexus alterna constantemente entre várias startups de sua carteira durante a mesma sessão de trabalho (`EDTAN-148`), o Back-end precisava de um mecanismo padronizado para saber qual startup está ativa em cada requisição HTTP enviada para os módulos operacionais, além de impedir que um usuário manipulasse o ID da startup ativa no cabeçalho HTTP para tentar operar em nome de uma startup pertencente a outra consultoria.

## Decisão
Decidimos criar o middleware `app/Http/Middleware/VerificarStartupAtiva.php` (registrado em `bootstrap/app.php` sob o alias `'startup.ativa'`), que intercepta o cabeçalho HTTP customizado **`X-Startup-Id`**, valida no banco de dados se a startup pertence ao `agencia_id` do usuário autenticado e injeta a instância Eloquent `$startup` diretamente nos atributos da requisição (`$request->attributes->set('startup_ativa', $startup)`).

## Justificativa
1. **Por que receber o contexto da startup via Header HTTP `X-Startup-Id` (`$request->header('X-Startup-Id')`):** Evita poluir todas as rotas da API REST com parâmetros obrigatórios na URL (`/api/startups/{startup_id}/kanban/...`). O cabeçalho `X-Startup-Id` trafega de forma transparente nos metadados HTTP de cada requisição.
2. **Por que validar o vínculo `where('id', $startupId)->where('agencia_id', $request->user()->agencia_id)` dentro de `VerificarStartupAtiva@handle`:**
   ```php
   $startupId = $request->header('X-Startup-Id');

   if ($startupId && $request->user()) {
       $startup = Startup::where('id', $startupId)
           ->where('agencia_id', $request->user()->agencia_id)
           ->first();

       if (!$startup) {
           return response()->json([
               'mensagem' => 'A startup selecionada não pertence à sua consultoria ou é inválida.'
           ], 403);
       }

       $request->attributes->set('startup_ativa', $startup);
   }
   ```
   Se um usuário mal-intencionado adulterar o valor enviado no header `X-Startup-Id` informando o ID de uma startup de outra agência (ou um ID inexistente), a query retorna `null` e o middleware bloqueia imediatamente a requisição na porta de entrada com HTTP **`403 Forbidden`**.
3. **Por que injetar o modelo validado em `$request->attributes->set('startup_ativa', $startup)`:** Ao anexar o objeto Eloquent `$startup` já carregado no ParameterBag `attributes` do objeto `Illuminate\Http\Request`, qualquer Controller subsequente pode acessar a instância completa da startup ativa (`$request->attributes->get('startup_ativa')`) sem precisar executar uma segunda consulta `SELECT` repetida no banco de dados MySQL.

## Consequências
- Proteção centralizada contra ataques de *IDOR* no cabeçalho de contexto `X-Startup-Id`.
- Reaproveitamento em memória da instância Eloquent `Startup` ao longo de todo o ciclo de vida da requisição HTTP.

