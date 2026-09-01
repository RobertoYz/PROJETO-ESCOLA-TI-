# Arquitetura e Implementação - Radar FINEP (EDTAN-72)

Este documento descreve as decisões arquiteturais e o funcionamento a nível de código do módulo de captura (Spider) focado na extração de editais do portal da **FINEP**.

## 1. O Desafio: Liferay, Autenticação e SPA

O portal da FINEP (`https://www.finep.gov.br/oportunidades`) apresenta um desafio complexo de web scraping: ele é construído sobre a plataforma corporativa **Liferay**.
A listagem real dos editais é fornecida por uma API interna no endpoint `/o/c/chamadapublicas`. No entanto, se tentarmos fazer um `GET` direto para essa API pelo PHP (via Guzzle ou Http), o Liferay rejeita a requisição com Erro 403 (Forbidden), pois exige tokens de sessão e cookies gerados dinamicamente no momento em que a página carrega.

## 2. A Solução: Engenharia Reversa com Browsershot (Node.js)

Para contornar o bloqueio da API do Liferay sem precisarmos apelar para a leitura frágil de seletores CSS na tela, utilizamos a biblioteca **Spatie Browsershot**. O Browsershot atua como uma ponte para o **Puppeteer** (Chrome Headless).

### Como funciona a nível de código:

O Roach PHP serve como orquestrador, mas a captura pesada acontece injetando JavaScript no navegador:
1. O método `parse()` aciona o Chrome Headless abrindo a URL principal (o que gera os cookies e a sessão validada do Liferay).
2. Imediatamente, injetamos um script de `fetch()` JavaScript que varre a API interna paginada de forma assíncrona, de **dentro** do navegador.
3. O JS concatena todos os editais retornados em um array gigante de JSON e o devolve para o PHP processar.

Essa técnica combina a **velocidade** de consumir uma API pura (estruturada em JSON) com o **poder de invisibilidade** de um navegador real, evitando totalmente a quebra caso a FINEP mude o CSS do site.

## 3. Pipeline e Esquema do Banco de Dados

Após receber o JSON da API e limpá-lo via `LimpaTextoTrait`, o método `parse()` utiliza o construtor `yield $this->item([...])` (Generator) para entregar cada edital individualmente ao `SalvarEditalProcessor`.

### Decisão Arquitetural: Schema (Database)
Para integrar os dados ricos fornecidos pela FINEP à nossa plataforma `Anjo Nexus`, o Schema da Fase_1 foi adaptado:
- A tabela `editais` sofreu um merge com a regra de negócio legada da FINEP, passando a suportar nativamente as colunas específicas: `objetivo`, `condicao_financiamento`, `operacao`, e `publico`.
- A coluna `external_id` recebe o UUID exclusivo fornecido pelo Liferay, garantindo que o método `updateOrCreate()` do Laravel jamais duplique registros ao rodar o Spider diariamente.
- Dados que não vêm detalhados de imediato (`min_budget`, `max_budget`, `deadline`) nascem como `NULL`. O preenchimento posterior desses campos é escalado para módulos futuros baseados em leitura de PDF (OCR + IA) ou extração Regex por anexo.

## 4. Pré-requisitos de Ambiente

Para rodar essa arquitetura localmente ou no servidor:
- **Puppeteer Instalado:** Executar `npm install puppeteer` localmente na pasta `back`.
- **Node.js Configurado:** O executável `node` (e o pacote `npm`) deve estar acessível no PATH (ex: `C:/nodejs/node.exe` no Windows).
