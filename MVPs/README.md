# 🛠️ Guia Completo de Configuração e Execução — Anjo Nexus

Este diretório contém a aplicação do **Anjo Nexus**, organizada fisicamente em uma arquitetura **Monorepo** que separa claramente as responsabilidades entre Back-end (`/back`) e Front-end (`/front`).

---

## ⚠️ AVISO IMPORTANTE: Arquivo `.env` e Chaves de API

Por motivos estritos de segurança da informação, **o arquivo `.env` original não é enviado ao GitHub**, pois contém chaves sensíveis de API (múltiplos tokens de Inteligência Artificial e chaves privadas do Gateway de Pagamento).

> 💡 **Para Avaliadores e Colegas de Equipe:** O arquivo `.env` original, completo e já configurado para rodar o sistema de imediato **está à disposição mediante solicitação direta aos membros do grupo** (Ademar, Pedro ou Roberto).

Caso deseje configurar o seu arquivo `.env` manualmente a partir do `.env.example`, você deverá preencher os seguintes blocos de variáveis:

~~~env
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
~~~

---

## 🏗️ Estrutura e Justificativas Tecnológicas

### Front-end (`/front` — Vanilla HTML/CSS/JS Modular)
Projetado sob princípios rígidos de *Clean Code* e separação de responsabilidades (HTML semântico puro, estilos isolados em `/estilos` e lógica modularizada com ES6 Modules em `/scripts`).
1. **Zero Dependências Pesadas de Build:** Facilita a implementação e manutenção sem exigir compiladores complexos no lado do cliente.
2. **Fetch API Modular (`api.js` e `servicoAutenticacao.js`):** Centraliza a comunicação HTTP com o Laravel e injeta automaticamente o token Bearer do *Laravel Sanctum* nas rotas protegidas.

### Back-end (`/back` — Ecossistema Laravel 12)
Configurado com bibliotecas específicas para mineração de dados, segurança SaaS e processamento assíncrono:
1. **Roach PHP (`roach-php/laravel`):** Biblioteca padrão-ouro no PHP para Web Scraping (inspirada no *Scrapy* do Python). Cria *Spiders* estruturados com pipelines para varrer portais governamentais.
2. **PdfParser (`smalot/pdfparser`):** Ferramenta nativa para extrair texto bruto de anexos de editais em PDF de forma rápida e sem depender de binários externos.
3. **Browsershot (`spatie/browsershot`) & Puppeteer:** Baseado no Google Chrome Headless. Contorna portais que exigem renderização de JavaScript antes da raspagem e auxilia na exportação de documentos.
4. **Laravel Sanctum:** Gerencia a autenticação por tokens de API para proteger as rotas do sistema.

---

## 🚀 Passo a Passo Completo: Como Iniciar o Projeto do Zero

Certifique-se de ter instalados: **PHP 8.2+**, **Composer**, **Node.js/NPM**, **XAMPP** e **ngrok**.

### Passo 1: Iniciar o XAMPP e Criar o Banco de Dados
1. Abra o painel de controle do **XAMPP** e inicie os módulos **Apache** e **MySQL**.
2. Acesse o phpMyAdmin no navegador: `http://localhost/phpmyadmin`.
3. Crie um novo banco de dados chamado exatamente **`anjo_nexus`**.

### Passo 2: Instalar as Dependências do Back-end
Abra o terminal na raiz do projeto, navegue até a pasta `back` da versão atual e instale os pacotes do PHP e do Node (Puppeteer):

~~~bash
cd "MVPs/Versão Atual/back"
composer install
npm install puppeteer
~~~

### Passo 3: Configurar o `.env` e a Chave da Aplicação
1. Coloque o arquivo `.env` (fornecido pela equipe ou copiado do `.env.example`) dentro da pasta `back/`.
2. Gere a chave de criptografia do Laravel:

~~~bash
php artisan key:generate
~~~

### Passo 4: Criar as Tabelas e Rodar os Seeders
Execute o comando abaixo para criar todas as tabelas do sistema e popular o banco com os planos iniciais de assinatura SaaS:

~~~bash
php artisan migrate:fresh --seed
~~~

### Passo 5: Iniciar o Servidor Laravel e a Fila de IA
Você precisará manter **dois terminais abertos** na pasta `back/`:

* **Terminal 1 — Servidor da API:**
  ~~~bash
  php artisan serve
  ~~~
  *(A API responderá exclusivamente em `http://127.0.0.1:8000`).*

* **Terminal 2 — Queue Worker (Fila de IA em Segundo Plano):**
  ~~~bash
  php artisan queue:work
  ~~~
  *(Importante: Sempre que alterar o `.env`, reinicie a fila com `php artisan queue:restart` e rode `queue:work` novamente).*

### Passo 6: Executar os Web Scrapers (Captura de Editais)
Em um terminal na pasta `back/`, dispare o robô desejado para alimentar o banco de dados com editais reais:

~~~bash
# Extrator FAPESC
php artisan spider:fapesc

# Extrator FAPESP
php artisan spider:fapesp

# Extrator FINEP
php artisan roach:run FinepSpider
~~~

### Passo 7: Iniciar o ngrok (Recebimento de Webhooks do AbacatePay)
Para que o sistema receba a confirmação automática de pagamento do PIX no ambiente local:
1. Baixe e instale o **ngrok** em sua máquina.
2. Autentique o seu ngrok utilizando o token compartilhado da equipe (necessário apenas na primeira vez):
   ~~~bash
   ngrok config add-authtoken SEU_TOKEN_DO_NGROK_AQUI
   ~~~
3. Em um **novo terminal**, inicie o túnel apontando para o domínio estático padronizado do projeto na porta 8000:
   ~~~bash
   ngrok http --domain=esophagus-swifter-cultivate.ngrok-free.dev 8000
   ~~~
   *(Nota: Apenas um desenvolvedor da equipe deve manter o túnel ativo por vez durante os testes de pagamento).*

### Passo 8: Iniciar o Front-end (Live Server)
Como o Front-end utiliza módulos nativos do JavaScript (`type="module"`), ele deve ser executado através de um servidor HTTP local:
1. Abra o projeto no **VS Code**.
2. Certifique-se de ter a extensão **Live Server** instalada.
3. Navegue até `front/paginas/cadastro.html` (ou `front/paginas/login.html`), clique com o botão direito sobre o arquivo e selecione **"Open with Live Server"**.