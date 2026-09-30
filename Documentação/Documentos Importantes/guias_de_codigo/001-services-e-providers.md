# Guia de Código: Camadas de Services e Providers

Este documento explica as responsabilidades e o funcionamento do código encontrado nas pastas `app/Services` e `app/Providers` dentro da arquitetura do Backend (Laravel).

---

## 1. Pasta `app/Services`

No padrão de arquitetura do Laravel, a pasta **Services** não é criada por padrão, mas é uma forte convenção utilizada para abrigar a "Regra de Negócio" pura ou integrações com APIs externas. O objetivo é tirar lógicas complexas de dentro dos Controllers ou dos Jobs, deixando o código mais limpo e reaproveitável.

### `DeepSeekService.php`
Esta classe é o coração da nossa Inteligência Artificial no backend. Ela é responsável por orquestrar as chamadas para os modelos LLMs (Large Language Models) para realizar a análise semântica dos editais.

**Principais Responsabilidades e Métodos:**

- **`analyzeEdital(string $titulo, string $objetivo, string $publico)`**
  É o ponto de entrada principal do serviço. Ele recebe os textos do edital e monta um "Prompt de Sistema" detalhado que ensina a IA como agir (como um especialista em Inovação e Editais).
  Ele tenta primeiro chamar o **DeepSeek**. Se o DeepSeek falhar, cair ou retornar Rate Limit, a classe possui uma rotina de *fallback* automático que direciona a requisição para o **Gemini (Google)**.
  
- **`callDeepSeek()` e `callGemini()`**
  Estes métodos fazem as requisições HTTP (usando a Facade `Http` do Laravel) diretamente para as APIs REST da Groq (DeepSeek) ou do Google (Gemini). Eles encapsulam os detalhes de cabeçalhos (`Authorization`, `Content-Type`) e tratam a formatação do payload.

- **Mecanismo de Rotação de Chaves (`getRandomKey`)**
  Para evitar o esgotamento rápido de saldo ou limites de requisições por minuto (*Rate Limits*), a aplicação pode receber múltiplas chaves de API pelo arquivo `.env` (ex: `GROQ_API_KEY_1`, `GROQ_API_KEY_2`). O método `getRandomKey` descobre todas as chaves configuradas com um prefixo específico e escolhe uma aleatoriamente para balancear a carga.

- **Tratamento de Erros e Parse de JSON**
  As IAs às vezes retornam o JSON envolvido em blocos de Markdown (\`\`\`json ... \`\`\`). A classe utiliza Expressões Regulares (`preg_replace`) para limpar a resposta e extrair apenas a string JSON válida, garantindo que o `json_decode` do PHP consiga transformar a resposta em um *array* utilizável para o Banco de Dados.

---

## 2. Pasta `app/Providers`

Os **Providers** (Provedores de Serviços) são o ponto central de inicialização de todas as aplicações Laravel. Quando a aplicação sobe (boot), ela passa pelos provedores para registrar serviços, injetar dependências ou carregar configurações globais antes que a requisição atinja as Rotas ou os Controllers.

### `AppServiceProvider.php`
Este é o provedor principal e padrão criado pelo Laravel.

**O que ele faz:**
- **Método `register()`:** Usado para registrar ligações no *Service Container* (ex: ensinar ao Laravel qual classe instanciar quando uma interface for pedida). No nosso cenário atual, está vazio.
- **Método `boot()`:** Usado para inicializar serviços extras após todos os outros já terem sido registrados. É muito comum usar este método para forçar conexões HTTPS (usando `URL::forceScheme('https')`), compartilhar variáveis globais com todas as views, ou definir padrões (como o tamanho máximo de string no banco de dados).

Atualmente, nosso `AppServiceProvider` está com as implementações padrão e sem lógicas intrusivas, atuando como um *placeholder* pronto para quando precisarmos expandir a configuração do ciclo de vida da aplicação.
