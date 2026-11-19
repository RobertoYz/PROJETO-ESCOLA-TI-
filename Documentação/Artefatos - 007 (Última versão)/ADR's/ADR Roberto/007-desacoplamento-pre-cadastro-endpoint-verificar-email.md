# ADR 007: Desacoplamento do Pré-Cadastro no Back-end e Criação do Endpoint `POST /api/auth/verificar-email` (`AuthController@verificarEmail`)

## Status
Aceito

## Contexto
Nas versões iniciais (`EDTAN-80` e `EDTAN-148`), a API realizava o `INSERT` do `User` e da `Agencia` no banco de dados antes de o usuário escolher o plano de assinatura. Durante a auditoria de bugs (`EDTAN-208`), constatamos um problema grave na regra de negócio do Back-end: se o usuário avançasse da primeira etapa, mas desistisse antes de escolher um plano ou voltasse para corrigir uma letra do nome/e-mail, o registro já estava persistido na tabela `users`. Na tentativa seguinte, a validação `unique:users,email` bloqueava a requisição com o erro `"The email has already been taken"`, deixando o banco poluído com contas sem plano definido.

## Decisão
Decidimos desacoplar no Back-end a **Etapa 1 (Verificação de Disponibilidade de E-mail)** da **Etapa 2 (Persistência Atômica com Plano e Geração de Cobrança)**, criando o método `verificarEmail(Request $requisicao): JsonResponse` no `AuthController.php` mapeado na rota pública `POST /api/auth/verificar-email`.

## Justificativa
1. **Por que criar o método `verificarEmail(Request $requisicao)` apenas com leitura (`SELECT EXISTS`):** O método valida o formato do campo (`'email' => 'required|string|email|max:255'`) e executa `$usuarioExistente = User::where('email', $requisicao->email)->exists();`. Por utilizar `exists()` em vez de `first()` ou `create()`, o Eloquent dispara uma query SQL ultrarrápida (`SELECT EXISTS(SELECT 1 FROM users WHERE email = ?)`) sem hidratar modelos em memória e sem realizar nenhum `INSERT` prematuro no banco de dados.
2. **Por que retornar HTTP `422` com `disponivel: false` quando o e-mail já consta na tabela `users`:** Se `$usuarioExistente` for verdadeiro, o método retorna status `422` com `['disponivel' => false, 'message' => 'Este e-mail já está cadastrado. Faça login para acessar ou gerenciar sua conta.', 'mensagem' => '...']`. Caso contrário, retorna HTTP `200` com `['disponivel' => true, 'mensagem' => 'E-mail disponível para cadastro.']`.
3. **Por que manter o método legado `AuthController@registrar` (`POST /api/auth/registro`) separado:** Enquanto o fluxo principal passou a usar o par `verificar-email` + `registrar-com-plano`, manter o método `registrar()` em `routes/api.php` garantiu retrocompatibilidade para testes automatizados e scripts legados que invocavam a rota antiga.

## Consequências
- Erradicação total do bug *"The email has already been taken"* quando o usuário altera seus dados antes de escolher o plano.
- Tabela `users` e `agencias` livres de registros prematuros sem plano associado.

