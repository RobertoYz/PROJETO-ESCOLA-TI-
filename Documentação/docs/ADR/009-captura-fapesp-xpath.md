# ADR 009: Captura de Editais da FAPESP via Goutte e Seletores CSS/XPath

## Status
Aceito

## Contexto
O portal da FAPESP (Fundação de Amparo à Pesquisa do Estado de São Paulo), diferentemente da FINEP, não depende de portlets dinâmicos com JavaScript massivo. A página de listagem de oportunidades ("Chamadas de Propostas") é renderizada nativamente no servidor (Server-Side Rendering) e possui uma estrutura HTML clássica e semântica.

## Decisão
Decidimos utilizar a biblioteca **Goutte** (Symfony/BrowserKit e DomCrawler) diretamente em um Spider simplificado no Laravel (ex: `FapespSpider`), sem invocar o Roach PHP com Puppeteer.
A captura de dados é baseada na extração via Seletores CSS (`->filter('.title')`) e, quando necessário, expressões XPath para isolar atributos como os links (href).

## Justificativa
Alocar recursos de *Headless Browser* (Puppeteer) para raspar um site estático como a FAPESP violaria as boas práticas de otimização e eficiência energética da plataforma. O Goutte executa uma simples requisição HTTP `GET` e injeta a resposta no analisador do Symfony. É infinitamente mais rápido, custa frações de milissegundos do processador e consome memória quase zero em comparação a instanciar o Chromium via Node.js.

## Consequências
- **Positivas:** 
  - Altíssima velocidade de execução do Spider.
  - Baixo uso de memória do servidor.
  - Código elegante e de fácil leitura para a equipe de Engenharia.
- **Negativas:** 
  - Se a FAPESP passar por uma modernização implementando SPA (Single Page Application) com React, Vue, ou Angular, este Spider clássico quebrará imediatamente, exigindo migração para o Roach/Puppeteer.
