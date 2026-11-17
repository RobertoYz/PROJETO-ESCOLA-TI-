# ADR 027: Listagem e Busca Multi-Coluna de Startups (`StartupController@index`) com Closure de Escopo Multi-Tenant

## Status
Aceito

## Contexto
No endpoint `GET /api/startups` (`StartupController@index` — `EDTAN-148` e `EDTAN-164`), o Back-end precisa retornar todas as startups pertencentes à agência do consultor autenticado, oferecendo também suporte a pesquisa textual via query string (`?busca=`) simultaneamente por **Nome Fantasia**, **Razão Social** ou **CNPJ**.
Um erro clássico e perigoso em SQL/Eloquent ao combinar filtro Multi-Tenant (`WHERE agencia_id = ?`) com múltiplos operadores `orWhere` (`OR razao_social LIKE ? OR cnpj LIKE ?`) é esquecer de agrupar as cláusulas `OR` entre parênteses: sem agrupamento, a precedência do operador `AND` sobre o `OR` no SQL faria a consulta retornar startups de **outras agências** sempre que o termo buscado coincidisse com a `razao_social` ou o `cnpj`!

## Decisão
Decidimos implementar `StartupController@index(Request $request)` em `app/Http/Controllers/StartupController.php` filtrando primariamente por `$agenciaId = $request->user()->agencia_id` e encapsulando as cláusulas `where` / `orWhere` de pesquisa dentro de uma **Closure de agrupamento lógico (`$query->where(function ($q) use ($termoBusca) { ... })`)**, ordenando o resultado por `nome_fantasia ASC`.

## Justificativa
1. **Por que encapsular a busca multi-coluna dentro de uma Closure no Eloquent:**
   ```php
   $agenciaId = $request->user()->agencia_id;
   $termoBusca = $request->query('busca');
   $query = Startup::where('agencia_id', $agenciaId);

   if (!empty($termoBusca)) {
       $query->where(function ($q) use ($termoBusca) {
           $q->where('nome_fantasia', 'LIKE', "%{$termoBusca}%")
             ->orWhere('razao_social', 'LIKE', "%{$termoBusca}%")
             ->orWhere('cnpj', 'LIKE', "%{$termoBusca}%");
       });
   }
   ```
   Passar uma função anônima (`Closure`) para `$query->where(...)` instrui o Query Builder do Laravel a envolver as três condições `LIKE` entre parênteses no SQL gerado:
   `SELECT * FROM startups WHERE agencia_id = ? AND (nome_fantasia LIKE ? OR razao_social LIKE ? OR cnpj LIKE ?) ORDER BY nome_fantasia ASC`.
   Isso garante matematicamente que o filtro `agencia_id = ?` jamais seja contornado pelas condições `OR`, blindando o isolamento Multi-Tenant.
2. **Por que ordenar no banco via `->orderBy('nome_fantasia', 'asc')->get()`:** Entrega a carteira de clientes da consultoria já ordenada alfabeticamente pelo MySQL com complexidade otimizada antes da serialização JSON (`response()->json($startups, 200)`).

## Consequências
- Busca flexível por Nome Fantasia, Razão Social ou CNPJ em um único parâmetro `?busca=` com zero risco de vazamento de dados entre consultorias concorrentes.

