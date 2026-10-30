# Explicação Detalhada do Diagrama de Atividades (Versão 005)

## 1. Visão Geral e Objetivo Acadêmico
O **Diagrama de Atividades (UML 2.5)** documenta o fluxo operacional e de controle do software sob a ótica dos papéis de negócio (**Consultor de Inovação** e **Gestor de Startup**). Ele descreve o comportamento dinâmico do sistema, mapeando a jornada desde a autenticação e cobrança até a mineração autônoma de editais, gestão na esteira Kanban, refinamento com IA em cascata e exportação de propostas no formato oficial de subvenção econômica.

Este documento consolida a revisão promovida na pasta **Artefatos - 005**, superando as limitações e defasagens identificadas nas versões 003 e 004.

---

## 2. Quadro Comparativo: O que mudou de Artefatos - 004 para 005?

| Aspecto | Versão Anterior (004) | Nova Versão (005) - Atualizada | Justificativa de Engenharia |
| :--- | :--- | :--- | :--- |
| **Nomenclatura do Kanban** | Grafado incorretamente como *"Kamban"*. | Padronizado rigorosamente como **Kanban Pipeline**. | Correção ortográfica e adequação terminológica aos padrões da indústria ágil. |
| **Propósito do Quadro Kanban** | Descrito apenas como *"CRUD genérico de metas e tarefas"*. | Mapeado como a **Esteira de Fomento e Pipeline de Editais** em 5 fases (*Triagem*, *Em Análise*, *Minuta*, *Submetido*, *Aprovado*). | Alinhamento com a regra de negócio real implementada no código (`KanbanController.php` e `kanban.html`). |
| **Integração Radar ➔ Kanban** | O fluxo pulava o Kanban e ia direto para uma geração de minuta isolada. | O consultor tria editais no Radar, analisa o **Match Inteligente** e envia o edital como Card na esteira Kanban. | Idempotência e rastreabilidade: o card passa a ser a âncora de todo o projeto de captação. |
| **Multi-tenancy B2B** | Laço confuso de seleção de perfil que não representava a arquitetura. | **Seletor Global de Startup Ativa** no cabeçalho/sidebar + tela de gestão **Minhas Startups** (EDTAN-164). | Reflete a capacidade de uma única consultoria gerenciar dezenas de startups clientes sem misturar dados. |
| **Ciclo de IA e Tolerância a Falhas** | Mencionava apenas "gerar minuta". | Mapeada a esteira de **Cascata Quádrupla de Resiliência** (Gemini ➔ DeepSeek ➔ Groq ➔ Motor Determinístico Local em <10ms). | Comprovação acadêmica do princípio de *Fault Tolerance* (RNF-02 e ADR 003). |
| **Estrutura da Minuta Técnica** | Campo aberto genérico. | Geração estruturada das **11 Seções Homologadas** do modelo oficial de inovação (Anjo Inovador/FAPESC). | Atendimento rigoroso aos requisitos da banca avaliadora e órgãos públicos. |
| **Exportação de Documentos** | Não detalhava formato nem tecnologia. | Exportação nativa para **Microsoft Word (.docx)** via ZipArchive OpenXML puro e **PDF**. | Comprovação de autonomia técnica sem dependência de pacotes externos pesados (ADR 012). |
| **Autenticação e Pagamentos** | Pagamento genérico sem fluxo de contingência. | Integração de Pix em tempo real via **AbacatePay Gateway** com tela de pooling e recuperação de senha segura (EDTAN-156). | Segurança de ponta a ponta (Laravel Sanctum + tokens temporários). |

---

## 3. As 6 Raias Operacionais do Diagrama

```
┌────────────────────────────────────────────────────────────────────────┐
│               FLUXO INTEGRADO DE ATIVIDADES - ANJO NEXUS               │
└────────────────────────────────────────────────────────────────────────┘

 [1. Acesso & Assinatura] ──> [2. Contexto Multi-Startup] ──> [3. Radar & Match]
                                                                     │
                                                                     ▼
 [6. Exportação .docx/.pdf] <── [5. Minuta IA & Cascata] <── [4. Pipeline Kanban]
```

### Raia 1: Módulo de Acesso, Planos e Autenticação
* O usuário pode optar pelo login tradicional ou recuperação de senha por link/token de uso único encaminhado ao e-mail cadastrado.
* Para novos usuários, o fluxo conduz à escolha de planos SaaS e geração imediata do QR Code Pix via **AbacatePay**. Uma tela dedicada aguarda o webhook de confirmação sem travar o navegador.
* Após autenticado, o Laravel Sanctum emite um Bearer Token para as requisições subsequentes.

### Raia 2: Contexto Multi-tenancy e Gestão de Startups
* Se o consultor não possuir nenhuma empresa cadastrada, o onboarding solicita o CNPJ. A consulta à API pública preenche instantaneamente Razão Social, CNAEs, Regime Tributário e Localização.
* O **Seletor Global** permite alternar instantaneamente entre startups. Toda a aplicação (cards do Kanban, propostas e histórico) se reconfigura para a startup ativa em memória, garantindo isolamento total de dados.

### Raia 3: Radar de Editais e Algoritmo de Match Inteligente
* O sistema consome editais previamente minerados pelos Spiders autônomos (FINEP, FAPESC e FAPESP) com custo zero de IA.
* O motor executa o algoritmo multicritério de **Match Inteligente** cruzando o perfil cadastral da startup com as regras editalícias:
  * Aderência temática de nicho (+20 pontos);
  * Elegibilidade de porte fiscal (+15 pontos);
  * Compatibilidade do teto orçamentário (+10 pontos).
* O consultor analisa o diagnóstico com pontos fortes e de atenção. Ao validar a oportunidade, um clique envia o edital para o pipeline da startup.

### Raia 4: Esteira Kanban de Fomento e Gestão de Documentos
* O edital ingressa como um Card na coluna de **Triagem / Backlog**.
* O consultor move o card de acordo com o ciclo de vida da captação (*Em Análise*, *Minuta em Elaboração*, *Submetido*, *Aprovado*). A ordenação é persistida via API REST (`PATCH /api/kanban/cards/{id}/mover`).
* No modal do card, é possível anexar CNDs, certidões e planilhas orçamentárias (arquivos de até 10MB persistidos no Storage e salvos na coluna `metadata` JSON) e acionar a elaboração da minuta técnica.

### Raia 5: Geração e Refinamento de Minutas Técnicas com IA
* A IA recebe os dados da startup ativa, as diretrizes do edital e o pitch da ideia proposto pelo consultor.
* Para garantir **100% de disponibilidade**, a esteira dispara a cascata de contingência: se as APIs de nuvem sofrerem *Rate Limit* ou queda de rede, o **Motor Determinístico Local** gera a proposta matematicamente no PHP em menos de 10 milissegundos.
* O texto preenche as 11 seções técnicas obrigatórias. O usuário refina o conteúdo no editor e salva versões auditáveis no banco de dados.

### Raia 6: Exportação de Documentos Oficiais
* A minuta aprovada é compilada nativamente pelo motor OpenXML (`DocumentExportService.php`), gerando arquivos `.docx` perfeitamente formatados para Microsoft Word e LibreOffice, além de versão em PDF.
* A proposta está pronta para protocolo oficial no portal do órgão de fomento.

---

## 4. Rastreabilidade com Requisitos de Engenharia de Software

| Requisito | Descrição | Onde está contemplado no Diagrama |
| :--- | :--- | :--- |
| **RF-01** | Mineração automatizada de editais | Raia 3 (Ingestão pelos Spiders) |
| **RF-03** | Cadastro e gestão de dados da startup | Raia 2 (Onboarding e Consulta CNPJ) |
| **RF-28** | Pipeline visual de oportunidades (Kanban) | Raia 4 (Quadro Kanban com 5 colunas e movimentação) |
| **RF-29** | Geração automatizada de proposta técnica | Raia 5 (Minutas com IA e 11 seções) |
| **RF-47** | Cálculo de Match e aderência de editais | Raia 3 (Score ponderado e diagnóstico semântico) |
| **RNF-02** | Tolerância a Falhas e Custo Zero de IA | Raia 5 (Cascata Gemini ➔ DeepSeek ➔ Groq ➔ Determinístico) |
| **RNF-06** | Usabilidade e Operação Híbrida Mobile | Raia 4 (Suporte a eventos touch e drag-and-drop) |

