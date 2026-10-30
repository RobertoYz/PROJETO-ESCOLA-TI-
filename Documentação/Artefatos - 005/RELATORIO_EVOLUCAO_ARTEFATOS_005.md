# Relatório de Revisão e Evolução dos Artefatos de Software (Versão 005)

**Data de Emissão:** 30 de Outubro de 2026  
**Demanda Vinculada:** Jira [EDTAN-217] - Revisar Diagrama de Atividades e Matriz de Artefatos  
**Projeto:** Anjo Nexus - Plataforma de Inteligência e Fomento a Startups  

---

## 1. Contexto e Motivação
Com a conclusão e homologação dos módulos recentes no repositório oficial:
- **EDTAN-156:** Módulo Seguro de Recuperação de Senha por Token / E-mail;
- **EDTAN-157:** Módulo de Edição de Perfil de Consultor e Startup;
- **EDTAN-164:** Gestão Multi-tenant de Startups na Tela Principal e Seletor Global;
- **EDTAN-178 / EDTAN-209:** Módulo Mobile Híbrido com Capacitor 7 e Esteira CI/CD;
- **EDTAN-183 / EDTAN-184:** Gestão de Anexos até 10MB e Metadados Flexíveis no Kanban;
- **EDTAN-208:** Integração Real com Gateway de Pagamento Pix AbacatePay;
- **EDTAN-210:** Ergonomia Mobile, Botões Touch no Kanban e Viewport Estrito sem transbordamento;

Constatou-se a necessidade de auditar todos os artefatos de Engenharia de Software da pasta anterior (`Artefatos - 004`) e consolidar a **versão definitiva na pasta `Artefatos - 005`**, alinhando a documentação estritamente com o código-fonte em produção.

---

## 2. Auditoria e Diagnóstico dos Artefatos

### 2.1 Diagrama de Atividades (Status: Revisado e Reconstruído)
* **Diagnóstico da Versão 004:**
  * Grafia desatualizada *"Tela Kamban"*;
  * Quadro Kanban descrito como simples gerenciador de tarefas genéricas;
  * Ausência da esteira de fomento com as 5 colunas (*Triagem*, *Em Análise*, *Minuta*, *Submetido*, *Aprovado*);
  * Omissão do fluxo de **Match Inteligente** no Radar conectando ao Kanban;
  * Omissão da **Cascata de Resiliência de IA** (Gemini ➔ DeepSeek ➔ Groq ➔ Motor Determinístico Local);
  * Omissão do **Seletor Global de Startup Ativa** (Multi-tenancy).
* **Ações Executadas na Versão 005:**
  * Criação do arquivo `DIAGRAMA_DE_ATIVIDADE.mmd` contemplando as 6 raias operacionais de ponta a ponta;
  * Documento analítico `EXPLICACAO_DIAGRAMA_ATIVIDADE.md` demonstrando a rastreabilidade com RF-01, RF-03, RF-28, RF-29, RF-47, RNF-02 e RNF-06;
  * Alinhamento estrito com os casos de uso de consultoria B2B.

---

### 2.2 Matriz de ADRs (Status: 100% OK e Expandida para 17 ADRs)
A pasta `ADR/` foi totalmente consolidada e catalogada no `ADR/README.md`:
* **ADR 001 a 015:** Revisadas e validadas (Desacoplamento API-First, Ingestão Custo Zero de IA, Browsershot Liferay, ZipArchive OpenXML nativo, Capacitor 7, CI/CD GitHub Actions).
* **Nova ADR 016:** Gestão Multi-tenant B2B e Seletor Global Reativo de Startups (`seletorGlobalStartup.js`).
* **Nova ADR 017:** Resiliência Touch e Ergonomia Mobile no Pipeline Kanban e Radar (solução para limitação de Drag-and-Drop em telas touch).

---

### 2.3 Design de Telas (Status: Atualizado com as 15 Telas Reais de Produção)
A pasta `DESIGN TELAS/` em `Artefatos - 004` continha rascunhos antigos desatualizados (`busca_editais(1).html`, `selecao_perfis_anjonexus.html`).  
Na pasta `Artefatos - 005/DESIGN TELAS/`, foram integradas as **15 interfaces reais** atualmente em execução na pasta `MVPs/Versão Atual/front/paginas/`:
1. `inicio.html` (Landing Page Institucional)
2. `login.html` (Autenticação com JWT/Sanctum)
3. `cadastro.html` (Onboarding do Consultor)
4. `selecionar-plano.html` (Checkout SaaS)
5. `pagamento-pendente.html` (Pooling Pix AbacatePay)
6. `esqueci-senha.html` (Recuperação de Acesso)
7. `redefinir-senha.html` (Redefinição de Senha por Token)
8. `cadastrar-startup.html` (Cadastro com Consulta CNPJ)
9. `selecionar-startup.html` (Seleção Inicial de Empresa)
10. `minhas-startups.html` (Gestão de Carteira B2B)
11. `nova-startup-painel.html` (Cadastro Rápido via Painel)
12. `perfil-startup.html` (Edição de TRL, Nicho e Pitch)
13. `RadarEditais.html` (Mineração e Match Inteligente)
14. `kanban.html` (Esteira Kanban, Upload 10MB e Minuta IA)
15. `baixar-app.html` (Distribuição do APK Android)

---

### 2.4 Diagramas de Sequência (Status: 100% OK)
Preservados os 8 fluxos de interação com seus respectivos códigos fonte Mermaid e arquivos vetoriais SVG:
* `DS-00-GERAL.mmd`
* `DS-01-AUTENTICACAO.mmd`
* `DS-02-ASSINATURA.mmd`
* `DS-03-CADASTRO-STARTUP.mmd`
* `DS-04-SCRAPING.mmd`
* `DS-05-OCR-PDF.mmd`
* `DS-06-MATCH.mmd`
* `DS-07-KANBAN.mmd`
* `DS-08-MINUTA-IA.mmd`

---

### 2.5 Diagramas Estruturais: DER e Classes (Status: 100% OK)
* `DIAGRAMA DER/`: Reflete a integridade relacional de Users, Agencias, Startups, Editais, KanbanBoards, KanbanColumns, KanbanCards (com campo flexível `metadata` JSON) e Minutas (com versionamento `parent_id`).
* `DIAGRAMA DE CLASSE/`: Modela a separação de responsabilidades entre Controllers, Models Eloquent e Services especializados (`MinutaAiService`, `PdfExtractorService`, `DocumentExportService`, `AbacatePayService`).

---

## 3. Estrutura de Pastas de `Artefatos - 005`

```
PROJETO-ESCOLA-TI/
└── Documentação/
    └── Artefatos - 005/
        ├── ADR/                                (17 Decisões Arquiteturais documentadas + README)
        ├── Analise swot,gut,duplodiamante,raci/ (Matrizes estratégicas de negócio)
        ├── CASOS DE USO/                       (Especificação dos 44 Casos de Uso)
        ├── DESIGN TELAS/                       (15 Telas reais de produção HTML5/CSS3)
        ├── DIAGRAMA DE ATIVIDADE/              (Diagrama Mermaid revisado, SVG e Explicação)
        ├── DIAGRAMA DE CLASSE/                 (Modelo estrutural orientado a objetos)
        ├── DIAGRAMA DE SEQUENCIA/              (8 fluxos detalhados em Mermaid e SVG)
        ├── DIAGRAMA DER/                       (Modelo Entidade-Relacionamento lógico e físico)
        ├── ESPECIFICAÇÃO REQUISITOS/           (Documento formal de requisitos funcionais e não-funcionais)
        └── RELATORIO_EVOLUCAO_ARTEFATOS_005.md (Este documento de auditoria e conformidade)
```

---

## 4. Conclusão e Próximos Passos
A pasta **`Artefatos - 005`** passa a ser a referência definitiva e incontestável do projeto para a apresentação à **Banca Avaliadora do TCC / Escola de TI**. 

O ticket **EDTAN-217 ("Revisar Diagrama de Atividade")** pode ser movido com segurança para o status **Concluído (Done)** no Jira.

