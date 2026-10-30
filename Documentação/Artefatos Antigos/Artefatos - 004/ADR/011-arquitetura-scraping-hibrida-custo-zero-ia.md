# ADR 011: Escolha da Arquitetura Híbrida de Web Scraping (Browsershot + Árvore XML/XPath) com Custo Zero de IA

* **Status**: Aceito
* **Data**: 2026-09-28
* **Autores**: Equipe de Arquitetura e Desenvolvimento (Anjo Nexus)
* **Ticket / Issue**: EDTAN-114

---

## 1. Contexto

Durante o desenvolvimento do módulo **Radar de Editais (Anjo Nexus)**, enfrentamos o desafio de extrair e catalogar editais de fontes heterogêneas (FINEP, FAPESP e FAPESC). 

Nas iterações anteriores (Geração 1), a raspagem dependia de seletores CSS simples e quebra de linhas de texto (`explode("\n")`), o que se mostrou extremamente frágil a mudanças de layout e ineficaz em páginas renderizadas via JavaScript/AJAX (Single Page Applications / Liferay).

Surgiu a necessidade de avaliar a estratégia mais eficiente e decidir se a Inteligência Artificial (LLMs) deveria ser utilizada para traduzir/extrair os dados brutos e anexos dos editais ou se deveríamos adotar uma abordagem 100% determinística.

---

## 2. Decisão

Decidimos adotar uma **Arquitetura Híbrida Determinística com Custo Zero de IA** para toda a etapa de Web Scraping e Indexação:

1. **FINEP (Geração 2 - Browser Automation & REST API)**:
   - Utilizar o `Spatie\Browsershot` (Headless Chrome) para injetar um script assíncrono que consome diretamente a API REST interna `/o/c/chamadapublicas` do próprio portal FINEP.
   - Retorna os dados em JSON nativo paginado, capturando 100% das oportunidades (477+ editais) sem depender de seletores CSS visuais.

2. **FAPESP e FAPESC (Geração 3 - Árvore XML / XPath)**:
   - Adotar a classe abstrata `BaseSpider` estendendo o RoachPHP.
   - Navegar na hierarquia DOM/XML usando **XPath** (`filterXPath('//ul[contains(@class, "list")]/li')`) para isolar os nós exatos de chamada, título e link sem quebras de string.

3. **Parse de PDFs e Datas (Expressões Regulares / Regex)**:
   - Extração de datas (`deadline`) e orçamentos via Regex compilados e leitores de PDF PHP nativos (`smalot/pdfparser` / `pdftotext`).

4. **Isolamento Rígido da Inteligência Artificial**:
   - **ZERO chamadas de IA durante a raspagem, leitura de PDFs e indexação**.
   - As APIs de IA (OpenAI / DeepSeek / Groq) ficam reservadas **exclusivamente para o Módulo de Geração de Minutas de Propostas (RF-44)** quando acionadas voluntariamente pelo consultor.

---

## 3. Consequências

### Positivas 🟢
* **Custo Financeiro Zero de IA no Pipeline**: Nenhuma cobrança de API de LLM durante a varredura e sincronização diária de editais.
* **Integridade e Cobertura Total**: Captura das 477 oportunidades da FINEP e 52 chamadas da FAPESP sem perdas por filtro visual.
* **Imunidade a Mudanças de Layout**: O consumo da API REST interna da FINEP e seletores XPath estruturados tornam a captura resistente a pequenas alterações de CSS.
* **Padronização e Reusabilidade**: Herança via `BaseSpider` simplifica a criação de novos spiders para outros estados.

### Negativas 🟡
* **Requisito de Infraestrutura para a FINEP**: A execução do Browsershot exige a presença do Node.js e Chromium/Puppeteer no servidor de aplicação.

---

## 4. Matriz de Decisão Arquitetural

| Fonte | Tecnologia Escolhida | Uso de IA | Motivo da Escolha |
| :--- | :--- | :--- | :--- |
| **FINEP** | Browsershot + API REST `/o/c/chamadapublicas` | ❌ Não | Requer suporte a JS. A API REST nativa entrega JSON completo de 477 editais. |
| **FAPESP** | `BaseSpider` + XPath (`//ul/li`) | ❌ Não | HTML estruturado estático. O XPath varre 52 editais em milissegundos. |
| **FAPESC** | `BaseSpider` + XPath (`//div[contains(@class, "upk-item")]`) | ❌ Não | Cards estruturados em WordPress lidos via XPath e datas parseadas via Regex. |
| **Minuta de Proposta** | OpenAI / DeepSeek / Groq Router | 🟢 **Sim** | Geração e redação assistida de texto de proposta sob demanda do usuário. |
