# ADR 004: Camada de Serviço (`RegistroService`) e Atomicidade com `DB::transaction` na Criação de Conta

## Status
Aceito

## Contexto
A criação de uma nova conta no Back-end do Anjo Nexus (`EDTAN-80` e `EDTAN-121`) envolve múltiplas operações de escrita dependentes entre si: inserir o registro na tabela `agencias`, inserir o usuário titular (`consultor_admin`) na tabela `users` vinculado ao `agencia_id` recém-criado, gerar o token de acesso na tabela `personal_access_tokens` e acionar a geração de cobrança externa (`AbacatePayService`). Se essas operações fossem executadas sem controle transacional e ocorresse uma falha na metade do processo, o banco de dados ficaria inconsistente com uma `Agencia` órfã sem usuário ou um `User` sem cobrança gerada.

## Decisão
Decidimos criar a camada de serviço `app/Services/RegistroService.php` (método `registrarNovaAgenciaEUsuario(array $dados)`) injetada via construtor em `AuthController.php`, bem como envolver toda a persistência de registro e checkout em blocos transacionais **`DB::transaction()`** e **`DB::beginTransaction()` / `DB::commit()` / `DB::rollBack()`**.

## Justificativa
1. **Por que criar a camada `app/Services/RegistroService.php`:** Segue o padrão arquitetural *Service Layer*, evitando acoplar regras de criação sequencial de modelos diretamente em múltiplos lugares e permitindo injeção de dependência (`protected $registroService; public function __construct(RegistroService $registroService)`) no `AuthController`.
2. **Por que usar `DB::transaction` (em `RegistroService@registrarNovaAgenciaEUsuario`) e `DB::beginTransaction()` com `try / catch` e `DB::rollBack()` (em `AuthController@registrarComPlano`):** Garante a propriedade **ACID (Atomicidade)** no MySQL. Se a chamada para a API do AbacatePay lançar uma `Exception` ou se houver erro de banco ao criar o usuário, o `DB::rollBack()` desfaz imediatamente o `INSERT` da `Agencia` e do `User`, evitando que o e-mail do cliente fique preso no banco em uma operação abortada.
3. **Por que aplicar `Hash::make($dados['password'])` e definir o papel inicial como `consultor_admin`:** Garante que nenhuma senha em texto puro seja persistida na coluna `password` (utilizando `bcrypt`) e cumpre o requisito `RF-02` atribuindo automaticamente o perfil de administrador da consultoria ao criador da conta.

## Consequências
- Zero registros parciais ou órfãos no banco de dados caso ocorra falha de persistência ou indisponibilidade do gateway de pagamento durante o registro.

