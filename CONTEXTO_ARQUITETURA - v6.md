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

6. **Orquestração Local Unificada via Docker Compose (`MVPs/Versão Atual/`):**
   - O ecossistema completo pode ser iniciado com um único comando a partir de `MVPs/Versão Atual/` via `docker-compose.yml` + `.env.docker` (gerado a partir de `.env.docker.example`).
   - O container `back` (`back/Dockerfile` + `back/docker/entrypoint.sh`) gerencia automaticamente a instalação do Composer, geração de `APP_KEY`, espera ativa pelo MySQL (`db`), migração/seeding controlada por `DOCKER_FRESH_DB` (`true` -> `migrate:fresh --seed --force`; `false` -> `migrate --force` + `PlanoSeeder`) e subida da API em `0.0.0.0:8000`, liberando por healthcheck os containers dependentes `scheduler`, `spiders` e `ngrok`.

---

## SEÇÃO 2: Mapa Completo de Arquivos e Diretórios (`MVPs/Versão Atual/`)

### 2.0. Infraestrutura Docker Compose (`MVPs/Versão Atual/`)

```text
MVPs/Versão Atual/
├── docker-compose.yml                                          # Orquestrador dos 6 serviços (db, back, scheduler, spiders, front, ngrok) na rede bridge anjonexus_net
├── .env.docker.example                                         # Template completo de variáveis de ambiente para execução via Docker Compose (DOCKER_FRESH_DB, DB_*, ABACATEPAY_*, MAIL_*, IA, NGROK_AUTHTOKEN)
└── back/
    ├── Dockerfile                                              # Imagem PHP 8.2-cli-bookworm com extensões Laravel 12 (pdo_mysql, zip/OpenXML, mbstring, xml, bcmath, curl, intl, gd, pcntl) + Composer 2
    ├── .dockerignore                                           # Exclui vendor, node_modules, .git, .env e storage/logs/*.log do contexto de build
    └── docker/
        └── entrypoint.sh                                       # Script LF (chmod +x) de boot automatizado: aguarda MySQL via PDO, composer install, APP_KEY, DOCKER_FRESH_DB (migrate:fresh --seed vs migrate) e php artisan serve
```

### 2.1. Back-end (`MVPs/Versão Atual/back/`)

```text
MVPs/Versão Atual/back/
├── Dockerfile                                                  # Definição da imagem Docker PHP 8.2-cli compartilhada pelos serviços back, scheduler e spiders
├── .dockerignore                                               # Filtros de exclusão de contexto de build Docker
├── docker/
│   └── entrypoint.sh                                           # Entrypoint inteligente do container Laravel (wait-for-db, migrations/seeders condicionais e start da API)
├── bootstrap/
│   └── app.php                                                 # Registra rotas (web, api, console) e aliases de middleware ('check.pagamento', 'startup.ativa')
├── config/
│   └── cors.php                                                # Configuração CORS permitindo http://localhost:5500, http://127.0.0.1:5500 e headers customizados (X-Startup-Id, X-Pending-Token, X-Pending-Email, X-Frontend-Url)
├── routes/
│   ├── api.php                                                 # Definição de todos os 48 endpoints REST da API (públicos, convites 2 canais, auth:sanctum, equipe, check.pagamento, kanban, minutas, cnpj)
│   ├── console.php                                             # Comandos Artisan base (inspire) e agendamento diário do ciclo de assinaturas (assinaturas:processar-ciclo às 03:00)
│   └── web.php                                                 # Rota web raiz GET /
├── database/
│   ├── migrations/
│   │   ├── 0001_01_01_000001_create_cache_table.php            # Cria tabelas 'cache' e 'cache_locks'
│   │   ├── 0001_01_01_000002_create_jobs_table.php             # Cria tabelas de filas 'jobs', 'job_batches' e 'failed_jobs'
│   │   ├── 2026_09_01_131306_create_editals_table.php          # Cria tabela 'editals' (editais raspados, orçamentos, prazos, metadados de IA e documentos JSON)
│   │   ├── 2026_09_15_000001_create_planos_table.php           # Cria tabela 'planos' (preço, id_transacao AbacatePay e cotas de startups, equipe e minutas IA)
│   │   ├── 2026_09_15_000002_create_agencias_table.php         # Cria tabela 'agencias' (tenant B2B, owner_id, forma_pagamento PIX/CARD, status_pagamento, url_checkout, pagamento_expira_em, assinatura_expira_em, alerta_vencimento_proximo_enviado_em, alerta_falha_pagamento_enviado_em)
│   │   ├── 2026_09_15_000003_create_users_table.php            # Cria tabela 'users' (vínculo com agencia_id, perfil_acesso, permissoes JSON, status pendente/ativo/inativo e colunas de convite em 2 canais)
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
│   │   ├── ProcessarCicloAssinaturas.php                       # Comando 'assinaturas:processar-ciclo' (chunkById 100): aviso preventivo (<= 5d), carência 2 dias + alerta SMTP, suspensão e expurgo LGPD (3d nunca pagas / 30d suspensas)
│   │   ├── ProcessarPdfEditaisCommand.php                      # Comando 'editais:processar-pdfs {--fonte=} {--limit=} {--force}' que extrai texto bruto, prazos, orçamentos e checklist de documentos dos PDFs (Custo Zero IA)
│   │   ├── SyncAllEditais.php                                  # Comando 'editais:sync {--fonte=}' que executa os 6 Spiders (FINEP, FAPESP, FAPESC, FAPEMIG, FAPERJ e FAPERGS) via RoachPHP
│   │   ├── SyncFapemig.php                                     # Comando 'fapemig:sync' para disparar isoladamente o FapemigSpider
│   │   ├── SyncFapergs.php                                     # Comando 'fapergs:sync' para disparar isoladamente o FapergsSpider
│   │   ├── SyncFaperj.php                                      # Comando 'faperj:sync' para disparar isoladamente o FaperjSpider
│   │   ├── SyncFapesc.php                                      # Comando 'spider:fapesc' para disparar isoladamente o FapeScSpider
│   │   ├── SyncFinepDetalhes.php                               # Comando 'finep:sync-detalhes {--limit=}' que raspa links de anexos, região e público-alvo das páginas Liferay da FINEP
│   │   └── TestJob.php                                         # Comando 'app:test-job' para testar execução síncrona do job AnalyzeEditalWithIA no primeiro edital
│   ├── Enums/
│   │   └── Permissao.php                                       # Catálogo oficial de permissões atômicas (startups.criar_editar, startups.excluir, kanban.gerenciar, minutas.gerenciar, equipe.gerenciar, assinatura.gerenciar) e presets (EDTAN-163 / ADR 040)
│   ├── Events/
│   │   └── CardMovedToProposal.php                             # Evento disparado quando um KanbanCard é movido para coluna contendo 'proposta' ou 'elaboração'
│   ├── Exceptions/
│   │   ├── CnpjNotFoundException.php                           # Exceção de domínio (HTTP 404) lançada quando um CNPJ válido não existe na Receita Federal
│   │   └── PlanLimitExceededException.php                      # Exceção de domínio (HTTP 403 LIMITE_STARTUPS_ATINGIDO / LIMITE_CONSULTORES_ATINGIDO) lançada pelo PlanoQuotaService quando a agência atinge o teto do plano (EDTAN-249 / EDTAN-309)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AlertaMatchController.php                       # Endpoints de listagem, verificação, contador e leitura de Alertas de Match Inteligente (RF-26 / EDTAN-172)
│   │   │   ├── AlertaPrazoController.php                       # Endpoints de listagem, varredura, contador, leitura e disparo de e-mail de Prazos Críticos (RF-47 / EDTAN-171)
│   │   │   ├── AssinaturaController.php                        # Consulta da assinatura/cotas atuais (restrita a isOwner), alerta proativo de vencimento (EDTAN-310), renovação idempotente (PIX/CARD) e atualização de forma de pagamento (EDTAN-160 / EDTAN-311)
│   │   │   ├── AuthController.php                              # Pré-validação de e-mail, registro com plano (PIX/CARD), login (com bloqueio 403 para convite pendente ou inativo), gestão de pagamento pendente, recuperação de senha, perfil (/api/me e /api/auth/perfil com is_owner e permissoes) e exclusão LGPD
│   │   │   ├── CnpjController.php                              # Consulta pública GET/POST de CNPJ com validação Módulo 11 e tratamento de erros (EDTAN-134)
│   │   │   ├── Controller.php                                  # Classe base abstrata dos Controllers Laravel
│   │   │   ├── EditalController.php                            # Listagem formatada de editais para o Radar (Custo Zero IA) e toggle/listagem de favoritos (RF-10 / RF-11)
│   │   │   ├── EquipeController.php                            # Gestão de equipe da consultoria (/api/equipe*), governança de cota_equipe e ativação pública de convite em 2 canais (/api/convites*) (EDTAN-163 / EDTAN-309 / ADR 040 / ADR 041 / ADR 042)
│   │   │   ├── KanbanController.php                            # CRUD de cards do Kanban, movimentação fractional indexing e upload validado de anexos (10MB/arq, 25MB/card)
│   │   │   ├── MinutaController.php                            # Geração de minutas com trava de cota mensal (EDTAN-250), versionamento imutável, exportação DOCX/PDF e ranking de Match IA
│   │   │   ├── PagamentoController.php                         # Endpoint legado para recuperar/regerar link de pagamento de agência pendente
│   │   │   ├── PlanoController.php                             # Listagem pública de planos e endpoint legado de seleção de plano
│   │   │   ├── StartupController.php                           # Listagem multi-tenant com metadados de cota, cadastro com trava PlanoQuotaService + StartupPolicy, exibição, edição e exclusão lógica (Soft Delete - EDTAN-249)
│   │   │   └── WebhookController.php                           # Receptor do webhook AbacatePay com validação de secret, ativação (+30 dias), limpeza de alertas de vencimento/falha e corte imediato em refunded/lost
│   │   ├── Middleware/
│   │   │   ├── CheckPagamentoAtivo.php                         # Alias 'check.pagamento': bloqueia (403 PAGAMENTO_PENDENTE) usuários cuja agência não está com pagamento ativo
│   │   │   └── VerificarStartupAtiva.php                       # Alias 'startup.ativa': valida anti-IDOR se o header 'X-Startup-Id' pertence à 'agencia_id' do usuário autenticado
│   │   └── Requests/
│   │       └── RegistroCadastroRequest.php                     # FormRequest de validação de registro inicial (name, email unique, password min:8, nome_agencia)
│   ├── Jobs/
│   │   ├── AnalyzeEditalWithIA.php                             # Job assíncrono que extrai HTML/PDF da FAPESC e envia edital ao DeepSeekService (Groq -> DeepSeek -> Gemini -> Mock)
│   │   └── ScrapeEditalCompletoJob.php                         # Job de Custo Zero de IA que extrai texto bruto e documentos exigidos de PDFs ou páginas HTML via PdfExtractorService
│   ├── Models/
│   │   ├── Agencia.php                                         # Model do tenant ('agencias') com isPagamentoAtivo() (carência de 2 dias), emPeriodoCarencia(), emJanelaVencimentoProximo() e relações com plano, usuarios, startups e kanbanBoards
│   │   ├── AlertaMatch.php                                     # Model de 'alerta_matches' com casts para criterios (array), lido (boolean) e score_match (integer)
│   │   ├── AlertaPrazo.php                                     # Model de 'alerta_prazos' com relações para user, startup, edital e kanbanCard
│   │   ├── Edital.php                                          # Model de 'editals' com casts para documentos (array), ai_analyzed (boolean) e ai_diagnosis (array)
│   │   ├── EditalFavorito.php                                  # Model de 'edital_favoritos' relacionando User, Edital e Startup
│   │   ├── KanbanBoard.php                                     # Model de 'kanban_boards' com relações ordenadas por 'position' para columns e cards
│   │   ├── KanbanCard.php                                      # Model de 'kanban_cards' com casts para position (double) e metadata (array)
│   │   ├── KanbanColumn.php                                    # Model de 'kanban_columns' com cast position (double) e relação ordenada com cards
│   │   ├── Minuta.php                                          # Model de 'minutas' com auto-relacionamento de versionamento (parentMinuta e versoesFilhas)
│   │   ├── Plano.php                                           # Model de 'planos' com casts numéricos para preço e limites de startups, equipe e minutas IA
│   │   ├── Startup.php                                         # Model de 'startups' (com trait SoftDeletes) pertencente a uma Agencia
│   │   └── User.php                                            # Model de 'users' (Sanctum HasApiTokens) com isOwner(), temPermissao(), permissoesEfetivas() e casts de permissoes/convite (EDTAN-163)
│   ├── Policies/
│   │   ├── EquipePolicy.php                                    # Policy de autorização de equipe (viewAny, create, manageMember, assignPermissions) com Anti-IDOR, proteção do owner_id e proibição de delegação transitiva (EDTAN-163 / ADR 040)
│   │   └── StartupPolicy.php                                   # Policy de autorização (create, view, update, delete) com isolamento Anti-IDOR por agencia_id e verificação granular de startups.criar_editar e startups.excluir
│   ├── Providers/
│   │   └── AppServiceProvider.php                              # Service Provider padrão da aplicação (registra StartupPolicy e EquipePolicy via Gate::policy)
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
│   │   ├── EquipeService.php                                   # Regras de negócio de equipe: governança transacional de cotas com lock pessimista (EDTAN-309 / ADR 042), convite seguro em 2 canais (token 48h + PIN 6 dígitos criptografado AES-256-CBC), exposição controlada por EquipePolicy::manageMember, ativação via hash_equals (5 tentativas) e revogação Sanctum (EDTAN-163 / ADR 041)
│   │   ├── KanbanService.php                                   # Inicializa quadro Kanban com 6 colunas padrão, filtra cards por startup_name e pré-preenche card com dados do edital
│   │   ├── MinutaAiService.php                                 # Gera minutas de 11 seções (Gemini 2.5 Flash -> DeepSeek -> Groq Llama 3.3 -> Fallback Determinístico) e calcula score de Match
│   │   ├── MinutaLimitService.php                              # Contabiliza minutas geradas no mês corrente por toda a agência e verifica se atingiu 'limite_minutas_ia' do plano (EDTAN-250)
│   │   ├── PdfExtractorService.php                             # Extrator determinístico (Custo Zero IA) de texto PDF (Smalot PdfParser), prazos, orçamentos, faturamento e checklist de 15 documentos
│   │   ├── PlanoQuotaService.php                               # Motor centralizado de cotas de startups e de equipe do plano (getUsageSummary, assertCanCreateStartup, obterResumoCotaEquipe, validarLimiteEquipe - EDTAN-249 / EDTAN-309 / ADR 038 / ADR 042)
│   │   └── RegistroService.php                                 # Serviço transacional para criação inicial de Agência (status pendente + owner_id) + Usuário (consultor_admin) + Token Sanctum
│   ├── Spiders/
│   │   ├── BaseSpider.php                                      # Classe abstrata RoachPHP com SalvarNoBancoProcessor, limpeza de texto, parser de datas BR e hash MD5 único
│   │   ├── FapemigSpider.php                                   # Spider Nuxt SSR/Regex para chamadas e editais da FAPEMIG (https://fapemig.br/oportunidades/chamadas-e-editais)
│   │   ├── FapergsSpider.php                                   # Spider Crawler para editais abertos e em julgamento da FAPERGS (https://fapergs.rs.gov.br/abertos)
│   │   ├── FaperjSpider.php                                    # Spider Crawler para editais e chamadas da FAPERJ (https://www.faperj.br/?id=28.5.7)
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
    ├── EquipeManagementAndInviteTest.php                       # Testa convite em 2 canais (PIN 6 dígitos + token), bloqueio login pendente/inativo, limite de 5 tentativas, proibição de delegação transitiva, Anti-IDOR, enforcement RBAC e revogação Sanctum (EDTAN-163)
    ├── EquipeQuotaLimitTest.php                                # Testa governança de cotas de equipe (8 cenários: inclusão titular, convite pendente, inativação liberando vaga, bloqueio reativação cheia, reativação liberada, cancelamento pendente, reenvio liberado, upgrade/ilimitado - EDTAN-309)
    ├── ExampleTest.php                                         # Smoke test básico na rota web GET /
    ├── KanbanApiTest.php                                       # Testa inicialização das 6 colunas padrão, criação/movimentação fractional indexing e limites de upload (10MB/arq e 25MB/card)
    ├── MinutaApiTest.php                                       # Testa geração de minuta v1, versionamento imutável v2, exportação .docx e bloqueio 403 quando atinge cota mensal do plano
    ├── SecurityMiddlewareTest.php                              # Testa rejeição 401 sem token/token inválido, bloqueio 403 do CheckPagamentoAtivo e proteção de conta ativa contra exclusão pendente
    └── StartupQuotaAndPolicyTest.php                           # Testa trava 403 LIMITE_STARTUPS_ATINGIDO (PlanoQuotaService), Soft Delete liberando vaga, StartupPolicy (Anti-IDOR + RBAC/owner_id) e estrutura de cota no GET /api/startups (EDTAN-249)
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
│   ├── aceitar-convite.html                                    # Página pública de ativação de convite de consultor em 2 canais (valida ?token= e solicita PIN de 6 dígitos + nova senha - EDTAN-163)
│   ├── baixar-app.html                                         # Página para download direto do APK Android (anjonexus.apk)
│   ├── busca-editais.html                                      # Tela principal de Busca e Radar de Editais com filtros avançados e drawer de diagnóstico
│   ├── cadastrar-startup.html                                  # Formulário de cadastro de nova startup com busca automática de CNPJ na Receita Federal
│   ├── cadastro.html                                           # Etapa 1 do onboarding SaaS: coleta Nome da Consultoria, Nome, E-mail e Senha e valida disponibilidade do e-mail
│   ├── equipe.html                                             # Painel de Gestão de Equipe (Consultores), indicador de capacidade de cota com barra de progresso, banner de limite atingido com CTA condicional de upgrade, presets e checkboxes de permissões granulares, modal pós-convite (PIN + Link) e revogação de acesso (EDTAN-163 / EDTAN-309)
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
    │   ├── seletorGlobalStartup.js                             # Header global unificado: dropdown de troca de startup, Central de Alertas (Match + Prazos), Toast Pop-up, Editar Perfil, Minha Assinatura (PIX/CARD, datas DD/MM/AAAA, Atualizar Forma de Pagamento), Alerta Proativo de Vencimento (badge + banner para consultor_admin) e Cancelamento LGPD
    │   └── sidebarGlobal.js                                    # Barra lateral responsiva unificada (Radar, Kanban, Minhas Startups, App Mobile e Logoff) com drawer mobile
    ├── paginas/
    │   ├── aceitarConvite.js                                   # Validação de token de convite público (GET /api/convites/{token}) e ativação com PIN de 6 dígitos + senha (POST /api/convites/aceitar)
    │   ├── cadastrarStartup.js                                 # Lógica de máscara de CNPJ/CEP, auto-preenchimento via GET /api/cnpj/{cnpj} e criação de startup
    │   ├── cadastro.js                                         # Envio e confirmação de OTP e persistência em sessionStorage('dados_pre_cadastro')
    │   ├── equipe.js                                           # Controlador de equipe.html: listagem de membros, convite com presets, modal de exibição única (PIN + Link), edição de permissões, inativação e remoção
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
        ├── servicoAutenticacao.js                              # Gestão completa de tokens, estado de usuário (is_owner, status, permissoes), helpers usuarioEhTitular/usuarioTemPermissao e chamadas de autenticação, perfil e cancelamento LGPD
        ├── servicoEquipe.js                                    # Consumo dos endpoints de gestão de equipe (/equipe*) e ativação pública de convites (/convites*) (EDTAN-163)
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
| **1** | **Standard** | `49.90` | `prod_NKNk5t4saLSRLGEEZ21qL0sG` | `2` | `1` | `3` |
| **2** | **Pro** | `99.90` | `prod_BrNPMy2ajTtF0MzhzWwdCkpp` | `10` | `5` | `15` |
| **3** | **Enterprise** | `299.90` | `prod_yjeeY2u5sXwGaZJZGGpEgW1g` | `50` | `15` | `50` |

---

#### 2. Tabela `agencias` (`2026_09_15_000002_create_agencias_table.php`)
| Coluna | Tipo Físico | Nullable | Default | Constraints / Observações |
| :--- | :--- | :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | Não | Auto Inc | `PRIMARY KEY` |
| `nome` | `VARCHAR(255)` | Não | - | Nome da consultoria / agência (Tenant B2B) |
| `owner_id` | `BIGINT UNSIGNED` | Sim | `NULL` | `INDEX` — ID do usuário titular/fundador financeiro da agência (`EDTAN-249` / `ADR 039`) |
| `plano_id` | `BIGINT UNSIGNED` | Sim | `NULL` | `FK -> planos.id` (`ON DELETE SET NULL`) |
| `forma_pagamento` | `ENUM` | Não | `'PIX'` | Valores: `['PIX', 'CARD']` — Meio de pagamento escolhido pela agência (`EDTAN-311`) |
| `id_transacao` | `VARCHAR(255)` | Sim | `NULL` | ID do checkout/cobrança atual no AbacatePay |
| `plano_upgrade_pendente_id` | `FK planos` | Sim | `NULL` | Destino aguardando confirmação |
| `upgrade_transacao_id` | `VARCHAR(255)` | Sim | `NULL` | Checkout da troca vigente |
| `upgrade_dias_extras` | `INT UNSIGNED` | Não | `0` | Dias bônus após confirmação |
| `upgrade_forma_pagamento` | `VARCHAR(20)` | Sim | `NULL` | Meio de pagamento da troca |
| `plano_agendado_id` | `FK planos` | Sim | `NULL` | Destino após o fim do ciclo |
| `url_checkout` | `TEXT` | Sim | `NULL` | URL de pagamento (`PIX` ou `CARD`) ativa no AbacatePay |
| `status_pagamento` | `ENUM` | Não | `'pendente'` | Valores: `['pendente', 'ativo', 'suspenso', 'cancelado']` |
| `pagamento_expira_em` | `TIMESTAMP` | Sim | `NULL` | Validade de 60 minutos da janela de checkout aberta |
| `assinatura_expira_em` | `TIMESTAMP` | Sim | `NULL` | Data/hora de expiração do ciclo mensal de 30 dias da assinatura |
| `alerta_vencimento_proximo_enviado_em` | `TIMESTAMP` | Sim | `NULL` | Data/hora em que o aviso preventivo SMTP de 5 dias (`EDTAN-310` / `RF-102`) foi enviado no ciclo atual |
| `alerta_falha_pagamento_enviado_em` | `TIMESTAMP` | Sim | `NULL` | Data/hora em que o alerta SMTP de carência (2 dias) foi enviado no ciclo atual |
| `created_at` / `updated_at` | `TIMESTAMP` | Sim | `NULL` | Timestamps padrão Eloquent |

---

#### 3. Tabela `users` (`2026_09_15_000003_create_users_table.php`)
| Coluna | Tipo Físico | Nullable | Default | Constraints / Observações |
| :--- | :--- | :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | Não | Auto Inc | `PRIMARY KEY` |
| `agencia_id` | `BIGINT UNSIGNED` | Sim | `NULL` | `FK -> agencias.id` (`ON DELETE CASCADE`) |
| `perfil_acesso` | `ENUM` | Não | `'consultor_admin'` | Valores: `['admin_global', 'consultor_admin', 'consultor_padrao', 'startup']` |
| `permissoes` | `JSON` | Sim | `NULL` | Array JSON de permissões atômicas (`App\Enums\Permissao` — `EDTAN-163` / `ADR 040`) |
| `status` | `ENUM` | Não | `'ativo'` | Valores: `['pendente', 'ativo', 'inativo']` (`EDTAN-163` / `ADR 041`) |
| `name` | `VARCHAR(255)` | Não | - | Nome completo do usuário/consultor |
| `email` | `VARCHAR(255)` | Não | - | `UNIQUE` (`users_email_unique`) |
| `email_verified_at` | `TIMESTAMP` | Sim | `NULL` | Data de verificação de e-mail (preenchida na ativação do convite em 2 canais) |
| `password` | `VARCHAR(255)` | Sim | `NULL` | Hash bcrypt da senha |
| `convite_token` | `VARCHAR(64)` | Sim | `NULL` | `UNIQUE` — Token do link de convite enviado por e-mail (Canal 1) |
| `convite_codigo_criptografado` | `TEXT` | Sim | `NULL` | Payload criptografado (`AES-256-CBC` via cast `'encrypted'`) do código PIN numérico de 6 dígitos (Canal 2 — `EDTAN-163` / `ADR 041`) |
| `convite_expira_em` | `TIMESTAMP` | Sim | `NULL` | Data/hora limite de validade do convite (48 horas) |
| `convite_tentativas` | `TINYINT UNSIGNED` | Não | `0` | Contador de falhas de PIN (bloqueia e invalida ao atingir 5 tentativas) |
| `convidado_por_id` | `BIGINT UNSIGNED` | Sim | `NULL` | `INDEX` — ID do usuário titular/gerente que emitiu o convite |
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
| `deleted_at` | `TIMESTAMP` | Sim | `NULL` | Coluna de exclusão lógica (`SoftDeletes` — `EDTAN-249` / `ADR 039`), com expurgo físico após 30 dias |

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

### 1. Multi-Tenancy, RBAC Granular (`App\Enums\Permissao`), Gestão de Equipe (`EDTAN-163`) e Contexto de Startup (`X-Startup-Id`)
- **Isolamento Multi-Tenant por `agencia_id`:** Toda entidade de cliente (`User`, `Startup`, `KanbanBoard`) pertence a uma `Agencia` (`agencia_id`). Consultas em `StartupController` e `EquipeService` filtram estritamente por `where('agencia_id', $request->user()->agencia_id)`, impedindo vazamento de dados entre consultorias concorrentes.
- **Catálogo de Permissões Atômicas (`App\Enums\Permissao` — `ADR 040`):**
  - `startups.criar_editar`: Criar novas startups e editar dados cadastrais/pitch (`StartupPolicy@create`, `StartupPolicy@update`).
  - `startups.excluir`: Excluir startups da carteira via Soft Delete (`StartupPolicy@delete`).
  - `kanban.gerenciar`: Criar, mover, atualizar anexos/metadados e remover cards do Kanban (`KanbanController`).
  - `minutas.gerenciar`: Gerar minutas com IA e salvar novas versões (`MinutaController`).
  - `equipe.gerenciar`: Listar equipe e convidar/gerenciar consultores operacionais (`EquipePolicy`). Concedível **apenas** pelo Titular (`owner_id`).
  - `assinatura.gerenciar`: Exclusiva do Titular financeiro (`User::isOwner()`), nunca delegável. Protege `GET /api/assinatura/atual`, `POST /api/assinatura/renovar`, `POST /api/assinatura/atualizar-forma-pagamento`, `POST /api/planos/selecionar`, `GET /api/pagamento/recuperar` e `DELETE|POST /api/conta/cancelar`.
  - **Presets Oficiais:** `Gerente Operacional` (todas as 5 permissões operacionais, disponível apenas para o Titular), `Consultor Pleno` (`startups.criar_editar`, `kanban.gerenciar`, `minutas.gerenciar`) e `Visualizador` (`[]`).
  - **Proibição de Delegação Transitiva (`EquipePolicy`):** Um consultor com `equipe.gerenciar` (que não seja `isOwner()`) **não pode** conceder/remover `equipe.gerenciar`, **não pode** conceder permissões que ele próprio não possua e **não pode** editar, reenviar convite, desativar ou remover o Titular (`owner_id`) nem outros membros que possuam `equipe.gerenciar`.
- **Fluxo de Convite Seguro em 2 Canais (*Out-of-Band*) com PIN Criptografado e Revogação Sanctum (`EquipeService` — `ADR 041`):**
  - `POST /api/equipe` cria o convidado com `status = 'pendente'`, `convite_token` único de 64 chars (48h de validade) e um **código PIN aleatório de 6 dígitos** salvo em `convite_codigo_criptografado` com cast `'encrypted'` (`AES-256-CBC` via `APP_KEY`, oculto em `User::$hidden`).
  - Envia o link de ativação (`aceitar-convite.html?token=...`) por e-mail em *best-effort* (Canal 1) e disponibiliza `link_convite` + `codigo_ativacao` (PIN de 6 dígitos) tanto no modal pós-convite quanto na listagem `GET /api/equipe` **exclusivamente** enquanto o convite estiver pendente e válido (`convite_expira_em->isFuture()`, `convite_tentativas < 5`) e somente para quem possui autorização `$actor->can('manageMember', $membro)` pela `EquipePolicy` (o Titular vê todos os convites pendentes; um Gerente vê apenas convites de consultores comuns e recebe `null` para convites de outros gerentes). A rota pública `GET /api/convites/{token}` jamais retorna `codigo_ativacao`.
  - `POST /api/convites/aceitar` valida o `token` + `codigo` (6 dígitos via `hash_equals`) + nova senha. Possui trava anti-força bruta de **5 tentativas** (`convite_tentativas`): ao ativar com sucesso, expirar ou atingir a 5ª falha, limpa `convite_codigo_criptografado = null`, `convite_token = null` e `convite_expira_em = null`. Quando válido, define a senha escolhida pelo convidado, muda `status = 'ativo'` e preenche `email_verified_at = now()`.
  - Usuários com `status === 'pendente'` ou `status === 'inativo'` são bloqueados no `POST /api/auth/login` com HTTP `403`.
  - Ao inativar (`PATCH /api/equipe/{id}/status`) ou remover (`DELETE /api/equipe/{id}`) um consultor, todos os seus tokens Sanctum (`$membro->tokens()->delete()`) são revogados imediatamente, mantendo 100% dos dados operacionais (startups, Kanban e minutas) intactos na agência.
- **Governança de Limite de Consultores por Plano (`PlanoQuotaService` + `PlanLimitExceededException` — `EDTAN-309` / `ADR 042`):**
  - **Alinhamento com a ADR 020:** O número máximo de consultores/membros é parametrizado em `planos.limite_membros_equipe` (`Standard = 1`, `Pro = 5`, `Enterprise = 15`), aceitando `null` para planos com membros ilimitados.
  - **Regra Fechada da Cota:** O Titular (`agencias.owner_id`) opera a plataforma e **consome 1 licença/vaga ocupada**. A contagem total de vagas ocupadas (`usados`) soma todos os usuários com `agencia_id` cujo status seja `'ativo'` (ou nulo em legado) **OU** `'pendente'`:
    $$\text{usados} = \text{ativos} + \text{pendentes}$$
  - Membros com `status = 'inativo'` **não consomem vaga**, liberando a franquia instantaneamente no banco.
  - **Métodos Centralizados no `PlanoQuotaService`:**
    - `obterResumoCotaEquipe(Agencia $agencia)`: Devolve `{ usados, ativos, pendentes, inativos, limite, restantes, ilimitado, atingiu_limite, plano_nome, inclui_titular: true }`.
    - `validarLimiteEquipe(Agencia $agencia, string $acao = 'convidar')`: Quando `atingiu_limite === true`, dispara `PlanLimitExceededException` (HTTP `403`, `codigo_erro: 'LIMITE_CONSULTORES_ATINGIDO'`), formatando mensagens adaptadas para convite e reativação.
  - **Lock Pessimista Anti-Race Condition:** Em `EquipeService::convidar()` e na reativação em `EquipeService::atualizarStatus()`, a checagem e a escrita ocorrem sob `DB::transaction` com `$agencia = Agencia::where('id', $actor->agencia_id)->lockForUpdate()->firstOrFail()`, eliminando estouros concorrentes de capacidade.
  - **Opção 1 (KISS — Sem Cooldown Artificial):** Não há bloqueio temporal de 24h para reativar um membro inativado. A trava é estritamente a cota de vagas ativas no momento da transação somada à revogação imediata de tokens Sanctum e sessões.
  - **Isenção de Bloqueio em `reenviarConvite`:** Como o membro com convite pendente já consome vaga, o reenvio de convite apenas renova token/PIN/validade sem barrar por limite.
  - **UX Adaptativa Web e Mobile:** Card de capacidade com barra de progresso no topo de `equipe.html`, botão `+ Convidar Consultor` preventivamente desabilitado e banner informativo contextual com CTA de upgrade (`abrirModalMinhaAssinatura()`) exclusivo para o Titular financeiro (`isOwner`).
- **Middleware Anti-IDOR `VerificarStartupAtiva` (`alias: 'startup.ativa'`):**
  - Inspeciona o cabeçalho `X-Startup-Id` enviado automaticamente pelo `api.js`.
  - Se presente e o usuário estiver autenticado, executa `Startup::where('id', $startupId)->where('agencia_id', $request->user()->agencia_id)->first()`.
  - Caso a startup não exista ou pertença a outra agência, aborta imediatamente com HTTP `403` (`'A startup selecionada não pertence à sua consultoria ou é inválida.'`). Se válida, injeta o model em `$request->attributes->set('startup_ativa', $startup)`.

---

### 2. Fluxo de Registro em Três Etapas (Wizard "Estado Fraco"), Pagamento PIX + Cartão (`EDTAN-311`) e Webhooks
- **Documentos do cadastro (ADR 045, EDTAN-286)**
  - Antes do OTP, o wizard Web/Capacitor consulta o par vigente, apresenta os textos e registra uma escolha explícita na sessão da aba. Ao desmarcar ou encontrar versões diferentes, descarta os metadados.
  - `DocumentoLegal` mapeia `documentos_legais`. Cada snapshot contém tipo, versão, título, seções, SHA-256 canônico e data de publicação. O marcador único e anulável `vigente_tipo` permite histórico por tipo. O `DocumentoLegalSeeder` cria apenas a primeira publicação de cada tipo.
  - `TermoAceiteService` publica versões em transação, valida o par antes da cobrança e grava exatamente esse par. `TermoAceite` mapeia `termos_aceites`, com FKs para snapshots, versões, hashes, data UTC, usuário e agência anuláveis, HMAC do e-mail, ID da chave, IP, agente do navegador, prazo e preservação excepcional.
  - Encerramento voluntário, cancelamento pendente e encerramento pelo ciclo de assinaturas iniciam a retenção de cinco anos na transação de exclusão. A suspensão reversível mantém o prazo nulo. O scheduler chama `ExpurgarTermosAceitesVencidosCommand` às 03h25 UTC (`termos:expurgar-aceites-vencidos`, com opção `--dry-run`).
  - `config/termos.php` configura chave dedicada, identificador e prazo. A v1 publica o contato de privacidade junto ao texto. A ADR 045 registra gestão de chaves, limites probatórios, pendências legais e de infraestrutura.
- **Etapa 1 — Confirmação do e-mail (ADR 044, EDTAN-283)**
  - `VerificacaoEmailCadastroService` mantém um `EmailVerificacaoCadastro` por endereço normalizado. A tabela `email_verificacoes_cadastro` guarda o hash do OTP, tentativas, prazos, envio, verificação e consumo. O reenvio invalida a prova anterior.
  - `CodigoVerificacaoCadastroMail` entrega o OTP via Laravel Mail e SMTP configurável. A confirmação em até 15 minutos permite avançar ao plano. O cliente guarda o endereço confirmado e o token de uso único no pré-cadastro em `sessionStorage`.
  - O comando diário `auth:limpar-verificacoes-email` roda às 03h10 e remove verificações expiradas ou consumidas há mais de 24 horas.
- **Etapa 2 — Escolha do Plano (`selecionar-plano.html` / `selecionarPlano.js`):**
  - Ao clicar em *"Selecionar Plano"*, não realiza chamada de escrita ao banco: salva o plano escolhido em `sessionStorage('plano_selecionado')` e redireciona para `escolher-pagamento.html` (mantendo `?modo=renovacao` se presente).
- **Etapa 3 — Escolha da Forma de Pagamento e Registro Atômico (`escolher-pagamento.html` ➔ `POST /api/auth/registrar-com-plano`):**
  - Exibe o resumo do plano com link `← Alterar plano` e 2 cards selecionáveis: **PIX** (`PIX`) e **Cartão de Crédito** (`CARD`).
  - Somente ao clicar em **"Finalizar e Ir para o Pagamento"**, chama `registrarComPlano` (ou `gerarNovaCobranca` em `modo=renovacao`), limpa `dados_pre_cadastro` e `plano_selecionado` do `sessionStorage` e redireciona para `url_checkout`.
  - `registrarComPlano` exige `name`, `email`, `password` (mín. 8 chars), `plano_id` (existente em `planos`), `nome_agencia` (obrigatório) e aceita `'forma_pagamento' => 'nullable|string|in:PIX,CARD'` (fallback `'PIX'`).
  - Ausência de aceite booleano verdadeiro retorna 422 `TERMOS_NAO_ACEITOS`. IDs ou versões divergentes retornam 422 `VERSAO_TERMOS_DESATUALIZADA`. Ambas as rejeições precedem os efeitos no token e no gateway.
  - O registro exige `verification_token` vinculado ao e-mail, válido por 60 minutos. A transação bloqueia a linha, consome o token, cria o usuário com `email_verified_at` e emite o token Sanctum de pendência. Prova ausente, alheia, vencida ou consumida retorna HTTP 422 com `VERIFICACAO_EMAIL_INVALIDA`.
  - Cria a `Agencia` com `forma_pagamento = $formaPagamento`, `status_pagamento = 'pendente'` e `pagamento_expira_em = Carbon::now()->addMinutes(60)` (janela de 60 minutos) e o `User` com `perfil_acesso = 'consultor_admin'`.
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
  - `emJanelaVencimentoProximo(): bool` retorna `true` quando `status_pagamento === 'ativo'`, `assinatura_expira_em !== null`, `assinatura_expira_em->isFuture()` e `assinatura_expira_em->lte(now()->addDays(5))`.
- **Consulta da Assinatura Atual e Alerta Proativo no Header Global (`GET /api/assinatura/atual` — `EDTAN-310` / `RF-102`):**
  - Retorna dados do plano, cotas de uso atual, `forma_pagamento` (`'PIX'` ou `'CARD'`), `forma_pagamento_label` (`'PIX'` ou `'Cartão de Crédito'`), `em_carencia` (`bool`), `vencimento_proximo` (`bool`), `exibir_alerta_assinatura` (`bool`), `alerta_assinatura_nivel` (`'critico'`, `'atencao'` ou `null`), `alerta_assinatura_mensagem` (`string|null`), `data_vencimento_formatada` (`d/m/Y`), `data_repeticao_formatada` (`d/m/Y`) e `data_liberacao_renovacao_formatada` (`d/m/Y`).
  - No Front-end Web e Mobile (`seletorGlobalStartup.js`), quando o usuário autenticado é `consultor_admin`, consulta silenciosamente `/api/assinatura/atual` ao inicializar o Header Global. Se `exibir_alerta_assinatura === true`, exibe um badge visual discreto (âmbar para `'atencao'` ou vermelho para `'critico'`) no botão do menu do usuário e ao lado de *"Minha Assinatura"*, além de um banner superior fechável na sessão com botão **"Gerenciar Assinatura"** que abre diretamente o modal `#modalMinhaAssinaturaOverlay`. Usuários `consultor_padrao` e `startup` não recebem esse alerta financeiro.
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
  - Eventos `'checkout.completed'`, `'subscription.completed'`, `'billing.paid'` ou status `'PAID'` são traduzidos para `'ativo'`: soma `+30 dias` acumulativos sobre `assinatura_expira_em` (se futuro) ou `now()->addDays(30)`, e limpa `url_checkout = null`, `pagamento_expira_em = null`, `alerta_vencimento_proximo_enviado_em = null` e `alerta_falha_pagamento_enviado_em = null`.
  - Eventos `'checkout.refunded'` / `'subscription.cancelled'` (estorno/reembolso) definem `status_pagamento = 'cancelado'`; eventos `'checkout.lost'` / `'subscription.expired'` (disputa/chargeback perdido) definem `status_pagamento = 'suspenso'`, cortando o acesso imediatamente (`isPagamentoAtivo()` retorna `false` na hora).
- **Rotina Agendada de Ciclo de Vida (`app/Console/Commands/ProcessarCicloAssinaturas.php` — `php artisan assinaturas:processar-ciclo`):**
  - Agendada em `routes/console.php` via `Schedule::command('assinaturas:processar-ciclo')->dailyAt('03:00')` utilizando `chunkById(100)` para baixo consumo de memória:
    - **Fase 0 — Aviso Preventivo de Vencimento Próximo ($\le 5$ dias antes de vencer — `EDTAN-310` / `RF-102`):** Seleciona agências com `status_pagamento = 'ativo'`, `assinatura_expira_em` entre `now()` e `now()->addDays(5)` e `alerta_vencimento_proximo_enviado_em IS NULL`. Envia um e-mail HTML via SMTP (`Mail::html`) ao `consultor_admin` (com nome do plano, forma de pagamento, dias restantes, vencimento em `DD/MM/AAAA` e instruções para renovação acumulativa em *"Minha Assinatura"*) e grava `alerta_vencimento_proximo_enviado_em = now()`.
    - **A) Contas que NUNCA pagaram (`status_pagamento = 'pendente'` e `assinatura_expira_em IS NULL`):** Após **3 dias** de criação (`env('ASSINATURA_DIAS_EXPURGO_NUNCA_PAGO', 3)`), executa o **Hard Delete** transacional em cascata nas 9 camadas do banco (princípio da minimização de dados da LGPD e liberação rápida do e-mail).
    - **B) Contas que JÁ pagaram antes (`assinatura_expira_em IS NOT NULL`):**
      1. **Fase 1 (Carência — entre `vencimento` e `vencimento + 2 dias`):** Se `status_pagamento === 'ativo'` e `alerta_falha_pagamento_enviado_em === null`, envia e-mail HTML via SMTP (`Mail::html`) ao `consultor_admin` avisando sobre os **2 dias de carência** para acessar *"Minha Assinatura" ➔ "Atualizar Forma de Pagamento"* e grava `alerta_falha_pagamento_enviado_em = now()`.
      2. **Fase 2 (Pausa/Suspensão — passou de `vencimento + 2 dias`):** Atualiza `status_pagamento = 'suspenso'` (bloqueando o painel e mantendo os dados intactos).
      3. **Fase 3 (Expurgo Legal — passou de `vencimento + 30 dias`):** Usando `env('ASSINATURA_DIAS_EXPURGO_SUSPENSA', 30)`, executa o **Hard Delete** transacional em cascata da conta suspensa.

---

#### Troca de plano do titular (EDTAN-161, ADR 043 de Roberto)

`TrocaPlanoService` usa os produtos oficiais da tabela `planos`, o gateway e o `PlanoQuotaService`. O upgrade cobra o preço integral do novo plano e converte o saldo do ciclo em dias bônus. O webhook confirma o identificador pendente sob bloqueio e concede 30 dias mais o bônus. A troca imediata de uma conta sem ciclo ativo concede 30 dias. O downgrade é agendado até o fim do ciclo, sujeito às cotas de startups e equipe. O comando `assinaturas:processar-downgrades` roda diariamente às 02h55 no fuso do scheduler. O comando e a renovação manual vencida revalidam as cotas e cancelam o agendamento em caso de excesso.

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
- **Cadastro, Regras de Unicidade, Governança de Cotas (`PlanoQuotaService`) e Exclusão Segura (`StartupPolicy` + `SoftDeletes` — `EDTAN-249` / `ADR 038` / `ADR 039`):**
  - **Motor Centralizado de Cotas (`PlanoQuotaService` + `PlanLimitExceededException`):**
    - `PlanoQuotaService::getUsageSummary(Agencia $agencia)` retorna `{ startups_atual, startups_limite, pode_criar_startup, plano_nome }`, contabilizando apenas startups ativas (`deleted_at IS NULL`) frente ao `limite_startups` do plano (`Standard = 2`, `Pro = 10`, `Enterprise = 50`, conforme `ADR 020` do Ademar e `PlanoSeeder`).
    - Antes de criar uma startup em `POST /api/startups`, o controller invoca `$this->quotaService->assertCanCreateStartup($agencia)`. Se `$startups_atual >= $startups_limite`, lança `PlanLimitExceededException`, renderizada globalmente em `bootstrap/app.php` como **HTTP `403`** com `{ message, code: 'LIMITE_STARTUPS_ATINGIDO', codigo: 'LIMITE_STARTUPS_ATINGIDO', atual, limite, plano_atual }`.
  - **Autorização Desacoplada (`StartupPolicy`):**
    - `view` e `update` validam isolamento Multi-Tenant Anti-IDOR (`$user->agencia_id === $startup->agencia_id`).
    - `delete` valida o `agencia_id` e exige que o usuário seja o titular financeiro (`$agencia->owner_id === $user->id`) ou possua `perfil_acesso` `'consultor_admin'` / `'admin_global'`.
  - **Unicidade, Soft Delete e Expurgo LGPD em 30 Dias:**
    - A tabela `startups` possui constraint `UNIQUE(agencia_id, cnpj)` e coluna `deleted_at` (`SoftDeletes`). Se o CNPJ já estiver ativo na agência, retorna HTTP `422`. Se o mesmo CNPJ havia sido excluído via *Soft Delete* pela mesma agência, `StartupController@store` restaura (`restore()`) e atualiza o registro sem erro de constraint única.
    - `DELETE /api/startups/{id}` executa `$startup->delete()` (*Soft Delete*), liberando **1 vaga imediatamente** na cota do plano. No Front-end (`minhas-startups.html`), a seção *"Zona de Perigo"* limpa a startup ativa do `localStorage` e redireciona sempre para `selecionar-startup.html?excluida=1`.
    - O comando diário `assinaturas:processar-ciclo` executa o expurgo físico definitivo das startups com `deleted_at <= now()->subDays(30)` (`Startup::onlyTrashed()->where('deleted_at', '<=', now()->subDays(30))->forceDelete()`).

---

### 6. Radar de Editais, Web Scraping (Spiders), Favoritos e Motor de Match / Alertas
- **Web Scraping e Ingestão de Editais (`app/Spiders/` e `app/Console/Commands/`):**
  - **Comando Unificado:** `php artisan editais:sync {--fonte=}` (`SyncAllEditais.php`) executa em sequência os 6 spiders configurados com **RoachPHP** e salva via `SalvarNoBancoProcessor` (`Edital::updateOrCreate(['external_id' => ...])`, que remove chaves transitórias não existentes na tabela como `agency` e normaliza `'status' => 'open'` para `'active'`):
    1. **`FinepSpider` (`FINEP`):** Consulta diretamente a API REST Liferay da FINEP (`https://www.finep.gov.br/o/c/chamadapublicas?sort=dataDePublicacao:desc&pageSize=250`) iterando por todas as páginas (`lastPage`). Caso a API REST falhe, possui fallback com **Spatie Browsershot** (Puppeteer/Chrome headless). Complementado pelo comando `php artisan finep:sync-detalhes`, que varre o HTML de cada chamada pública para extrair links de anexos (`.pdf`, `.doc`, `/documents/`), região e público-alvo.
    2. **`FapespSpider` (`FAPESP`):** Raspa `https://fapesp.br/2185/chamadas-de-propostas-2026` via XPath (`//ul[contains(@class, "list")]/li`), extraindo código da chamada, link, título e data limite via regex.
    3. **`FapeScSpider` (`FAPESC`):** Raspa `https://fapesc.sc.gov.br/chamadas-abertas/` via XPath (`//div[contains(@class, "upk-item")]`), gerando `external_id` por hash MD5 (`titulo|link`).
    4. **`FapemigSpider` (`FAPEMIG`):** Raspa `https://fapemig.br/oportunidades/chamadas-e-editais` (Nuxt SSR/Regex), extraindo orçamento, datas, público-alvo, linha de fomento e anexos de `api.site.fapemig.br/wp-content/uploads/`.
    5. **`FaperjSpider` (`FAPERJ`):** Raspa `https://www.faperj.br/?id=28.5.7` e `?id=lista-de-editais--chamadas-(2026)`, ignorando datas riscadas (`line-through` / `<strike>`) de prorrogações e definindo `status` como `'active'` ou `'closed'`.
    6. **`FapergsSpider` (`FAPERGS`):** Raspa `https://fapergs.rs.gov.br/abertos` e `/em-julgamento`, navegando nas páginas internas de cada edital (`parseEditalDetalhe`) para extrair anexos PDF, orçamento, prazos e `status` (`'active'` ou `'closed'`).
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
  - A EDTAN-290 (ADR 047 do Roberto) adiciona `planos.limite_armazenamento_mb` e `planos.limite_arquivo_mb`. Standard, Pro e Enterprise oferecem, respectivamente, 1.024, 5.120 e 25.600 MB por agência, com 50 MB por arquivo. Os formatos aceitos são PDF, DOC/DOCX, XLS/XLSX, CSV, TXT, PNG e JPEG.
  - `ArmazenamentoKanbanService` bloqueia a linha da agência, verifica o consumo em bytes e registra o arquivo privado em `kanban_anexos`. Os anexos históricos do JSON são importados pelo backfill. A exclusão física de anexos e o descarte em cascata após commit possuem recuperação por `kanban_exclusoes_pendentes`. O downgrade mantém os documentos acessíveis.
  - Minha Assinatura inclui créditos do `MinutaLimitService` e barras de consumo total e por startup. Em Minhas Startups, a engrenagem abre edição, perfil do portal ou arquivos ordenados por tamanho. Downloads autenticados e exclusão com `kanban.gerenciar` estão disponíveis no Kanban e na lista da startup.

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
| `POST` | `/api/auth/enviar-codigo-verificacao` | `AuthController@enviarCodigoVerificacao` | Público, 10/min por IP | `email`, `nome` opcional | 200 com validade (900 s) e reenvio (60 s). 422 para e-mail cadastrado. 429 no cooldown com `segundos_restantes`. |
| `POST` | `/api/auth/verificar-codigo-email` | `AuthController@verificarCodigoEmail` | Público, 15/min por IP | `email`, `codigo` de seis dígitos | 200 com `verification_token` e validade (3600 s). 422 para código inválido ou expirado. 429 após cinco erros. |
| `POST` | `/api/auth/verificar-email` | `AuthController@verificarEmail` | Público | `{ "email": "string" }` | `200`: `{ "disponivel": true, "message": "..." }` \| `422`: `{ "disponivel": false, "pagamento_pendente": bool, "message": "..." }` |
| `GET` | `/api/termos/vigentes` | `TermoAceiteController@vigentes` | Público | — | Par completo com IDs, versões, hashes e textos ou 503 |
| `GET` | `/api/termos/meu-aceite` | `TermoAceiteController@meuAceite` | Sanctum | — | Eventos do usuário com textos históricos, sem HMAC ou ID da chave |
| `POST` | `/api/auth/registrar-com-plano` | `AuthController@registrarComPlano` | Público | `{ "name", "email", "password" (min:8), "plano_id", "nome_agencia", "verification_token", "termos_aceitos": true, "documento_termos_id", "documento_privacidade_id", "versao_termos", "versao_privacidade", "forma_pagamento"?: "PIX\|CARD" }` | `201`: `{ "message", "url_checkout", "token_pendencia", "access_token", "token", "usuario", "agencia" }` \| `422` \| `500` |
| `POST` | `/api/auth/registro` | `AuthController@registrarComPlano` | Público | Mesmo contrato de `registrar-com-plano` | Mesmo retorno de `registrar-com-plano` |
| `POST` | `/api/auth/login` | `AuthController@login` | Público | `{ "email", "password" }` | `200`: `{ "status": "sucesso", "message", "token", "dados": { "usuario", "agencia" } }` \| `401`: Credenciais inválidas \| `403`: `{ "codigo": "PAGAMENTO_PENDENTE", "token_pendencia", "agencia" }` |
| `GET` | `/api/auth/login` | `Closure` (`routes/api.php`) | Público (`name: login`) | Nenhum | `401`: `{ "mensagem": "Não autenticado. Por favor, faça login." }` |
| `POST` | `/api/auth/esqueci-senha` | `AuthController@solicitarRecuperacaoSenha` | Público | `{ "email": "string" }` | `200`: `{ "message": "Enviamos um link..." }` \| `404`: E-mail não encontrado \| `500`: Falha SMTP |
| `POST` | `/api/auth/redefinir-senha` | `AuthController@redefinirSenha` | Público | `{ "email", "token", "password" (min:8), "password_confirmation" }` | `200`: `{ "message": "Sua senha foi redefinida..." }` \| `400`: Token inválido/expirado (>60m) \| `404` |
| `GET` | `/api/assinatura/simular-troca` | `AssinaturaController@simularTroca` | Sanctum, titular | Query `plano_id` | Tipo, planos, crédito, bônus, valor, vigência e impedimentos |
| `POST` | `/api/assinatura/trocar-plano` | `AssinaturaController@trocarPlano` | Sanctum, titular | `plano_id` e `forma_pagamento` opcional | Checkout ou agendamento. Excesso de uso retorna HTTP 422 e `DOWNGRADE_EXCEDE_COTA_PLANO` |
| `DELETE` | `/api/assinatura/downgrade-agendado` | `AssinaturaController@cancelarDowngradeAgendado` | Sanctum, titular | Sem corpo | Cancela o plano agendado |
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
| `GET` | `/api/me` | `AuthController@obterPerfil` | `auth:sanctum` | Nenhum | `200`: `{ "status": "sucesso", "id", "name", "email", "perfil_acesso", "is_owner": bool, "permissoes": string[], "usuario": {...} }` |
| `GET` | `/api/auth/perfil` | `AuthController@obterPerfil` | `auth:sanctum` | Nenhum | `200`: `{ "status": "sucesso", "id", "name", "email", "perfil_acesso", "is_owner": bool, "permissoes": string[], "usuario": {...} }` |
| `PUT` | `/api/auth/perfil` | `AuthController@atualizarPerfil` | `auth:sanctum` | `{ "name", "email", "password"?: string (min:8) }` | `200`: `{ "status": "sucesso", "message": "Perfil atualizado com sucesso!", "usuario": {...} }` \| `422` |
| `GET` | `/api/equipe` | `EquipeController@index` | `auth:sanctum` *(exige `isOwner` ou `equipe.gerenciar`)* | Nenhum | `200`: `{ "agencia", "cota_equipe": {...}, "membros": [...], "catalogo_permissoes": [...], "presets": [...], "ator": {...} }` \| `403` |
| `POST` | `/api/equipe` | `EquipeController@store` | `auth:sanctum` *(exige `isOwner` ou `equipe.gerenciar`)* | `{ "name": string, "email": string, "permissoes"?: string[] }` | `201`: `{ "message", "membro": {...}, "link_convite": string, "codigo_ativacao": "6 dígitos", "expira_em", "email_enviado": bool, "cota_equipe": {...} }` \| `403`: `LIMITE_CONSULTORES_ATINGIDO` \| `422` |
| `PUT` | `/api/equipe/{id}/permissoes` | `EquipeController@atualizarPermissoes` | `auth:sanctum` *(validado por `EquipePolicy`)* | `{ "permissoes": string[] }` | `200`: `{ "message": "Permissões atualizadas com sucesso.", "membro": {...} }` \| `403` \| `422` |
| `PATCH` | `/api/equipe/{id}/status` | `EquipeController@atualizarStatus` | `auth:sanctum` *(validado por `EquipePolicy`)* | `{ "status"?: "ativo\|inativo" }` | `200`: `{ "message", "membro": {...}, "cota_equipe": {...} }` *(revoga tokens Sanctum ao inativar; valida cota e lockForUpdate ao reativar)* \| `403`: `LIMITE_CONSULTORES_ATINGIDO` \| `422` |
| `POST` | `/api/equipe/{id}/reenviar-convite` | `EquipeController@reenviarConvite` | `auth:sanctum` *(validado por `EquipePolicy`)* | Nenhum *(apenas membros `pendente`, não bloqueia por cota)* | `200`: `{ "message", "membro": {...}, "link_convite", "codigo_ativacao", "expira_em", "email_enviado" }` \| `403` \| `422` |
| `DELETE` | `/api/equipe/{id}` | `EquipeController@destroy` | `auth:sanctum` *(validado por `EquipePolicy`)* | Parâmetro URL: `id` | `200`: `{ "message", "cota_equipe": {...} }` *(libera vaga imediatamente e revoga tokens)* \| `403` \| `404` |
| `GET` | `/api/convites/{token}` | `EquipeController@validarConvitePublico` | Público | Parâmetro URL: `token` | `200`: `{ "valido": true, "agencia_nome", "convidado_nome", "email_mascarado", "expira_em", "tentativas_restantes" }` \| `422` |
| `POST` | `/api/convites/aceitar` | `EquipeController@aceitarConvitePublico` | Público | `{ "token": string, "codigo": "6 dígitos", "password" (min:8), "password_confirmation" }` | `200`: `{ "message": "Conta ativada com sucesso!...", "usuario": {...} }` \| `422` *(incrementa tentativas; invalida na 5ª falha)* |
| `DELETE` \| `POST` | `/api/conta/cancelar` | `AuthController@cancelarConta` | `auth:sanctum` *(exige `isOwner()`)* | `{ "password": "string", "ciente_lgpd": true }` | `200`: `{ "message": "Sua conta, assinatura e todos os dados vinculados foram permanentemente excluídos conforme a LGPD." }` \| `403` \| `422` |
| `GET` | `/api/assinatura/consumo` | `ArquivoKanbanController@consumo` | Sanctum de agência | Nenhum | Créditos de minutas e armazenamento total, por startup e sem vínculo |
| `GET` | `/api/startups/{startup}/arquivos` | `ArquivoKanbanController@arquivos` | Sanctum de agência | Startup da agência | Resumo e anexos por tamanho, com card e etapa |
| `GET` | `/api/kanban/anexos/{id}` | `ArquivoKanbanController@baixar` | Sanctum de agência | Anexo de quadro da agência | Download privado |
| `DELETE` | `/api/kanban/anexos/{id}` | `ArquivoKanbanController@excluir` | Sanctum, kanban.gerenciar | Anexo de quadro da agência | Consumo atualizado |
| `GET` | `/api/assinatura/atual` | `AssinaturaController@atual` | `auth:sanctum` | Nenhum | `200`: `{ "plano": {...}, "uso_atual": {...}, "cota_startups": {...}, "cota_equipe": {...}, "forma_pagamento": "PIX\|CARD", "forma_pagamento_label", "em_carencia": bool, "vencimento_proximo": bool, "exibir_alerta_assinatura": bool, "alerta_assinatura_nivel": "critico\|atencao\|null", "alerta_assinatura_mensagem": string\|null, "data_vencimento_formatada", "data_repeticao_formatada", "data_liberacao_renovacao_formatada", "status_pagamento", "assinatura_ativa", "dias_restantes", "pode_renovar", ... }` |
| `POST` | `/api/assinatura/renovar` | `AssinaturaController@renovar` | `auth:sanctum` *(exige `consultor_admin`)* | `{ "forma_pagamento"?: "PIX\|CARD" }` | `200`: `{ "message", "reaproveitada": bool, "forma_pagamento", "id_transacao", "url_checkout", "pagamento_expira_em" }` \| `403` \| `422` (>5 dias restantes) |
| `POST` | `/api/assinatura/atualizar-forma-pagamento` | `AssinaturaController@atualizarFormaPagamento` | `auth:sanctum` *(exige `consultor_admin`)* | `{ "forma_pagamento": "PIX\|CARD" }` | `200`: `{ "message", "forma_pagamento", "forma_pagamento_label", "requer_checkout": bool, "url_checkout": string\|null, ... }` \| `403` \| `422` |
| `GET` | `/api/pagamento/recuperar` | `PagamentoController@recuperarLinkPagamento` | `auth:sanctum` | Nenhum | `200`: `{ "url_checkout": "..." }` \| `400`: Já ativa \| `404` |
| `POST` | `/api/planos/selecionar` | `PlanoController@selecionarPlano` | `auth:sanctum` | `{ "plano_id": int }` | `200`: `{ "message", "plano", "url_checkout" }` \| `404` \| `500` |
| `GET` | `/api/startups` | `StartupController@index` | `auth:sanctum`, `check.pagamento` | Query opcional: `busca`, `incluir_cota` (`1`) | `200`: Array JSON de startups ativas com `cota` (`{ startups_atual, startups_limite, pode_criar_startup, plano_nome }`) e `pode_excluir_startup` (ou envelope `{ data, startups, cota, pode_excluir_startup }` se `incluir_cota=1`) \| `403`: `PAGAMENTO_PENDENTE` |
| `POST` | `/api/startups` | `StartupController@store` | `auth:sanctum`, `check.pagamento` | `{ "cnpj", "razao_social", "nome_fantasia"?, "data_abertura"?, "cep"?, "logradouro"?, "numero"?, "bairro"?, "cidade"?, "estado"?, "porte"?, "regime_tributario"?, "nicho_atuacao"?, "pitch"? }` | `201`: `{ "message": "Startup cadastrada com sucesso.", "startup": {...}, "cota": {...} }` \| `403`: `LIMITE_STARTUPS_ATINGIDO` \| `422`: CNPJ duplicado na agência |
| `GET` | `/api/startups/{id}` | `StartupController@show` | `auth:sanctum`, `check.pagamento` | Parâmetro URL: `id` | `200`: Objeto JSON da startup (validado por `StartupPolicy@view`) + `pode_excluir_startup` \| `403` \| `404` |
| `PUT` | `/api/startups/{id}` | `StartupController@update` | `auth:sanctum`, `check.pagamento` | `{ "razao_social" (required), "cnpj"?, "nome_fantasia"?, "email"?, "telefone"?, "endereco...", "porte"?, "regime_tributario"?, "nicho_atuacao"?, "pitch"? }` | `200`: `{ "sucesso": true, "message": "Perfil da startup atualizado com sucesso.", "startup": {...} }` \| `403` \| `404` |
| `DELETE` | `/api/startups/{id}` | `StartupController@destroy` | `auth:sanctum`, `check.pagamento` | Parâmetro URL: `id` *(validado por `StartupPolicy@delete`: Anti-IDOR + titular `owner_id` ou `consultor_admin`)* | `200`: `{ "sucesso": true, "message": "Startup excluída com sucesso. Uma vaga foi liberada no seu plano.", "cota": {...} }` \| `403` \| `404` |
| `GET` | `/api/kanban` | `KanbanController@index` | Sanctum de agência | Agência da sessão. Filtros opcionais por `startup_id` ou nome legado | `200`: `{ "status": "sucesso", "data": { "id", "title", "columns": [{ ..., "cards": [...] }] } }` |
| `POST` | `/api/kanban/cards` | `KanbanController@storeCard` | Sanctum de agência | `{ "board_id"?, "column_id"?, "edital_id"?, "title"?, "description"?, "startup_id"?, "startup_name"?, "metadata"? }` | `201`: `{ "status": "sucesso", "message": "Card criado com sucesso!", "data": {...} }` \| `422`: Sem `title` e sem `edital_id` |
| `PATCH` | `/api/kanban/cards/{id}/mover` | `KanbanController@moveCard` | Sanctum de agência | `{ "target_column_id": int (required), "prev_position"?: float, "next_position"?: float }` | `200`: `{ "status": "sucesso", "message": "Card movimentado com sucesso!", "data": {...} }` |
| `POST` | `/api/kanban/cards/{id}/dados-anexos` | `KanbanController@updateCard` | Sanctum de agência | `multipart/form-data` ou JSON: `{ "title"?, "description"?, "metadata"?: array, "arquivo"?: file (máx. 50 MB, cota por agência) }` | `200`: `{ "status": "sucesso", "message": "Card atualizado com sucesso!", "data": {...}, "card": {...} }` \| `422`: Limite ou formato de arquivo inválido |
| `DELETE` | `/api/kanban/cards/{id}` | `KanbanController@destroyCard` | Sanctum de agência | Parâmetro URL: `id` | `200`: `{ "status": "sucesso", "message": "Card removido com sucesso!" }` |
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

---

## SEÇÃO 6: Orquestração Docker Compose (Ambiente Local Unificado)

A raiz operacional **`MVPs/Versão Atual/`** conta com uma infraestrutura completa baseada em **Docker Compose** (`docker-compose.yml`), permitindo subir todo o ecossistema do **Anjo Nexus** com um único comando e eliminando a necessidade de abrir múltiplos terminais manuais.

### 6.1. Tabela dos 6 Serviços Orquestrados (`anjonexus_net`)

| Serviço | Container Name | Imagem / Build | Porta (Host:Container) | Dependência (`depends_on`) | Papel e Comportamento no Ecossistema |
| :--- | :--- | :--- | :---: | :--- | :--- |
| **`db`** | `anjonexus_db` | `mysql:8.0` | **`3307:3306`** | Nenhuma | Banco de dados MySQL 8.0 (`anjonexus`, `utf8mb4_unicode_ci`) com volume persistente `mysql_data` e healthcheck ativo via `mysqladmin ping`. |
| **`back`** | `anjonexus_back` | Build `./back` (`anjonexus-back:latest`) | **`8000:8000`** | `db` (`service_healthy`) | API Laravel 12 (PHP 8.2-cli + extensões `pdo_mysql`, `zip`/OpenXML, `mbstring`, `xml`, `bcmath`, `curl`, `intl`, `gd`, `pcntl`). Executa o `entrypoint.sh`, roda migrations/seeders e inicia `cd /var/www/html/public && exec php -S 0.0.0.0:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php` (preservando 100% das variáveis `DB_*` do Docker sem sofrer filtragem do `ServeCommand` contra o `back/.env` local). Seu healthcheck (`GET /api/planos`) só fica `healthy` após as migrations e seeders terminarem. |
| **`scheduler`** | `anjonexus_scheduler` | `anjonexus-back:latest` | Interna | `back` (`service_healthy`) | Executa `php artisan schedule:work` em background contínuo, mantendo ativo o processamento automático de `assinaturas:processar-ciclo` (avisos de vencimento, carência de 2 dias, suspensão e expurgo LGPD) e rotinas agendadas. |
| **`spiders`** | `anjonexus_spiders` | `anjonexus-back:latest` | Interna | `back` (`service_healthy`) | Job one-shot (`restart: "no"`) que dispara automaticamente os radares reais assim que a API e o banco estão prontos (`php artisan editais:sync`, `php artisan finep:sync-detalhes --limit=40` e `php artisan editais:processar-pdfs --limit=25`), sem bloquear a disponibilidade da API `back`. |
| **`front`** | `anjonexus_front` | `nginx:alpine` | **`5500:80`** | Nenhuma | Servidor HTTP estático servindo `./front` na porta `5500`. Compatível nativamente com a detecção `ehDevNavegadorLocal` do `front/scripts/servicos/api.js` e liberado em `back/config/cors.php`. |
| **`ngrok`** | `anjonexus_ngrok` | `ngrok/ngrok:latest` | **`4040:4040`** | `back` (`service_healthy`) | Túnel HTTP reverso (`ngrok http --domain=${NGROK_DOMAIN:-esophagus-swifter-cultivate.ngrok-free.dev} back:8000`, `restart: on-failure:2`) isolado sob `profiles: ["ngrok"]`. Exige `NGROK_AUTHTOKEN` válido em `.env.docker` e respeita o limite do plano gratuito (1 túnel ativo por vez no mesmo domínio); sem Ngrok, o pagamento AbacatePay ainda pode ser testado e ativado localmente via polling ativo em `GET /api/pagamentos/consultar-anterior`. |

### 6.2. Mapeamento de Portas e Estratégia Anti-Conflito com XAMPP / Windows
- **Porta `3307` (`db` ➔ `3307:3306`):** O MySQL do container é exposto na porta **`3307`** do host para **não conflitar com o MySQL do XAMPP local (`3306`)**. Internamente, na rede `anjonexus_net`, os containers `back`, `scheduler` e `spiders` comunicam-se diretamente via `DB_HOST=db` e `DB_PORT=3306`.
- **Porta `8000` (`back` ➔ `8000:8000`):** Expõe a API RESTful exatamente em `http://127.0.0.1:8000/api`, endereço que o Front-end (`api.js`) consome automaticamente em ambiente de desenvolvimento.
- **Porta `5500` (`front` ➔ `5500:80`):** Expõe o Front-end em `http://localhost:5500` (mesma porta padrão do VS Code Live Server), garantindo compatibilidade imediata com `FRONT_URL=http://localhost:5500`, CORS (`config/cors.php`) e URLs de retorno do checkout AbacatePay.
- **Porta `4040` (`ngrok` ➔ `4040:4040`):** Painel web local de inspeção de requisições e webhooks do Ngrok (`http://localhost:4040`).
- **Isolamento de `vendor/` no Windows:** Os serviços `back`, `scheduler` e `spiders` montam `./back:/var/www/html` acompanhado de um volume anônimo `/var/www/html/vendor`, evitando lentidão de I/O do bind-mount Windows/WSL2 e protegendo a pasta `vendor/` local do host. Além disso, o `Dockerfile` sanitiza quebras de linha CRLF para LF (`sed -i 's/\r$//'`).

### 6.3. Boot Automatizado (`back/docker/entrypoint.sh`) e Controle `DOCKER_FRESH_DB`
Na inicialização do container `back`, o script `entrypoint.sh` executa a seguinte sequência determinística:
1. Verifica se `vendor/autoload.php` existe; caso contrário, executa `composer install --no-interaction`.
2. Garante a existência do arquivo `.env` (copiando de `.env.example` se ausente) e gera `APP_KEY` caso esteja vazia.
3. Aguarda ativamente o serviço `db` aceitar conexões PDO MySQL antes de invocar qualquer comando Artisan.
4. Avalia a variável `DOCKER_FRESH_DB` (definida em `.env.docker`, padrão `true`):
   - **`DOCKER_FRESH_DB=true`:** Executa `php artisan migrate:fresh --seed --force`, recriando todas as tabelas limpas e populando os planos (`PlanoSeeder`: Standard, Pro e Enterprise).
   - **`DOCKER_FRESH_DB=false`:** Executa `php artisan migrate --force` e `php artisan db:seed --class=PlanoSeeder --force`, preservando todas as agências, usuários, startups, editais e minutas já gravados no volume `mysql_data`.
5. Cria o link simbólico de storage (`php artisan storage:link`), limpa os caches (`php artisan optimize:clear`) e inicia o servidor em `0.0.0.0:8000`.

### 6.4. Comandos Principais de Operação (a partir de `MVPs/Versão Atual/`)

```bash
# Preparação inicial (apenas na 1ª vez)
cp .env.docker.example .env.docker

# 1. Subida completa (MySQL + Migrations/Seeders + API + Scheduler + Spiders + Front)
docker compose --env-file .env.docker up --build

# 2. Subida completa incluindo túnel Ngrok (para Webhooks AbacatePay)
docker compose --env-file .env.docker --profile ngrok up --build

# 3. Reexecutar manualmente os radares/spiders a qualquer momento
docker compose --env-file .env.docker run --rm spiders

# 4. Derrubar os containers (mantendo ou apagando o volume de dados do MySQL)
docker compose down
docker compose down -v
```


## Portal de acompanhamento das startups (EDTAN-297, ADR 046)

O login unificado em `POST /api/auth/login` aceita `email`, `identificador` ou `login`. O caractere @ seleciona a autenticação de agência. O nome de usuário normalizado seleciona `StartupPortalAcesso`, identidade Sanctum da tabela `startup_portal_acessos`, com FKs para agência e startup e um único perfil por startup. A resposta indica `tipo_acesso`. Web e Mobile direcionam a sessão externa para `portal-startup.html`.

| Operação | Autorização e efeito |
| --- | --- |
| `POST /api/startups/{startup}/portal-acesso` | Pagamento ativo e política de edição da startup. Cria o perfil e retorna o resumo seguro. |
| `PUT/PATCH /api/startups/{startup}/portal-acesso` | Mesma política. Atualiza login, senha ou estado. Pausar ou redefinir a senha revoga as sessões. |
| `GET /api/portal/acompanhamento` | Identidade externa ativa e ability `portal-startup`. Retorna identificação da startup, agência, totais por etapa e processos. |
| `POST /api/auth/logout` | Revoga o token apresentado de qualquer um dos dois tipos de acesso. |

`StartupPortalController` valida a operação e delega a `StartupPortalService` a gestão transacional, autenticação do perfil e projeção pública. `IsolarPortalStartup` participa de todo o grupo API, inclusive das rotas legadas. A listagem da agência carrega `Startup::portalAcesso` antecipadamente. O portal consulta `kanban_cards.startup_id`, `kanban_boards.agencia_id` e a coluna do mesmo quadro. De `editals` utiliza título, objetivo e prazo. Cards sem vínculo inequívoco permanecem fora da consulta.
