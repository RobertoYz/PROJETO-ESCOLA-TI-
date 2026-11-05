# ADR 004: Escolha do PHP e Laravel para Web Scraping

## Status
Aceito

## Contexto
O projeto "Anjo Nexus" tem como um de seus requisitos fundamentais a capacidade de realizar a varredura e extração de dados (Web Scraping) do portal da FINEP e de outros sites governamentais para alimentar a nossa inteligência artificial.

Segundo a literatura e a recomendação acadêmica (inclusive pelo professor orientador), o framework Laravel e a linguagem PHP não são as ferramentas mais adequadas ou recomendadas nativamente para Web Scraping de alta complexidade. O mercado considera o Python (com bibliotecas como Beautiful Soup, Scrapy ou Selenium) a escolha ideal e mais madura para esse tipo de tarefa.

## Decisão
Apesar da ressalva técnica, a equipe optou por **manter toda a arquitetura de varredura e extração desenvolvida em PHP utilizando o ecossistema do Laravel** (ferramentas como Roach-PHP e Spatie Browsershot).

## Justificativa
A decisão foi baseada fortemente no pilar da Viabilidade do Projeto e Gestão de Conhecimento do Time:

1. **Curva de Aprendizado e Nível de Entendimento:** A linguagem PHP possui uma curva de complexidade menor e maior aceitação pelo atual time de desenvolvimento. Inserir Python apenas para a camada de Scraping adicionaria uma complexidade indesejada (microsserviços, múltiplas linguagens no mesmo repositório, comunicação entre APIs), o que inviabilizaria o tempo de entrega e a manutenibilidade para o nível atual da equipe.
2. **Framework Consolidado no Mercado:** O Laravel é uma referência absoluta no mercado global e nacional para o desenvolvimento web. Possui um ecossistema extremamente rico e maduro (Jobs, Queues, Eloquent ORM), que resolveu com facilidade as necessidades de processamento em background (ver ADR 002).
3. **Evolução Contínua do PHP:** A linguagem PHP está em constante evolução (lançamentos frequentes de versões 8.x) entregando cada vez mais performance e ferramentas modernas, provando que é totalmente capaz de orquestrar ferramentas de Headless Browser (através de integrações com Puppeteer) para suprir as deficiências de leitura de páginas dinâmicas.

## Consequências
- **Positivas:** O time consegue iterar, corrigir bugs e dar manutenção no projeto inteiro de ponta a ponta na mesma linguagem e no mesmo ecossistema (Laravel).
- **Negativas (Mitigadas):** Houve a necessidade de configurar ferramentas de terceiros (Node.js e Puppeteer por baixo dos panos) para realizar o "Deep Scrape", pois bibliotecas nativas de PHP como Goutte não leem sites protegidos por SPAs ou frameworks reativos de forma nativa. Esse ponto foi superado e estabilizado.
