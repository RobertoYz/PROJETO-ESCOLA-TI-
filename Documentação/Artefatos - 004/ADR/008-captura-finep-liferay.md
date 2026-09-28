# ADR 008: Captura de Editais da FINEP via Roach PHP e Engenharia Reversa de Liferay

## Status
Aceito

## Contexto
A FINEP (Financiadora de Estudos e Projetos) é uma das maiores agências de fomento à inovação do Brasil. O desafio principal para capturar seus editais é que o portal oficial é construído utilizando o Liferay Portal e a renderização do catálogo de editais ocorre de forma dinâmica (Client-side rendering via JavaScript), não expondo as informações diretamente no HTML inicial da página.

## Decisão
Foi decidido utilizar a biblioteca **Roach PHP** (baseada na arquitetura do Scrapy do Python) com integração ao **Puppeteer** para a execução do navegador Headless.
Adicionalmente, optou-se por realizar engenharia reversa das requisições de rede feitas pelo Liferay (os famosos `p_p_id` e parâmetros de portlet), simulando as requisições AJAX e navegando através dos payloads em JSON, ao invés de depender puramente da extração do DOM renderizado.

## Justificativa
O uso de Roach PHP fornece uma estrutura robusta de fila de itens (*Item Pipelines*) ideal para o ecossistema Laravel. 
A engenharia reversa da API interna do Liferay mostrou-se mais rápida e resiliente a quebras de layout do que o *Screen Scraping* visual com o Puppeteer sozinho. O Puppeteer age como facilitador inicial, mas os dados reais (Título, Objetivo, Link) são pescados das respostas JSON do portlet de editais.

## Consequências
- **Positivas:** 
  - Maior precisão e velocidade na extração, sem sofrer com lentidão do render do front-end da FINEP.
  - Estrutura clara e orientada a objetos (Spiders, ItemPipelines, Items).
- **Negativas:** 
  - Exigência de Node.js e Puppeteer rodando no servidor, aumentando o consumo de RAM.
  - A manutenção da aranha (Spider) requer conhecimento dos cabeçalhos específicos da API fechada da FINEP, que podem mudar a cada atualização do Liferay.
