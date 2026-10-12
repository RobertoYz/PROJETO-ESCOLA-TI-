# Guia de Código: Módulo de IA, Geração de Minutas e Exportações (EDTAN-122)

Este guia documenta a arquitetura, funcionamento técnico e fluxo de dados do **Módulo de Inteligência Artificial e Documentos** implementado na task **EDTAN-122** (Sprint 5).

---

## 1. Visão Geral da Arquitetura

O módulo atende aos requisitos funcionais **RF-29**, **RF-30**, **RF-31**, **RF-32**, **RF-32.1**, **RF-33**, **RF-34** e **RF-47**.
A arquitetura é dividida em quatro pilares fundamentais:

```
[ Frontend: kanban.html ]
      │
      ├──> POST /api/minutas/gerar ─────────> MinutaController::gerar()
      │                                             │
      │                                             ├──> MinutaAiService (Prompt 11 Seções)
      │                                             │       ├──> Gemini 2.5 Flash
      │                                             │       ├──> DeepSeek / Groq (Fallback 1)
      │                                             │       └──> Motor Determinístico (Fallback 2)
      │                                             │
      │                                             └──> Minuta::create() (Persistência no BD)
      │
      ├──> PUT /api/minutas/{id} ───────────> MinutaController::atualizar() (Versionamento RF-30)
      │
      ├──> GET /api/minutas?edital_id={id} ──> MinutaController::index() (Filtro por Edital RF-33)
      │
      ├──> GET /api/minutas/{id}/exportar-* ─> DocumentExportService
      │                                             ├──> Exportação Word .docx (OpenXML nativo)
      │                                             └──> Exportação PDF/HTML para impressão
      │
      └──> POST /api/ia/match-editais ──────> MinutaAiService::calcularMatchEditais() (RF-47)
```

---

## 2. Camada de Negócio (`app/Services`)

### 2.1 `MinutaAiService.php`
Centraliza a orquestração de Inteligência Artificial para elaboração documental e compatibilidade de editais.

- **`gerarMinuta(Startup $startup, Edital $edital, string $ideiaProjeto): array`**
  - Monta o contexto enriquecido da empresa (CNPJ, razão social, porte, regime tributário, nicho, pitch) e do edital (título, órgão, diretrizes, teto orçamentário).
  - Envia um prompt estruturado exigindo retorno em JSON padronizado com **11 seções oficiais** extraídas do modelo institucional dos editais (Anjo Inovador / FAPESC / FINEP):
    1. *Identificação da Empresa Proponente*
    2. *Enquadramento Temático e Alinhamento com ODS*
    3. *Resumo Executivo (Pitch da Proposta)*
    4. *Problema, Dor de Mercado e Justificativa*
    5. *Objeto do Projeto e Objetivos Específicos*
    6. *Grau de Inovação e Maturidade Tecnológica (TRL)*
    7. *Metas a Serem Atingidas e Produtos Entregáveis*
    8. *Metodologia e Plano de Trabalho Sintético*
    9. *Recursos Solicitados, Contrapartida e Orçamento (5% de contrapartida obrigatória)*
    10. *Matriz de Riscos e Plano de Contingenciamento*
    11. *Impactos Socioeconômicos e Ambientais*
  - **Mecanismo de Cascata (Custo Zero / Alta Disponibilidade):**
    1. Tenta **Gemini 2.5 Flash** (Google API).
    2. Em caso de falha ou rate limit, aciona **DeepSeek / Groq**.
    3. Se ambas as APIs estiverem offline ou sem chave válida, ativa o **Motor Determinístico Local**, que sintetiza as 11 seções matematicamente com os dados reais do banco sem quebrar a experiência do usuário.

- **`calcularMatchEditais(Startup $startup, $editais): array` (RF-47)**
  - Executa a análise multicritério de compatibilidade (nicho, porte e teto orçamentário).
  - Retorna uma pontuação de 45% a 98% e sintetiza uma justificativa textual com pontos fortes e pontos de atenção.

---

### 2.2 `DocumentExportService.php`
Responsável pela geração de arquivos físicos nos formatos oficiais para submissão em órgãos de fomento.

- **`gerarDocx(Minuta $minuta): string` (RF-32)**
  - Gera um arquivo Microsoft Word (`.docx`) válido utilizando **OpenXML puro** com a extensão `ZipArchive` do PHP.
  - Dispensa pacotes externos pesados (como `phpoffice/phpword`), montando os arquivos `[Content_Types].xml`, `_rels/.rels`, `word/_rels/document.xml.rels` e `word/document.xml`.
  - Formata títulos, divisores, cabeçalhos, metadados de versão e parágrafos com estilos limpos compatíveis com o Microsoft Word e LibreOffice.

- **`gerarPdf(Minuta $minuta): string` (RF-32.1)**
  - Gera um layout HTML/CSS profissional com tipografia limpa, tabelas de metadados, caixas de destaque e comandos de quebra de página `@media print` prontos para renderização ou impressão direta em PDF.

---

## 3. Camada de Controle (`app/Http/Controllers/MinutaController.php`)

Controlador RESTful responsável pela validação, auditoria e controle de versionamento:

- **`gerar()`**: Valida os inputs obrigatórios (`startup_id`, `edital_id`, `ideia_projeto`), aciona o `MinutaAiService`, registra a minuta inicial (`versao: 1`) e loga a auditoria em conformidade com o **RF-34**.
- **`atualizar()` (RF-30)**: Recebe edições do editor de tela. Se o texto foi modificado, arquiva a versão anterior e gera uma nova versão filha (`versao = versao + 1`), mantendo rastreabilidade total de autoria e horário.
- **`index()` (RF-33)**: Lista as minutas cadastradas permitindo filtro estrito por `edital_id` e `startup_id`.
- **`exportarDocx()` e `exportarPdf()`**: Stream de download com cabeçalhos HTTP adequados (`application/vnd.openxmlformats-officedocument.wordprocessingml.document` e `text/html`).

---

## 4. Camada de Dados (`app/Models/Minuta.php` e Migrations)

A tabela `minutas` armazena os seguintes campos essenciais:
- `startup_id`, `edital_id`, `user_id`, `kanban_card_id`
- `versao` (inteiro autoincremental por proposta)
- `parent_minuta_id` (auto-relacionamento para árvore de versões filhas)
- `titulo_projeto`, `ideia_projeto`
- `conteudo_gerado` (texto integral compilado)
- `secoes_estruturadas` (coluna JSON com as 11 seções individuais)
- `status` (`rascunho`, `em_revisao`, `concluido`)
- `modelo_ia_usado`, `autor_nome` (campos auditáveis)

---

## 5. Camada Frontend (`front/kanban.html`)

A interface foi construída em Vanilla JS sem dependências externas:
- **Editor Interativo em Tela (RF-30):** Exibe a proposta estruturada para leitura, refinamento e expansão de texto.
- **Cópia em 1 Clique (RF-31):** Utiliza a API moderna `navigator.clipboard.writeText()` com fallback para `document.execCommand('copy')`.
- **Isolamento Estrito por Edital:** Ao selecionar um edital no seletor, o frontend requisita apenas as minutas daquele edital via `/api/minutas?edital_id={id}`. Minutas de editais diferentes nunca se misturam.
- **Prevenção de Recarga Involuntária:** Todos os botões de ação utilizam `type="button"` e manipulação de eventos via `preventDefault()`.
