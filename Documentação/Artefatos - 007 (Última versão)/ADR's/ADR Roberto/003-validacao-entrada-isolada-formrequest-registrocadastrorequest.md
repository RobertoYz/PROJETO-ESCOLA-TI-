# ADR 003: Isolamento de Validação de Entrada em `RegistroCadastroRequest` e Nomeação Automática de Consultoria (`nome_agencia`)

## Status
Aceito

## Contexto
No fluxo de registro de novas contas no Back-end (`EDTAN-80` e `EDTAN-148`), precisávamos validar rigorosamente os dados recebidos no payload HTTP (`name`, `email`, `password` e `nome_agencia`) antes de iniciar qualquer transação no MySQL. Além disso, obrigar o envio de um nome empresarial para a agência logo no primeiro passo aumentava a fricção para consultores autônomos, mas a coluna `nome` da tabela `agencias` é obrigatória (`NOT NULL`).

## Decisão
Decidimos isolar as regras de validação na classe FormRequest dedicada `app/Http/Requests/RegistroCadastroRequest.php` (e replicar a mesma assinatura de validação nos métodos de registro do `AuthController.php`), definindo a regra de `nome_agencia` como opcional (`nullable|string|max:255`) e gerando automaticamente o nome padrão `'Consultoria de ' . $dados['name']` no Back-end quando o campo não é enviado.

## Justificativa
1. **Por que criar a classe `app/Http/Requests/RegistroCadastroRequest.php`:** No Laravel, utilizar classes que estendem `Illuminate\Foundation\Http\FormRequest` (implementando os métodos `authorize(): bool`, `rules(): array` e `messages(): array`) separa a responsabilidade de validação de entrada da lógica de controle. Caso `name` esteja vazio, `email` seja inválido/duplicado (`unique:users,email`) ou `password` tenha menos de 8 caracteres (`min:8`), o Laravel interrompe o request antes de entrar no Controller e devolve HTTP `422 Unprocessable Entity` com as mensagens customizadas em português.
2. **Por que tornar `nome_agencia` opcional (`nullable`) e gerar `'Consultoria de ' . $dados['name']` no Back-end (`RegistroService.php` e `AuthController.php`):** A expressão ternária `$nomeAgencia = !empty($dados['nome_agencia']) ? $dados['nome_agencia'] : 'Consultoria de ' . $dados['name'];` garante que a tabela `agencias` sempre receba uma string válida e semântica nas colunas `nome` e `nome_fantasia`, mesmo quando a requisição HTTP envia apenas `name`, `email` e `password`.

## Consequências
- Contratos de validação HTTP `422` padronizados em português para qualquer cliente que consuma a API.
- Integridade garantida na coluna `nome` da tabela `agencias` sem exigir campos extras obrigatórios no payload de cadastro.

