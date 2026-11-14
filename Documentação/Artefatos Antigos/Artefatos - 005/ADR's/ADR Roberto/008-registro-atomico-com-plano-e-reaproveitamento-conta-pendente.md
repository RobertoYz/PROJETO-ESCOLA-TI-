# ADR 008: Registro Atômico com Plano (`AuthController@registrarComPlano`) e Reaproveitamento Inteligente de Cadastro Pendente

## Status
Aceito

## Contexto
No endpoint `POST /api/auth/registrar-com-plano` (`AuthController@registrarComPlano` — `EDTAN-208` e `EDTAN-164`), o Back-end recebe simultaneamente os dados cadastrais (`name`, `email`, `password`) e o `plano_id` escolhido para criar o `User`, criar a `Agencia`, acionar o `AbacatePayService` e devolver a URL do checkout PIX. Precisávamos resolver um cenário crítico de concorrência e usabilidade no servidor: o que acontece se o usuário escolheu um plano (criando o registro com `status_pagamento = 'pendente'`), foi redirecionado para o gateway do AbacatePay, mas clicou em "Voltar" e enviou uma nova requisição em `/api/auth/registrar-com-plano` escolhendo outro plano? Se aplicássemos cegamente a regra `unique:users,email`, o usuário seria bloqueado por e-mail duplicado mesmo sem nunca ter ativado a conta.

## Decisão
Decidimos implementar dentro de `AuthController@registrarComPlano(Request $requisicao, AbacatePayService $servicoAbacatePay)` uma lógica condicional de **inspeção prévia e reaproveitamento inteligente de conta pendente**, combinada com validação estrita da URL retornada pelo gateway antes do `DB::commit()`.

## Justificativa
1. **Por que normalizar o e-mail e inspecionar `$usuarioExistente` antes do `$requisicao->validate()`:** Nas linhas iniciais do método, executamos `$emailNormalizado = strtolower(trim((string) $requisicao->input('email')))` e buscamos `$usuarioExistente = User::with('agencia')->where('email', $emailNormalizado)->first()`.
   - **Caso 1 (Conta já paga/ativa):** Se `$usuarioExistente` possui agência com `pagamento_ativo == true` ou `status_pagamento === 'ativo'`, o método barra imediatamente retornando HTTP `422` (`'Este e-mail já está cadastrado e possui assinatura ativa. Faça login para acessar sua conta.'`).
   - **Caso 2 (Conta existente, porém pendente de pagamento):** A variável `$regraEmail` omite a cláusula `unique:users,email` (`'required|string|email|max:255'`). Dentro da `DB::beginTransaction()`, em vez de executar um novo `User::create()`, o método reaproveita a instância `$usuarioExistente`, atualiza `name` e `password` (`Hash::make`), atualiza a `$agencia` existente com o novo `$plano->id` e renova `pagamento_expira_em = Carbon::now()->addMinutes(60)`.
   - **Caso 3 (E-mail inédito):** Aplica `'unique:users,email'` e cria a `Agencia` e o `User` (`perfil_acesso = 'consultor_admin'`) do zero.
2. **Por que validar se `$urlCheckout` contém `'abacatepay.com'` antes de executar `DB::commit()`:** Após chamar `$cobranca = $servicoAbacatePay->gerarCobranca($agencia, $usuario, $plano)`, o método verifica `if (empty($urlCheckout) || !str_starts_with($urlCheckout, 'http') || !str_contains($urlCheckout, 'abacatepay.com'))`. Se o gateway falhar ou devolver um payload inválido, uma `Exception` é lançada, acionando o `DB::rollBack()` no bloco `catch` e retornando HTTP `500` sem deixar registros inconsistentes no banco.

## Consequências
- Resiliência total no Back-end quando o usuário volta da página do gateway para escolher outro plano ou corrigir sua senha antes de pagar.
- Garantia de que uma conta só é persistida no banco se um link real de checkout do AbacatePay tiver sido emitido com sucesso.

