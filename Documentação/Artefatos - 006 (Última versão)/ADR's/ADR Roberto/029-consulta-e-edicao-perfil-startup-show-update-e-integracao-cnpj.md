# ADR 029: Consulta Individual (`show`), Atualização Completa de Perfil de Startup (`StartupController@update`) e Ressincronização com a Receita Federal

## Status
Aceito

## Contexto
Para atender aos casos de uso de visualização detalhada e edição completa do perfil cadastral, fiscal e de inovação de uma startup (`RF-39` a `RF-48` e `RF-96` — `EDTAN-148`, `EDTAN-157` e `EDTAN-164`), o Back-end precisava disponibilizar os endpoints `GET /api/startups/{id}` (`StartupController@show`) e `PUT /api/startups/{id}` (`StartupController@update`), operando em conjunto com os endpoints de consulta à Receita Federal (`GET /api/cnpj/{cnpj}` em `CnpjController@consultar`).

## Decisão
Decidimos implementar os métodos `show(Request $request, $id)` e `update(Request $request, $id)` em `app/Http/Controllers/StartupController.php` utilizando busca blindada por agência (`Startup::where('agencia_id', $agenciaId)->where('id', $id)->firstOrFail()`) e validando os 16 atributos estruturais, de contato e de inovação no método `update()`.

## Justificativa
1. **Por que usar `Startup::where('agencia_id', $agenciaId)->where('id', $id)->firstOrFail()` tanto no `show` quanto no `update`:**
   Se usássemos apenas `Startup::findOrFail($id)`, um usuário autenticado na Agência A poderia passar na URL o `{id}` de uma startup pertencente à Agência B (`GET /api/startups/42` ou `PUT /api/startups/42`) e ler ou sobrescrever dados de outra consultoria (vulnerabilidade de *IDOR — Insecure Direct Object Reference*). Ao encadear `where('agencia_id', $request->user()->agencia_id)->where('id', $id)->firstOrFail()`, o Eloquent lança `ModelNotFoundException` (convertida automaticamente pelo Laravel em HTTP `404 Not Found`) caso aquela startup não pertença à agência do token autenticado.
2. **Por que as regras de validação de `StartupController@update` diferem das regras de `store`:**
   - Em `update()`, o campo `cnpj` passa a ser `'nullable|string|max:18'` (já que o identificador fiscal original já está gravado no registro e permanece bloqueado na edição comum, permitindo enviar o payload focado nos dados mutáveis);
   - São incluídas as regras de validação para os canais de contato institucional da startup: `'email' => 'nullable|email|max:255'` e `'telefone' => 'nullable|string|max:50'`, somando-se a `razao_social` (`required`), `nome_fantasia`, `data_abertura`, `cep`, `logradouro`, `numero`, `bairro`, `cidade`, `estado` (`max:2`), `porte`, `regime_tributario`, `nicho_atuacao` e `pitch`.
3. **Por que integrar a edição de perfil com o endpoint `GET /api/cnpj/{cnpj}` (`CnpjController` / `CnpjService`):** Permite que, a qualquer momento durante a vida útil da startup na plataforma, o consultor acione a ressincronização sob demanda com as bases oficiais da Receita Federal (BrasilAPI com fallback para ReceitaWS) e persista os dados fiscais, societários e de endereço atualizados através do `PUT /api/startups/{id}`, que retorna HTTP `200` com `['sucesso' => true, 'mensagem' => 'Perfil da startup atualizado com sucesso.', 'startup' => $startup]`.

## Consequências
- Isolamento Multi-Tenant absoluto na leitura (`show`) e edição (`update`) de qualquer startup por `{id}`.
- Persistência completa das 16 dimensões da startup que alimentam o cálculo de Match de Editais (`POST /api/ia/match-editais`) e a geração de Minutas (`POST /api/minutas/gerar`).

