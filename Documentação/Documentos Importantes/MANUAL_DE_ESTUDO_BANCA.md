# 🎓 Manual Definitivo de Estudo para Banca Avaliadora
## Anjo Nexus — Radar Inteligente de Editais e Plataforma de Subvenção à Inovação

> **Finalidade do Documento:**  
> Este manual foi estruturado especificamente para a **defesa acadêmica perante a Banca Examinadora do TCC / Projeto Integrador**.  
> Ele sintetiza todos os artefatos de engenharia de software (diagramas UML, casos de uso, requisitos, DER), os 15 Registros de Decisões Arquiteturais (ADRs), as escolhas de stack técnica, as justificativas acadêmicas de projeto e o passo a passo detalhado do código-fonte (Backend Laravel 12, Frontend Vanilla JS e Mobile Capacitor 7).

---

## 📌 Sumário Executivo para Apresentação
1. [A Visão Geral do Produto (O Problema e a Solução)](#1-a-visão-geral-do-produto)
2. [Arquitetura Geral do Sistema e Princípios Adotados](#2-arquitetura-geral-do-sistema)
3. [Decisões Arquiteturais Fundamentais (Matriz de ADRs)](#3-decisões-arquiteturais-fundamentais-matriz-de-adrs)
4. [Engenharia de Dados e Modelagem (DER e Classes)](#4-engenharia-de-dados-e-modelagem)
5. [Módulo a Módulo: Explicação Didática e Defesa de Código](#5-módulo-a-módulo-explicação-de-código)
   - 5.1. [Autenticação e Assinatura de Planos (SaaS)](#51-autenticação-e-assinatura-de-planos)
   - 5.2. [Motor de Web Scraping Híbrido com Custo Zero de IA](#52-motor-de-web-scraping-híbrido)
   - 5.3. [Extração e Processamento de PDFs de Editais](#53-extração-e-processamento-de-pdfs)
   - 5.4. [Esteira Kanban e Gestão do Pipeline de Submissão](#54-esteira-kanban-e-pipeline)
   - 5.5. [Módulo de IA: Diagnóstico de Aderência (Match RF-47) e Minutas Técnicas (RF-29 a RF-34)](#55-módulo-de-ia-match-e-minutas)
   - 5.6. [Motor Nativo de Exportação OpenXML (.docx) e PDF](#56-motor-nativo-de-exportação-openxml-e-pdf)
   - 5.7. [Frontend Desacoplado: Vanilla JS e Design System](#57-frontend-desacoplado-vanilla-js)
   - 5.8. [Deploy em Produção (HostGator/Apache) e Esteira CI/CD Mobile](#58-deploy-em-produção-hostgatorapache-e-esteira-cicd-mobile)
6. [Simulador de Perguntas & Respostas da Banca (Q&A de Arguição)](#6-simulador-de-perguntas-e-respostas-da-banca)
7. [Glossário e Nomenclatura para Argumentação](#7-glossário-técnico-para-a-banca)

---

## 1. A Visão Geral do Produto

### 1.1 O Problema Real de Mercado
Empresas de base tecnológica (Startups, Micro e Pequenas Empresas - MPEs) perdem anualmente milhões de reais em recursos de subvenção econômica não reembolsável (FAPESC, FINEP, FAPESP, CNPq). Os principais gargalos são:
1. **Dispersão e Assimetria de Informações:** Editais publicados em diários oficiais e portais isolados com layouts heterogêneos.
2. **Incerteza de Elegibilidade:** Editais possuem centenas de páginas em PDF; a leitura manual para verificar requisitos de porte, faturamento e TRL (Nível de Maturidade Tecnológica) consome dias de consultoria.
3. **Barreira Técnica na Elaboração da Proposta:** A montagem do Plano de Trabalho exige jargão técnico (ODS, TRL, matriz de riscos, plano de aplicação de recursos) que founders técnicos muitas vezes não dominam.

### 1.2 A Solução Anjo Nexus
Uma plataforma SaaS B2B que atua como **Radar Contínuo e Co-Piloto de Submissão**:
- Raspa e unifica oportunidades governamentais diariamente.
- Avalia a compatibilidade da startup com o edital (**Match Inteligente**).
- Gerencia o ciclo de vida da oportunidade através de um **Pipeline Kanban interativo**.
- Gera automaticamente **Minutas de Proposta estruturadas em 11 seções oficiais**, permitindo edição, versionamento e exportação para Word (.docx) e PDF.

---

## 2. Arquitetura Geral do Sistema

A arquitetura do Anjo Nexus foi concebida sob o paradigma **API-First e Arquitetura Desacoplada (Stateless RESTful)**.

```
┌────────────────────────────────────────────────────────────────────────┐
│                        ARQUITETURA GERAL DO SISTEMA                    │
└────────────────────────────────────────────────────────────────────────┘

    [ CLIENTE: NAVEGADOR WEB ]
          │  HTML5 + Vanilla CSS + JavaScript Puro (SPA Leve)
          │  Zero build-tools, fetch API nativa, manipulação limpa do DOM
          ▼
    [ PROTOCOLO HTTP/REST (JSON) ] (Header: Authorization Bearer Token)
          │
          ▼
┌────────────────────────────────────────────────────────────────────────┐
│               BACKEND: LARAVEL 12 (API RESTful STATELESS)              │
├────────────────────────────────────────────────────────────────────────┤
│  routes/api.php                                                        │
│    └── Middleware (Sanctum / Token Guard / CORS)                       │
│          └── Controllers (Validação via Form Request / Injeção de Dep.)│
│                │                                                       │
│                ├──> Services Layer (Regras de Negócio e Algoritmos)    │
│                │      ├── MinutaAiService (Prompt 11 Seções + Fallback)│
│                │      ├── DocumentExportService (OpenXML .docx / PDF)  │
│                │      ├── KanbanService & CardMovementService          │
│                │      ├── AbacatePayService (Billing / Pix)            │
│                │      └── PdfExtractorService (Regex + smalot/pdfparser│
│                │                                                       │
│                ├──> Spiders Layer (Web Scraping Contínuo)              │
│                │      ├── FinepSpider (Browsershot / Headless Chrome)  │
│                │      ├── FapespSpider (RoachPHP + XPath DOM)          │
│                │      └── FapeScSpider (RoachPHP + XPath DOM)          │
│                │                                                       │
│                └──> Models Eloquent (Mapeamento Objeto-Relacional)     │
│                       ├── User, Startup, Edital, Agencia               │
│                       ├── KanbanBoard, KanbanColumn, KanbanCard        │
│                       └── Minuta (Árvore de Versões e Auditoria)       │
└────────────────────────────────────────────────────────────────────────┘
          │
          ▼
    [ BANCO DE DADOS RELACIONAL: PostgreSQL / MySQL / SQLite ]
```

### Princípios de Engenharia de Software Aplicados:
1. **Single Responsibility Principle (SRP):** Controllers apenas recebem a requisição e devolvem respostas JSON; toda a inteligência reside nas classes de `Services`.
2. **Dependency Injection (DI):** Injeção de dependências nativa do container do Laravel para serviços externos e modelos.
3. **Loose Coupling (Baixo Acoplamento):** O Frontend não conhece detalhes internos do banco de dados, comunicando-se estritamente através de contratos JSON documentados.
4. **Fault Tolerance (Tolerância a Falhas):** Política de contingência multinível na IA (Gemini ➔ DeepSeek ➔ Motor Determinístico Local), garantindo quase 100% de disponibilidade.

---

## 3. Decisões Arquiteturais Fundamentais (Matriz de ADRs)

A banca avaliadora valoriza intensamente a justificativa do **porquê** as decisões foram tomadas. A tabela abaixo resume os 15 Registros de Decisões Arquiteturais (ADRs):

| ADR | Decisão Tomada | Alternativa Rejeitada | Justificativa Acadêmica para a Banca |
| :--- | :--- | :--- | :--- |
| **001** | Backend Laravel API + Frontend Vanilla JS | Aplicação Monolítica com Laravel Blade | Desacoplamento estrito, separação clara de responsabilidades e independência de deploy. |
| **002** | Processamento Background (Queues) para PDFs | Processamento síncrono no request HTTP | Evita estouro de timeout (30s) e travamento de thread HTTP na leitura de PDFs com mais de 50 páginas. |
| **003** | Estratégia Custo Zero de IA no Pipeline | Uso de LLMs para ler todo HTML de editais | Viabilidade financeira. Usar IA em raspagem de 500 editais geraria custos proibitivos e imprevisíveis. |
| **004** | Laravel PHP para Web Scraping | Microserviço isolado em Python (Scrapy) | Redução de complexidade operacional e overhead de infraestrutura: mantém toda a equipe em uma única linguagem backend. |
| **005** | Trade-off de Scraping: APIs Internas + XPath | Raspagem puramente visual baseada em Regex | APIs internas e árvores XPath são imunes a pequenas alterações de cores, margens e layouts CSS. |
| **006** | Extração de PDF determinística (Regex estruturado) | OCR por Visão Computacional / IA | Editais públicos são PDFs digitais pesquisáveis (com texto vetorizado); OCR adicionaria lentidão desnecessária. |
| **007** | Rotação Dinâmica de Chaves de API (`getRandomKey`) | Chave única estática no `.env` | Contorna os limites de requisições por minuto (*Rate Limits*) em camadas gratuitas de LLMs. |
| **008** | Browsershot (Headless Chrome) para FINEP | cURL simples / GuzzleHTTP | O portal da FINEP é uma Single Page Application baseada em Liferay que exige execução de motor JavaScript. |
| **009** | XPath DOM para FAPESP e FAPESC | Quebra de strings (`explode('\n')`) | XPath navega semanticamente na árvore XML/HTML sem quebrar se uma linha em branco for adicionada. |
| **010** | Vanilla JS no Frontend (Fase 1) | Frameworks pesados (React, Angular, Vue) | Elimina pipeline complexo de build (`npm run build`), dependências frágeis e foca na validação da API REST. |
| **011** | Arquitetura Híbrida de Scraping | Solução única genérica para todas as fontes | Respeita a heterogeneidade da web pública brasileira (Liferay na FINEP vs WordPress na FAPESC). |
| **012** | Gerador Nativo OpenXML (.docx) e Minutas em 11 Seções | Pacotes PHP externos pesados (`phpoffice/phpword`)| Geração leve e independente via `ZipArchive` do PHP; alinhamento estrito ao modelo Anjo Inovador/FAPESC. |
| **013** | Gestão de Anexos e Metadados no Card em JSON | Tabela relacional rígida com colunas fixas | Flexibilidade schema-less para notas e múltiplos anexos até 10MB no Kanban. |
| **014** | Adoção do Capacitor para Canal Mobile Híbrido | Reescrita em React Native / Flutter | Reaproveitamento da base web, mitigação de overhead de build e comprovação do desacoplamento RESTful da API. |
| **015** | Deploy Apache/cPanel Unificado + CI/CD no GitHub Actions | Deploy manual em VPS ou Docker pesado | Estabilidade em produção com custo zero de build, isolamento de assets estáticos vs rotas dinâmicas do Laravel e compilação do APK nativo sem ferir regras acadêmicas. |

---

## 4. Engenharia de Dados e Modelagem

### 4.1 Diagrama Entidade-Relacionamento (DER) Sintético

```mermaid
erDiagram
    USERS ||--o{ STARTUPS : "gerencia"
    USERS ||--o{ MINUTAS : "autor de"
    STARTUPS ||--o{ KANBAN_BOARDS : "possui"
    STARTUPS ||--o{ MINUTAS : "proponente em"
    EDITAIS ||--o{ KANBAN_CARDS : "vinculado a"
    EDITAIS ||--o{ MINUTAS : "base para"
    KANBAN_BOARDS ||--o{ KANBAN_COLUMNS : "composto por"
    KANBAN_COLUMNS ||--o{ KANBAN_CARDS : "contem"
    KANBAN_CARDS ||--o{ MINUTAS : "possui minuta"
    MINUTAS ||--o{ MINUTAS : "versao filha de (parent_id)"

    USERS {
        bigint id PK
        string name
        string email
        string password
    }

    STARTUPS {
        bigint id PK
        bigint user_id FK
        string razao_social
        string nome_fantasia
        string cnpj
        string porte
        string nicho_atuacao
        decimal faturamento_anual
    }

    EDITAIS {
        bigint id PK
        string title
        string fonte
        text objetivo
        decimal max_budget
        date deadline
        string publico
        json documentos_exigidos
    }

    KANBAN_CARDS {
        bigint id PK
        bigint kanban_column_id FK
        bigint edital_id FK
        string title
        integer position
    }

    MINUTAS {
        bigint id PK
        bigint startup_id FK
        bigint edital_id FK
        bigint kanban_card_id FK
        bigint parent_minuta_id FK
        integer versao
        string titulo_projeto
        text conteudo_gerado
        json secoes_estruturadas
        string status
        string modelo_ia_usado
        string autor_nome
    }
```

### 4.2 Destaques de Modelagem para Argumentação:
- **Auto-Relacionamento em `minutas` (`parent_minuta_id`):** Implementa o padrão de **Versionamento Imutável**. Ao editar uma proposta, o sistema nunca sobrescreve o texto anterior; ele cria uma nova linha com `versao = versao + 1` e aponta para a matriz, assegurando auditoria completa (RF-33 e RF-34).
- **Coluna JSON `secoes_estruturadas`:** Permite armazenar as 11 seções como entidades atômicas pesquisáveis (ex: buscar todas as propostas que citam *ODS 9* ou *TRL 7*), combinando a rigidez do modelo relacional com a flexibilidade NoSQL do formato JSON.

---

## 5. Módulo a Módulo: Explicação Didática e Defesa de Código

### 5.1 Autenticação e Assinatura de Planos (SaaS)
- **Tecnologia:** Laravel Sanctum (`auth:sanctum`), tokens Bearer, bcrypt.
- **Fluxo:** 
  1. Usuário envia credenciais via `POST /api/login`.
  2. `AuthController::login` valida via Hash::check e gera token pessoal.
  3. Toda rota subsequente valida o cabeçalho `Authorization: Bearer <token>`.
- **Billing:** Integração via `AbacatePayService` para geração e liquidação de Pix em tempo real, liberando acessos às funcionalidades do painel.

---

### 5.2 Motor de Web Scraping Híbrido com Custo Zero de IA

#### Como a FINEP é minerada (`FinepSpider.php`):
O portal da FINEP utiliza o CMS corporativo Liferay com renderização assíncrona.
- **A Solução:** Ao invés de raspar HTML visual sujeito a falhas de paginação, usamos o `Browsershot` (Puppeteer/Chromium) para injetar um script que invoca a API interna `/o/c/chamadapublicas`.
- **Resultado:** Captura limpa de mais de 470 editais em formato JSON nativo com 100% de precisão de datas e links.

#### Como FAPESP e FAPESC são mineradas (`FapespSpider.php` e `FapeScSpider.php`):
- Estendem a classe abstrata `BaseSpider`.
- Utilizam seletores semânticos **XPath**:
  ```php
  $nodes = $response->filterXPath('//div[contains(@class, "chamada-item")]');
  ```
- **Justificativa para a Banca:** O XPath navega na árvore hierárquica do DOM independentemente de regras visuais de CSS, garantindo imunidade a mudanças de estilo do portal do governo.

---

### 5.3 Extração e Processamento de PDFs de Editais (`PdfExtractorService.php`)
- **Desafio:** Editais de inovação são PDFs de 30 a 120 páginas.
- **Solução Custo Zero:** Leitura do fluxo de bytes usando `smalot/pdfparser`. O texto bruto passa por **Expressões Regulares (Regex)** compiladas para identificar:
  - Teto Orçamentário: `/(?:valor\s+global|recursos\s+de\s+at[ée])\s*(?:R\$\s*)?([\d\.,]+)/i`
  - Prazo Final: `/(?:prazo\s+final|submiss[ãa]o\s+at[ée])\s*:?\s*(\d{2}\/\d{2}\/\d{4})/i`
  - Relação de Anexos e Documentos Exigidos.

---

### 5.4 Esteira Kanban e Gestão do Pipeline (`KanbanService.php` e `kanban.html`)
- **Objetivo:** Permitir ao consultor acompanhar oportunidades em colunas de fluxo contínuo (*Backlog/Triagem*, *Em Análise*, *Minuta em Elaboração*, *Submetido*, *Aprovado*).
- **Implementação:**
  - **Drag and Drop Nativo:** Eventos HTML5 (`dragstart`, `dragover`, `drop`) com cálculo dinâmico de ordenação e posições flutuantes (*midpoint ordering*).
  - **Persistência RESTful:** Endpoint `PATCH /api/kanban/cards/{id}/mover`, recalculando as posições ordinais no banco de dados para persistência consistente.
  - **Gestão de Anexos e Metadados (ADR 013 / EDTAN-183):** Endpoint `POST /api/kanban/cards/{id}/dados-anexos` para upload de arquivos de até 10MB (CNDs, planilhas orçamentárias, cartas de anuência) e salvamento de valor estimado do projeto (R$) e notas internas na coluna `metadata` (JSON) de `kanban_cards`.
  - **Isolamento Multi-tenant (B2B):** Filtragem dinâmica do quadro por startup ativa selecionada no cabeçalho global (`seletorGlobalStartup.js`).
  - **Exclusão e Ações Rápidas:** Remoção de cards via `DELETE /api/kanban/cards/{id}` e botão de acionamento direto para geração de minuta técnica com IA.

---

### 5.5 Módulo de IA: Match Inteligente e Minutas Técnicas

#### O Algoritmo de Match Inteligente (RF-47):
Implementado em `MinutaAiService::calcularMatchEditais()`. Trata-se de uma análise multicritério de pontuação ponderada:
1. **Aderência Temática de Nicho (+20 pontos):** Cruza termos do nicho da startup (ex: *Cidades Inteligentes*, *Software*, *Biotecnologia*) com as diretrizes do edital.
2. **Elegibilidade de Porte Jurídico (+15 pontos):** Valida se o enquadramento fiscal da empresa (ME, EPP, Startup) é atendido pelo público-alvo oficial do edital.
3. **Capacidade Financeira e Contrapartida (+10 pontos):** Analisa a compatibilidade do teto orçamentário com a contrapartida mínima de 5% exigida em editais públicos.
4. **Justificativa Textual:** Gera um diagnóstico sintético destacando pontos fortes e pontos de atenção para subsidiar a tomada de decisão do consultor.

#### O Gerador de Minutas Técnicas (RF-29):
Seguindo o modelo oficial do edital **Anjo Inovador / FAPESC**, a IA sintetiza rigorosamente 11 seções estruturadas:
1. *Identificação da Empresa Proponente*
2. *Enquadramento Temático e Alinhamento com ODS (Objetivos de Desenvolvimento Sustentável)*
3. *Resumo Executivo (Pitch da Proposta)*
4. *Problema, Dor de Mercado e Justificativa do Fomento*
5. *Objeto do Projeto e Objetivos Específicos*
6. *Grau de Inovação e Maturidade Tecnológica (TRL Inicial e Final)*
7. *Metas Quantitativas e Entregáveis Verificáveis*
8. *Metodologia e Plano de Trabalho Sintético (Cronograma Macro de 12 Meses)*
9. *Plano de Aplicação de Recursos (Custeio vs Investimento e 5% de Contrapartida)*
10. *Matriz de Riscos e Planos de Contingenciamento*
11. *Impactos Socioeconômicos e Ambientais*

#### O Mecanismo de Fallback em Cascata (Resiliência Operacional):
```mermaid
flowchart TD
    Req[Consultor clica 'Estruturar Minuta'] --> Level1[Nível 1: Google Gemini 2.5 Flash]
    Level1 -->|Sucesso| Finish[Minuta Gerada com Sucesso]
    Level1 -->|Falha ou Rate Limit| Level2[Nível 2: DeepSeek / Groq]
    Level2 -->|Sucesso| Finish
    Level2 -->|Falha ou Sem Conexão| Level3[Nível 3: Motor Determinístico Local]
    Level3 --> Finish
```
- **Por que isso é fundamental na defesa?** Demonstra maturidade de arquitetura de software: o sistema é imune a falhas de APIs de terceiros. A aplicação nunca exibe tela branca ou erro 500 para o cliente.

---

### 5.6 Motor Nativo de Exportação OpenXML (.docx) e PDF

#### Por que criar um gerador OpenXML nativo (`DocumentExportService.php`)?
A maioria dos projetos acadêmicos instala bibliotecas genéricas como `phpoffice/phpword`. Nós optamos por construir a estrutura OpenXML padrão ISO/IEC 29500 pura usando o `ZipArchive` do PHP:
- **Vantagem 1:** Arquivos compactos gerados em menos de 10 milissegundos.
- **Vantagem 2:** Zero vulnerabilidades ou dívidas técnicas de pacotes externos obsoletos.
- **Vantagem 3:** Arquivos 100% compatíveis com Microsoft Word, LibreOffice Writer e Google Docs, com cabeçalhos institucionais, numeração de versão e metadados de autoria.

---

### 5.7 Frontend Desacoplado: Vanilla JS e Design System
- **Arquitetura:** Pasta `front/` 100% isolada da pasta do backend.
- **Zero Build:** Nenhum Webpack, Vite ou Babel necessário para rodar o cliente.
- **Comunicação Centralizada:** Todas as chamadas usam a `fetch` API com cabeçalho `Accept: application/json`.
- **Isolamento de Estado por Edital:** O editor e o histórico sincronizam exclusivamente com o edital selecionado na tela, impedindo vazamento de dados ou mistura de minutas entre editais concorrentes.

---

### 5.8 Deploy em Produção (HostGator/Apache) e Esteira CI/CD Mobile
- **Roteamento Unificado no Servidor:** Configuração via `.htaccess` permitindo a convivência transparente entre as páginas estáticas do frontend desacoplado e os endpoints RESTful (`/api/*`) processados pelo `public/index.php` do Laravel 12.
- **Resolução de Autenticação na API:** Registro formal da rota nomeada `name('login')` no `routes/api.php`, assegurando que requisições desprovidas de Bearer Token recebam status JSON 401 Unauthorized e nunca disparem exceções internas (`RouteNotFoundException`).
- **Automação Contínua dos Spiders:** Agendador Linux (Cron Job do cPanel) executando `php artisan schedule:run` a cada minuto, garantindo a captura periódica e autônoma dos editais da FINEP, FAPESP e FAPESC.
- **Esteira de Compilação Nativa (GitHub Actions):** Workflow CI/CD (`build-apk.yml`) operando em container Ubuntu com Java 21 Zulu, Android SDK 35 e Gradle 8.11, compilando o projeto nativo do Capacitor 7 em um binário `.apk` de ~3.57 MB e publicando-o automaticamente.
- **Distribuição Multiplataforma para a Banca:** Página pública de onboarding (`/paginas/baixar-app.html`) com QR Code dinâmico, acesso instantâneo como PWA e botão de download direto do `.apk` para smartphones Android.

---

## 6. Simulador de Perguntas & Respostas da Banca (Q&A de Arguição)

### P1: "Por que vocês não usaram um framework moderno no Frontend, como React ou Next.js?"
> **Resposta Ideal:**  
> *"Nesta primeira fase de validação tecnológica, nosso foco principal de pesquisa foi a robustez do pipeline de dados: Web Scraping resiliente, esteira Kanban e o orquestrador de IA com fallbacks. Adotar React traria overhead de build, configuração de Webpack e dependências desnecessárias para o objetivo central. Optamos por Vanilla JS puro seguindo estritamente as boas práticas do DOM moderno e da Fetch API. Como a nossa API Laravel é 100% RESTful e desacoplada, qualquer equipe de frontend pode plugar um aplicativo React ou Mobile no futuro sem alterar uma única linha de código do backend."*

---

### P2: "Se a chave da API do Gemini ou da Groq for bloqueada por limite de requisições, o sistema quebra?"
> **Resposta Ideal:**  
> *"Não. Conforme documentado na nossa ADR 003 e ADR 012, implementamos uma política de contingência em três níveis. Primeiramente, temos rotação automática de múltiplas chaves via `.env`. Caso o Gemini falhe, a requisição é interceptada e enviada ao DeepSeek. Se ambos estiverem indisponíveis ou sem internet, entra em ação o nosso Motor Determinístico Local, que compila as 11 seções da proposta usando cálculos orçamentários exatos e regras de negócio com os dados reais do banco. O usuário final nunca fica sem o documento."*

---

### P3: "Por que não usar IA generativa para ler os sites e raspar os editais?"
> **Resposta Ideal:**  
> *"Essa decisão foi formalizada na ADR 011. Realizar raspagem de 500 editais via LLM geraria um custo financeiro proibitivo e imprevisível, além de introduzir problemas de alucinação em datas e valores de orçamentos públicos. Editais exigem precisão jurídica absoluta. Por isso, isolamos a raspagem em uma arquitetura híbrida determinística com Browsershot e XPath com Custo Zero de IA, reservando os modelos LLMs exclusivamente para a etapa de redação de propostas e minutas onde a capacidade generativa agrega real valor."*

---

### P4: "Como vocês garantem a rastreabilidade e auditoria das propostas geradas?"
> **Resposta Ideal:**  
> *"Atendendo aos requisitos RF-33 e RF-34, adotamos o padrão de Versionamento Imutável. Toda edição em tela não sobrescreve o registro original; ela insere uma nova linha na tabela `minutas` incrementando o número da versão e referenciando o `parent_minuta_id`. Além disso, persistimos o carimbo de data/hora, o identificador do consultor responsável e qual modelo de IA originou a minuta inicial, garantindo governança total e conformidade com os princípios da LGPD."*

---

### P5: "Como vocês geram o arquivo .docx sem instalar o pacote PHPWord?"
> **Resposta Ideal:**  
> *"Arquivos .docx são, por especificação da ISO/IEC 29500, arquivos ZIP contendo uma estrutura de documentos XML (OpenXML). Nossa classe `DocumentExportService` instancia a extensão nativa `ZipArchive` do PHP e monta diretamente o pacote com os arquivos `[Content_Types].xml`, `_rels/.rels` e `word/document.xml`. Essa abordagem consome uma fração mínima de memória RAM, elimina dezenas de dependências de terceiros e entrega um documento leve e 100% formatado para o usuário."*

---

### P6: "Como a aplicação foi colocada em produção e como os robôs (spiders) rodam sem intervenção humana?"
> **Resposta Ideal:**  
> *"Conforme documentado na nossa ADR 015, publicamos a aplicação em servidor Apache real sob o domínio anjonexus.intalenti.com.br. Integramos um arquivo .htaccess especializado que serve os arquivos estáticos do frontend desacoplado com compressão gzip e redireciona todas as rotas RESTful para o kernel do Laravel. Para a autonomia dos spiders, configuramos uma tarefa agendada (Cron Job) no Linux do servidor executando `php artisan schedule:run` a cada minuto, garantindo que os spiders de FAPESC, FAPESP e FINEP sejam disparados conforme o cronograma do sistema sem qualquer operador manual."*

---

### P7: "Como o aplicativo mobile Android foi construído? Essa esteira ou o Capacitor violam o regulamento acadêmico contra geradores de código?"
> **Resposta Ideal:**  
> *"Não viola de forma alguma. Todo o código do aplicativo — telas, componentes, lógica de negócio, chamadas RESTful e folhas de estilo — foi escrito manualmente pela equipe em Vanilla JS, HTML5 e CSS3 (ADR 014). O Capacitor 7 funciona estritamente como um runtime de empacotamento nativo (WebView nativa acelerada por hardware), e o Gradle com o GitHub Actions atuam como ferramentas profissionais de compilação (build tools / CI-CD) padrão da indústria. Não utilizamos nenhuma plataforma 'low-code' ou 'no-code'; toda a engenharia de software e autoria intelectual pertencem integralmente à equipe."*

---

## 7. Glossário e Nomenclatura para Argumentação

Para se expressar com excelência técnica durante a apresentação, utilize os termos abaixo:

- **Subvenção Econômica:** Recurso financeiro público não-reembolsável concedido pelo governo para apoiar projetos de P&D (Pesquisa e Desenvolvimento) e inovação nas empresas.
- **Contrapartida:** Parcela financeira ou econômica que a empresa beneficiária deve aportar com recursos próprios no projeto (habitualmente de 5% a 10% do valor global).
- **TRL (Technology Readiness Level):** Escala de 1 a 9 que mede a maturidade de uma tecnologia (do TRL 1 - princípios básicos ao TRL 9 - sistema provado em ambiente operacional real).
- **ODS (Objetivos de Desenvolvimento Sustentável):** 17 metas globais estabelecidas pela ONU. O alinhamento com ODS pontua positivamente na avaliação de editais públicos.
- **Stateless:** Arquitetura onde cada requisição HTTP carrega em si toda a informação necessária para ser atendida, sem dependência de estado de sessão guardado na memória do servidor.
- **Headless Chrome:** Navegador Google Chrome executado em segundo plano sem interface gráfica, utilizado para renderizar páginas dinâmicas e capturar chamadas AJAX/SPA.
- **XPath (XML Path Language):** Linguagem de consulta que permite navegar e selecionar nós específicos em documentos XML e HTML com alta precisão semântica.
- **Fallback em Cascata:** Padrão de resiliência onde um sistema tenta uma operação primária e, em caso de erro, degrada suavemente para rotas secundárias pré-configuradas.
- **OpenXML:** Padrão aberto baseado em XML e compactação ZIP utilizado pelo pacote Microsoft Office (.docx, .xlsx).
- **CI/CD (Continuous Integration / Continuous Delivery):** Prática de automação em que alterações de código disparam testes e compilações automáticas em nuvem (ex: GitHub Actions compilando o APK Android nativo).

