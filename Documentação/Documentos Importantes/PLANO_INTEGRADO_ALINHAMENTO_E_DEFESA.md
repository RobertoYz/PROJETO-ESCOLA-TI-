# 🎯 Plano Integrado de Ação: Alinhamento Documental e Arquitetural
## Conectando Diagramas, Especificações, Código Real e Apresentação para a Banca

> **Base do Planejamento:**  
> Este documento integra o diagnóstico do [`PLANO_ALINHAMENTO_DOCS_E_ARTEFATOS.md`](file:///c:/Users/Usuário/Downloads/PROJETO-ESCOLA-TI-/Documentação/Documentos%20Importantes/PLANO_ALINHAMENTO_DOCS_E_ARTEFATOS.md), o manual para arguição [`MANUAL_DE_ESTUDO_BANCA.md`](file:///c:/Users/Usuário/Downloads/PROJETO-ESCOLA-TI-/Documentação/Documentos%20Importantes/MANUAL_DE_ESTUDO_BANCA.md) e as decisões consolidadas de arquitetura (ADRs 001 a 012).

---

## 📌 Visão em 3 Fases de Execução

```
┌────────────────────────────────────────────────────────────────────────┐
│                   PLANO INTEGRADO DE ALINHAMENTO GERAL                 │
└────────────────────────────────────────────────────────────────────────┘

 [ FASE 1: ARTEFATOS GRÁFICOS (.MMD) ]
    ├── 1.1 Atualizar DER (Agência, Plano, Minuta em 11 seções com parent_id)
    ├── 1.2 Atualizar DCL (MinutaAiService, DocumentExportService, KanbanCard)
    └── 1.3 Atualizar DS (DS-08 Fallback 3 níveis, DS-07 Rotas REST do Kanban)
                             │
                             ▼
 [ FASE 2: ESPECIFICAÇÃO TEXTUAL & REQUISITOS ]
    ├── 2.1 Readequar RF-05 (Substituir OCR por Parser Vetorial + Regex)
    ├── 2.2 Readequar RF-29 e RF-30 (11 seções temáticas e versionamento)
    └── 2.3 Readequar RF-32 (Exportação nativa OpenXML .docx ISO 29500)
                             │
                             ▼
 [ FASE 3: UX E ROTEIRO DE DEMONSTRAÇÃO DA BANCA ]
    ├── 3.1 Fluxo Contínuo: Login ➔ Radar ➔ Kanban ➔ Minuta
    ├── 3.2 Sincronização Estrita da Minuta por Edital (RF-33 e RF-34)
    └── 3.3 Simulação de Perguntas da Banca (Q&A de Arguição Técnica)
```

---

## 🚀 FASE 1: Atualização dos Diagramas Mermaid (.mmd)

As atualizações devem ser aplicadas nos arquivos das pastas:
- `Documentação/Artefatos - 003/`
- `Documentação/Artefatos - 004/DIAGRAMA DE SEQUENCIA/Codigo mermaid/`
- `Documentação/Artefatos - 004/DIAGRAMA DER/`
- `Documentação/Artefatos - 004/DIAGRAMA DE CLASSE/`

### 1.1 Diagrama Entidade-Relacionamento (`DIAGRAMA DER DEFINITIVO.mmd`)
* **O que alinhar:**
  1. Inserir a entidade `Agencia` intermediando `Plano` e `Usuario`/`Startup` (Multi-Tenancy SaaS B2B).
  2. Ajustar `KanbanBoard 1 -> * KanbanColumn 1 -> * KanbanCard`.
  3. Atualizar `Minuta` com auto-relacionamento de versões imutáveis (`parent_minuta_id`).
* **Código Mermaid de Referência:** Seção 4.1 do `PLANO_ALINHAMENTO_DOCS_E_ARTEFATOS.md`.

### 1.2 Diagrama de Classes (`DIAGRAMA DE CLASSE Definitivo.mmd`)
* **O que alinhar:**
  1. Renomear classes conceituais para as classes que existem no backend Laravel:
     - `MinutaAiService` (com `gerarMinuta()` e `calcularMatchEditais()`).
     - `DocumentExportService` (com `gerarDocx()` e `gerarPdf()`).
     - `PdfExtractorService` (com `extrairTexto()` e `parseDatasRegex()`).
     - `AbacatePayService` (cobrança e liquidação Pix).
* **Código Mermaid de Referência:** Seção 4.2 do `PLANO_ALINHAMENTO_DOCS_E_ARTEFATOS.md`.

### 1.3 Diagramas de Sequência (`DS-08-MINUTA-IA.mmd` e `DS-07-KANBAN.mmd`)
* **`DS-08-MINUTA-IA.mmd`:**
  - Substituir a anotação de "Streaming WebSocket" pelo fluxo real de **Fallback em Cascata de Três Níveis**:
    `Nível 1 (Gemini 2.5 Flash)` ➔ `Nível 2 (DeepSeek / Groq)` ➔ `Nível 3 (Motor Determinístico Local)`.
  - Incluir a exportação nativa Word (.docx) via `DocumentExportService`.
* **`DS-07-KANBAN.mmd`:**
  - Ajustar as rotas reais da API: `POST /api/kanban/cards`, `PATCH /api/kanban/cards/{id}/mover` e `DELETE /api/kanban/cards/{id}`.

---

## 📝 FASE 2: Especificação de Requisitos e Relatório Técnico

Atualizar o documento em `Documentação/Artefatos - 004/ESPECIFICAÇÃO REQUISITOS`:

1. **RF-05 (Extração de Dados do Edital):**
   - *Correção:* Substituir a menção de "OCR por visão computacional" por **"Extração semântica vetorial e Expressões Regulares (Regex) com Custo Zero de IA"** (conforme ADR 006 e ADR 011).
2. **RF-29 (Gerador de Minutas):**
   - *Correção:* Explicitar que a minuta atende ao modelo institucional oficial de **11 seções temáticas** (padrão Anjo Inovador / FAPESC / FINEP).
3. **RF-32 (Exportação Word):**
   - *Correção:* Registrar que a exportação `.docx` utiliza **OpenXML nativo puro (ISO/IEC 29500)** compilado com `ZipArchive` do PHP sem pacotes externos pesados (conforme ADR 012).
4. **RF-33 e RF-34 (Histórico e Auditoria):**
   - *Correção:* Registrar o padrão de **Versionamento Imutável** com vínculo estrito por edital (`edital_id`), autor auditável e modelo de IA utilizado.

---

## 🖥️ FASE 3: Experiência da Interface e Demonstração para a Banca

Para a banca, a usabilidade e a fluidez entre as telas demonstram o domínio da equipe sobre o ciclo de vida do software:

### 3.1 Sequência Operacional Unificada:
1. **Login:** Entra direto no Radar de Editais (eliminando o `inicio.html` intermediário).
2. **Radar de Editais (`RadarEditais.html`):**
   - Consultor visualiza o edital, pontuação de match (%) e clica em **"Candidatar & Estruturar Proposta"**.
3. **Pipeline Kanban (`kanban.html`):**
   - O card já aparece na esteira de acompanhamento.
   - Clicar no card abre imediatamente o **Estúdio de Minuta** pré-carregado com os dados daquele edital específico.
4. **Estúdio de Minuta (11 Seções):**
   - Edição em tela (RF-30).
   - Cópia em 1 clique (RF-31).
   - Download imediato em Word `.docx` formatado (RF-32) e PDF (RF-32.1).

---

## 🛡️ Guia Rápido de Arguição (Perguntas Frequentes da Banca)

Tenha estas respostas na ponta da língua (detalhadas na Seção 6 do `MANUAL_DE_ESTUDO_BANCA.md`):

1. **"Por que há diferenças entre o DER do início e o banco atual?"**  
   *Resposta:* O projeto adotou desenvolvimento ágil. Na Sprint 3, evoluímos de monousuário para SaaS Multi-tenant B2B através da entidade `Agencia`, e na Sprint 5 estruturamos a `Minuta` em 11 seções temáticas com versionamento imutável (ADR 012).
2. **"Por que não usaram OCR para ler os PDFs?"**  
   *Resposta:* Editais públicos são documentos digitais pesquisáveis. Usar OCR gastaria tempo excessivo de CPU. Optamos por extração vetorial com Regex determinístico (ADR 006), alcançando 100% de precisão com Custo Zero.
3. **"Se a API do Gemini cair durante a apresentação, o sistema quebra?"**  
   *Resposta:* Não. Implementamos tolerância a falhas com fallback de três níveis: Gemini ➔ DeepSeek ➔ Motor Determinístico Local offline.
