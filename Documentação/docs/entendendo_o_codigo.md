# Entendendo o Código do Projeto (Anjo Nexus - Fase 1)

Este documento serve como um guia para novos desenvolvedores ou avaliadores entenderem como o código está estruturado.

## 1. A Arquitetura (Back e Front Separados)
Seguindo a premissa de um sistema escalável, o projeto foi dividido em duas partes totalmente independentes:
- **Backend (Laravel 12):** Funciona estritamente como uma API RESTful e um processador de tarefas em background (Workers).
- **Frontend (Vanilla JS):** Um site puramente estático (HTML, CSS e JS) que consome os dados da API. Não há renderização no servidor (Blade), garantindo que o Front-end possa ser hospedado em qualquer CDN (como Vercel, Netlify ou S3) a custo zero.

## 2. O Fluxo de Dados (Pipeline)

O ciclo de vida de um edital no sistema funciona da seguinte forma:

1. **A Captura (FinepSpider)**
   - O comando `php artisan roach:run FinepSpider` é executado.
   - Ele consome a API pública da Finep para descobrir novos editais.
   - Para cada edital, ele cria um registro no banco de dados e dispara um Job chamado `ScrapeEditalCompletoJob`.

2. **O Deep Scrape (ScrapeEditalCompletoJob)**
   - Este Job roda em background (Fila).
   - Ele acessa a URL original do edital, faz o download do HTML completo da página e extrai apenas o texto útil (removendo scripts, menus, etc).
   - O texto gigante extraído é salvo na coluna `conteudo_completo` do banco de dados.
   - Em seguida, ele dispara o próximo Job da esteira: `AnalyzeEditalWithIA`.

3. **A Inteligência Artificial (AnalyzeEditalWithIA e DeepSeekService)**
   - Este Job pega o `conteudo_completo` recém-capturado e envia para a IA através do nosso serviço `DeepSeekService`.
   - O serviço possui um sistema de **Fallback (Cascata)**:
     - Tenta enviar para a API do **DeepSeek**.
     - Se o DeepSeek falhar (ex: por falta de saldo), o sistema intercepta o erro e tenta enviar para o **Google Gemini** (v2.5 Flash).
   - A IA foi instruída rigorosamente (Zero Alucinação) a ler o texto e cuspir um objeto JSON contendo TRL, Faturamento exigido, Nicho, Match Score e os Diagnósticos.
   - O JSON retornado é salvo diretamente no Banco de Dados (MySQL) nas colunas `ai_nicho`, `ai_trl`, `ai_match`, etc.

4. **A Exibição (Front-end e EditalController)**
   - Quando você abre o `index.html`, o JavaScript faz um `fetch()` para a rota `/api/editais`.
   - O `EditalController` no Laravel pega os editais no banco e os devolve formatados (ex: data "d/m/Y às H:i").
   - O Front-end pega esses dados já processados (incluindo a análise da IA que já estava salva no banco) e pinta na tela. **A IA nunca é chamada pelo Front-end.**

## 3. Principais Arquivos e Pastas

### Backend (Laravel)
- `routes/api.php`: Define a rota `/editais` que o Front-end acessa.
- `app/Http/Controllers/EditalController.php`: Prepara os dados do banco e os envia como JSON para o Front-end.
- `app/Spiders/FinepSpider.php`: O robô inicial que busca a lista de editais.
- `app/Jobs/ScrapeEditalCompletoJob.php`: O trabalhador que raspa a página do edital.
- `app/Jobs/AnalyzeEditalWithIA.php`: O trabalhador que coordena a IA.
- `app/Services/DeepSeekService.php`: O maestro que conversa com o DeepSeek e com o Google Gemini.

### Frontend
- `front/index.html`: A estrutura do site.
- `front/css/style.css`: O design premium (Glassmorphism, cores, etc).
- `front/js/app.js`: A lógica de consumo da API (fetch) e preenchimento dos cards e detalhes.

## 4. Por que usar Filas (Queues)?
Se fizéssemos o robô capturar a página, mandar pra IA e só depois responder, o processo demoraria mais de 30 segundos por edital e o servidor "travaria". Com as filas (`queue:work`), tudo isso roda em background. O Front-end só consome os dados depois que o trabalho pesado já foi feito e gravado permanentemente no banco.
