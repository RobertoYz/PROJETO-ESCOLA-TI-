# Projeto Anjo Nexus - Escola de TI

Bem-vindo ao repositório do **Anjo Nexus**, uma plataforma de radar de editais de fomento à inovação. 

Este repositório contém documentações, artefatos de engenharia de software e os códigos-fonte da nossa Fase 1.

## 🚀 Como Configurar o Ambiente (Backend)

Nosso backend foi construído em **Laravel 12** e possui um módulo de Web Scraping poderoso (Roach PHP + Puppeteer) para varrer editais. Siga os passos abaixo para rodar o projeto na sua máquina:

### 1. Pré-requisitos
Certifique-se de ter instalado em sua máquina:
- **PHP 8.2+**
- **Composer**
- **Node.js e NPM** (Obrigatório para o motor do Puppeteer)
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

3. **Instale as dependências do PHP (Laravel, Roach, etc):**
   ```bash
   composer install
   ```

4. **Instale o Puppeteer localmente (Essencial para o Spider funcionar):**
   ```bash
   npm install puppeteer
   ```

5. **Configure o Arquivo de Ambiente:**
   Copie o arquivo de exemplo para criar o seu próprio `.env`:
   ```bash
   # No Windows (CMD/Powershell)
   copy .env.example .env
   
   # No Linux/Mac
   cp .env.example .env
   ```

6. **Gere a Chave de Criptografia do Laravel:**
   ```bash
   php artisan key:generate
   ```

7. **Configure o Banco de Dados:**
   Abra o seu arquivo `.env` recém-criado e ajuste as credenciais do seu banco de dados local (ex: `DB_DATABASE=EscolaTI_AnjoNexus`).
   Depois, crie as tabelas rodando:
   ```bash
   php artisan migrate
   ```

### 3. Rodando o Motor de Captura (Spider)

Para iniciar o nosso robô que varre o portal da FINEP em busca de editais e salva no banco de dados, execute:

```bash
php artisan roach:run FinepSpider
```

---
*Para mais detalhes sobre as decisões técnicas da captura de editais via API Liferay, leia nosso [Documento de Arquitetura do Spider](docs/ARCHITECTURE_EDTAN-72.md).*
