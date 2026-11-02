# 🛠️ Guia Completo de Configuração e Execução — Anjo Nexus

Este diretório contém a aplicação do **Anjo Nexus**, organizada fisicamente em uma arquitetura **Monorepo** que separa claramente as responsabilidades entre Back-end (`/back`) e Front-end (`/front`)[cite: 20].

---

## ⚠️ AVISO IMPORTANTE: Arquivo `.env` e Chaves de API

Por motivos estritos de segurança da informação, **o arquivo `.env` original não é enviado ao GitHub**, pois contém chaves sensíveis de API (múltiplos tokens de Inteligência Artificial, chaves privadas do Gateway de Pagamento e credenciais SMTP de e-mail).

> 💡 **Para Avaliadores e Colegas de Equipe:** O arquivo `.env` original, completo e já configurado para rodar o sistema de imediato **está à disposição mediante solicitação direta aos membros do grupo** (Ademar, Pedro ou Roberto)[cite: 20].

Caso deseje configurar o seu arquivo `.env` manualmente a partir do `.env.example`, você deverá preencher e ajustar os seguintes blocos de variáveis[cite: 20, 21]:

```env
# 1. Configurações de Debug e Banco de Dados (XAMPP)
APP_DEBUG=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=anjo_nexus
DB_USERNAME=root
DB_PASSWORD=

# 2. Gateway de Pagamento e Webhook (AbacatePay API v2)
ABACATEPAY_API_KEY=abc_dev_sua_chave_aqui
ABACATEPAY_WEBHOOK_SECRET=senha_webhook_definida_no_painel

# 3. Roteador Inteligente de IA (AI Router - Suporta múltiplas chaves separadas por vírgula para Round Robin)
GROQ_API_KEY=gsk_chave1,gsk_chave2
DEEPSEEK_API_KEY=sk_chave1,sk_chave2
GEMINI_API_KEY=AIzaSy_chave1

# 4. Servidor de E-mail SMTP (Gmail — Recuperação de Senha)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=465
MAIL_USERNAME=seu_email_mensageiro@gmail.com
MAIL_PASSWORD=sua_senha_de_app_de_16_letras_sem_espacos
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=seu_email_mensageiro@gmail.com
MAIL_FROM_NAME="Anjo Nexus"

# 5. Drivers de Sessão, Fila Assíncrona e Cache (Banco de Dados)
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

### 📧 Como Configurar um Gmail Mensageiro Manualmente (Recuperação de Senha)
Se você estiver montando o seu próprio `.env` do zero e quiser testar o disparo real de e-mails no módulo de **Esqueci Minha Senha**, o Google exige uma **Senha de App** (a sua senha normal de login do Gmail será bloqueada pelo protocolo SMTP):
1. Acesse a conta Google que servirá como mensageira em [myaccount.google.com](https://myaccount.google.com/).
2. No menu lateral **Segurança**, ative a **Verificação em duas etapas** (pré-requisito obrigatório do Google).
3. Na barra de pesquisa no topo da página da Conta Google, pesquise por **"Senhas de app"** (App Passwords).
4. Crie uma nova senha de aplicativo (ex: `Anjo Nexus SMTP`), copie o código gerado de **16 letras** e cole no campo `MAIL_PASSWORD` do seu `.env` **sem espaços**[cite: 21].
5. Preencha o endereço desse mesmo Gmail nos campos `MAIL_USERNAME` e `MAIL_FROM_ADDRESS`[cite: 21].

---

## 🏗️ Estrutura e Justificativas Tecnológicas

### Front-end (`/front` — Vanilla HTML/CSS/JS Modular)
Projetado sob princípios rígidos de *Clean Code* e separação de responsabilidades (HTML semântico puro, estilos isolados em `/estilos` e lógica modularizada com ES6 Modules em `/scripts`)[cite: 20].
1. **Zero Dependências Pesadas de Build:** Facilita a implementação e manutenção sem exigir compiladores complexos no lado do cliente[cite: 20].
2. **Fetch API Modular (`api.js` e `servicoAutenticacao.js`):** Centraliza a comunicação HTTP com o Laravel e injeta automaticamente o token Bearer do *Laravel Sanctum* nas rotas protegidas[cite: 20].

### Back-end (`/back` — Ecossistema Laravel 12)
Configurado com bibliotecas específicas para mineração de dados, segurança SaaS e processamento assíncrono[cite: 20]:
1. **Roach PHP (`roach-php/laravel`):** Biblioteca padrão-ouro no PHP para Web Scraping (inspirada no *Scrapy* do Python). Cria *Spiders* estruturados com pipelines para varrer portais governamentais[cite: 20].
2. **PdfParser (`smalot/pdfparser`):** Ferramenta nativa para extrair texto bruto de anexos de editais em PDF de forma rápida e sem depender de binários externos[cite: 20].
3. **Browsershot (`spatie/browsershot`) & Puppeteer:** Baseado no Google Chrome Headless. Contorna portais que exigem renderização de JavaScript antes da raspagem e auxilia na exportação de documentos[cite: 20].
4. **Laravel Sanctum:** Gerencia a autenticação por tokens de API para proteger as rotas do sistema[cite: 20].

---

## 🚀 Passo a Passo Completo: Como Iniciar o Projeto do Zero

Certifique-se de ter instalados: **PHP 8.2+**, **Composer**, **Node.js/NPM**, **XAMPP** e **ngrok**[cite: 20].

### Passo 1: Iniciar o XAMPP e Criar o Banco de Dados
1. Abra o painel de controle do **XAMPP** e inicie os módulos **Apache** e **MySQL**[cite: 20].
2. Acesse o phpMyAdmin no navegador: `http://localhost/phpmyadmin`[cite: 20].
3. Crie um novo banco de dados chamado exatamente **`anjo_nexus`**[cite: 20, 21].

### Passo 2: Instalar as Dependências do Back-end
Abra o terminal na raiz do projeto, navegue até a pasta `back` da versão atual e instale os pacotes do PHP e do Node (Puppeteer)[cite: 20]:

```bash
cd "MVPs/Versão Atual/back"
composer install
npm install puppeteer
```

### Passo 3: Configurar o `.env` e a Chave da Aplicação
1. Coloque o arquivo `.env` (fornecido pela equipe ou copiado do `.env.example`) dentro da pasta `back/`[cite: 20].
2. Gere a chave de criptografia do Laravel[cite: 20]:

```bash
php artisan key:generate
```

### Passo 4: Criar as Tabelas e Rodar os Seeders
Execute o comando abaixo para criar todas as tabelas do sistema e popular o banco com os planos iniciais de assinatura SaaS[cite: 20]:

```bash
php artisan migrate:fresh --seed
```

### Passo 5: Iniciar o Servidor Laravel e a Fila de IA
Você precisará manter **dois terminais abertos** na pasta `back/`[cite: 20]:

- **Terminal 1 — Servidor da API:**
  ```bash
  php artisan serve
  ```
  *(A API responderá exclusivamente em `[http://127.0.0.1:8000](http://127.0.0.1:8000)`)*[cite: 20].

- **Terminal 2 — Queue Worker (Fila de IA em Segundo Plano):**
  ```bash
  php artisan queue:work
  ```
  *(Importante: Sempre que alterar o `.env`, reinicie a fila com `php artisan queue:restart` e rode `queue:work` novamente)*[cite: 20].

### Passo 6: Executar os Web Scrapers (Captura de Editais)
Em um terminal na pasta `back/`, dispare o robô desejado para alimentar o banco de dados com editais reais[cite: 20]:

```bash
# Extrator FAPESC
php artisan spider:fapesc

# Extrator FAPESP
php artisan spider:fapesp

# Extrator FINEP
php artisan roach:run FinepSpider
```

### Passo 7: Iniciar o ngrok (Recebimento de Webhooks do AbacatePay)
Para que o sistema receba a confirmação automática de pagamento do PIX no ambiente local[cite: 20]:
1. Baixe e instale o **ngrok** em sua máquina[cite: 20].
2. Autentique o seu ngrok utilizando o token compartilhado da equipe (necessário apenas na primeira vez)[cite: 20]:
   ```bash
   ngrok config add-authtoken SEU_TOKEN_DO_NGROK_AQUI
   ```
3. Em um **novo terminal**, inicie o túnel apontando para o domínio estático padronizado do projeto na porta 8000[cite: 20]:
   ```bash
   ngrok http --domain=esophagus-swifter-cultivate.ngrok-free.dev 8000
   ```
   *(Nota: Apenas um desenvolvedor da equipe deve manter o túnel ativo por vez durante os testes de pagamento)*[cite: 20].

### Passo 8: Iniciar o Front-end (Live Server)
Como o Front-end utiliza módulos nativos do JavaScript (`type="module"`), ele deve ser executado através de um servidor HTTP local[cite: 20]:
1. Abra o projeto no **VS Code**[cite: 20].
2. Certifique-se de ter a extensão **Live Server** instalada[cite: 20].
3. Navegue até `front/paginas/cadastro.html` (ou `front/paginas/login.html`), clique com o botão direito sobre o arquivo e selecione **"Open with Live Server"**[cite: 20].

---

### ⚠️ ATENÇÃO: Configuração do VS Code e Live Server (Prevenção de Loop de Reload)

Para que o Front-end e o Back-end rodem juntos em harmonia durante o desenvolvimento local (sem que o Live Server fique recarregando a página sozinho quando o Laravel escreve arquivos de log no Back-end), **você DEVE abrir a pasta raiz correta no VS Code**[cite: 20].

**O jeito certo de abrir o projeto no VS Code:**
1. No menu superior, clique em **File (Arquivo) > Open Folder (Abrir Pasta)**[cite: 20].
2. Selecione *exatamente* a pasta **`PROJETO-ESCOLA-TI-`** (aquela que contém a pasta oculta `.vscode` dentro dela)[cite: 20].
3. **Por que isso é necessário?** O projeto possui um arquivo `.vscode/settings.json` que instrui o Live Server a ignorar as alterações na pasta `/back`. O VS Code só consegue aplicar essas regras e evitar que a sua página recarregue no meio do pagamento/cadastro se a pasta `.vscode` estiver posicionada no nível mais alto da sua janela ("raiz"). Se você abrir uma pasta acima ou abaixo disso, o Live Server entrará em um loop de reload infinito durante as requisições API[cite: 20].