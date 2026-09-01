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

## 🚀 Como Iniciar o Projeto Localmente

Siga o passo a passo abaixo para rodar o projeto em ambiente de desenvolvimento.

### 1. Inicializando o Backend (Laravel)
Abra um terminal, acesse a pasta `/back` e inicie o servidor embutido do Laravel:
```bash
cd back

# Instale as dependências (se ainda não estiverem instaladas)
composer install

# Inicie o servidor REST API (por padrão na porta 8000)
php artisan serve
```
A API estará escutando em `http://localhost:8000`.

### 2. Inicializando o Frontend (HTML)
Como o frontend é puro (HTML/JS/CSS secos), ele não precisa de compilação ou processos "build". 
Porém, para evitar erros de **CORS** (Cross-Origin Resource Sharing) no navegador ao fazer chamadas de API, **não** abra o arquivo clicando nele diretamente (`file:///...`). Você precisa rodar através de um servidor local.

**Opções rápidas:**
- **VS Code:** Instale a extensão "Live Server". Clique com o botão direito no arquivo `front/index.html` e escolha *Open with Live Server*.
- **Via PHP:** Se você tiver o PHP instalado globalmente, abra outro terminal e use:
  ```bash
  cd front
  php -S localhost:5500
  ```
- **Via Node (npx):** Se tiver NPM na máquina:
  ```bash
  cd front
  npx serve .
  ```

O Frontend abrirá no navegador (geralmente em `http://localhost:5500` ou similar), pronto para conectar com a API do Backend.
