# 🚀 Projeto Anjo Nexus — Escola de TI

Bem-vindo ao repositório oficial do **Anjo Nexus**, uma plataforma SaaS inteligente de radar de editais de fomento à inovação e gestão de captação de recursos para consultorias e startups.

Este repositório armazena as documentações, artefatos de Engenharia de Software (ADRs, diagramas, pesquisas) e o código-fonte em arquitetura Monorepo do projeto.

---

## 👥 Autores (Equipe de Desenvolvimento)

Projeto desenvolvido pelos acadêmicos de Engenharia de Software:

* **Ademar de Araújo Teisen** — RA: `23182969-2`
* **Pedro Emanuel Ferreira de Andrade** — RA: `23167567-2`
* **Roberto Yanez Sanz** — RA: `23079491-2`

---

## 💡 Sobre o Projeto

O **Anjo Nexus** nasceu para resolver a fragmentação e a complexidade no processo de busca e submissão de projetos para editais de subvenção econômica e fomento à pesquisa no Brasil (como FINEP, FAPESC, FAPESP, entre outros). 

A plataforma atua como um ecossistema completo para **Agências e Consultorias de Inovação**, automatizando desde a descoberta de oportunidades em portais governamentais até a gestão do portfólio de startups clientes e o acompanhamento das propostas.

### 🎯 Funcionalidades Atuais e Visão de Futuro (Roadmap)

* **Radar Automatizado de Editais (Web Scraping & OCR):** Robôs extratores (*Spiders*) que varrem portais de fomento, baixam os anexos e extraem o texto bruto dos PDFs automaticamente.
* **Roteador Inteligente de IA (AI Router):** Processamento em fila (*Background Jobs*) que analisa semanticamente os editais utilizando múltiplos provedores de LLM (**Groq**, **DeepSeek** e **Google Gemini**) em esquema de rodízio (*Round Robin*) para evitar limites de requisição.
* **Gestão Multi-tenant de Agências e Startups:** Ambiente seguro onde cada consultoria gerencia sua carteira de startups (com dados integrados à estrutura da Receita Federal/CNPJ, porte, regime tributário, nicho de atuação e *pitch*).
* **Ecossistema SaaS e Pagamentos Reais (AbacatePay):** Fluxo automatizado de assinatura via PIX com controle de acesso por middleware e liberação instantânea da conta via **Webhooks** em tempo real.
* **Pipeline Kanban de Captação:** Quadro visual interativo para gerenciar o funil de submissão de cada edital.
* **Match Inteligente de Oportunidades *(Em evolução)*:** Cruzamento automatizado entre os requisitos técnicos/financeiros dos editais abertos e o perfil das startups cadastradas na agência.
* **Geração Assistida de Minutas com IA *(Em evolução)*:** Redação automática de rascunhos de projetos e propostas técnicas cruzando o *pitch* da startup com as exigências específicas do edital selecionado.

---

## 🛠️ Tecnologias Utilizadas

### Back-end (`/back`)
* **PHP 8.2+** & **Laravel 12** (API RESTful Stateless)
* **Laravel Sanctum** (Autenticação segura via Bearer Token)
* **Roach PHP** & **Puppeteer / Browsershot** (Web Scraping avançado e renderização Headless)
* **Smalot PDF Parser** (Extração nativa de texto de editais em PDF)
* **AbacatePay API v2** (Gateway de pagamentos PIX e Webhooks)
* **AI Router** (Integração com APIs da Groq, DeepSeek e Gemini)

### Front-end (`/front`)
* **HTML5 Semântico, CSS3 e JavaScript Puro (ES6+ Modules)**
* Arquitetura modular limpa (*Strict Separation of Concerns*), consumindo a API de forma desacoplada via `fetch`.

### Banco de Dados e Infraestrutura
* **MySQL / MariaDB** (Gerenciado localmente via XAMPP)
* **ngrok** (Tunelamento com domínio estático para recebimento de Webhooks locais)

---

## 📥 Como Clonar o Repositório

Para baixar o projeto na sua máquina local, abra o terminal e execute:

~~~bash
git clone https://github.com/RobertoYz/PROJETO-ESCOLA-TI-.git
cd PROJETO-ESCOLA-TI-
~~~

### ▶️ Como Iniciar o Sistema?
Após clonar o repositório, navegue até a pasta **`MVPs/`**. 

👉 **O arquivo `README.md` localizado dentro da pasta [`MVPs/`](./MVPs/README.md) contém o guia completo, passo a passo, com todos os comandos necessários para configurar o banco de dados, as chaves de ambiente e iniciar o servidor.**

---