# Radar de Editais - Anjo Nexus

Este repositório contém a aplicação "Radar de Editais", dividida fisicamente em uma arquitetura **Monorepo**, separando claramente as responsabilidades entre Front-end e Back-end.

## 🏗️ Estrutura do Projeto

O projeto foi organizado em duas pastas principais para garantir independência tecnológica:

- `/back` - Contém a API REST Stateless desenvolvida em Laravel 11. Responsável por todo o processamento de regras de negócio, acesso a banco de dados e execução de jobs assíncronos pesados (Scraping, OCR, IA).
- `/front` - Contém a interface com o usuário (Client-Side). Projetada para ser extremamente leve e "seca" (Vanilla HTML/CSS/JS), consumindo o back-end estritamente via requisições HTTP (API REST).

---

## 🛠️ Recomendações e Justificativas de Tecnologias

### Front-end (Vanilla HTML/CSS/JS)
O objetivo do front-end é ser de fácil implementação, rápido e com o menor setup possível, garantindo estabilidade a longo prazo.

1. **Design / Estilização:** 
   - **Recomendação:** `Tailwind CSS (via CDN)`. 
   - **Por quê?** O Tailwind permite estilizar páginas rapidamente direto no HTML sem precisar escrever dezenas de arquivos CSS ou configurar Node.js. Como o projeto exige um visual moderno, ele é mais versátil que o Bootstrap para construir Dashboards e painéis Kanban "bonitos" e responsivos rapidamente.
2. **Requisições HTTP:** 
   - **Recomendação:** `Fetch API nativa` ou `Axios` (via CDN).
   - **Por quê?** O Fetch é nativo do navegador e elimina dependências. O Axios facilita o tratamento de erros e interceptores (ex: colocar token JWT em todas as chamadas automaticamente). Ambos atendem perfeitamente.

### Back-end (Bibliotecas do Laravel)
O diretório `/back` já está configurado com bibliotecas vitais para a mineração de dados, devidamente justificadas para a banca do TCC:

1. **Roach PHP (`roach-php/laravel`)**
   - **Justificativa:** É a biblioteca padrão-ouro no ecossistema PHP para Web Scraping, inspirada no famoso *Scrapy* do Python. Diferente de usar funções soltas, o Roach cria `Spiders` estruturados com pipelines, suportando requisições concorrentes, proxies e tratamento automático de falhas. Perfeito para escalar a varredura de portais de governo.
2. **PdfParser (`smalot/pdfparser`)**
   - **Justificativa:** Ferramenta leve e nativa em PHP para extrair texto bruto de arquivos PDF estruturados. Ideal para uma leitura inicial (OCR básico) dos anexos de editais em formato digital nativo, extraindo termos importantes de forma ultra-rápida.
3. **Browsershot (`spatie/browsershot`)**
   - **Justificativa:** Baseado no Puppeteer (Google Chrome Headless). Necessário para contornar bloqueios de portais que renderizam o conteúdo em JavaScript (como SPA React/Vue) antes de raspar, e também essencial para gerar a exportação dos documentos finais (a Minuta/Proposta Kanban) convertendo o HTML do nosso sistema para um PDF altamente formatado.

---

## 🚀 Como Iniciar o Projeto Localmente (Back-end)

Siga o passo a passo exato abaixo para configurar o banco de dados MySQL via XAMPP e rodar a API em ambiente de desenvolvimento.

### Passo 1: Instalar Dependências
Abra o terminal, acesse a pasta `/back` e instale os pacotes necessários:
```bash
cd back
composer install
```

### Passo 2: Configurar o Arquivo de Ambiente (.env)
O repositório não sobe o arquivo de ambiente por segurança. É necessário criá-lo e configurá-lo:
1. Na pasta `back`, faça uma cópia do arquivo `.env.example` e renomeie essa cópia para `.env`.
2. No terminal, gere a chave de criptografia da aplicação executando:
   ```bash
   php artisan key:generate
   ```
3. Abra o arquivo `.env` no seu editor de código e ative a exibição de erros alterando:
   ```env
   APP_DEBUG=true
   ```
4. No mesmo arquivo `.env`, configure as variáveis de banco de dados para usar o MySQL do XAMPP com as credenciais padrão:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=anjo_nexus
   DB_USERNAME=root
   DB_PASSWORD=
   ```

### Passo 3: Criar o Banco de Dados
1. Certifique-se de que os módulos **Apache** e **MySQL** estejam iniciados no painel de controle do XAMPP.
2. Abra o navegador e acesse o phpMyAdmin (`http://localhost/phpmyadmin`).
3. Crie um novo banco de dados com o nome exato que foi configurado no `.env`: **`anjo_nexus`**.

### Passo 4: Construir as Tabelas
Volte ao terminal, certifique-se de estar na pasta `/back` e execute as migrações para gerar as tabelas no banco de dados recém-criado:
```bash
php artisan migrate
```

### Passo 5: Iniciar o Servidor
Com tudo pronto, inicie o servidor embutido do Laravel:
```bash
php artisan serve
```
**Observação Importante sobre Acesso:** Para acessar a aplicação no navegador, utilize estritamente a URL e porta fornecidas pelo terminal (ex: `http://127.0.0.1:8000`). Digitar a URL concatenada ao localhost (como `localhost/127.0.0.1:8000`) causará um erro *403 Forbidden* de permissão de pasta no Apache.
