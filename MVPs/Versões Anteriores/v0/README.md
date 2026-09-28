# Projeto Anjo Nexus - Fase 1 🚀

Este repositório contém a Fase 1 do MVP do **Projeto Anjo Nexus**, uma plataforma inteligente de mapeamento e análise de editais (focada inicialmente na FINEP) utilizando extração de dados e Inteligência Artificial.

## 🏗️ Arquitetura do Sistema

A arquitetura foi desenhada para ser limpa, escalável e de fácil manutenção, respeitando as seguintes decisões documentadas (ADRs):

### 1. Separação Total (API Stateless)
- **Backend:** Desenvolvido em **Laravel 12**, operando exclusivamente como uma API RESTful sem estado.
- **Frontend:** Construído com Vanilla JS moderno (ES6+), HTML e CSS. As pastas `back` e `front` são totalmente separadas, permitindo deploy independente e facilitando a futura criação de aplicativos mobile.

### 2. Uso de Filas (Jobs) para IA
A extração de informações complexas (como Nicho e Faturamento) é feita utilizando a API do **DeepSeek**. 
Para evitar travamentos e lentidão na experiência do usuário, toda chamada de IA é processada em segundo plano através das Queues do Laravel (`php artisan queue:work`). O frontend atualiza a interface assincronamente assim que o dado fica pronto.

### 3. Extração Inteligente (Crawler Híbrido)
Em vez de depender de _HTML scraping_ frágil, o nosso robô (`FinepSpider`) injeta um script JavaScript via um navegador invisível (utilizando a biblioteca **Browsershot**). Isso permite consultar a API interna oculta da FINEP com os devidos tokens validados pelo Chrome, extraindo os dados paginados e limpos em formato JSON, sem sofrer bloqueios (Anti-bot).

---

## 🛠️ Como rodar o projeto localmente

### Pré-requisitos
- PHP 8.2+
- Composer
- Node.js (necessário para o Browsershot rodar o Chrome Headless)
- Servidor de Banco de Dados (MySQL / SQLite)

### Passos para rodar
1. Entre na pasta do backend: `cd back`
2. Instale as dependências: `composer install`
3. Configure o arquivo `.env` com a sua chave do DeepSeek:
   ```env
   DEEPSEEK_API_KEY=sua_chave_aqui
   QUEUE_CONNECTION=database
   ```
4. Rode as migrations para preparar o banco e as tabelas de Jobs: `php artisan migrate`
5. Em um terminal, inicie o servidor: `php artisan serve`
6. Em **outro terminal**, inicie o trabalhador da fila (para a IA funcionar): `php artisan queue:work`
7. Para rodar a extração dos editais: `php artisan roach:run FinepSpider`
8. Abra o arquivo `front/index.html` no seu navegador ou via Live Server para visualizar o painel.
