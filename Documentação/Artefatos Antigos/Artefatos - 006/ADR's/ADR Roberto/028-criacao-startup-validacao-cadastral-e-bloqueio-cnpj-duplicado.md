# ADR 028: Criação de Startup (`StartupController@store`), Validação de Atributos Estruturais e Verificação de Duplicidade de CNPJ por Agência

## Status
Aceito

## Contexto
No endpoint `POST /api/startups` (`StartupController@store` — `EDTAN-148` e `EDTAN-164`), o Back-end recebe os dados de uma nova startup para cadastrá-la na carteira da consultoria autenticada. Precisávamos garantir no Back-end:
1. Que o `agencia_id` nunca viesse do corpo da requisição (evitando que um usuário injetasse uma startup em outra agência), sendo extraído estritamente do token autenticado (`$request->user()->agencia_id`);
2. Que se o consultor tentasse cadastrar um CNPJ que já existe na sua própria consultoria, o Back-end devolvesse uma resposta amigável HTTP `422` em português antes de bater em um erro bruto de SQL (`QueryException 1062 Duplicate entry`).

## Decisão
Decidimos implementar `StartupController@store(Request $request)` em `app/Http/Controllers/StartupController.php` combinando validação declarativa de 14 campos (`$request->validate`), verificação prévia de existência escopada por agência (`Startup::where('agencia_id', $agenciaId)->where('cnpj', ...)->exists()`), injeção forçada de `$dadosValidados['agencia_id'] = $agenciaId` e persistência via `Startup::create($dadosValidados)`.

## Justificativa
1. **Por que verificar `$jaExiste` antes do `Startup::create($dadosValidados)`:**
   ```php
   $jaExiste = Startup::where('agencia_id', $agenciaId)
       ->where('cnpj', $dadosValidados['cnpj'])
       ->exists();

   if ($jaExiste) {
       return response()->json([
           'mensagem' => 'Esta startup (CNPJ) já está cadastrada na sua consultoria.'
       ], 422);
   }
   ```
   Embora a tabela `startups` possua o índice único composto `unique(['agencia_id', 'cnpj'])` (`ADR 026`), deixar o erro estourar no MySQL lançaria uma `QueryException` HTTP `500`. Fazer a verificação prévia com `exists()` permite retornar HTTP **`422 Unprocessable Entity`** com uma mensagem clara de domínio de negócio.
2. **Por que exigir apenas `cnpj` e `razao_social` como `required` e manter os outros 12 atributos como `nullable`:** No momento do cadastro rápido da startup (seja no onboarding inicial ou pelo painel interno), a consulta automática à Receita Federal nem sempre retorna todos os campos secundários (por exemplo, empresas sem `nome_fantasia` registrado ou sem complemento de endereço). Tornar `nome_fantasia`, `data_abertura`, `cep`, `logradouro`, `numero`, `bairro`, `cidade`, `estado`, `porte`, `regime_tributario`, `nicho_atuacao` e `pitch` opcionais (`nullable`) permite concluir o cadastro sem travar o consultor e complementar o restante depois.
3. **Por que retornar o objeto `$startup` recém-criado junto com o status HTTP `201 Created`:** Devolver `['mensagem' => 'Startup cadastrada com sucesso.', 'startup' => $startup]` (incluindo o `$startup->id` gerado pelo `AUTO_INCREMENT`) permite que o cliente já selecione imediatamente a startup recém-criada como ativa na sessão sem precisar fazer um novo `GET /api/startups`.

## Consequências
- Criação segura de startups vinculadas estritamente ao `agencia_id` autenticado.
- Tratamento semântico (`422`) contra duplicação de CNPJ na mesma carteira.

