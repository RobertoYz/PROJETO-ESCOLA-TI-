# 📊 Análise Comparativa da Evolução das 3 Gerações de Scraping

Este documento apresenta a análise evolutiva das **três gerações de raspagem (Web Scraping)** desenvolvidas no projeto **Radar de Editais - Anjo Nexus**, comparando a eficiência, arquitetura, prós, contras e viabilidade de cada abordagem.

---

## 🛠️ Matriz Comparativa Resumida

| Critério | Geração 1: HTML/CSS + Line Split | Geração 2: Headless Chrome + API Interna (Browsershot) | Geração 3 (Atual): Árvore XML/XPath + Regex & PDF Parser (Custo Zero de IA) |
| :--- | :--- | :--- | :--- |
| **Abordagem Core** | Varredura de HTML bruto por classes CSS e `explode("\n")` | Execução de script JS em Headless Chrome consumindo a API REST do órgão | Navegação por nós de Árvore XML (XPath) + Parse Determinístico de PDFs + Regex |
| **Uso de IA no Scraping** | ❌ Não usava | ❌ Não usava | 🚫 **CUSTO ZERO DE IA** (100% Determinístico sem chamadas a LLMs) |
| **Lidar com AJAX/JS** | 🔴 **Muito Frágil** (falha se o DOM não vier pré-renderizado) | 🟢 **Excelente** (espera o JS executar e injeta `fetch` direto no contexto da página) | 🟢 **Excelente** (combina o consumo de APIs REST nativas com parsing por Árvore XML) |
| **Volume Capturado** | ⚠️ Parcial (apenas a 1ª página ou HTML estático visível) | 🟢 **Total** (varre 100% da paginação: 477 itens na FINEP) | 🟢 **Total e Estruturado** |
| **Consumo de Memória / CPU** | 🟢 Mínimo (apenas cURL / Guzzle) | 🔴 Elevado (requer ambiente Node.js + Chromium rodando) | 🟡 Moderado/Otimizado (Processamento direto sem latência de IA) |
| **Manutenibilidade** | 🔴 Baixa (qualquer mudança de `class` ou quebra de linha no HTML quebra o parser) | 🟢 Alta (imune a mudanças visuais de layout, depende apenas das rotas da API) | 🟢 **Altíssima** (Padrão de Projeto Herança `BaseSpider` + XPath limpo) |

---

## 🔍 Detalhamento Técnico das 3 Gerações

### 1️⃣ Geração 1: Scraping HTML Tradicional (DomCrawler / Seletores CSS / Quebra de Linhas)

* **Funcionamento**:
  - Utilizava seletores CSS como `$response->filter('.page-body ul.list > li')`.
  - Como os textos vinham em blocos HTML misturados, o script fazia um `strip_tags(str_replace(['<br>'], "\n", ...))` seguido de um `explode("\n", $texto)` e percorria linha por linha verificando `str_contains($linha, 'data limite')`.
* **O Problema do AJAX / Gambiarra**:
  - Em portais modernos como o da FINEP (baseados em Liferay/Single Page Applications), a requisição HTTP inicial do cURL retorna um HTML esqueleto sem os editais.
  - A raspagem CSS tradicional falhava totalmente (`count() == 0`) ou tentava gambiarras como montar URLs na mão sem saber a paginação.

---

### 2️⃣ Geração 2: Injeção de Browser Automation + Consumo de APIs Internas (Browsershot)

* **Funcionamento**:
  - Empregou a biblioteca `Spatie\Browsershot` controlando uma instância oculta do Google Chrome (`chrome.exe` via Node.js/Puppeteer).
  - Em vez de ler os elementos visuais da tela, injeta um script `Promise/fetch` assíncrono diretamente na sessão ativa do navegador para chamar a API REST `/o/c/chamadapublicas` do próprio portal FINEP.
  - Itera automaticamente pelas páginas (`page=1&pageSize=250`, `page=2`, etc.) e converte a resposta em JSON nativo.
* **Resultados**:
  - Capturou **477 oportunidades** na FINEP com 100% de integridade (público-alvo, prazos, tipo de cooperação e links).

---

### 3️⃣ Geração 3 (Atual): Arquitetura Determinística (Árvore XML/XPath + PDF Parser & Regex)

* **Funcionamento**:
  - **Pilar 1 (Herança `BaseSpider`)**: Todos os spiders herdam de uma classe base centralizadora que padroniza o salvamento no banco, limpeza de textos e parse de datas.
  - **Pilar 2 (Navegação por Árvore XML / XPath)**: Em sites como a FAPESP, utiliza consultas XPath (`filterXPath('//ul[contains(@class, "list")]/li')`) para navegar chirurgicamente pelos galhos do documento XML/DOM.
  - **Pilar 3 (Parse Determinístico de PDFs do Edital)**: Baixa os PDFs dos anexos e utiliza leitores nativos PHP (`smalot/pdfparser` ou `pdftotext`) para ler o texto puro do edital.
  - **Pilar 4 (Extração Custo Zero com Regex)**: Extrai datas de encerramento (`deadline`), orçamentos e requisitos de faturamento via Expressões Regulares compiladas diretamente sobre o texto do PDF/XML.
* **Isolamento da IA**:
  - **ZERO chamadas de IA durante o Scraping e Indexação**.
  - A IA (OpenAI / DeepSeek / Groq) é **reservada exclusivamente para a Geração da Minuta de Proposta** (módulo de auxílio na escrita do projeto de captação).

---

## 🎯 Conclusão e Regra de Ouro da Arquitetura

| Portal | Estratégia Recomendada | Depende de IA? | Motivo |
| :--- | :--- | :--- | :--- |
| **FINEP** | **Browsershot / API REST Interna** | ❌ **NÃO** | O consumo da API REST interna em JSON traz os 477 editais com campos estruturados nativos. |
| **FAPESP** | **XPath + PDF Parser / Regex** | ❌ **NÃO** | O XPath lê a árvore XML da lista e o PDF Parser extrai os anexos via Regex. |
| **FAPESC** | **XPath + PDF Parser / Regex** | ❌ **NÃO** | A lista em WordPress é lida via XPath e os anexos são lidos deterministicamente. |

> [!IMPORTANT]
> **Garantia de Custo Zero e Desempenho**: NENHUMA etapa de raspagem, indexação ou leitura de PDFs utiliza IA. O uso de LLMs é mantido **100% restrito à etapa final de Geração da Minuta de Proposta pelo Consultor**.
