# CONTEXTO_ARQUITETURA.md — Memória Consolidada e Fonte Única da Verdade (Anjo Nexus)

> **Propósito deste Documento:** Este arquivo é a **Fonte Única da Verdade (Single Source of Truth)** da arquitetura, esquema de banco de dados, contratos de API e regras de negócio extraídas diretamente por engenharia reversa do código-fonte real do projeto **Anjo Nexus** (`MVPs/Versão Atual/`).

---

## SEÇÃO 1: Diretrizes Operacionais para o Agente de IA

1. **Protocolo de Leitura Direcionada (Zero Buscas Cegas):**
   - **SEMPRE** leia este arquivo `CONTEXTO_ARQUITETURA.md` antes de iniciar qualquer tarefa, refatoração, correção de bug ou criação de teste.
   - Utilize o **Mapa Completo de Arquivos (Seção 2)** e o **Catálogo de Endpoints (Seção 5)** para abrir diretamente os arquivos exatos necessários, evitando buscas cegas (`grep`, `find`) ou leitura de diretórios ignorados (`vendor/`, `node_modules/`, `.git/`, `.css`, imagens, fontes ou logs).

2. **Regra Estrita de Migrations (`migrate:fresh --seed`):**
   - O ambiente utiliza reconstrução limpa de banco (`php artisan migrate:fresh --seed`).
   - **É PROIBIDO criar migrations incrementais** (`add_*_to_*_table.php`, `alter_*`, etc.).
   - Toda alteração estrutural de banco de dados (novas colunas, índices, chaves estrangeiras ou alteração de enums) **deve ser feita diretamente na migration original `create_*_table.php` correspondente**.

3. **Contrato Padronizado de Respostas JSON (`message`):**
   - Todas as respostas JSON da API (sucesso, validação ou erro) utilizam **exclusivamente a chave `'message'`** (nunca `'mensagem'`, com a única exceção legada da rota `GET /api/auth/login` em `routes/api.php`).
   - Códigos de erro de domínio para interceptação no Front-end seguem chaves padronizadas como `'codigo' => 'PAGAMENTO_PENDENTE'` / `'codigo_erro' => 'PAGAMENTO_PENDENTE'` (HTTP `403`) e `'codigo' => 'LIMITE_MINUTAS_ATINGIDO'` (HTTP `403`).

4. **Sincronização Obrigatória Web / Mobile (Capacitor Android):**
   - O aplicativo Android utiliza Capacitor apontando para `MVPs/Versão Atual/mobile/www/`.
   - Qualquer alteração realizada em componentes globais ou serviços no Front-end Web (especialmente `MVPs/Versão Atual/front/scripts/componentes/seletorGlobalStartup.js`, `sidebarGlobal.js`, `centralPrazos.js`, `notificacaoMobile.js` e `front/scripts/servicos/api.js`) **deve ser espelhada em `MVPs/Versão Atual/mobile/www/scripts/...`**.

5. **Gerenciamento de Estado no Front-end (`localStorage` / `sessionStorage`) e Interceptadores (`api.js`):**
   - **Detecção de Ambiente (`api.js`):** Se rodar em Capacitor/WebView Android ou domínio de produção, consome `https://anjonexus.intalenti.com.br/api`. Só utiliza `http://127.0.0.1:8000/api` se for navegador desktop local via `http:` com porta explícita (ex.: `:5500` ou `:3000`). Aceita override via `localStorage.getItem('api_base_url_custom')`.
   - **Chaves de Sessão/Storage:**
     - `token_autenticacao` (`localStorage`): Bearer token Sanctum de acesso pleno (gerado no login com assinatura ativa).
     - `token_pendencia` (`sessionStorage` e `localStorage`): Bearer token Sanctum restrito (`ability: ['pagamento:gerenciar']`) emitido quando a agência possui pagamento pendente. Ao salvar `token_pendencia`, o `servicoAutenticacao.js` remove `token_autenticacao` para evitar conflito de headers.
     - `email_pendencia` e `agencia_pendencia` (`sessionStorage` e `localStorage`): Dados da conta em estado fraco/pendente para conciliação ou regeneração de cobrança (`PIX` ou `CARD`).
     - `dados_pre_cadastro` (`sessionStorage`): Objeto `{ nome_agencia, name, email, password }` salvo na etapa 1 (`cadastro.html`) antes de escolher o plano em `selecionar-plano.html`.
     - `plano_selecionado` (`sessionStorage`): Objeto `{ id, nome, preco, limite_startups, limite_membros_equipe, limite_minutas_ia, descricao }` salvo na etapa 2 (`selecionar-plano.html`) antes de escolher a forma de pagamento (`PIX` ou `CARD`) na etapa 3 (`escolher-pagamento.html`).
     - `startup_ativa_id` e `startup_ativa_nome` (`localStorage`): Contexto da startup atualmente selecionada pelo consultor no header global.
     - `usuario_logado`, `usuario_nome`, `usuario_email` (`localStorage`): Cache local dos dados do consultor autenticado (`{ id, name, email, perfil_acesso }`).
     - `tema_preferido` (`localStorage`): `'dark'` ou `'light'`.
   - **Headers HTTP Injetados Automaticamente por `requisicaoApi()` (`api.js`):**
     - `Authorization: Bearer <token>` (prioriza `token_pendencia` em rotas de pendência/renovação e `token_autenticacao` nas demais).
     - `X-Startup-Id: <startup_ativa_id>` (sempre que houver startup ativa selecionada no `localStorage`).
     - `X-Pending-Token` e `X-Pending-Email` (em fluxos de pagamento pendente).
     - `X-Frontend-Url: window.location.href` (usado pelo Back-end para construir links dinâmicos de retorno do checkout AbacatePay e recuperação de senha).
   - **Interceptador Global de Inadimplência (`api.js`):** Qualquer resposta HTTP `403` contendo `codigo === 'PAGAMENTO_PENDENTE'` ou `codigo_erro === 'PAGAMENTO_PENDENTE'` salva automaticamente `token_pendencia`, `email_pendencia` e `agencia_pendencia` e redireciona o navegador para `pagamento-pendente.html` (caso não esteja em uma página pública).

---

## SEÇÃO 2: Mapa Completo de Arquivos e Diretórios (`MVPs/Versão Atual/`)

### 2.1. Back-end (`MVPs/Versão Atual/back/`)

```text
MVPs/Versão Atual/back/
├── bootstrap/
│   └── app.php                                                 # Registra rotas (web, api, console) e aliases de middleware ('check.pagamento', 'startup.ativa')
├── routes/
│   ├── api.php                                                 # Definição de todos os 39 endpoints REST da API (públicos, auth:sanctum, check.pagamento, kanban, minutas, cnpj)
│   ├── console.php                                             # Comandos Artisan base (inspire) e agendamento diário do ciclo de assinaturas (assinaturas:processar-ciclo às 03:00)
│   └── web.php                                                 # Rota web raiz GET /
├── database/
│   ├── migrations/
│   │   ├── 0001_01_01_000001_create_cache_table.php            # Cria tabelas 'cache' e 'cache_locks'
│   │   ├── 0001_01_01_000002_create_jobs_table.php             # Cria tabelas de filas 'jobs', 'job_batches' e 'failed_jobs'
│   │   ├── 2026_09_01_131306_create_editals_table.php          # Cria tabela 'editals' (editais raspados, orçamentos, prazos, metadados de IA e documentos JSON)
│   │   ├── 2026_09_15_000001_create_planos_table.php           # Cria tabela 'planos' (preço, id_transacao AbacatePay e cotas de startups, equipe e minutas IA)
│   │   ├── 2026_09_15_000002_create_agencias_table.php         # Cria tabela 'agencias' (tenant B2B, forma_pagamento PIX/CARD, status_pagamento, url_checkout, pagamento_expira_em, assinatura_expira_em, alerta_falha_pagamento_enviado_em)
│   │   ├── 2026_09_15_000003_create_users_table.php            # Cria tabela 'users' (vínculo com agencia_id e enum perfil_acesso RBAC)
│   │   ├── 2026_09_15_000004_create_password_reset_tokens_table.php # Cria tabela 'password_reset_tokens' para recuperação de senha
│   │   ├── 2026_09_15_000005_create_sessions_table.php         # Cria tabela 'sessions'
│   │   ├── 2026_09_17_222232_create_personal_access_tokens_table.php # Cria tabela 'personal_access_tokens' (Laravel Sanctum)
│   │   ├── 2026_09_20_054026_create_kanban_boards_table.php    # Cria tabela 'kanban_boards' vinculada a 'agencias'
│   │   ├── 2026_09_20_054033_create_kanban_columns_table.php   # Cria tabela 'kanban_columns' com ordenação double 'position' e 'slug'
│   │   ├── 2026_09_20_054039_create_kanban_cards_table.php     # Cria tabela 'kanban_cards' (vínculo com board, column, edital, position double e metadata JSON)
│   │   ├── 2026_10_10_033507_create_startups_table.php         # Cria tabela 'startups' (dados cadastrais da Receita, endereço, porte, regime, nicho, pitch e unique [agencia_id, cnpj])
│   │   ├── 2026_10_12_185047_create_minutas_table.php          # Cria tabela 'minutas' (propostas IA, secoes_estruturadas JSON, versionamento parent_minuta_id e auditoria)
│   │   ├── 2026_11_08_000001_create_edital_favoritos_table.php # Cria tabela 'edital_favoritos' (favoritos por user_id, edital_id e startup_id)
│   │   ├── 2026_11_08_000002_create_alerta_matches_table.php   # Cria tabela 'alerta_matches' (alertas de compatibilidade >= 70%, unique [user_id, startup_id, edital_id])
│   │   └── 2026_11_09_000001_create_alerta_prazos_table.php    # Cria tabela 'alerta_prazos' (alertas de vencimento critico/urgente/atencao, controle de e-mail e unique [user_id, edital_id, nivel_urgencia])
│   └── seeders/
│       ├── DatabaseSeeder.php                                  # Orquestrador principal que invoca o PlanoSeeder
│       ├── PlanoSeeder.php                                     # Semeia de forma idempotente (updateOrCreate) os planos Standard, Pro e Enterprise com limites exatos
│       └── StartupSeeder.php                                   # Semeia 2 startups de demonstração (PILAR I9 e AGROTITAN) vinculadas à primeira agência
├── app/
│   ├── Adapters/
│   │   ├── AbacatePayAdapter.php                               # Traduz payloads e webhooks entre a API AbacatePay v2 e o domínio interno
│   │   └── BrasilApiCnpjAdapter.php                            # Normaliza respostas da BrasilAPI e ReceitaWS (porte, Simples Nacional, CNAE, QSA e startup_payload)
│   ├── Console/Commands/
│   │   ├── DebugEditalPdf.php                                  # Comando 'editais:debug-pdf {id?}' para inspecionar texto extraído de PDF/HTML e documentos do edital
│   │   ├── ProcessarCicloAssinaturas.php                       # Comando 'assinaturas:processar-ciclo' (chunkById 100): carência 2 dias + alerta SMTP, suspensão e expurgo LGPD (3d nunca pagas / 30d suspensas)
│   │   ├── SyncAllEditais.php                                  # Comando 'editais:sync {--fonte=}' que executa os Spiders FINEP, FAPESP e FAPESC via RoachPHP
│   │   ├── SyncFapesc.php                                      # Comando 'spider:fapesc' para disparar isoladamente o FapeScSpider
│   │   ├── SyncFinepDetalhes.php                               # Comando 'finep:sync-detalhes {--limit=}' que raspa links de anexos, região e público-alvo das páginas Liferay da FINEP
│   │   └── TestJob.php                                         # Comando 'app:test-job' para testar execução síncrona do job AnalyzeEditalWithIA no primeiro edital
│   ├── Events/
│   │   └── CardMovedToProposal.php                             # Evento disparado quando um KanbanCard é movido para coluna contendo 'proposta' ou 'elaboração'
│   ├── Exceptions/
│   │   └── CnpjNotFoundException.php                           # Exceção de domínio (HTTP 404) lançada quando um CNPJ válido não existe na Receita Federal
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AlertaMatchController.php                       # Endpoints de listagem, verificação, contador e leitura de Alertas de Match Inteligente (RF-26 / EDTAN-172)
│   │   │   ├── AlertaPrazoController.php                       # Endpoints de listagem, varredura, contador, leitura e disparo de e-mail de Prazos Críticos (RF-47 / EDTAN-171)
│   │   │   ├── AssinaturaController.php                        # Consulta da assinatura/cotas atuais, renovação idempotente (PIX/CARD) e atualização de forma de pagamento (EDTAN-160 / EDTAN-311)
│   │   │   ├── AuthController.php                              # Pré-validação de e-mail, registro com plano (PIX/CARD), login, gestão de pagamento pendente, recuperação de senha, perfil e exclusão LGPD (EDTAN-158 / EDTAN-311)
│   │   │   ├── CnpjController.php                              # Consulta pública GET/POST de CNPJ com validação Módulo 11 e tratamento de erros (EDTAN-134)
│   │   │   ├── Controller.php                                  # Classe base abstrata dos Controllers Laravel
│   │   │   ├── EditalController.php                            # Listagem formatada de editais para o Radar (Custo Zero IA) e toggle/listagem de favoritos (RF-10 / RF-11)
│   │   │   ├── KanbanController.php                            # CRUD de cards do Kanban, movimentação fractional indexing e upload validado de anexos (10MB/arq, 25MB/card)
│   │   │   ├── MinutaController.php                            # Geração de minutas com trava de cota mensal (EDTAN-250), versionamento imutável, exportação DOCX/PDF e ranking de Match IA
│   │   │   ├── PagamentoController.php                         # Endpoint legado para recuperar/regerar link de pagamento de agência pendente
│   │   │   ├── PlanoController.php                             # Listagem pública de planos e endpoint legado de seleção de plano
│   │   │   ├── StartupController.php                           # Listagem multi-tenant com busca, cadastro com trava de CNPJ duplicado, exibição e edição de startups
│   │   │   └── WebhookController.php                           # Receptor do webhook AbacatePay com validação de secret, ativação (+30 dias), limpeza de alerta de falha e corte imediato em refunded/lost
│   │   ├── Middleware/
│   │   │   ├── CheckPagamentoAtivo.php                         # Alias 'check.pagamento': bloqueia (403 PAGAMENTO_PENDENTE) usuários cuja agência não está com pagamento ativo
│   │   │   └── VerificarStartupAtiva.php                       # Alias 'startup.ativa': valida anti-IDOR se o header 'X-Startup-Id' pertence à 'agencia_id' do usuário autenticado
│   │   └── Requests/
│   │       └── RegistroCadastroRequest.php                     # FormRequest de validação de registro inicial (name, email unique, password min:8, nome_agencia)
│   ├── Jobs/
│   │   ├── AnalyzeEditalWithIA.php                             # Job assíncrono que extrai HTML/PDF da FAPESC e envia edital ao DeepSeekService (Groq -> DeepSeek -> Gemini -> Mock)
│   │   └── ScrapeEditalCompletoJob.php                         # Job de Custo Zero de IA que extrai texto bruto e documentos exigidos de PDFs ou páginas HTML via PdfExtractorService
│   ├── Models/
│   │   ├── Agencia.php                                         # Model do tenant ('agencias') com isPagamentoAtivo() (carência de 2 dias), emPeriodoCarencia() e relações com plano, usuarios, startups e kanbanBoards
│   │   ├── AlertaMatch.php                                     # Model de 'alerta_matches' com casts para criterios (array), lido (boolean) e score_match (integer)
│   │   ├── AlertaPrazo.php                                     # Model de 'alerta_prazos' com relações para user, startup, edital e kanbanCard
│   │   ├── Edital.php                                          # Model de 'editals' com casts para documentos (array), ai_analyzed (boolean) e ai_diagnosis (array)
│   │   ├── EditalFavorito.php                                  # Model de 'edital_favoritos' relacionando User, Edital e Startup
│   │   ├── KanbanBoard.php                                     # Model de 'kanban_boards' com relações ordenadas por 'position' para columns e cards
│   │   ├── KanbanCard.php                                      # Model de 'kanban_cards' com casts para position (double) e metadata (array)
│   │   ├── KanbanColumn.php                                    # Model de 'kanban_columns' com cast position (double) e relação ordenada com cards
│   │   ├── Minuta.php                                          # Model de 'minutas' com auto-relacionamento de versionamento (parentMinuta e versoesFilhas)
│   │   ├── Plano.php                                           # Model de 'planos' com casts numéricos para preço e limites de startups, equipe e minutas IA
│   │   ├── Startup.php                                         # Model de 'startups' pertencente a uma Agencia
│   │   └── User.php                                            # Model de 'users' (Sanctum HasApiTokens) com mutator/accessor de compatibilidade 'role' <-> 'perfil_acesso'
│   ├── Providers/
│   │   └── AppServiceProvider.php                              # Service Provider padrão da aplicação
│   ├── Rules/
│   │   └── CnpjValidationRule.php                              # Validação algorítmica oficial da Receita Federal (Módulo 11 dos 2 dígitos verificadores), sanitização e formatação
│   ├── Services/
│   │   ├── AbacatePayService.php                               # Comunicação HTTP com AbacatePay v2 (/checkouts/create com methods PIX/CARD e /checkouts/get), resolução dinâmica de returnUrl e checagem de expiração
│   │   ├── AlertaMatchService.php                              # Calcula aderência startup-edital e cria alertas idempotentes para score >= 70%
│   │   ├── AlertaPrazoService.php                              # Varre editais com deadline <= 15 dias, classifica urgência (critico, urgente, atencao) e envia e-mail HTML dark mode
│   │   ├── CardMovementService.php                             # Movimenta cards no Kanban com cálculo de Fractional Indexing (step 65536.0) e dispara CardMovedToProposal
│   │   ├── CnpjService.php                                     # Consulta CNPJ com cache de 7 dias, provedor primário BrasilAPI e fallback automático para ReceitaWS
│   │   ├── DeepSeekService.php                                 # Analisador semântico de editais com cascata Multi-LLM: 1. Groq (qwen3.6-27b) -> 2. DeepSeek -> 3. Gemini -> 4. Mock
│   │   ├── DocumentExportService.php                           # Gera arquivos Word (.docx) nativos via ZipArchive/OpenXML e HTML estilizado A4 para impressão/PDF das 11 seções da minuta
│   │   ├── KanbanService.php                                   # Inicializa quadro Kanban com 6 colunas padrão, filtra cards por startup_name e pré-preenche card com dados do edital
│   │   ├── MinutaAiService.php                                 # Gera minutas de 11 seções (Gemini 2.5 Flash -> DeepSeek -> Groq Llama 3.3 -> Fallback Determinístico) e calcula score de Match
│   │   ├── MinutaLimitService.php                              # Contabiliza minutas geradas no mês corrente por toda a agência e verifica se atingiu 'limite_minutas_ia' do plano (EDTAN-250)
│   │   ├── PdfExtractorService.php                             # Extrator determinístico (Custo Zero IA) de texto PDF (Smalot PdfParser), prazos, orçamentos, faturamento e checklist de 15 documentos
│   │   └── RegistroService.php                                 # Serviço transacional para criação inicial de Agência (status pendente) + Usuário (consultor_admin) + Token Sanctum
│   ├── Spiders/
│   │   ├── BaseSpider.php                                      # Classe abstrata RoachPHP com SalvarNoBancoProcessor, limpeza de texto, parser de datas BR e hash MD5 único
│   │   ├── FapeScSpider.php                                    # Spider XPath para chamadas abertas da FAPESC (https://fapesc.sc.gov.br/chamadas-abertas/)
│   │   ├── FapespSpider.php                                    # Spider XPath para chamadas de propostas da FAPESP (https://fapesp.br/2185/chamadas-de-propostas-2026)
│   │   ├── FinepSpider.php                                     # Spider híbrido para FINEP: consome API REST Liferay (/o/c/chamadapublicas) com paginação de 250 itens e fallback Browsershot
│   │   └── Processors/
│   │       └── SalvarNoBancoProcessor.php                      # Pipeline RoachPHP que executa Edital::updateOrCreate baseado no 'external_id'
│   └── Traits/
│       └── LimpaTextoTrait.php                                 # Trait utilitária para achatar arrays, remover tags HTML e normalizar espaços
└── tests/Feature/
    ├── AlertaMatchApiTest.php                                  # Testa autenticação, geração idempotente de alertas >= 70%, listagem, contador e marcação de leitura (RF-26)
    ├── AlertaPrazoApiTest.php                                  # Testa detecção de urgência (critico <=3d, urgente <=7d, atencao <=15d), idempotência, isolamento multi-usuário e envio de e-mail
    ├── AssinaturaAtualApiTest.php                              # Testa GET /api/assinatura/atual (datas d/m/Y, forma_pagamento), renovação/idempotência PIX e CARD, atualizar-forma-pagamento, webhook refunded/lost e comando assinaturas:processar-ciclo
    ├── AuthApiTest.php                                         # Testa verificar-email, registrar-com-plano, login ativo (200), login pendente (403) e recuperação/redefinição de senha (60 min)
    ├── CancelamentoContaApiTest.php                            # Testa hard delete em cascata LGPD (EDTAN-158), validação de senha/ciente_lgpd, liberação de e-mail, RBAC e carência de 2 dias em isPagamentoAtivo()
    ├── CnpjApiTest.php                                         # Testa consulta BrasilAPI, fallback ReceitaWS, rejeição 422 Módulo 11 sem chamada HTTP, erro 404 e cache de 7 dias
    ├── EditalFavoritoTest.php                                  # Testa toggle de favoritos, filtro ?favoritos=1 e isolamento de favoritos entre usuários
    ├── ExampleTest.php                                         # Smoke test básico na rota web GET /
    ├── KanbanApiTest.php                                       # Testa inicialização das 6 colunas padrão, criação/movimentação fractional indexing e limites de upload (10MB/arq e 25MB/card)
    ├── MinutaApiTest.php                                       # Testa geração de minuta v1, versionamento imutável v2, exportação .docx e bloqueio 403 quando atinge cota mensal do plano
    └── SecurityMiddlewareTest.php                              # Testa rejeição 401 sem token/token inválido, bloqueio 403 do CheckPagamentoAtivo e proteção de conta ativa contra exclusão pendente
```

### 2.2. Front-end (`MVPs/Versão Atual/front/`)

```text
MVPs/Versão Atual/front/
├── index.html                                                  # Landing page pública institucional do Anjo Nexus
├── manifest.json                                               # Manifesto Web App (PWA)
├── sw.js                                                       # Service Worker para suporte PWA e notificações nativas
├── js/
│   └── app.js                                                  # Controlador da tela RadarEditais.html (listagem, filtros por fonte/favoritos, detalhes do edital e envio para o Kanban)
├── paginas/
│   ├── baixar-app.html                                         # Página para download direto do APK Android (anjonexus.apk)
│   ├── cadastrar-startup.html                                  # Formulário de cadastro de nova startup com busca automática de CNPJ na Receita Federal
│   ├── cadastro.html                                           # Etapa 1 do onboarding SaaS: coleta Nome da Consultoria, Nome, E-mail e Senha e valida disponibilidade do e-mail
│   ├── escolher-pagamento.html                                 # Etapa 3 do onboarding SaaS (ou modo=renovacao): exibe resumo do plano, escolha entre PIX e Cartão de Crédito e aciona checkout AbacatePay
│   ├── esqueci-senha.html                                      # Tela de solicitação de link de recuperação de senha por e-mail
│   ├── inicio.html                                             # Menu hub intermediário pós-seleção de startup (atalhos para Radar, Kanban e troca de startup)
│   ├── kanban.html                                             # Tela do Pipeline Kanban + Modal de Minutas IA (Gerador, Diagnóstico de Fit, Histórico de Versões e Anexos do Card)
│   ├── login.html                                              # Tela de login com redirecionamento inteligente (selecionar-startup.html se ativo, pagamento-pendente.html se 403)
│   ├── minhas-startups.html                                    # Carteira de startups da consultoria com busca em tempo real e edição completa na mesma página
│   ├── pagamento-pendente.html                                 # Tela de retenção ("estado fraco") para conciliar pagamento anterior, gerar nova cobrança ou excluir cadastro pendente
│   ├── perfil-startup.html                                     # Tela dedicada de configuração/edição dos dados cadastrais, pitch e sincronização Receita Federal de uma startup
│   ├── RadarEditais.html                                       # Tela principal do Radar de Editais com tabela responsiva, filtro por órgão/favoritos e painel lateral de diagnóstico
│   ├── redefinir-senha.html                                    # Tela de criação de nova senha validando token de recuperação recebido por e-mail
│   ├── selecionar-plano.html                                   # Etapa 2 do onboarding SaaS (ou modo=renovacao): exibe planos Standard, Pro e Enterprise, salva plano_selecionado no sessionStorage e avança para escolher-pagamento.html
│   └── selecionar-startup.html                                 # Tela pós-login para escolha da startup ativa que guiará o contexto (X-Startup-Id) da sessão
└── scripts/
    ├── componentes/
    │   ├── centralPrazos.js                                    # Componente de painel e polling de contagem regressiva de prazos críticos com integração a notificações mobile
    │   ├── notificacaoMobile.js                                # Gerenciador de Web Push Notifications / Service Worker e vibração tátil para dispositivos móveis
    │   ├── seletorGlobalStartup.js                             # Header global unificado: dropdown de troca de startup, Central de Alertas (Match + Prazos), Toast Pop-up, Editar Perfil, Minha Assinatura (PIX/CARD, datas DD/MM/AAAA, Atualizar Forma de Pagamento) e Cancelamento LGPD
    │   └── sidebarGlobal.js                                    # Barra lateral responsiva unificada (Radar, Kanban, Minhas Startups, App Mobile e Logoff) com drawer mobile
    ├── paginas/
    │   ├── cadastrarStartup.js                                 # Lógica de máscara de CNPJ/CEP, auto-preenchimento via GET /api/cnpj/{cnpj} e criação de startup
    │   ├── cadastro.js                                         # Validação de pré-cadastro, chamada a POST /api/auth/verificar-email e persistência em sessionStorage('dados_pre_cadastro')
    │   ├── escolherPagamento.js                                # Lógica da Etapa 3: seleção entre PIX e CARD e persistência final via registrarComPlano ou gerarNovaCobranca
    │   ├── esqueciSenha.js                                     # Submissão de recuperação de senha com cooldown de 30 segundos contra spam
    │   ├── inicio.js                                           # Controle de sessão e navegação do hub inicio.html
    │   ├── login.js                                            # Submissão de login e tratamento do fluxo HTTP 403 (PAGAMENTO_PENDENTE) vs HTTP 200 (assinatura ativa)
    │   ├── minhasStartups.js                                   # Listagem ordenada (startup ativa no topo), busca, edição in-place e ressincronização com a Receita Federal
    │   ├── pagamentoPendente.js                                # Operações da tela de pendência: conciliar pagamento anterior, redirecionar para escolha de novo plano ou hard delete pendente
    │   ├── pagina-inicial.js                                   # Controle de tema claro/escuro, abas demonstrativas e redirecionamento automático para login no Capacitor
    │   ├── perfilStartup.js                                    # Carregamento, sincronização de CNPJ com a Receita e atualização PUT /api/startups/{id}
    │   ├── redefinirSenha.js                                   # Validação visual em tempo real de requisitos de senha e envio de PUT/POST /api/auth/redefinir-senha
    │   ├── selecionarPlano.js                                  # Renderização dos cards de planos (com fallback local), salvamento em sessionStorage('plano_selecionado') e redirecionamento para escolher-pagamento.html
    │   └── selecionarStartup.js                                # Busca com debounce (180ms) da carteira de startups e seleção da startup ativa para abrir o Radar
    └── servicos/
        ├── api.js                                              # Cliente HTTP fetch centralizado com injeção automática de Bearer token, X-Startup-Id e interceptação global de erro 403 PAGAMENTO_PENDENTE
        ├── servicoAlertas.js                                   # Funções de consumo dos endpoints de Alertas de Match (/alertas/*) e Alertas de Prazos Críticos (/alertas/prazos-criticos/*)
        ├── servicoAutenticacao.js                              # Gestão completa de tokens, estado de usuário, startup ativa e chamadas de autenticação, perfil e cancelamento LGPD
        ├── servicoPlanos.js                                    # Consumo das rotas de listagem de planos, geração de nova cobrança (PIX/CARD), consulta/renovação de assinatura e atualização de forma de pagamento
        └── servicoStartups.js                                  # Consumo das rotas CRUD de startups (/startups) e enriquecimento de CNPJ (/cnpj/{cnpj})
```

---

## SEÇÃO 3: Esquema Físico do Banco de Dados e Relacionamentos (Single Source of Truth)

### 3.1. Tabelas de Domínio

#### 1. Tabela `planos` (`2026_09_15_000001_create_planos_table.php`)
| Coluna | Tipo Físico | Nullable | Default | Constraints / Observações |
| :--- | :--- | :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | Não | Auto Inc | `PRIMARY KEY` |
| `nome` | `VARCHAR(255)` | Não | - | Nome comercial do plano (`Standard`, `Pro`, `Enterprise`) |
| `preco` | `DECIMAL(8, 2)` | Não | - | Valor mensal em R$ |
| `id_transacao` | `VARCHAR(255)` | Sim | `NULL` | ID do produto no gateway AbacatePay |
| `limite_startups` | `INT` | Não | `1` | Cota máxima de startups gerenciadas pela agência |
| `limite_membros_equipe` | `INT` | Não | `1` | Cota máxima de usuários na agência |
| `limite_minutas_ia` | `INT` | Não | `5` | Cota mensal de minutas geradas por IA pela agência |
| `created_at` / `updated_at` | `TIMESTAMP` | Sim | `NULL` | Timestamps padrão Eloquent |

**Valores Exatos Semeados pelo `PlanoSeeder.php` (`Plano::updateOrCreate`):**
| `id` | `nome` | `preco` | `id_transacao` | `limite_startups` | `limite_membros_equipe` | `limite_minutas_ia` |
| :---: | :--- | :---: | :--- | :---: | :---: | :---: |
| **1** | **Standard** | `49.90` | `prod_sWfDg0eZXTLbtZEHYpqnMLKP` | `2` | `1` | `3` |
| **2** | **Pro** | `99.90` | `prod_sWfDg0eZXTLbtZEHYpqnMLKP` | `10` | `5` | `15` |
| **3** | **Enterprise** | `299.90` | `prod_sWfDg0eZXTLbtZEHYpqnMLKP` | `50` | `15` | `50` |

---

#### 2. Tabela `agencias` (`2026_09_15_000002_create_agencias_table.php`)
| Coluna | Tipo Físico | Nullable | Default | Constraints / Observações |
| :--- | :--- | :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | Não | Auto Inc | `PRIMARY KEY` |
| `nome` | `VARCHAR(255)` | Não | - | Nome da consultoria / agência (Tenant B2B) |
| `plano_id` | `BIGINT UNSIGNED` | Sim | `NULL` | `FK -> planos.id` (`ON DELETE SET NULL`) |
| `forma_pagamento` | `ENUM` | Não | `'PIX'` | Valores: `['PIX', 'CARD']` — Meio de pagamento escolhido pela agência (`EDTAN-311`) |
| `id_transacao` | `VARCHAR(255)` | Sim | `NULL` | ID do checkout/cobrança atual no AbacatePay |
| `url_checkout` | `TEXT` | Sim | `NULL` | URL de pagamento (`PIX` ou `CARD`) ativa no AbacatePay |
| `status_pagamento` | `ENUM` | Não | `'pendente'` | Valores: `['pendente', 'ativo', 'suspenso', 'cancelado']` |
| `pagamento_expira_em` | `TIMESTAMP` | Sim | `NULL` | Validade de 60 minutos da janela de checkout aberta |
| `assinatura_expira_em` | `TIMESTAMP` | Sim | `NULL` | Data/hora de expiração do ciclo mensal de 30 dias da assinatura |
| `alerta_falha_pagamento_enviado_em` | `TIMESTAMP` | Sim | `NULL` | Data/hora em que o alerta SMTP de carência (2 dias) foi enviado no ciclo atual |
| `created_at` / `updated_at` | `TIMESTAMP` | Sim | `NULL` | Timestamps padrão Eloquent |

---

#### 3. Tabela `users` (`2026_09_15_000003_create_users_table.php`)
| Coluna | Tipo Físico | Nullable | Default | Constraints / Observações |
| :--- | :--- | :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | Não | Auto Inc | `PRIMARY KEY` |
| `agencia_id` | `BIGINT UNSIGNED` | Sim | `NULL` | `FK -> agencias.id` (`ON DELETE CASCADE`) |
| `perfil_acesso` | `ENUM` | Não | `'consultor_admin'` | Valores: `['admin_global', 'consultor_admin', 'consultor_padrao', 'startup']` |
| `name` | `VARCHAR(255)` | Não | - | Nome completo do usuário/consultor |
| `email` | `VARCHAR(255)` | Não | - | `UNIQUE` (`users_email_unique`) |
| `email_verified_at` | `TIMESTAMP` | Sim | `NULL` | Data de verificação de e-mail |
| `password` | `VARCHAR(255)` | Não | - | Hash bcrypt da senha |
| `remember_token` | `VARCHAR(100)` | Sim | `NULL` | Token de sessão persistente |
| `created_at` / `updated_at` | `TIMESTAMP` | Sim | `NULL` | Timestamps padrão Eloquent |

---

#### 4. Tabela `startups` (`2026_10_10_033507_create_startups_table.php`)
| Coluna | Tipo Físico | Nullable | Default | Constraints / Observações |
| :--- | :--- | :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | Não | Auto Inc | `PRIMARY KEY` |
| `agencia_id` | `BIGINT UNSIGNED` | Não | - | `FK -> agencias.id` (`ON DELETE CASCADE`) |
| `cnpj` | `VARCHAR(18)` | Não | - | CNPJ com máscara (`00.000.000/0001-00`). Compõe `UNIQUE(agencia_id, cnpj)` |
| `razao_social` | `VARCHAR(255)` | Não | - | Razão Social oficial na Receita Federal |
| `nome_fantasia` | `VARCHAR(255)` | Sim | `NULL` | Nome Fantasia da startup |
| `data_abertura` | `DATE` | Sim | `NULL` | Data de início de atividade (`YYYY-MM-DD`) |
| `email` | `VARCHAR(255)` | Sim | `NULL` | E-mail de contato da startup |
| `telefone` | `VARCHAR(255)` | Sim | `NULL` | Telefone de contato |
| `cep` | `VARCHAR(255)` | Sim | `NULL` | CEP do endereço |
| `logradouro` | `VARCHAR(255)` | Sim | `NULL` | Logradouro |
| `numero` | `VARCHAR(255)` | Sim | `NULL` | Número predial |
| `bairro` | `VARCHAR(255)` | Sim | `NULL` | Bairro |
| `cidade` | `VARCHAR(255)` | Sim | `NULL` | Município |
| `estado` | `VARCHAR(2)` | Sim | `NULL` | Sigla da UF (`SC`, `PR`, `SP`, etc.) |
| `porte` | `VARCHAR(255)` | Sim | `NULL` | Ex.: `Microempresa (ME)`, `Empresa de Pequeno Porte (EPP)`, `Médio Porte`, `Grande Porte`, `Demais` |
| `regime_tributario` | `VARCHAR(255)` | Sim | `NULL` | Ex.: `Simples Nacional`, `Lucro Presumido / Real` |
| `nicho_atuacao` | `VARCHAR(255)` | Sim | `NULL` | Descrição do CNAE principal ou área tecnológica |
| `pitch` | `TEXT` | Sim | `NULL` | Resumo executivo / solução tecnológica da startup |
| `created_at` / `updated_at` | `TIMESTAMP` | Sim | `NULL` | Constraint composta: `UNIQUE (agencia_id, cnpj)` |

---

#### 5. Tabela `editals` (`2026_09_01_131306_create_editals_table.php`)
| Coluna | Tipo Físico | Nullable | Default | Constraints / Observações |
| :--- | :--- | :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | Não | Auto Inc | `PRIMARY KEY` |
| `external_id` | `VARCHAR(255)` | Sim | `NULL` | `UNIQUE` — Identificador único na fonte ou hash MD5 (`titulo|link`) |
| `title` | `VARCHAR(255)` | Não | - | Título oficial da chamada pública / edital |
| `source_url` | `TEXT` | Sim | `NULL` | Link direto da página do edital ou PDF oficial |
| `original_file_path` | `TEXT` | Sim | `NULL` | Caminho de arquivo local se baixado |
| `min_budget` | `DECIMAL(15, 2)` | Sim | `NULL` | Valor mínimo de financiamento |
| `max_budget` | `DECIMAL(15, 2)` | Sim | `NULL` | Teto máximo de subvenção/orçamento |
| `deadline` | `DATETIME` | Sim | `NULL` | Data/hora limite de submissão de propostas |
| `open_date` | `DATETIME` | Sim | `NULL` | Data de abertura das inscrições |
| `result_date` | `DATETIME` | Sim | `NULL` | Data prevista de divulgação do resultado |
| `published_at` | `DATETIME` | Sim | `NULL` | Data de publicação do edital |
| `status` | `ENUM` | Não | `'active'` | Valores: `['active', 'closed']` |
| `ai_analyzed` | `BOOLEAN` | Não | `false` | Flag indicando se já passou pelo `AnalyzeEditalWithIA` |
| `ai_match` | `INT` | Sim | `NULL` | Score base de compatibilidade tecnológica (0 a 100) |
| `ai_trl` | `VARCHAR(255)` | Sim | `NULL` | Faixa de TRL exigida (ex.: `TRL 3 a 7`) |
| `ai_faturamento` | `VARCHAR(255)` | Sim | `NULL` | Exigência de faturamento/receita bruta |
| `ai_nicho` | `VARCHAR(255)` | Sim | `NULL` | Nicho temático identificado pela IA |
| `ai_diagnosis` | `JSON` | Sim | `NULL` | Array de objetos `[{ "type": "success|warning|danger|info", "text": "..." }]` |
| `objetivo` | `TEXT` | Sim | `NULL` | Resumo/objetivo do edital |
| `conteudo_completo` | `LONGTEXT` | Sim | `NULL` | Texto bruto extraído do HTML/PDF (até 10.000/15.000 chars) |
| `condicao_financiamento` | `VARCHAR(255)` | Sim | `NULL` | Ex.: Subvenção econômica, Não reembolsável |
| `operacao` | `VARCHAR(255)` | Sim | `NULL` | Tipo de operação |
| `publico` | `VARCHAR(255)` | Sim | `NULL` | Público-alvo / elegibilidade |
| `fonte` | `VARCHAR(255)` | Não | `'FINEP'` | Órgão fomentador (`FINEP`, `FAPESP`, `FAPESC`, etc.) |
| `documentos` | `JSON` | Sim | `NULL` | Array de documentos exigidos `[{ "titulo": "...", "link": "..." }]` |
| `regiao` | `VARCHAR(255)` | Sim | `NULL` | Abrangência geográfica (ex.: `Nacional`, `SC`, `SP`) |
| `nicho_tema` | `VARCHAR(255)` | Sim | `NULL` | Tema complementar |
| `created_at` / `updated_at` | `TIMESTAMP` | Sim | `NULL` | Timestamps padrão Eloquent |

---

#### 6. Tabelas do Pipeline Kanban (`kanban_boards`, `kanban_columns`, `kanban_cards`)
- **`kanban_boards` (`2026_09_20_054026_create_kanban_boards_table.php`):**
  - `id` (`BIGINT UNSIGNED PK`), `agencia_id` (`FK -> agencias.id ON DELETE CASCADE`, nullable), `title` (`VARCHAR(255)`, default `'Pipeline de Editais'`), `timestamps`.
- **`kanban_columns` (`2026_09_20_054033_create_kanban_columns_table.php`):**
  - `id` (`BIGINT UNSIGNED PK`), `board_id` (`FK -> kanban_boards.id ON DELETE CASCADE`), `title` (`VARCHAR(255)`), `position` (`DOUBLE`, default `1.0`), `slug` (`VARCHAR(255)`, nullable), `timestamps`.
- **`kanban_cards` (`2026_09_20_054039_create_kanban_cards_table.php`):**
  - `id` (`BIGINT UNSIGNED PK`), `board_id` (`FK -> kanban_boards.id ON DELETE CASCADE`), `column_id` (`FK -> kanban_columns.id ON DELETE CASCADE`), `edital_id` (`FK -> editals.id ON DELETE SET NULL`, nullable), `title` (`VARCHAR(255)`), `description` (`TEXT`, nullable), `startup_name` (`VARCHAR(255)`, nullable), `position` (`DOUBLE`, default `65536.0`), `metadata` (`JSON`, nullable — armazena `fonte`, `max_budget`, `deadline`, `ai_match`, `publico`, `valor_estimado`, `notas_internas` e array `anexos`), `timestamps`.

---

#### 7. Tabela `minutas` (`2026_10_12_185047_create_minutas_table.php`)
| Coluna | Tipo Físico | Nullable | Default | Constraints / Observações |
| :--- | :--- | :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | Não | Auto Inc | `PRIMARY KEY` |
| `startup_id` | `BIGINT UNSIGNED` | Não | - | `FK -> startups.id` (`ON DELETE CASCADE`) |
| `edital_id` | `BIGINT UNSIGNED` | Não | - | `FK -> editals.id` (`ON DELETE CASCADE`) |
| `user_id` | `BIGINT UNSIGNED` | Sim | `NULL` | `FK -> users.id` (`ON DELETE SET NULL`) — Autor da versão |
| `kanban_card_id` | `BIGINT UNSIGNED` | Sim | `NULL` | `FK -> kanban_cards.id` (`ON DELETE SET NULL`) |
| `versao` | `INT UNSIGNED` | Não | `1` | Número sequencial da versão (`1`, `2`, `3`...) |
| `parent_minuta_id` | `BIGINT UNSIGNED` | Sim | `NULL` | `FK -> minutas.id` (`ON DELETE SET NULL`) — Aponta para a minuta mãe |
| `titulo_projeto` | `VARCHAR(255)` | Sim | `NULL` | Título comercial/técnico gerado ou editado |
| `ideia_projeto` | `TEXT` | Sim | `NULL` | Escopo original submetido pelo consultor |
| `conteudo_gerado` | `LONGTEXT` | Não | - | Texto integral consolidado da proposta |
| `secoes_estruturadas` | `JSON` | Sim | `NULL` | Objeto JSON com as 11 seções oficiais isoladas |
| `status` | `VARCHAR(255)` | Não | `'concluido'` | Valores usados: `'gerando'`, `'concluido'`, `'revisado'`, `'erro'` |
| `modelo_ia_usado` | `VARCHAR(255)` | Sim | `NULL` | Ex.: `'Google Gemini (gemini-2.5-flash)'`, `'DeepSeek (deepseek-chat)'`, `'Groq (Llama-3)'`, `'Motor Determinístico Local (Fallback Seguro)'` |
| `autor_nome` | `VARCHAR(255)` | Sim | `NULL` | Cache do nome do consultor para auditoria rápida |
| `created_at` / `updated_at` | `TIMESTAMP` | Sim | `NULL` | Timestamps padrão Eloquent |

---

#### 8. Tabelas de Favoritos e Alertas (`edital_favoritos`, `alerta_matches`, `alerta_prazos`)
- **`edital_favoritos` (`2026_11_08_000001_create_edital_favoritos_table.php`):**
  - `id` (`PK`), `user_id` (`FK -> users.id ON DELETE CASCADE`), `edital_id` (`FK -> editals.id ON DELETE CASCADE`), `startup_id` (`FK -> startups.id ON DELETE CASCADE`, nullable), `timestamps`.
  - Índices: `INDEX(user_id, edital_id)` e `INDEX(user_id, startup_id)`.
- **`alerta_matches` (`2026_11_08_000002_create_alerta_matches_table.php`):**
  - `id` (`PK`), `user_id` (`FK -> users.id ON DELETE CASCADE`), `startup_id` (`FK -> startups.id ON DELETE CASCADE`), `edital_id` (`FK -> editals.id ON DELETE CASCADE`), `score_match` (`INT`, default `0`), `titulo` (`VARCHAR(255)`), `mensagem` (`TEXT`), `criterios` (`JSON`, nullable), `lido` (`BOOLEAN`, default `false`), `timestamps`.
  - Constraints: `UNIQUE(user_id, startup_id, edital_id)` nomeada `user_startup_edital_alerta_unique`, `INDEX(user_id, lido)`, `INDEX(startup_id, lido)`.
- **`alerta_prazos` (`2026_11_09_000001_create_alerta_prazos_table.php`):**
  - `id` (`PK`), `user_id` (`FK -> users.id ON DELETE CASCADE`), `startup_id` (`FK -> startups.id ON DELETE SET NULL`, nullable), `edital_id` (`FK -> editals.id ON DELETE CASCADE`), `kanban_card_id` (`FK -> kanban_cards.id ON DELETE SET NULL`, nullable), `tipo_prazo` (`VARCHAR(255)`, default `'submissao'`), `data_limite` (`DATETIME`), `dias_restantes` (`INT`), `nivel_urgencia` (`VARCHAR(255)` — `'critico'`, `'urgente'`, `'atencao'`), `titulo` (`VARCHAR(255)`), `mensagem` (`TEXT`), `lido` (`BOOLEAN`, default `false`), `email_enviado` (`BOOLEAN`, default `false`), `email_enviado_em` (`DATETIME`, nullable), `timestamps`.
  - Constraints: `UNIQUE(user_id, edital_id, nivel_urgencia)` nomeada `user_edital_urgencia_unique`, `INDEX(user_id, lido)`, `INDEX(startup_id, lido)`, `INDEX(nivel_urgencia)`, `INDEX(data_limite)`.

#### 9. Tabelas de Infraestrutura e Autenticação
- **`personal_access_tokens`:** Padrão Laravel Sanctum (`id`, `tokenable_type`, `tokenable_id`, `name`, `token` VARCHAR(64) UNIQUE, `abilities`, `last_used_at`, `expires_at`, `timestamps`).
- **`password_reset_tokens`:** `email` (`VARCHAR(255) PRIMARY KEY`), `token` (`VARCHAR(255)` hashed bcrypt), `created_at` (`TIMESTAMP`).
- **`sessions`:** `id` (`VARCHAR PRIMARY KEY`), `user_id` (`INDEX`), `ip_address`, `user_agent`, `payload`, `last_activity`.
- **`cache` / `cache_locks` / `jobs` / `job_batches` / `failed_jobs`:** Tabelas nativas do Laravel para cache de CNPJ e processamento de filas.

---

## SEÇÃO 4: Regras de Negócio Completas por Módulo (Extraídas do Código Real)

### 1. Multi-Tenancy, RBAC e Contexto de Startup (`X-Startup-Id`)
- **Isolamento Multi-Tenant por `agencia_id`:** Toda entidade de cliente (`User`, `Startup`, `KanbanBoard`) pertence a uma `Agencia` (`agencia_id`). Consultas em `StartupController` (`index`, `store`, `show`, `update`) filtram estritamente por `where('agencia_id', $request->user()->agencia_id)`, impedindo vazamento de dados entre consultorias concorrentes.
- **Papéis RBAC (`perfil_acesso` na tabela `users`):**
  - Enum suportado no banco: `['admin_global', 'consultor_admin', 'consultor_padrao', 'startup']` (default: `'consultor_admin'`).
  - O model `User` possui mutator `setRoleAttribute` e accessor `getRoleAttribute` que mapeiam virtualmente `$user->role` para `$user->perfil_acesso`.
  - **Restrições Exclusivas de `consultor_admin`:** Apenas usuários com `perfil_acesso === 'consultor_admin'` podem gerar cobrança de renovação de assinatura (`POST /api/assinatura/renovar`), atualizar a forma de pagamento (`POST /api/assinatura/atualizar-forma-pagamento`) e encerrar definitivamente a conta da consultoria (`DELETE|POST /api/conta/cancelar`). Um `consultor_padrao` recebe HTTP `403 Forbidden`.
- **Middleware Anti-IDOR `VerificarStartupAtiva` (`alias: 'startup.ativa'`):**
  - Inspeciona o cabeçalho `X-Startup-Id` enviado automaticamente pelo `api.js`.
  - Se presente e o usuário estiver autenticado, executa `Startup::where('id', $startupId)->where('agencia_id', $request->user()->agencia_id)->first()`.
  - Caso a startup não exista ou pertença a outra agência, aborta imediatamente com HTTP `403` (`'A startup selecionada não pertence à sua consultoria ou é inválida.'`). Se válida, injeta o model em `$request->attributes->set('startup_ativa', $startup)`.

---

### 2. Fluxo de Registro em Três Etapas (Wizard "Estado Fraco"), Pagamento PIX + Cartão (`EDTAN-311`) e Webhooks
- **Etapa 1 — Pré-validação de E-mail (`cadastro.html` ➔ `POST /api/auth/verificar-email`):**
  - Antes de ir para a escolha de planos, o front-end valida o e-mail e armazena os dados em `sessionStorage('dados_pre_cadastro')` sem persistir no MySQL.
  - Se o e-mail não existir em `users`, retorna HTTP `200` (`disponivel: true`).
  - Se já existir, retorna HTTP `422` (`disponivel: false`) indicando em `pagamento_pendente` (`true`/`false`) se a agência vinculada está pendente (`!$agencia->isPagamentoAtivo()`) ou se já é um cliente ativo.
- **Etapa 2 — Escolha do Plano (`selecionar-plano.html` / `selecionarPlano.js`):**
  - Ao clicar em *"Selecionar Plano"*, não realiza chamada de escrita ao banco: salva o plano escolhido em `sessionStorage('plano_selecionado')` e redireciona para `escolher-pagamento.html` (mantendo `?modo=renovacao` se presente).
- **Etapa 3 — Escolha da Forma de Pagamento e Registro Atômico (`escolher-pagamento.html` ➔ `POST /api/auth/registrar-com-plano`):**
  - Exibe o resumo do plano com link `← Alterar plano` e 2 cards selecionáveis: **PIX** (`PIX`) e **Cartão de Crédito** (`CARD`).
  - Somente ao clicar em **"Finalizar e Ir para o Pagamento"**, chama `registrarComPlano` (ou `gerarNovaCobranca` em `modo=renovacao`), limpa `dados_pre_cadastro` e `plano_selecionado` do `sessionStorage` e redireciona para `url_checkout`.
  - `registrarComPlano` exige `name`, `email`, `password` (mín. 8 chars), `plano_id` (existente em `planos`), `nome_agencia` (obrigatório) e aceita `'forma_pagamento' => 'nullable|string|in:PIX,CARD'` (fallback `'PIX'`).
  - Se o e-mail já existir com assinatura ativa, bloqueia com HTTP `422`. Se existir mas estiver com pagamento pendente, **reaproveita e atualiza** o registro do usuário e da agência dentro de `DB::beginTransaction()`.
  - Cria/atualiza a `Agencia` com `forma_pagamento = $formaPagamento`, `status_pagamento = 'pendente'` e `pagamento_expira_em = Carbon::now()->addMinutes(60)` (janela de 60 minutos) e o `User` com `perfil_acesso = 'consultor_admin'`.
  - Aciona `AbacatePayService::gerarCobranca($agencia, $usuario, $plano, $formaPagamento)`, que usa `AbacatePayAdapter::paraPayloadCriacao()` enviando `frequency: 'ONE_TIME'`, `methods: [$formaPagamento]` (`'PIX'` ou `'CARD'`), `externalId: 'agencia_' . $agencia->id . '_' . uniqid()` e `returnUrl` / `completionUrl` apontando dinamicamente para `.../paginas/login.html` (resolvido via `FRONT_URL` ou header `X-Frontend-Url`).
  - Salva `url_checkout` e `id_transacao` na agência, emite um token Sanctum restrito `createToken('token_pendencia', ['pagamento:gerenciar'])` e retorna HTTP `201`.
- **Bloqueio de Inadimplentes (`CheckPagamentoAtivo` e `AuthController@login`):**
  - No `POST /api/auth/login`, se as credenciais estiverem corretas mas `!$agencia->isPagamentoAtivo()`, o login **não emite token pleno**: retorna HTTP `403` com `'codigo' => 'PAGAMENTO_PENDENTE'`, um novo `token_pendencia` e os dados da agência (`url_checkout`, `pagamento_expira_em`).
  - O middleware `CheckPagamentoAtivo` (`check.pagamento`) protege as rotas internas (como `/api/startups`) e retorna HTTP `403` (`PAGAMENTO_PENDENTE`) se `$usuario->agencia->isPagamentoAtivo()` for `false`.
- **Resolução Multicanal de Conta Pendente (`AuthController::resolverUsuarioPendente`):**
  - Permite que contas pendentes consultem conciliação (`GET /api/pagamentos/consultar-anterior`), troquem de plano/forma de pagamento e gerem nova cobrança (`POST /api/planos/gerar-nova-cobranca`) ou cancelem o cadastro pendente (`DELETE /api/auth/cancelar-cadastro-pendente`) identificando o usuário por: 1º `email` (input/query/header `X-Pending-Email`), 2º `PersonalAccessToken::findToken()` (Bearer / `X-Pending-Token` / `token_pendencia`) ou 3º guard Sanctum.
  - Em `consultarPagamentoAnterior`, se o webhook ainda não tiver chegado, o backend consulta ativamente `AbacatePayService::consultarStatusCobranca($agencia->id_transacao)` (`GET https://api.abacatepay.com/v2/checkouts/get`) e, se o status externo for `PAID`, ativa a assinatura automaticamente na hora e limpa `alerta_falha_pagamento_enviado_em = null`.

---

### 3. Ciclo de Assinatura Mensal (30 Dias), Carência de 2 Dias, Atualização de Forma de Pagamento e Scheduler (`EDTAN-160` / `EDTAN-311`)
- **Regra Central de Ativação com Carência de 2 Dias (`Agencia::isPagamentoAtivo()` e `Agencia::emPeriodoCarencia()`):**
  - `isPagamentoAtivo(): bool` opera em 3 passos:
    1. Se `$this->status_pagamento !== 'ativo'`, retorna `false` imediatamente (bloqueio instantâneo para `'pendente'`, `'suspenso'` ou `'cancelado'`).
    2. Se `$this->assinatura_expira_em === null`, retorna `true`.
    3. Caso possua data de expiração, retorna `$this->assinatura_expira_em->copy()->addDays(2)->isFuture()`, concedendo **2 dias de carência** após o vencimento antes da pausa da conta.
  - `emPeriodoCarencia(): bool` retorna `true` quando `status_pagamento === 'ativo'`, `assinatura_expira_em` já venceu (`isPast()`) e `assinatura_expira_em->copy()->addDays(2)->isFuture()`.
- **Consulta da Assinatura Atual (`GET /api/assinatura/atual`):**
  - Retorna dados do plano, cotas de uso atual, `forma_pagamento` (`'PIX'` ou `'CARD'`), `forma_pagamento_label` (`'PIX'` ou `'Cartão de Crédito'`), `em_carencia` (`bool`), `data_vencimento_formatada` (`d/m/Y`), `data_repeticao_formatada` (`d/m/Y`) e `data_liberacao_renovacao_formatada` (`d/m/Y`).
- **Janela de Renovação Antecipada ($\le 5$ dias) e Idempotência Condicional (`POST /api/assinatura/renovar`):**
  - Calculada em `AssinaturaController` como `$diasRestantes = (int) max(0, ceil(now()->floatDiffInDays($expiraEm, false)))`.
  - Se a assinatura estiver ativa e faltarem **mais de 5 dias** (`$diasRestantes > 5`), `pode_renovar` é `false` e qualquer chamada a `POST /api/assinatura/renovar` é bloqueada com HTTP `422` (`'A renovação antecipada fica disponível faltando 5 dias para o vencimento da assinatura.'`), informando a `data_liberacao_renovacao` (`$expiraEm->copy()->subDays(5)`).
  - Aceita `'forma_pagamento' => 'nullable|string|in:PIX,CARD'`.
  - **Idempotência de 60 minutos:** Só reaproveita a `url_checkout` aberta (`'reaproveitada' => true`) se a `forma_pagamento` solicitada for **igual à atual da agência**; caso o consultor tenha trocado de `PIX` para `CARD` (ou vice-versa), invalida o checkout anterior, gera nova cobrança no AbacatePay e atualiza `$agencia->forma_pagamento`.
- **Atualização de Forma de Pagamento (`POST /api/assinatura/atualizar-forma-pagamento`):**
  - Restrita a `consultor_admin` (`403` para outros perfis) e valida `'forma_pagamento' => 'required|string|in:PIX,CARD'`.
  - Se a assinatura estiver ativa com **`> 5 dias` restantes**, apenas atualiza `$agencia->forma_pagamento` no banco para o próximo ciclo e retorna `200 OK` (`requer_checkout: false`, `url_checkout: null`) sem gerar cobrança antecipada.
  - Se a assinatura estiver **vencida, em carência ($\le 2$ dias após vencimento), suspensa ou na janela de renovação ($\le 5$ dias)**, gera imediatamente um novo checkout no AbacatePay com a nova `forma_pagamento` e retorna `url_checkout` (`requer_checkout: true`).
- **Soma Acumulativa (+30 Dias) e Proteção contra Reembolso/Chargeback no `WebhookController@abacatePayWebhook`:**
  - Valida a query string `?webhookSecret=` contra `env('ABACATEPAY_WEBHOOK_SECRET')` (retorna HTTP `401` se divergir).
  - Eventos `'checkout.completed'`, `'subscription.completed'`, `'billing.paid'` ou status `'PAID'` são traduzidos para `'ativo'`: soma `+30 dias` acumulativos sobre `assinatura_expira_em` (se futuro) ou `now()->addDays(30)`, e limpa `url_checkout = null`, `pagamento_expira_em = null` e `alerta_falha_pagamento_enviado_em = null`.
  - Eventos `'checkout.refunded'` / `'subscription.cancelled'` (estorno/reembolso) definem `status_pagamento = 'cancelado'`; eventos `'checkout.lost'` / `'subscription.expired'` (disputa/chargeback perdido) definem `status_pagamento = 'suspenso'`, cortando o acesso imediatamente (`isPagamentoAtivo()` retorna `false` na hora).
- **Rotina Agendada de Ciclo de Vida (`app/Console/Commands/ProcessarCicloAssinaturas.php` — `php artisan assinaturas:processar-ciclo`):**
  - Agendada em `routes/console.php` via `Schedule::command('assinaturas:processar-ciclo')->dailyAt('03:00')` utilizando `chunkById(100)` para baixo consumo de memória:
    - **A) Contas que NUNCA pagaram (`status_pagamento = 'pendente'` e `assinatura_expira_em IS NULL`):** Após **3 dias** de criação (`env('ASSINATURA_DIAS_EXPURGO_NUNCA_PAGO', 3)`), executa o **Hard Delete** transacional em cascata nas 9 camadas do banco (princípio da minimização de dados da LGPD e liberação rápida do e-mail).
    - **B) Contas que JÁ pagaram antes (`assinatura_expira_em IS NOT NULL`):**
      1. **Fase 1 (Carência — entre `vencimento` e `vencimento + 2 dias`):** Se `status_pagamento === 'ativo'` e `alerta_falha_pagamento_enviado_em === null`, envia e-mail HTML via SMTP (`Mail::html`) ao `consultor_admin` avisando sobre os **2 dias de carência** para acessar *"Minha Assinatura" ➔ "Atualizar Forma de Pagamento"* e grava `alerta_falha_pagamento_enviado_em = now()`.
      2. **Fase 2 (Pausa/Suspensão — passou de `vencimento + 2 dias`):** Atualiza `status_pagamento = 'suspenso'` (bloqueando o painel e mantendo os dados intactos).
      3. **Fase 3 (Expurgo Legal — passou de `vencimento + 30 dias`):** Usando `env('ASSINATURA_DIAS_EXPURGO_SUSPENSA', 30)`, executa o **Hard Delete** transacional em cascata da conta suspensa.

---

### 4. Cancelamento de Conta e Exclusão Física em Cascata LGPD (`EDTAN-158`)
- **Rota Híbrida:** `Route::match(['delete', 'post'], '/conta/cancelar', [AuthController::class, 'cancelarConta'])` protegida por `auth:sanctum`.
- **Travas de Segurança e Permissão:**
  1. Usuário deve ter `perfil_acesso === 'consultor_admin'` (caso contrário, retorna HTTP `403`: `'Apenas o administrador da consultoria pode encerrar a conta.'`).
  2. Validação obrigatória de payload: `'password' => 'required|string'` e `'ciente_lgpd' => 'required|accepted'`.
  3. Reautenticação de senha via `Hash::check($requisicao->password, $usuario->password)` (se incorreta, retorna HTTP `422` com mensagem `'A senha informada está incorreta.'`).
- **Ordem Exata da Deleção Física em Cascata (`DB::transaction`):**
  1. Coleta todos os IDs e e-mails de usuários vinculados à agência (`$idsUsuarios`), todas as startups (`$idsStartups`), quadros (`$idsBoards`), colunas (`$idsColumns`) e cards (`$idsCards`).
  2. **Tokens Sanctum:** Revoga todos os tokens de todos os membros da agência (`$membro->tokens()->delete()` e `PersonalAccessToken::whereIn('tokenable_id', $idsUsuarios)->delete()`).
  3. **1º — Minutas:** `Minuta::where(...)` deletando por `user_id`, `startup_id` ou `kanban_card_id`.
  4. **2º — Alertas:** `AlertaPrazo::where(...)` (por `user_id`, `startup_id` ou `kanban_card_id`) e `AlertaMatch::where(...)` (por `user_id` ou `startup_id`).
  5. **3º — Favoritos:** `EditalFavorito::where(...)` (por `user_id` ou `startup_id`).
  6. **4º — Pipeline Kanban:** Deleta fisicamente na ordem `KanbanCard` -> `KanbanColumn` -> `KanbanBoard`.
  7. **5º — Startups:** `Startup::whereIn('id', $idsStartups)->delete()`.
  8. **6º — Credenciais temporárias e Sessões:** Remove registros em `password_reset_tokens` e `sessions`.
  9. **7º — Usuários e Agência:** Executa `User::whereIn('id', $idsUsuarios)->forceDelete()` e `$agencia->forceDelete()`.
- **Liberação para Recadastro:** Como o hard delete remove fisicamente os registros das tabelas `users`, `agencias` e `startups`, o mesmo endereço de e-mail e os mesmos CNPJs ficam 100% livres para criar uma nova conta do zero imediatamente após o cancelamento.

---

### 5. Gestão de Startups e Enriquecimento de CNPJ (`EDTAN-134` / `UC-10`)
- **Validação Algorítmica Oficial (`CnpjValidationRule`):**
  - Sanitiza caracteres não numéricos, exige exatamente 14 dígitos, rejeita sequências de dígitos repetidos (`00000000000000` a `99999999999999`) e calcula o **Módulo 11** oficial da Receita Federal para o 1º dígito verificador (pesos `[5,4,3,2,9,8,7,6,5,4,3,2]`) e 2º dígito verificador (pesos `[6,5,4,3,2,9,8,7,6,5,4,3,2]`).
  - Se o CNPJ for matematicamente inválido, `CnpjController` rejeita imediatamente com HTTP `422` **sem disparar nenhuma requisição HTTP externa**.
- **Arquitetura de Enriquecimento com Cache e Fallback (`CnpjService` + `BrasilApiCnpjAdapter`):**
  - **Cache:** Consulta primeiro a chave `cnpj_data_{14_digitos}` no Cache com TTL de **7 dias** (`CACHE_TTL_DAYS = 7`), a menos que a flag `refresh=true` seja enviada. Quando retorna do cache, adiciona `'origem' => 'cache'`.
  - **Provedor Primário (BrasilAPI):** `GET https://brasilapi.com.br/api/cnpj/v1/{digits}` (timeout 8s). Se retornar HTTP `404`, lança `CnpjNotFoundException` (HTTP `404`). Se retornar erro `5xx`/timeout, registra warning e aciona o fallback.
  - **Provedor Secundário de Fallback (ReceitaWS):** `GET https://receitaws.com.br/v1/cnpj/{digits}` (timeout 8s). Se ambos falharem por indisponibilidade de rede, retorna HTTP `503`.
  - **Normalização (`BrasilApiCnpjAdapter`):** Padroniza o porte em `'Microempresa (ME)'`, `'Empresa de Pequeno Porte (EPP)'`, `'Médio Porte'`, `'Grande Porte'` ou `'Demais'`; traduz opção pelo Simples em `'Simples Nacional'` ou `'Lucro Presumido / Real'`; usa a descrição do CNAE fiscal como `nicho_atuacao` (ou fallback `'Tecnologia / Inovação'`); e entrega um sub-objeto `startup_payload` pronto para persistência na tabela `startups`.
- **Cadastro e Regras de Unicidade (`StartupController`):**
  - A tabela `startups` possui constraint `UNIQUE(agencia_id, cnpj)`. Antes de criar (`POST /api/startups`), o controller verifica se a agência já cadastrou aquele CNPJ e retorna HTTP `422` (`'Esta startup (CNPJ) já está cadastrada na sua consultoria.'`).
  - *Nota Técnica de Auditoria:* O limite contratual `limite_startups` do plano é exibido no painel de assinatura (`GET /api/assinatura/atual` -> `uso_atual.startups_cadastradas` vs `plano.limite_startups`), porém `StartupController@store` atualmente valida apenas a duplicidade de CNPJ por agência, não bloqueando via código a criação caso `startups()->count() >= $plano->limite_startups`.

---

### 6. Radar de Editais, Web Scraping (Spiders), Favoritos e Motor de Match / Alertas
- **Web Scraping e Ingestão de Editais (`app/Spiders/` e `app/Console/Commands/`):**
  - **Comando Unificado:** `php artisan editais:sync {--fonte=}` (`SyncAllEditais.php`) executa em sequência os spiders configurados com **RoachPHP** e salva via `SalvarNoBancoProcessor` (`Edital::updateOrCreate(['external_id' => ...])`):
    1. **`FinepSpider` (`FINEP`):** Consulta diretamente a API REST Liferay da FINEP (`https://www.finep.gov.br/o/c/chamadapublicas?sort=dataDePublicacao:desc&pageSize=250`) iterando por todas as páginas (`lastPage`). Caso a API REST falhe, possui fallback com **Spatie Browsershot** (Puppeteer/Chrome headless). Complementado pelo comando `php artisan finep:sync-detalhes`, que varre o HTML de cada chamada pública para extrair links de anexos (`.pdf`, `.doc`, `/documents/`), região e público-alvo.
    2. **`FapespSpider` (`FAPESP`):** Raspa `https://fapesp.br/2185/chamadas-de-propostas-2026` via XPath (`//ul[contains(@class, "list")]/li`), extraindo código da chamada, link, título e data limite via regex.
    3. **`FapeScSpider` (`FAPESC`):** Raspa `https://fapesc.sc.gov.br/chamadas-abertas/` via XPath (`//div[contains(@class, "upk-item")]`), gerando `external_id` por hash MD5 (`titulo|link`).
  - **Extração Determinística Custo Zero (`ScrapeEditalCompletoJob` & `PdfExtractorService`):** Usa `Smalot\PdfParser\Parser` para ler PDFs locais ou remotos e extrair via expressões regulares (sem gastar tokens de LLM): `deadline`, `max_budget`, `faturamento` e um checklist de até **15 documentos institucionais** (CND Federal, CNDT, FGTS/CRF, Cartão CNPJ, Contrato Social, Balanço/DRE, Extrato CAF, Plano de Trabalho, Currículo Lattes, etc., com fallback de 5 documentos básicos caso nenhum padrão dê match).
  - **Análise Semântica Multi-LLM (`AnalyzeEditalWithIA` & `DeepSeekService`):** Quando acionado em background, envia título, público e objetivo com prompt "Zero Alucinação" seguindo a cascata:
    1. **Groq API** (`https://api.groq.com/openai/v1/chat/completions`, modelo `qwen/qwen3.6-27b`, `temperature: 0.1`);
    2. **DeepSeek API** (`https://api.deepseek.com/chat/completions`, modelo `deepseek-chat`);
    3. **Google Gemini API** (`gemini-3.6-flash:generateContent`);
    4. **Mock Determinístico de Emergência** (`match: 50`, `trl: 'A definir (Fallback IA)'`).
    - Suporta balanceamento de carga por múltiplas chaves separadas por vírgula no `.env` (`getRandomKey()`).
- **Listagem e Favoritos por Startup (`EditalController` — `RF-10` / `RF-11`):**
  - `GET /api/editais` filtra opcionalmente por `fonte` (`FINEP`, `FAPESP`, `FAPESC`) e identifica o usuário autenticado via guard `'sanctum'`.
  - Se autenticado, busca em `edital_favoritos` os IDs favoritados pelo usuário (filtrando por `startup_id` ou `startup_id IS NULL` quando `startup_id` é informado) e marca `'favorite' => true/false` em cada item.
  - Se enviado `?favoritos=1`, `?apenas_favoritos=1` ou `?fonte=favoritos` (ou via rota `GET /api/editais/favoritos`), filtra a query com `whereIn('id', $favoritosIds)`.
  - `POST /api/editais/{id}/favoritar` alterna (toggle) o registro em `edital_favoritos` para o par `(user_id, edital_id, startup_id)`.
- **Cálculo do Score de Match / Elegibilidade (`MinutaAiService::calcularMatchEditais` — `RF-47`):**
  - Parte de um **score base de `50` pontos** e avalia 3 critérios determinísticos entre a `Startup` e cada `Edital`:
    1. **Nicho de Atuação (+20 pontos):** Se `startup->nicho_atuacao` estiver contido em `edital->ai_nicho` / `edital->objetivo` (ou ambos contiverem `'tecnologia'` ou `'software'`), soma **+20 pontos** e adiciona ponto forte; caso contrário, adiciona ponto de atenção transdisciplinar.
    2. **Porte Empresarial (+15 ou +5 pontos):** Se `edital->publico` contiver `'startup'`, `'micro'`, `'pequena'` ou `'mei'`, soma **+15 pontos**; caso contrário, soma **+5 pontos**.
    3. **Teto Orçamentário (+10 pontos):** Se `edital->max_budget > 0`, soma **+10 pontos**.
  - O score final é limitado no intervalo **`[45, 98]`** (`min(98, max(45, $score))`) e o ranking é ordenado de forma decrescente.
- **Alertas de Match Compatível (`AlertaMatchController` & `AlertaMatchService` — `RF-26` / `EDTAN-172`):**
  - `POST /api/alertas/verificar` executa `calcularMatchEditais` para todos os editais e persiste de forma **idempotente** (`AlertaMatch::firstOrCreate` na constraint única `[user_id, startup_id, edital_id]`) alertas para todos os editais com **`score >= 70%`**.
- **Alertas de Prazos Críticos e Disparo de E-mail (`AlertaPrazoController` & `AlertaPrazoService` — `RF-47` / `EDTAN-171`):**
  - `POST /api/alertas/prazos-criticos/verificar` busca editais com `deadline` entre `now()->subHours(12)` e `now()->addDays($diasMaximos)` (default: **15 dias**).
  - Calcula `$diasRestantes = (int) max(0, ceil($segundosRestantes / 86400))` e classifica em 3 níveis de urgência:
    - **`'critico'` ($\le 3$ dias / 72h, incluindo $\le 24$h):** Prefixo `🚨 Prazo Crítico: `;
    - **`'urgente'` ($4$ a $7$ dias):** Prefixo `⚠️ Prazo Urgente: `;
    - **`'atencao'` ($8$ a $15$ dias):** Prefixo `📅 Prazo Próximo: `.
  - Tenta vincular automaticamente um `KanbanCard` existente para aquele `edital_id` e `startup_name` (`nome_fantasia` ou `razao_social`).
  - Persiste de forma **idempotente** via `AlertaPrazo::updateOrCreate` na chave única `[user_id, edital_id, nivel_urgencia]`.
  - Se a flag `$enviarEmail` estiver ativa (ou quando acionado `POST /api/alertas/prazos-criticos/{id}/enviar-email`) e `nivel_urgencia` for `'critico'` ou `'urgente'`, envia e-mail HTML institucional Dark Mode via `Mail::html()` e registra `email_enviado = true` e `email_enviado_em = now()`.
  - No Front-end (`seletorGlobalStartup.js`), ao carregar qualquer página autenticada, dispara em paralelo `verificarAlertasMatch` e `verificarAlertasPrazosCriticos(stId, 15)` e exibe automaticamente um **Toast Pop-up flutuante** (com Web Notification nativa e vibração mobile) para o alerta não lido mais crítico da sessão.

---

### 7. Pipeline Kanban (`KanbanController`, `KanbanService` e `CardMovementService`)
- **Inicialização Automática e Colunas Padrão (`KanbanService::getOrCreateBoard`):**
  - Busca o quadro da agência (`agencia_id`) e filtra os cards das colunas por `startup_name` (quando informado na query string `?startup_name=`).
  - Se a agência ainda não possuir quadro, cria em transação um `KanbanBoard` (`title: 'Pipeline de Editais'`) com **6 colunas padrão**:
    1. `Backlog` (`slug: 'backlog'`, `position: 10000.0`)
    2. `A fazer` (`slug: 'todo'`, `position: 20000.0`)
    3. `Em elaboração` (`slug: 'doing'`, `position: 30000.0`)
    4. `Bloqueado` (`slug: 'blocked'`, `position: 40000.0`)
    5. `Pronto para Submissão` (`slug: 'review'`, `position: 50000.0`)
    6. `Submetido` (`slug: 'done'`, `position: 60000.0`)
- **Criação de Cards e Vínculo com Editais (`POST /api/kanban/cards`):**
  - Exige que pelo menos `title` ou `edital_id` seja informado (retorna HTTP `422` se ambos estiverem vazios).
  - Se `column_id` for omitido, aloca o card na primeira coluna do quadro (`Backlog`).
  - Se `edital_id` for informado, busca o `Edital` e pré-preenche automaticamente `title` (`$edital->title`), `description` (`$edital->objetivo`) e o JSON `metadata` (`fonte`, `max_budget`, `deadline`, `ai_match`, `publico`).
  - Define a posição inicial como `$lastCard->position + 65536.0` (ou `65536.0` se a coluna estiver vazia).
- **Movimentação via Fractional Indexing (`PATCH /api/kanban/cards/{id}/mover`):**
  - `CardMovementService::move()` recebe `target_column_id`, `prev_position` (nullable) e `next_position` (nullable) e calcula a nova posição em ponto flutuante (`double`) sem precisar reindexar os demais cards da coluna:
    - Sem vizinhos (`prev === null && next === null`): `65536.0`
    - No topo da coluna (`prev === null`): `$nextPosition / 2.0`
    - No fim da coluna (`next === null`): `$prevPosition + 65536.0`
    - Entre dois cards: `($prevPosition + $nextPosition) / 2.0`
  - Se o card mudou para uma coluna cujo título contenha `'proposta'` ou `'elaboração'`, dispara o evento de domínio `event(new CardMovedToProposal($card))`.
- **Anexos e Dados Customizados do Card (`POST /api/kanban/cards/{id}/dados-anexos` — `EDTAN-170`):**
  - Permite atualizar `title`, `description`, mesclar novos campos no JSON `metadata` (como `valor_estimado` e `notas_internas`) e enviar arquivo via `multipart/form-data` no campo `arquivo`.
  - **Regras Estritas de Upload:**
    - Tamanho máximo por arquivo: **10 MB** (`MAX_ANEXO_KB = 10240` / `10 * 1024 * 1024` bytes).
    - Extensões/MIME types permitidos: `pdf, doc, docx, xls, xlsx, csv, txt, png, jpg, jpeg`.
    - Limite acumulado por card: **25 MB** (`MAX_ANEXOS_CARD_BYTES = 25 * 1024 * 1024` bytes), calculado somando o campo `tamanho` de todos os itens existentes em `$card->metadata['anexos']`. Se o novo arquivo ultrapassar 25 MB acumulados, retorna HTTP `422` informando o espaço disponível em MB.
    - Os arquivos são armazenados em `storage/app/public/kanban_anexos` (`$file->store('kanban_anexos', 'public')`) e registrados em `metadata['anexos']` com `{ id, nome, caminho: '/storage/kanban_anexos/...', tamanho, enviado_em }`.

---

### 8. Motor de Geração de Minutas com IA (Multi-LLM), Versionamento, Exportação e Controle de Cotas (`EDTAN-250`)
- **Controle de Cotas Mensais por Plano (`MinutaLimitService` — `EDTAN-250`):**
  - Antes de acionar qualquer LLM em `POST /api/minutas/gerar`, o controller chama `$limitService->obterConsumoMinutas($usuario, $startup)`.
  - O serviço identifica a `Agencia` e seu `Plano` (`limite_minutas_ia`: Standard = `3`, Pro = `15`, Enterprise = `50`; fallback = `5`).
  - Contabiliza todas as minutas criadas no **mês calendário corrente** (`where('created_at', '>=', Carbon::now()->startOfMonth())`) por qualquer usuário da agência (`whereIn('user_id', $userIds)`) ou qualquer startup da agência (`orWhereIn('startup_id', $startupIds)`).
  - Se `$minutasUsadas >= $limite`, bloqueia imediatamente com **HTTP `403`** (`'codigo' => 'LIMITE_MINUTAS_ATINGIDO'`) **antes** de chamar `MinutaAiService`, garantindo custo zero de tokens excedentes.
- **Roteador Multi-LLM em Cascata e Estrutura do Prompt (`MinutaAiService::gerarMinuta`):**
  - Monta o contexto combinando:
    1. **Dados da Startup:** Razão Social, Nome Fantasia, CNPJ, Porte, Regime Tributário, Nicho de Atuação, Cidade/Estado e `pitch`;
    2. **Dados do Edital:** Título, Fonte (`FINEP`/`FAPESC`/`FAPESP`), Teto de Recursos (`max_budget`) e até 1.500 caracteres do `objetivo` / `conteudo_completo`;
    3. **Escopo do Consultor:** `ideia_projeto` (mínimo de 15 caracteres).
  - Solicita retorno estritamente em JSON contendo `titulo_projeto` e o objeto `secoes` com as **11 seções oficiais de fomento** (padrão SEIA Anjo Inovador / FINEP / FAPESC):
    1. `identificacao` (Identificação da Empresa Proponente)
    2. `enquadramento_ods` (Enquadramento Temático e Alinhamento com ODS)
    3. `resumo_executivo` (Resumo Executivo / Pitch da Proposta)
    4. `problema_justificativa` (Problema, Dor de Mercado e Justificativa)
    5. `objeto_objetivos` (Objeto do Projeto e Objetivos Específicos)
    6. `grau_inovacao_trl` (Grau de Inovação e Maturidade Tecnológica TRL)
    7. `metas_entregaveis` (Metas a serem Atingidas e Produtos Entregáveis)
    8. `metodologia_plano_trabalho` (Metodologia e Plano de Trabalho Sintético de 12 meses)
    9. `plano_aplicacao_recursos` (Recursos Solicitados, Contrapartida de 5% e Orçamento)
    10. `matriz_riscos` (Matriz de Riscos e Plano de Contingenciamento)
    11. `impactos_socioeconomicos` (Impactos Socioeconômicos e Ambientais)
  - **Cascata de Provedores configurada em `MinutaAiService`:**
    1. **1ª Tentativa — Google Gemini:** `gemini-2.5-flash:generateContent` (`GEMINI_API_KEY`, `temperature: 0.2`, timeout 70s);
    2. **2ª Tentativa — DeepSeek:** `deepseek-chat` (`DEEPSEEK_API_KEY`, `temperature: 0.2`, timeout 70s);
    3. **3ª Tentativa — Groq:** `llama-3.3-70b-versatile` (`GROQ_API_KEY`, `temperature: 0.2`, timeout 70s);
    4. **4ª Tentativa — Motor Determinístico Local (`gerarMinutaFallback`):** Caso todas as APIs externas falhem ou estejam sem chave no `.env`, gera localmente as 11 seções preenchidas com os dados reais da startup, cálculo de subvenção + contrapartida de 5% e cronograma trimestral de 12 meses, garantindo disponibilidade de 100%.
- **Versionamento Imutável e Auditoria (`MinutaController@update` — `RF-30` / `RF-33` / `RF-34`):**
  - A geração inicial cria um registro em `minutas` com `versao = 1`, `status = 'concluido'`, `user_id` e `autor_nome`.
  - Quando o consultor edita o texto e salva (`PUT /api/minutas/{id}`), o sistema **NÃO sobrescreve** o registro original: ele cria um **novo registro filho** em `minutas` com `versao = $minutaOriginal->versao + 1`, `parent_minuta_id = $minutaOriginal->id` e `status = 'revisado'`, preservando a árvore histórica completa para auditoria.
  - *Nota Técnica:* Edições manuais (`PUT /api/minutas/{id}`) criam novas linhas na tabela `minutas`, o que também é contabilizado na query atual de `MinutaLimitService` se pertencerem ao mesmo mês.
- **Exportação Oficial em `.docx` e `.pdf` (`DocumentExportService` — `RF-32` / `RF-32.1`):**
  - `GET /api/minutas/{id}/exportar-docx`: Gera um arquivo Microsoft Word (`.docx`) nativo construindo um pacote OpenXML (`[Content_Types].xml`, `_rels/.rels` e `word/document.xml`) via `ZipArchive` nativo do PHP, sem depender de bibliotecas pesadas de terceiros, e remove o arquivo temporário após o download (`deleteFileAfterSend(true)`).
  - `GET /api/minutas/{id}/exportar-pdf`: Renderiza uma página HTML institucional formato A4 (`@page { size: A4; margin: 20mm; }`) com cabeçalho do edital, quadro da proponente, as 11 seções numeradas e rodapé de auditoria pronta para impressão/salvamento em PDF pelo navegador.

---

## SEÇÃO 5: Catálogo Completo de Endpoints da API (`routes/api.php`)

> **Nota sobre o Prefixo:** Todas as rotas abaixo são registradas em `routes/api.php` e recebem automaticamente o prefixo `/api` configurado em `bootstrap/app.php`.

| Método HTTP | URI Completa | Controller@Método | Middlewares | Payload de Entrada (Body / Query) | Resposta e Códigos HTTP |
| :---: | :--- | :--- | :--- | :--- | :--- |
| `GET` | `/api/editais` | `EditalController@index` | Público *(lê `auth:sanctum` opcional)* | Query opcional: `fonte` (`FINEP`, `FAPESP`, `FAPESC`, `favoritos`), `startup_id`, `favoritos` (`1`), `apenas_favoritos` (`1`) | `200`: Array JSON de editais formatados para o Radar com flag booleana `favorite` |
| `GET` | `/api/planos` | `PlanoController@listarPlanos` | Público | Nenhum | `200`: Array de planos (`id`, `nome`, `preco`, `preco_mensal`, `limite_startups`, `limite_membros_equipe`, `limite_usuarios`, `limite_minutas_ia`) |
| `POST` | `/api/auth/verificar-email` | `AuthController@verificarEmail` | Público | `{ "email": "string" }` | `200`: `{ "disponivel": true, "message": "..." }` \| `422`: `{ "disponivel": false, "pagamento_pendente": bool, "message": "..." }` |
| `POST` | `/api/auth/registrar-com-plano` | `AuthController@registrarComPlano` | Público | `{ "name", "email", "password" (min:8), "plano_id", "nome_agencia", "forma_pagamento"?: "PIX\|CARD" }` | `201`: `{ "message", "url_checkout", "token_pendencia", "access_token", "token", "usuario", "agencia" }` \| `422` \| `500` |
| `POST` | `/api/auth/registro` | `AuthController@registrar` | Público | `{ "name", "email", "password" (min:8), "nome_agencia" }` | `201`: `{ "message", "token", "usuario", "agencia" }` \| `422` |
| `POST` | `/api/auth/login` | `AuthController@login` | Público | `{ "email", "password" }` | `200`: `{ "status": "sucesso", "message", "token", "dados": { "usuario", "agencia" } }` \| `401`: Credenciais inválidas \| `403`: `{ "codigo": "PAGAMENTO_PENDENTE", "token_pendencia", "agencia" }` |
| `GET` | `/api/auth/login` | `Closure` (`routes/api.php`) | Público (`name: login`) | Nenhum | `401`: `{ "mensagem": "Não autenticado. Por favor, faça login." }` |
| `POST` | `/api/auth/esqueci-senha` | `AuthController@solicitarRecuperacaoSenha` | Público | `{ "email": "string" }` | `200`: `{ "message": "Enviamos um link..." }` \| `404`: E-mail não encontrado \| `500`: Falha SMTP |
| `POST` | `/api/auth/redefinir-senha` | `AuthController@redefinirSenha` | Público | `{ "email", "token", "password" (min:8), "password_confirmation" }` | `200`: `{ "message": "Sua senha foi redefinida..." }` \| `400`: Token inválido/expirado (>60m) \| `404` |
| `POST` | `/api/webhook/abacatepay` | `WebhookController@abacatePayWebhook` | Público *(valida `?webhookSecret=`)* | Query: `webhookSecret`. Body: Payload AbacatePay (`event`, `data.id`, `data.status`, `data.externalId`) | `200`: `{ "message": "Webhook processado com sucesso" }` \| `400`: Sem `externalId` \| `401`: Secret inválido \| `404`: Agência não encontrada |
| `GET` | `/api/pagamentos/consultar-anterior` | `AuthController@consultarPagamentoAnterior` | Público / Híbrido (`resolverUsuarioPendente`) | Query/Header: `email`, `token_pendencia`, `X-Pending-Email`, `X-Pending-Token` ou Bearer | `200`: `{ "expirado": false, "pago": true/false, "url_checkout", "message" }` \| `400`: `{ "expirado": true, "message" }` \| `404` |
| `POST` | `/api/planos/gerar-nova-cobranca` | `AuthController@gerarNovaCobranca` | Público / Híbrido (`resolverUsuarioPendente`) | `{ "plano_id": int, "forma_pagamento"?: "PIX\|CARD", "email"?: string, "token_pendencia"?: string }` | `200`: `{ "url_checkout", "forma_pagamento", "message" }` \| `404` \| `422` \| `500` |
| `DELETE` | `/api/auth/cancelar-cadastro-pendente` | `AuthController@cancelarCadastroPendente` | Público / Híbrido (`resolverUsuarioPendente`) | `{ "email"?: string, "token_pendencia"?: string }` ou Bearer | `200`: `{ "message": "Pagamento cancelado e conta excluída com sucesso." }` \| `400`: Conta já ativa \| `401` |
| `POST` | `/api/editais/{id}/favoritar` | `EditalController@toggleFavorito` | `auth:sanctum` | `{ "startup_id"?: int }` | `200`: `{ "sucesso": true, "favorito": true/false, "message": "..." }` \| `401` \| `404` |
| `GET` | `/api/editais/favoritos` | `EditalController@listarFavoritos` | `auth:sanctum` | Query opcional: `startup_id` | `200`: Array JSON contendo apenas os editais favoritados pelo usuário |
| `GET` | `/api/alertas/match` | `AlertaMatchController@index` | `auth:sanctum` | Query opcional: `startup_id`, `apenas_nao_lidos` (`bool`) | `200`: `{ "sucesso": true, "total_nao_lidos": int, "dados": [...] }` |
| `POST` | `/api/alertas/verificar` | `AlertaMatchController@verificar` | `auth:sanctum` | `{ "startup_id": int (required) }` | `200`: `{ "sucesso": true, "message", "total_gerados": int, "total_nao_lidos": int, "dados": [...] }` |
| `GET` | `/api/alertas/contador` | `AlertaMatchController@contador` | `auth:sanctum` | Query opcional: `startup_id` | `200`: `{ "sucesso": true, "total_nao_lidos": int, "detalhes": { "match": int, "prazos": int } }` |
| `PATCH` | `/api/alertas/{id}/lido` | `AlertaMatchController@marcarLido` | `auth:sanctum` | Nenhum | `200`: `{ "sucesso": true, "message": "Alerta marcado como lido." }` \| `404` |
| `POST` | `/api/alertas/marcar-todos-lidos` | `AlertaMatchController@marcarTodosLidos` | `auth:sanctum` | `{ "startup_id"?: int }` | `200`: `{ "sucesso": true, "message", "total_afetados": int }` |
| `GET` | `/api/alertas/prazos-criticos` | `AlertaPrazoController@index` | `auth:sanctum` | Query opcional: `startup_id`, `apenas_nao_lidos` (`bool`), `nivel_urgencia` (`critico`, `urgente`, `atencao`) | `200`: `{ "sucesso": true, "total_nao_lidos": int, "dados": [...] }` |
| `POST` | `/api/alertas/prazos-criticos/verificar` | `AlertaPrazoController@verificar` | `auth:sanctum` | `{ "startup_id"?: int, "dias_maximos"?: int (1..90, default 15), "enviar_email"?: bool }` | `200`: `{ "sucesso": true, "message", "total_gerados": int, "total_nao_lidos": int, "dados": [...] }` |
| `GET` | `/api/alertas/prazos-criticos/contador` | `AlertaPrazoController@contador` | `auth:sanctum` | Query opcional: `startup_id` | `200`: `{ "sucesso": true, "total_nao_lidos": int }` |
| `PATCH` | `/api/alertas/prazos-criticos/{id}/lido` | `AlertaPrazoController@marcarLido` | `auth:sanctum` | Nenhum | `200`: `{ "sucesso": true, "message", "alerta": {...} }` \| `404` |
| `POST` | `/api/alertas/prazos-criticos/marcar-todos-lidos` | `AlertaPrazoController@marcarTodosLidos` | `auth:sanctum` | `{ "startup_id"?: int }` | `200`: `{ "sucesso": true, "message", "total_atualizados": int }` |
| `POST` | `/api/alertas/prazos-criticos/{id}/enviar-email` | `AlertaPrazoController@enviarEmail` | `auth:sanctum` | Nenhum | `200`: `{ "sucesso": bool, "message": "..." }` \| `404` |
| `GET` | `/api/auth/perfil` | `AuthController@obterPerfil` | `auth:sanctum` | Nenhum | `200`: `{ "status": "sucesso", "usuario": { "id", "name", "email", "perfil_acesso" } }` |
| `PUT` | `/api/auth/perfil` | `AuthController@atualizarPerfil` | `auth:sanctum` | `{ "name", "email", "password"?: string (min:8) }` | `200`: `{ "status": "sucesso", "message": "Perfil atualizado com sucesso!", "usuario": {...} }` \| `422` |
| `DELETE` \| `POST` | `/api/conta/cancelar` | `AuthController@cancelarConta` | `auth:sanctum` *(exige `consultor_admin`)* | `{ "password": "string", "ciente_lgpd": true }` | `200`: `{ "message": "Sua conta, assinatura e todos os dados vinculados foram permanentemente excluídos conforme a LGPD." }` \| `403` \| `422` |
| `GET` | `/api/assinatura/atual` | `AssinaturaController@atual` | `auth:sanctum` | Nenhum | `200`: `{ "plano": {...}, "uso_atual": {...}, "forma_pagamento": "PIX\|CARD", "forma_pagamento_label", "em_carencia": bool, "data_vencimento_formatada", "data_repeticao_formatada", "data_liberacao_renovacao_formatada", "status_pagamento", "assinatura_ativa", "dias_restantes", "pode_renovar", ... }` |
| `POST` | `/api/assinatura/renovar` | `AssinaturaController@renovar` | `auth:sanctum` *(exige `consultor_admin`)* | `{ "forma_pagamento"?: "PIX\|CARD" }` | `200`: `{ "message", "reaproveitada": bool, "forma_pagamento", "id_transacao", "url_checkout", "pagamento_expira_em" }` \| `403` \| `422` (>5 dias restantes) |
| `POST` | `/api/assinatura/atualizar-forma-pagamento` | `AssinaturaController@atualizarFormaPagamento` | `auth:sanctum` *(exige `consultor_admin`)* | `{ "forma_pagamento": "PIX\|CARD" }` | `200`: `{ "message", "forma_pagamento", "forma_pagamento_label", "requer_checkout": bool, "url_checkout": string\|null, ... }` \| `403` \| `422` |
| `GET` | `/api/pagamento/recuperar` | `PagamentoController@recuperarLinkPagamento` | `auth:sanctum` | Nenhum | `200`: `{ "url_checkout": "..." }` \| `400`: Já ativa \| `404` |
| `POST` | `/api/planos/selecionar` | `PlanoController@selecionarPlano` | `auth:sanctum` | `{ "plano_id": int }` | `200`: `{ "message", "plano", "url_checkout" }` \| `404` \| `500` |
| `GET` | `/api/startups` | `StartupController@index` | `auth:sanctum`, `check.pagamento` | Query opcional: `busca` (filtra nome_fantasia, razao_social ou cnpj) | `200`: Array JSON de startups da agência do usuário ordenadas por `nome_fantasia` \| `403`: `PAGAMENTO_PENDENTE` |
| `POST` | `/api/startups` | `StartupController@store` | `auth:sanctum`, `check.pagamento` | `{ "cnpj", "razao_social", "nome_fantasia"?, "data_abertura"?, "cep"?, "logradouro"?, "numero"?, "bairro"?, "cidade"?, "estado"?, "porte"?, "regime_tributario"?, "nicho_atuacao"?, "pitch"? }` | `201`: `{ "message": "Startup cadastrada com sucesso.", "startup": {...} }` \| `422`: CNPJ duplicado na agência |
| `GET` | `/api/startups/{id}` | `StartupController@show` | `auth:sanctum`, `check.pagamento` | Parâmetro URL: `id` | `200`: Objeto JSON da startup (restrito à `agencia_id` do usuário) \| `404` |
| `PUT` | `/api/startups/{id}` | `StartupController@update` | `auth:sanctum`, `check.pagamento` | `{ "razao_social" (required), "cnpj"?, "nome_fantasia"?, "email"?, "telefone"?, "endereco...", "porte"?, "regime_tributario"?, "nicho_atuacao"?, "pitch"? }` | `200`: `{ "sucesso": true, "message": "Perfil da startup atualizado com sucesso.", "startup": {...} }` \| `404` |
| `GET` | `/api/kanban` | `KanbanController@index` | Público | Query opcional: `agencia_id`, `startup_name` | `200`: `{ "status": "sucesso", "data": { "id", "title", "columns": [{ ..., "cards": [...] }] } }` |
| `POST` | `/api/kanban/cards` | `KanbanController@storeCard` | Público | `{ "board_id"?, "column_id"?, "edital_id"?, "title"?, "description"?, "startup_name"?, "metadata"? }` | `201`: `{ "status": "sucesso", "message": "Card criado com sucesso!", "data": {...} }` \| `422`: Sem `title` e sem `edital_id` |
| `PATCH` | `/api/kanban/cards/{id}/mover` | `KanbanController@moveCard` | Público | `{ "target_column_id": int (required), "prev_position"?: float, "next_position"?: float }` | `200`: `{ "status": "sucesso", "message": "Card movimentado com sucesso!", "data": {...} }` |
| `POST` | `/api/kanban/cards/{id}/dados-anexos` | `KanbanController@updateCard` | Público | `multipart/form-data` ou JSON: `{ "title"?, "description"?, "metadata"?: array, "arquivo"?: file (max 10MB, max 25MB/card) }` | `200`: `{ "status": "sucesso", "message": "Card atualizado com sucesso!", "data": {...}, "card": {...} }` \| `422`: Limite ou formato de arquivo inválido |
| `DELETE` | `/api/kanban/cards/{id}` | `KanbanController@destroyCard` | Público | Parâmetro URL: `id` | `200`: `{ "status": "sucesso", "message": "Card removido com sucesso!" }` |
| `GET` | `/api/minutas/consumo` | `MinutaController@consumo` | Público *(lê `$request->user()` se enviado)* | Query opcional: `startup_id` | `200`: `{ "sucesso": true, "dados": { "plano_id", "plano_nome", "limite", "usadas", "disponiveis", "porcentagem_usada", "atingiu_limite", "ciclo_inicio", "ciclo_fim" } }` |
| `POST` | `/api/minutas/gerar` | `MinutaController@gerar` | Público *(lê `$request->user()` se enviado)* | `{ "startup_id": int, "edital_id": int, "ideia_projeto": string (min:15), "kanban_card_id"?: int }` | `201`: `{ "sucesso": true, "message", "dados": {...}, "consumo": {...} }` \| `403`: `{ "sucesso": false, "codigo": "LIMITE_MINUTAS_ATINGIDO", "consumo": {...} }` |
| `GET` | `/api/minutas` | `MinutaController@index` | Público | Query opcional: `startup_id`, `edital_id`, `kanban_card_id` | `200`: `{ "sucesso": true, "dados": { "data": [...], ...paginação (15/pág) } }` |
| `GET` | `/api/minutas/{id}` | `MinutaController@show` | Público | Parâmetro URL: `id` | `200`: `{ "sucesso": true, "dados": { ..., "versoes_filhas": [...] } }` \| `404` |
| `PUT` | `/api/minutas/{id}` | `MinutaController@update` | Público *(lê `$request->user()` se enviado)* | `{ "conteudo_gerado": string (required), "secoes_estruturadas"?: array, "titulo_projeto"?: string }` | `200`: `{ "sucesso": true, "message": "Proposta atualizada com sucesso para a versão v{N}.", "dados": {...} }` |
| `GET` | `/api/minutas/{id}/exportar-docx` | `MinutaController@exportarDocx` | Público | Parâmetro URL: `id` | `200`: Download binário `.docx` (`Minuta_Proposta_{Startup}_v{N}.docx`) |
| `GET` | `/api/minutas/{id}/exportar-pdf` | `MinutaController@exportarPdf` | Público | Parâmetro URL: `id` | `200`: Documento `text/html; charset=UTF-8` formatado em A4 para impressão/PDF |
| `POST` | `/api/ia/match-editais` | `MinutaController@matchEditais` | Público | `{ "startup_id": int (required) }` | `200`: `{ "sucesso": true, "startup": {...}, "ranking": [{ "edital_id", "titulo", "fonte", "match_percentual", "prazo", "orcamento_maximo", "justificativa_fit", "pontos_fortes", "pontos_atencao" }] }` |
| `GET` | `/api/cnpj/{cnpj}` | `CnpjController@consultar` | Público | Parâmetro URL: `cnpj`. Query opcional: `refresh` (`bool`) | `200`: `{ "status": "sucesso", "message", "dados": { "provedor", "cnpj", "cnpj_formatado", "razao_social", "porte", "regime_tributario", "endereco", "startup_payload", ... } }` \| `404` \| `422` \| `503` |
| `POST` | `/api/cnpj/consultar` | `CnpjController@consultarPost` | Público | `{ "cnpj": string (required, CnpjValidationRule), "refresh"?: bool }` | `200`: `{ "status": "sucesso", "message", "dados": {...} }` \| `404` \| `422` \| `503` |
