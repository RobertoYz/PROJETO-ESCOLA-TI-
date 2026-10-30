# Matriz Consolidada de Registros de Decisões Arquiteturais (ADRs) - Versão 005

Este catálogo reúne as 17 Decisões Arquiteturais que norteiam o desenvolvimento e a infraestrutura do ecossistema **Anjo Nexus**, detalhando o contexto, alternativas descartadas e a justificativa acadêmica para a banca avaliadora.

| ADR | Decisão Arquitetural | Problema Resolvido | Justificativa para a Banca |
| :--- | :--- | :--- | :--- |
| **001** | Backend Laravel API + Frontend Vanilla JS Desacoplado | Monolito vs API REST | Desacoplamento estrito, separação de responsabilidades e independência de deploy. |
| **002** | Processamento Background (Queues) para PDFs | Timeout HTTP na ingestão de PDFs de 100 páginas | Não bloqueia threads do servidor web e garante resiliência na esteira assíncrona. |
| **003** | Estratégia Custo Zero de IA no Pipeline de Scraping | Custo inviável de LLM para ler 500 editais brutos | Viabilidade econômica: IA generativa só entra no funil final (match e elaboração). |
| **003B**| Fallback de IA em Cascata (Gemini ➔ DeepSeek ➔ Groq ➔ Determinístico)| Indisponibilidade de APIs de terceiros | RNF-02: Tolerância a falhas. O sistema nunca exibe erro 500 para o usuário. |
| **004** | Laravel PHP Unificado para Web Scraping | Fragmentação tecnológica com Python/Scrapy | Redução de overhead operacional: toda a equipe opera na mesma stack backend. |
| **005** | Scraping por APIs Internas e Árvores XPath | Fragilidade de raspagem visual por Regex | Imune a mudanças cosméticas de layout CSS e cores nos portais públicos. |
| **006** | Extração de Metadados de PDF via Regex Pré-compilado | OCR pesado e lento por IA | Editais públicos são PDFs vetorizados pesquisáveis; regex entrega dados em 5ms. |
| **007** | Rotação Dinâmica de Chaves de API (`getRandomKey`) | Esgotamento de cota (*Rate Limits 429*) | Distribui carga uniformemente em pools de chaves gratuitas sem gargalo. |
| **008** | Ingestão Híbrida FINEP: API Liferay + Fallback Browsershot | Renderização assíncrona JavaScript no portal | Acesso direto ao endpoint JSON interno com fallback headless caso haja barreira. |
| **009** | XPath Hierárquico para FAPESP e FAPESC | Mudanças de estrutura em portais legados | Navegação semântica na árvore DOM sem quebrar por simples quebras de linha. |
| **010** | Vanilla JS Zero-Build no Frontend | Complexidade de empacotadores (Webpack, Vite) | Elimina pipeline frágil de build web e foca na validação rigorosa dos contratos REST. |
| **011** | Arquitetura de Scraping Híbrida e Idempotente | Duplicação de editais na base | Chave externa determinística (`md5(titulo + url)`) garante unicidade dos dados. |
| **012** | Gerador Nativo OpenXML (.docx) via ZipArchive e 11 Seções | Pacotes obsoletos e pesados (`phpword`) | Geração ultraveloz em <10ms sem dependências externas, no formato Anjo Inovador. |
| **013** | Gestão de Anexos até 10MB e Metadados no Card em JSON | Esquema relacional rígido para anexos | Flexibilidade schema-less na coluna `metadata` de `kanban_cards`. |
| **014** | Adoção do Capacitor 7 para Canal Híbrido Mobile | Reescrita custosa em React Native/Flutter | Reutilização de 100% da lógica e telas web com desempenho acelerado por hardware. |
| **015** | Deploy Apache/cPanel Unificado + CI/CD GitHub Actions | Deploy manual propenso a erro humano | Build autônomo do APK nativo em nuvem e distribuição estável em produção. |
| **016** | Gestão Multi-tenant B2B e Seletor Global de Startups | Isolamento de carteira de clientes | Consultor gerencia N startups sem vazamento de dados, com eventos reativos na UI. |
| **017** | Resiliência Touch e Ergonomia Mobile no Kanban e Radar | Incompatibilidade de drag-and-drop em telas touch| Botões direcionais touch, modais em viewport estrito e eliminação de saltos de scroll.|

