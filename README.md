# Projeto Anjo Nexus - Escola de TI

Bem-vindo ao repositório do **Anjo Nexus**, uma plataforma de radar de editais de fomento à inovação. 

Este repositório contém documentações, artefatos de engenharia de software e os códigos-fonte da nossa Fase 1.

## 🚀 Como Configurar o Ambiente (Backend)

Nosso backend foi construído em **Laravel 12** e possui um módulo de Web Scraping poderoso para varrer editais e um **AI Router** integrado para análise semântica. Siga os passos abaixo para rodar o projeto na sua máquina:

### 1. Pré-requisitos
Certifique-se de ter instalado em sua máquina:
- **PHP 8.2+**
- **Composer**
- **Node.js e NPM** (Obrigatório para o motor do Puppeteer em alguns spiders)
- **MySQL ou SQLite** (Para o banco de dados)

### 2. Passo a Passo da Instalação

1. **Clone o repositório:**
   ```bash
   git clone https://github.com/RobertoYz/PROJETO-ESCOLA-TI-.git
   cd PROJETO-ESCOLA-TI-
   ```

2. **Navegue até a pasta do Backend da Fase 1:**
   ```bash
   cd "MVPs/Projeto Anjo Nexus/Fase_1/back"
   ```

3. **Instale as dependências do PHP:**
   Isso instalará o Laravel, o Roach PHP (para FINEP) e o `smalot/pdfparser` (usado para extrair texto de PDFs de editais sem depender de softwares externos).
   ```bash
   composer install
   ```

4. **Instale o Puppeteer localmente:**
   Essencial para executar spiders que requerem renderização de JavaScript (ex: portal da FINEP).
   ```bash
   npm install puppeteer
   ```

5. **Configure o Arquivo de Ambiente e as Chaves de IA:**
   Copie o arquivo de exemplo para criar o seu próprio `.env`:
   ```bash
   # No Windows (CMD/Powershell)
   copy .env.example .env
   
   # No Linux/Mac
   cp .env.example .env
   ```

   **Importante (AI Router):** O nosso sistema possui roteamento inteligente de IA (Groq -> DeepSeek -> Gemini). Para evitar limites de taxa (*Rate Limit*), você pode adicionar **várias chaves separadas por vírgula** no seu `.env`. O sistema fará um sorteio (*Round Robin*) a cada edital analisado:
   ```env
   GROQ_API_KEY=gsk_chave1,gsk_chave2
   DEEPSEEK_API_KEY=sk_chave1,sk_chave2
   GEMINI_API_KEY=AIzaSy_chave1
   ```

6. **Gere a Chave de Criptografia do Laravel:**
   ```bash
   php artisan key:generate
   ```

7. **Configure o Banco de Dados:**
   Abra o seu arquivo `.env` e ajuste as credenciais do seu banco de dados local. Depois, crie as tabelas:
   ```bash
   php artisan migrate
   ```

### 3. Executando a Aplicação

A arquitetura atual processa a raspagem de dados em tempo real, mas transfere a comunicação pesada com as Inteligências Artificiais para uma fila em segundo plano (*Background Jobs*). Você precisará de **dois terminais** abertos:

**Terminal 1: Rodar o Queue Worker (Fila de IA)**
Esse processo fica escutando a fila e analisando os PDFs conforme são baixados, utilizando as chaves de API sorteadas no seu `.env`.
```bash
php artisan queue:work
```
*(Nota: Sempre que alterar as chaves no `.env` ou alterar o código, reinicie o worker rodando `php artisan queue:restart` e depois `queue:work` novamente).*

**Terminal 2: Disparar os Spiders (Robôs de Captura)**
No outro terminal, você pode rodar os nossos robôs extratores de acordo com a fundação que deseja analisar:

- **FAPESC (Extração via Regex e PDF Parser):**
  ```bash
  php artisan spider:fapesc
  ```

- **FAPESP:**
  ```bash
  php artisan spider:fapesp
  ```

- **FINEP (via Roach e API Liferay):**
  ```bash
  php artisan roach:run FinepSpider
  ```

O Frontend (na pasta `front/`) está desacoplado. Basta abrir o arquivo `index.html` ou utilizar uma extensão como o *Live Server* do VSCode para consumir a API RESTful do backend.

---
*Para mais detalhes sobre as decisões técnicas, consulte os [Documentos de Arquitetura e ADRs](Documentação/docs/ADR/).*
