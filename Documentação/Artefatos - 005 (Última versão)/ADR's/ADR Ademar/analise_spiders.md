# Resumo Analítico: Arquitetura de Spiders (Mvp_anjo vs Fase_1)

Abaixo está o detalhamento analítico de como o sistema de *web scraping* evoluiu do protótipo legado (`Mvp_anjo`) para a arquitetura escalável atual (`MVPs\Projeto Anjo Nexus\Fase_1\back`).

---

## 1. O Modelo Antigo (`Mvp_anjo`)

No sistema antigo, a extração funcionava de maneira **monolítica e síncrona**. Os Spiders faziam praticamente todo o trabalho sozinhos no momento em que eram executados.

*   **Acoplamento Forte:** O Spider da FAPESP (e outros) estendia o `AbstractSpider` básico do Roach, mas não utilizava os recursos do framework. Em vez disso, ele raspava os dados da página da web e executava a query no banco de dados diretamente via `Edital::updateOrCreate(...)` dentro do próprio script do Spider.
*   **Pipeline Ignorado:** Embora existisse um arquivo `SalvarNoBancoProcessor.php` na pasta antiga, o `FapespSpider` tinha seus arrays de `$itemProcessors` vazios, ignorando o fluxo de pipeline do RoachPHP.
*   **Campos Engessados:** O mapeamento do banco de dados usava as colunas antigas (`titulo`, `prazo_submissao`), e forçava *strings* padrão via código ("Chamada aberta para submissão...") quando um campo como `objetivo` não era encontrado de imediato, o que limitava a visualização.
*   **Sem Background Jobs:** Tudo acontecia no terminal no momento da chamada do comando. Se um site demorasse a responder, o terminal do robô ficava travado.

---

## 2. O Modelo Novo (`Fase_1\back`)

No sistema atual, implementamos a verdadeira arquitetura de **Pipeline Baseado em Eventos**, dividindo a responsabilidade da extração em camadas assíncronas de Custo Zero e Inteligência Artificial.

*   **Padrão de Pipeline (RoachPHP):** O `FapespSpider` agora estende a classe ajudante `BasicSpider`. Sua única responsabilidade passou a ser ler o painel de listagem dos editais e extrair o mínimo possível, devolvendo os resultados usando geradores (`yield $this->item([...])`).
*   **Desacoplamento do Banco (Processors):** O salvamento no banco de dados foi retirado do código do Spider. Ele repassa o item para a fila do `SalvarNoBancoProcessor`. Isso traz idempotência automatizada e limpeza de código.
*   **Integração com Filas Assíncronas (Deep Scrape):** A maior evolução de todas. O novo `SalvarNoBancoProcessor` não apenas salva os dados essenciais (com a nova nomenclatura `title`, `deadline`), como ele atua como um gatilho. Quando um novo edital é descoberto, ele joga o ID do edital na fila do Laravel despachando o `ScrapeEditalCompletoJob`.
*   **Enriquecimento em Duas Camadas:**
    1.  **Custo Zero (Regex):** O sistema assíncrono entra na página oficial (seguindo até *meta refreshes* escondidos), baixa o PDF/HTML e varre o texto via expressões regulares (regex) para achar Datas, Orçamentos e Faturamento gratuitamente.
    2.  **Inteligência Artificial:** Após o Regex tentar encontrar os valores fáceis, as APIs (Groq/DeepSeek/Gemini) avaliam o texto completo para gerar pontuações técnicas (TRL, Match) e o diagnóstico descritivo.

## Resumo das Diferenças

| Recurso | `Mvp_anjo` (Antigo) | `Fase_1` (Novo) |
| :--- | :--- | :--- |
| **Padrão de Salvamento** | *Hardcoded* no corpo do Spider | Arquitetura via Pipeline (`ItemProcessorInterface`) |
| **Download de Conteúdo** | Apenas na listagem inicial | *Deep Scrape* (Entra na página de cada edital para baixar PDF/texto longo) |
| **Processamento** | Síncrono (Travava o comando) | Assíncrono (Fila background via Laravel Jobs) |
| **Resiliência a Quedas** | Falhava e parava o processo inteiro | Se a IA falha, o Regex Custo Zero age como *Fallback* garantindo datas e orçamentos. |
