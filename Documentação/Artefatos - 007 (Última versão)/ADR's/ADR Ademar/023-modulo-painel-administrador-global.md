# ADR 023: Módulo Back-end do Painel do Administrador Global (ADM) — Gestão de Empresas, Métricas Globais e Healthcheck de Radares

## Contexto
Com o avanço da plataforma Anjo Nexus em direção ao modelo SaaS comercial multi-tenant, surgiu a necessidade de governança, supervisão operacional e suporte de alto nível por parte dos fundadores e da equipe de administração do sistema (**EDTAN-364 / EDTAN-302**).

Anteriormente, o sistema permitia apenas o fluxo de usuários consultores vinculados às suas respectivas agências (`consultor_admin` e `consultor_padrao`), sem uma camada unificada para:
1. Visualizar métricas consolidadas de negócio (MRR estimado, distribuição de planos, usuários totais e status de pagamentos).
2. Gerenciar empresas/consultorias (ativar, suspender/desativar acessos, conceder dias bônus e excluir agências com limpeza operacional em cascata).
3. Monitorar a saúde dos robôs de extração (*healthcheck* de spiders: FINEP, FAPESP, FAPERJ, FAPEMIG, FAPEG e FAPDF), verificando latência, status ao vivo dos portais públicos e permitindo o disparo manual de sincronizações diretamente pelo painel.

---

## Decisão de Arquitetura

### 1. Perfil e Autenticação Unificada (`admin_global`)
- O Administrador Global faz login pela **mesma interface de login comum (`login.html`)**, utilizando as rotas padrão `/api/auth/login`.
- O payload de retorno identifica o perfil `perfil_acesso: 'admin_global'`.
- O Super Admin é isento das travas financeiras do middleware `CheckPagamentoAtivo`, garantindo acesso irrestrito independentemente do estado de assinatura de qualquer agência.
- Foi implementado o seeder oficial `AdminGlobalSeeder` com criação idempotente das credenciais do administrador principal (`admin@anjonexus.com.br`).

### 2. Middleware de Proteção Estrita (`EnsureAdminGlobal`)
- Criado o middleware `App\Http\Middleware\EnsureAdminGlobal`, registrado no kernel/bootstrap como alias `'admin.global'`.
- Todas as rotas administrativas em `/api/admin/*` são protegidas duplamente por `['auth:sanctum', 'admin.global']`.
- Tentativas de acesso por usuários não autenticados retornam `401 Unauthorized`, enquanto tentativas de consultores ou titulares comuns retornam `403 Forbidden` com `codigo_erro: 'ACESSO_NEGADO_ADMIN_GLOBAL'`.

### 3. Serviço Centralizado de Negócio (`AdminService`)
Para manter a arquitetura limpa e desacoplada, toda a lógica foi concentrada na classe `App\Services\AdminService`:
- **`obterMetricasDashboard()`**:
  - Usuários (totais, ativos, inativos, pendentes de ativação).
  - Agências (ativas, suspensas, pendentes e canceladas).
  - Planos e Faturamento (distribuição por plano e MRR estimado).
  - Métodos de Pagamento (proporção PIX vs. Cartão).
  - Editais (total por fonte e por região federativa).
- **`listarAgencias()`**:
  - Paginação configurável (1 a 100 itens).
  - Busca por nome da agência ou e-mail/nome do titular.
  - Filtros por status de pagamento e tipo de plano.
  - Eager loading de planos, titulares, contadores de startups e usuários.
- **`obterDetalhesAgencia()`**:
  - Recupera estrutura completa da agência, equipe, startups cadastradas, plano agendado e histórico de aceites de termos de uso (LGPD).
- **`alterarStatusAgencia()`**:
  - Transição de status (`ativo`, `suspenso`, `cancelado`, `pendente`).
  - Ao suspender, o middleware `CheckPagamentoAtivo` bloqueia automaticamente toda a equipe daquela consultoria.
  - Ao reativar, a assinatura é revalidada e o acesso é liberado instantaneamente.
- **`ajustarPlanoAgencia()`**:
  - Concede upgrades/downgrades manuais ou soma dias extras de cortesia à assinatura.
- **`excluirAgencia()`**:
  - Exclusão atômica (`DB::transaction`) com expurgo em cascata de cards de kanban, minutas geradas, startups vinculadas, tokens de sessão e contas de usuários da consultoria.

### 4. Monitoramento em Tempo Real dos Radares (*Radar Healthcheck*)
- **`obterStatusRadares()`**:
  - Monitora as 6 fontes oficiais do sistema: **FINEP**, **FAPESP** (SP), **FAPERJ** (RJ), **FAPEMIG** (MG), **FAPEG** (GO) e **FAPDF** (DF).
  - Executa probes HTTP de baixa latência (`timeout: 4s`) com medição em milissegundos para diagnosticar disponibilidade (`Online`, `Instável / WAF` ou `Offline / Timeout`).
  - Totaliza editais armazenados e editais abertos por fonte.
  - Expõe a data e hora do último edital atualizado e o método de extração híbrido.
- **`sincronizarRadar()`**:
  - Endpoint `POST /api/admin/radares/{fonte}/sincronizar` para disparo sob demanda dos spiders (`fapeg:sync`, `fapdf:sync`, `editais:sync`).
  - Retorna tempo de execução em segundos, status de saída e logs completos do processo.

---

## Rotas Mapeadas na API REST

| Método | Endpoint | Middleware | Descrição |
|---|---|---|---|
| `GET` | `/api/admin/dashboard` | `auth:sanctum, admin.global` | Métricas consolidadas da plataforma e MRR |
| `GET` | `/api/admin/agencias` | `auth:sanctum, admin.global` | Listagem paginada e filtros de consultorias |
| `GET` | `/api/admin/agencias/{id}` | `auth:sanctum, admin.global` | Detalhes completos e histórico da consultoria |
| `PATCH` | `/api/admin/agencias/{id}/status` | `auth:sanctum, admin.global` | Ligar/Desligar (Ativar ou Suspender) acessos |
| `POST` | `/api/admin/agencias/{id}/ajustar-plano` | `auth:sanctum, admin.global` | Ajuste manual de plano e concessão de dias |
| `DELETE` | `/api/admin/agencias/{id}` | `auth:sanctum, admin.global` | Exclusão atômica em cascata de consultoria |
| `GET` | `/api/admin/radares/status` | `auth:sanctum, admin.global` | Healthcheck ao vivo e status de cada radar |
| `POST` | `/api/admin/radares/{fonte}/sincronizar` | `auth:sanctum, admin.global` | Disparo manual de sincronização de spider |

---

## Consequências e Resultados
- **Controle Operacional Total**: A equipe fundadora ganha visibilidade instantânea sobre faturamento, clientes ativos e saúde da infraestrutura de web scraping.
- **Segurança RBAC Reforçada**: Isolamento absoluto entre consultorias e a camada administrativa global através de middleware dedicado.
- **Suíte de Testes Automatizados**: A suíte `AdminGlobalTest.php` valida 100% dos cenários de autorização, integridade de dados e saúde dos radares (**11 testes, 74 asserções aprovadas**).
- **Impacto no Ecossistema Global**: 155 testes aprovados (**1.071 asserções**, 0 falhas) em toda a aplicação.
