# Projeto Anjo Nexus - Escola de TI

Bem-vindo ao repositório do **Anjo Nexus**, uma plataforma de radar de editais de fomento à inovação com gestão de agências, planos e pagamentos via AbacatePay.

Este repositório contém documentações, artefatos de engenharia de software e os códigos-fonte do projeto.

## 🚀 Como Configurar o Ambiente (Backend)

Nosso backend foi construído em **Laravel 12** com arquitetura em Monorepo, separando a API (`/back`) e o Frontend (`/front`). Siga os passos abaixo para rodar o projeto localmente do zero:

### 1. Pré-requisitos

Certifique-se de ter instalado em sua máquina:

- **PHP 8.2+**
- **Composer**
- **Node.js e NPM** (obrigatório para o motor do Puppeteer em alguns spiders)
- **MySQL** (XAMPP ou ambiente local)

### 2. Passo a Passo da Instalação

#### 2.1. Clone o repositório

```bash
git clone https://github.com/RobertoYz/PROJETO-ESCOLA-TI-.git
cd PROJETO-ESCOLA-TI-
```

#### 2.2. Navegue até a pasta do Backend

```bash
cd back
```

#### 2.3. Instale as dependências do PHP

Isso instalará o Laravel, o Laravel Sanctum, o Roach PHP (para FINEP) e o smalot/pdfparser.

```bash
composer install
```

#### 2.4. Instale o Puppeteer localmente

Essencial para executar spiders que requerem renderização de JavaScript (ex: portal da FINEP).

```bash
npm install puppeteer
```

#### 2.5. Configure o Arquivo de Ambiente (`.env`)

Copie o arquivo de exemplo para criar o seu próprio `.env`:

```bash
# No Windows (CMD/Powershell)
copy .env.example .env

# No Linux/Mac
cp .env.example .env
```

#### 2.6. Chaves de API e Gateway de Pagamento

Certifique-se de configurar a chave do AbacatePay para habilitar o fluxo de registro e os tokens de IA (Groq, DeepSeek, Gemini):

```env
ABACATEPAY_API_KEY=abc_dev_sua_chave_aqui

GROQ_API_KEY=gsk_chave1,gsk_chave2
DEEPSEEK_API_KEY=sk_chave1,sk_chave2
GEMINI_API_KEY=AIzaSy_chave1
```

#### 2.7. Gere a Chave de Criptografia do Laravel

```bash
php artisan key:generate
```

#### 2.8. Configure o Banco de Dados e os Seeders

Abra o seu arquivo `.env`, ajuste as credenciais do seu banco MySQL (ex: `DB_DATABASE=anjo_nexus`) e execute o comando que limpa, recria as tabelas, publica as rotas do Sanctum e popula os planos iniciais do SaaS:

```bash
php artisan migrate:fresh --seed
```

### 3. Executando a Aplicação

Para o pleno funcionamento do sistema (cadastro, pagamentos, filas de IA e robôs de captura), você precisará de terminais separados:

#### Terminal 1: Servidor da API (Laravel)

```bash
php artisan serve
```

A API estará rodando em `http://127.0.0.1:8000`. Você pode testar o endpoint de registro de agências via `POST` em `http://127.0.0.1:8000/api/auth/registro`.

#### Terminal 2: Queue Worker (Fila de IA e Processamento)

Esse processo fica escutando a fila e analisando os documentos em segundo plano.

```bash
php artisan queue:work
```

#### Terminal 3: Disparar os Spiders (Robôs de Captura - Opcional)

Caso queira rodar os extratores de editais:

**FAPESC** (Extração via Regex e PDF Parser):

```bash
php artisan spider:fapesc
```

**FAPESP**:

```bash
php artisan spider:fapesp
```

**FINEP** (via Roach e API Liferay):

```bash
php artisan roach:run FinepSpider
```

## Frontend

O Frontend (na pasta `/front`) está desacoplado e consome esta API RESTful.

Para mais detalhes sobre as decisões técnicas, consulte a documentação do projeto.