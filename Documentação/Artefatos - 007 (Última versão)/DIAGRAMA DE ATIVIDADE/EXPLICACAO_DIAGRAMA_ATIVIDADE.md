# Explicação Detalhada do Diagrama de Atividades (Versão 007 - Completa)

## 1. Visão Geral e Objetivo Arquitetural
O **Diagrama de Atividades (UML 2.5)** documenta o fluxo operacional e de controle do software sob a ótica dos papéis de negócio (**Consultor Administrador**, **Consultor Padrão**, **Startup Cliente** e **Administrador Global**). Ele descreve o comportamento dinâmico e integrado da plataforma **Anjo Nexus**, mapeando a jornada desde a entrada inicial e gestão financeira recorrente até a mineração autônoma de editais em âmbito nacional e estadual, esteira Kanban de 6 colunas, motor de minutas técnicas com cascata de inteligência artificial de 4 níveis, exportação documental e encerramento voluntário definitivo em conformidade com a Lei Geral de Proteção de Dados (LGPD).

Este documento consolida a evolução completa promovida na pasta **Artefatos - 007 (Última versão)**, estruturando o fluxo de engenharia em **7 Raias Operacionais** e alinhando 100% dos nós à nova especificação de requisitos e regras de negócio do projeto.

---

## 2. Quadro Comparativo: O que mudou das Versões Anteriores (005/006) para a Versão 007?

| Aspecto Operacional | Versões Anteriores (005 / 006) | Versão 007 (Última Versão - Completa) | Justificativa de Engenharia e Negócio |
| :--- | :--- | :--- | :--- |
| **Organização do Fluxo** | Dividido em 6 raias parciais. | Estruturado em **7 Raias Operacionais Integradas** (`subgraph`). | Inclusão da Raia 7 dedicada à Governança de Equipe (RBAC), Ciclo Mensal e Exclusão LGPD. |
| **Modelos de Planos** | Grafado incorretamente como *"Starter, Pro, Enterprise"*. | Padronizado rigorosamente como **Standard, Pro e Enterprise**. | Alinhamento com a matriz de planos e limites contratuais da plataforma SaaS. |
| **Cadastro e Pagamento** | Cadastro em etapa única direta. | **Pré-Cadastro em 2 Etapas**, confirmação de e-mail, aceite LGPD e opções via **PIX e Cartão de Crédito**. | Elimina registros órfãos, valida a titularidade do e-mail e garante conformidade jurídica. |
| **Ciclo de Cobrança e Renovação** | Sem definição clara de periodicidade. | **Ciclo Mensal de 30 Dias** com **+30 dias acumulativos** na renovação antecipada (habilitada nos últimos 5 dias). | Preserva o acesso ativo nos dias finais e protege contra a perda de dias de vigência. |
| **Tratativa de Inadimplência** | Tela simples de tentar novamente. | **Tela de Pagamento Pendente** com 3 rotas: conciliação ativa, troca de plano ou exclusão definitiva inicial. | Permite recuperação autônoma do lead ou liberação imediata de e-mail/CNPJ para novo cadastro. |
| **Abrangência do Radar** | Apenas portais genéricos (FINEP, FAPESC, FAPESP). | **Portais Nacionais e Estaduais** (FINEP e FAPs: SC, SP, MG, RJ, RS, BA, PE, GO, DF) com checklist de até 15 docs. | Expansão da cobertura geográfica de editais de fomento tecnológico. |
| **Cálculo de Match** | Score genérico sem limiar de disparo. | Score multicritério (Nicho +20, Porte +15, Orçamento +10) com **alerta automático quando Score >= 70%**. | Triagem inteligente de oportunidades de alta aderência operacional. |
| **Colunas do Kanban** | 5 colunas antigas (Triagem, Em Análise, etc.). | **6 Colunas Oficiais**: `Backlog`, `A fazer`, `Em elaboração`, `Bloqueado`, `Pronto para Submissão` e `Submetido`. | Padronização com a especificação de processos de submissão a editais públicos. |
| **Controle de Upload e Armazenamento** | Apenas menção a 10MB por arquivo. | Validação de **10 MB por arquivo** e **Cota Máxima de Armazenamento (MB)** por plano contratado. | Prevenção de estouro de storage e monetização baseada em limites de infraestrutura. |
| **Monitoramento de Prazos** | Apenas 72h e 24h pontuais. | Monitoramento contínuo em janela de 15 dias: **Atenção (8-15d)**, **Urgente (4-7d)** e **Crítico (72h/24h)** multicanal. | Alertas escalonados no Sino do Painel, E-mail e WhatsApp. |
| **Cotas de Minutas IA** | Sem travas prévias de consumo. | **Trava Prévia de Cota Mensal de Minutas IA** (`Standard: 3`, `Pro: 15`, `Enterprise: 50`) antes da chamada. | Proteção contra custos descontrolados de API e estímulo ao upgrade de plano. |
| **Versionamento da Minuta** | Sem regra de cobrança de IA na edição. | Edição manual com **versionamento encadeado imutável sem descontar da cota mensal de IA**. | Transparência contratual: o consultor pode refinar o texto livremente sem penalização de cotas. |
| **Perfis e Roteamento RBAC** | Foco quase exclusivo no consultor. | Roteamento explícito para **Administrador Global** (Back-Office ADM) e **Startup Cliente** (Portal White-Label). | Segregação estrita de privilégios de acesso e suporte à operação White-Label. |
| **Cancelamento de Conta** | Antigo soft-delete com dados retidos. | **Exclusão Física Transacional em Cascata sob a LGPD**, exigindo reautenticação e termo de ciência. | Garantia legal do Direito à Eliminação de Dados (Lei 13.709/2018) com liberação de e-mail e CNPJs. |

---

## 3. As 7 Raias Operacionais do Diagrama

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                      FLUXO INTEGRADO DE ATIVIDADES - ANJO NEXUS                        │
└────────────────────────────────────────────────────────────────────────────────────────┘

 [1. Acesso & Assinatura] ──────> [2. Contexto Multi-Startup] ──────> [3. Radar & Match]
            │                                                                │
            ▼                                                                ▼
 [7. Gestão RBAC & LGPD] <────── [6. Exportação .docx/.pdf] <────── [4. Pipeline Kanban]
                                                ▲                            │
                                                └──── [5. Minuta IA] <───────┘
```

### Raia 1: Acesso, Registro em 2 Etapas, Planos e Assinatura
* **Novos Usuários:** O pré-cadastro coleta os dados do titular e da agência com aceite legal dos Termos e LGPD. O sistema valida previamente a disponibilidade do e-mail e envia mensagem eletrônica de confirmação. Na segunda etapa, o consultor escolhe o plano SaaS (**Standard**, **Pro** ou **Enterprise**) e a forma de pagamento (**PIX** ou **Cartão de Crédito**), gerando cobrança com tolerância de 60 minutos e controle de idempotência.
* **Confirmação e Regularização:** A compensação financeira ativa a assinatura por um ciclo mensal de **30 dias** (+30 dias cumulativos) e emite a credencial autenticada. Em caso de pendência, a tela dedicada permite conciliar a cobrança aberta, selecionar outro plano ou cancelar o cadastro inicial para liberar o e-mail.
* **Login e Roteamento RBAC:** Usuários existentes autenticam-se com verificação de credenciais e validação da vigência de 30 dias. Usuários com assinatura vencida são retidos na regularização; usuários regulares são roteados de acordo com seu papel: o **Administrador Global** acessa o Back-Office ADM, a **Startup Cliente** entra no Portal White-Label em modo leitura, e os **Consultores** seguem para a operação.

### Raia 2: Contexto Multi-Tenancy e Gestão da Carteira de Startups
* **Onboarding e Cadastro:** Na ausência de startups cadastradas, o sistema conduz ao cadastro inicial, verificando a cota máxima permitida pelo plano (**Standard: 2**, **Pro: 10**, **Enterprise: 50**). Se houver vaga, a consulta via CNPJ com validação dos dígitos verificadores (Módulo 11), integração externa e cache de 7 dias preenche instantaneamente os dados fiscais e cadastrais.
* **Contexto Dinâmico:** No painel principal, o **Seletor Global** permite alternar instantaneamente a startup ativa sem realizar novo login. Toda a esteira operacional (Kanban, editais favoritados, histórico de minutas e diagnósticos) se reconfigura dinamicamente para a empresa em foco, assegurando isolamento multi-tenant completo. O consultor pode ainda solicitar a ressincronização cadastral sob demanda com a Receita Federal.

### Raia 3: Radar Nacional/Estadual de Editais, Favoritos e Match Inteligente
* **Mineração Contínua:** Rotinas autônomas em segundo plano varrem portais de fomento federais e estaduais (**FINEP, FAPESC, FAPESP, FAPEMIG, FAPERJ, FAPERGS, FAPESB, FACEPE, FAPEG e FAPDF**), realizam a leitura de anexos normativos em PDF e geram checklists interativos de até 15 documentos.
* **Algoritmo de Match:** O motor cruza os metadados da oportunidade com o perfil da startup ativa, atribuindo pontuações objetivas para Nicho (+20), Porte (+15) e Orçamento (+10). Quando a aderência atinge o limiar satisfatório (**Score >= 70%**), o sistema emite alertas automáticos (Painel, E-mail e WhatsApp).
* **Filtros e Ações:** O consultor aplica filtros combinados, visualiza detalhes e pode favoritar a oportunidade (vinculando-a unicamente à startup ativa) ou despachá-la diretamente para o pipeline Kanban.

### Raia 4: Esteira Pipeline Kanban, Limites de Upload e Alertas de Prazo
* **6 Colunas Oficiais:** O processo tramita pelas fases padronizadas de **Backlog**, **A fazer**, **Em elaboração**, **Bloqueado**, **Pronto para Submissão** e **Submetido**, com movimentação suportada por ordenação fracionária contínua.
* **Documentos e Cotas de Armazenamento:** No modal do cartão, a equipe realiza o upload de certidões e planilhas orçamentárias, com validação rigorosa do limite de **10 MB por arquivo** e da capacidade acumulada de armazenamento em megabytes contratada no plano.
* **Monitoramento Contínuo de Prazos:** O agendador monitora as datas de encerramento em uma janela de até 15 dias, classificando os prazos de forma escalonada em **Atenção (8 a 15 dias)**, **Urgente (4 a 7 dias)** e **Crítico (72h e 24h finais)**, disparando notificações internas, e-mails e mensagens no WhatsApp.

### Raia 5: Motor de Minutas Técnicas IA, Controle de Cota e Versionamento
* **Trava Prévia de Cota:** Antes de iniciar a redação, o sistema consulta a cota mensal de minutas geradas por IA do plano (**Standard: 3**, **Pro: 15**, **Enterprise: 50**). Caso o limite tenha sido atingido, a operação é bloqueada com aviso de upgrade.
* **Cascata Multi-LLM de 4 Níveis:** Com cota disponível, o roteador inteligente sorteia chaves e executa a contingência em quatro níveis de resiliência (**1º Google Gemini ➔ 2º DeepSeek ➔ 3º Groq ➔ 4º Motor Determinístico Local em menos de 10ms**), garantindo 100% de disponibilidade.
* **Estruturação e Versionamento:** A minuta é formatada nas **11 Seções Oficiais Homologadas** (com cálculo automático de contrapartida de 5% e cronograma de 12 meses). O consultor edita o texto livremente no editor Rich-Text e salva revisões em **árvore de versionamento encadeado imutável**, sem cobrança adicional de cotas de IA. O histórico permite consultar ou restaurar versões passadas a qualquer momento.

### Raia 6: Exportação Documental Nativa (.DOCX e .PDF A4)
* **Compilação Oficial:** A proposta finalizada pode ser exportada para **Microsoft Word (.docx)** nativo (via OpenXML formatado com cabeçalhos profissionais) ou para **PDF estruturado em padrão de folha A4**, pronto para protocolo oficial.
* **Conclusão:** O arquivo é descarregado diretamente no dispositivo do usuário e o processo é marcado como concluído, permitindo à equipe retornar ao Kanban para acompanhar a fase de submissão externa.

### Raia 7: Gestão de Equipe, Ciclo de Assinatura (30 Dias) e Encerramento LGPD
* **Governança de Equipe (RBAC):** O Consultor Administrador gerencia os assentos de equipe (**Standard: 1**, **Pro: 5**, **Enterprise: 15**), cadastrando, editando ou revogando acessos de Consultores Padrão e credenciais de visualização de Startups clientes.
* **Ciclo de Assinatura e Renovação:** O painel exibe o consumo de cotas e a contagem regressiva da vigência de 30 dias. Nos **últimos 5 dias antes do vencimento** (ou caso a assinatura esteja vencida), o sistema dispara alertas e libera o botão de renovação antecipada. A quitação adiciona **+30 dias acumulativos** à vigência sem interrupção de acesso. O administrador pode ainda solicitar Upgrade (com cálculo pró-rata) ou Downgrade (validando previamente se o uso atual cabe nas cotas inferiores).
* **Encerramento Voluntário e LGPD:** Na Zona de Perigo, o Consultor Administrador pode solicitar a exclusão definitiva da conta. O sistema exige a confirmação criptográfica da **senha atual** e o aceite formal do termo de ciência em **modal próprio**. Ao confirmar, executa a **eliminação física e irreversível em cascata** de todos os registros da agência, startups, cartões, minutas e equipe em uma única transação atômica, revogando todas as sessões e liberando imediatamente o e-mail e os CNPJs para futuros cadastros.

---

## 4. Matriz de Rastreabilidade com os Requisitos de Engenharia (Versão 007)

A tabela abaixo correlaciona as etapas modeladas no Diagrama de Atividades com a especificação formal de requisitos do projeto (`ESPECIFICACAO_DE_REQUISITOS.md`):

| Requisitos Mapeados | Descrição da Regra de Negócio / Restrição | Raia Operacional Contemplada |
| :--- | :--- | :--- |
| **RF-01, RF-99, RF-100** | Pré-cadastro em duas etapas, validação e confirmação de e-mail e aceite obrigatório dos Termos de Uso e LGPD. | **Raia 1** (Acesso, Registro em 2 Etapas, Planos e Assinatura) |
| **RF-11, RF-12, RF-13, RNF-23, RNF-24** | Encerramento voluntário de conta ativa com reautenticação de senha, modal próprio e exclusão física transacional em cascata (LGPD). | **Raia 7** (Gestão de Equipe, Ciclo de Assinatura e Encerramento LGPD) |
| **RF-14 a RF-27, RF-101 a RF-103** | Modelos de planos (Standard, Pro, Enterprise), travas de cotas, ciclo de 30 dias, renovação em <= 5 dias (+30d acumulativos), PIX/Cartão e Upgrade/Downgrade. | **Raias 1, 2 e 7** (Planos, Pagamentos e Governança de Assinatura) |
| **RF-28 a RF-37, RNF-04, RNF-18** | Controle de acesso baseado em papéis (RBAC), cotas de assentos de equipe e Portal White-Label Somente Leitura da Startup. | **Raias 1, 2 e 7** (Roteamento de Perfis e Gestão de Usuários) |
| **RF-38 a RF-48, RF-94, RF-96, RNF-12** | Gestão multi-startup, seletor de contexto dinâmico e busca automatizada por CNPJ com validação Módulo 11 e cache de 7 dias. | **Raia 2** (Contexto Multi-Tenancy e Gestão de Startups) |
| **RF-49 a RF-59, RNF-06** | Mineração automatizada de portais federais e estaduais (FINEP e FAPs), filtros combinados e favoritos exclusivos por startup. | **Raia 3** (Radar Nacional/Estadual de Editais e Favoritos) |
| **RF-60 a RF-67, RF-104, RF-105** | Pipeline Kanban de 6 colunas, limite de 10 MB por arquivo e cota de upload, com alertas de prazos em 72h/24h via Painel, E-mail e WhatsApp. | **Raia 4** (Esteira Pipeline Kanban e Gestão de Prazos) |
| **RF-68 a RF-84, RF-97, RNF-11, RNF-19, RNF-22** | Extração de anexos PDF, Score de Match >= 70%, trava de cota mensal de IA, cascata Multi-LLM de 4 níveis, versionamento imutável e exportação .DOCX/.PDF. | **Raias 3, 5 e 6** (Match, Motor de Minutas IA e Exportação Nativa) |
| **RF-85 a RF-88** | Painel Back-Office exclusivo para Administrador Global com métricas de consumo de IA, assinaturas ativas e faturamento. | **Raia 1** (Roteamento de Acesso e Painel ADM) |
