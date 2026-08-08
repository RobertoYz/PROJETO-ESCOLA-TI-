# 📖 Manual de Código — Radar de Editais B2B
## Guia Didático Completo: do Backend ao Frontend

> **Para quem é este manual?**
> Para **todos os integrantes da equipe**, seja você o Tech Lead com conhecimento intermediário ou o aluno que está abrindo o VS Code para aprender Laravel e JavaScript pela primeira vez. O manual funciona em dois níveis:
> - **Para quem está aprendendo:** Leia os blocos de `💡 Por que fazemos assim?` antes do código.
> - **Para a banca:** Os conceitos, a nomenclatura e os exemplos aqui são o que você vai defender na arguição.

---

## 📌 Sumário

1. [Como funciona o projeto: a Grande Imagem](#1-como-funciona-o-projeto-a-grande-imagem)
2. [Antes de começar: Convenções de Nomenclatura](#2-antes-de-começar-convenções-de-nomenclatura)
3. [Backend — Ciclo de Vida de uma Requisição](#3-backend--ciclo-de-vida-de-uma-requisição)
4. [Backend — O CRUD Completo Passo a Passo](#4-backend--o-crud-completo-passo-a-passo)
5. [Frontend — Design System e Estilização Padrão](#5-frontend--design-system-e-estilização-padrão)
6. [Frontend — A Função Central de Comunicação com a API](#6-frontend--a-função-central-de-comunicação-com-a-api)
7. [Frontend — Página Completa (HTML + CSS + JS)](#7-frontend--página-completa-html--css--js)
8. [Autenticação (Login, Cadastro e Token)](#8-autenticação-login-cadastro-e-token)
9. [Filtros, Busca e Paginação](#9-filtros-busca-e-paginação)
10. [Upload de Arquivos (PDF e Imagens)](#10-upload-de-arquivos-pdf-e-imagens)
11. [WebSockets — Notificações em Tempo Real](#11-websockets--notificações-em-tempo-real)
12. [Filas e Jobs Assíncronos (Queues)](#12-filas-e-jobs-assíncronos-queues)
13. [Testes Automatizados (PHPUnit)](#13-testes-automatizados-phpunit)
14. [Web Scraping — Motor de Mineração de Editais](#14-web-scraping--motor-de-mineração-de-editais)
15. [Glossário Técnico para a Banca](#15-glossário-técnico-para-a-banca)

---

## 1. Como funciona o projeto: a Grande Imagem

Antes de tocar em uma linha de código, entenda o modelo mental. Toda vez que algo "não funciona", volte a este diagrama.

```
╔══════════════════════════════════════════════════════════════════════╗
║                     RADAR DE EDITAIS — FLUXO GERAL                  ║
╠════════════════════╦═════════════════════╦═══════════════════════════╣
║   FRONTEND          ║   REDE (HTTP/JSON)  ║   BACKEND (Laravel 12)   ║
║  HTML + CSS + JS    ║                     ║                          ║
║                     ║                     ║  routes/api.php          ║
║  [Formulário]  ────────── POST /api/... ──────> [Controller]         ║
║                     ║                     ║       ↓                  ║
║  [Lista de Cards] <──── JSON de Resposta ─────── [Model → BD]        ║
╚════════════════════╩═════════════════════╩═══════════════════════════╝
```

### As 3 regras de ouro do projeto:

**Regra 1 — O Backend só fala JSON:**
O Laravel NUNCA vai retornar HTML, Blade ou qualquer template visual. A única saída é:
```json
{ "success": true, "data": { ... }, "message": "..." }
```

**Regra 2 — O Frontend só consome JSON:**
O HTML/JavaScript nunca renderiza dados vindos do PHP diretamente. Ele faz uma pergunta (`fetch()`) e monta a tela com a resposta.

**Regra 3 — Toda requisição protegida usa Token:**
Depois do login, o servidor entrega um `token`. O Frontend guarda esse token e o envia em TODA requisição seguinte, como uma "senha de sessão":
```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGci...
```

---

## 2. Antes de começar: Convenções de Nomenclatura

> 💡 **Por que isso importa?** Quando 4 pessoas trabalham no mesmo código, sem padrão, o projeto vira uma bagunça impossível de revisar. A banca vai olhar o código: código limpo e padronizado impressiona.

| Tipo de Arquivo/Código | Convenção | Exemplo Correto | Exemplo ERRADO |
| --- | --- | --- | --- |
| Classe PHP | `PascalCase` | `StartupController` | `startup_controller` |
| Método PHP | `camelCase` | `store()`, `findByUser()` | `Store()`, `find_by_user()` |
| Tabela do Banco | `plural_snake_case` | `startups`, `editais` | `Startup`, `edital` |
| Coluna do Banco | `snake_case` | `data_publicacao` | `dataPublicacao`, `DataPublicacao` |
| Variável JavaScript | `camelCase` | `dadosEditais`, `authToken` | `dados_editais`, `AuthToken` |
| Classe CSS | `kebab-case` | `.card-edital`, `.btn-primary` | `.cardEdital`, `.btnPrimary` |
| ID HTML | `kebab-case` | `#modal-startup`, `#lista-cards` | `#modalStartup`, `#listaCards` |

### Estrutura de pastas do projeto:
```
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/AuthController.php       → Login, Cadastro, Logout
│   │   │   ├── EditalController.php          → CRUD de Editais
│   │   │   ├── StartupController.php         → CRUD de Startups
│   │   │   └── PropostaController.php        → CRUD de Propostas/Minutas
│   │   └── Requests/
│   │       └── StoreStartupRequest.php       → Validação de formulários
│   ├── Models/
│   │   ├── User.php
│   │   ├── Startup.php
│   │   ├── Edital.php
│   │   └── Proposta.php
│   └── Jobs/
│       └── ProcessarPdfEdital.php            → Tarefa pesada em segundo plano
├── database/
│   └── migrations/                           → Estrutura das tabelas
├── routes/
│   └── api.php                               → TODAS as rotas da API
└── public/                                   ← TUDO do Frontend fica aqui
    ├── css/app.css                           → Design System único
    ├── js/
    │   ├── api.js                            → Função fetch() central
    │   ├── auth.js                           → Login e Token
    │   ├── editais.js                        → Lógica de editais
    │   └── startups.js                       → Lógica de startups
    └── pages/
        ├── login.html
        ├── editais.html
        └── startups.html
```

---

## 3. Backend — Ciclo de Vida de uma Requisição

> 💡 **Antes de escrever código, entenda o que acontece quando o Frontend manda uma requisição.**

Quando o JavaScript faz um `fetch('POST /api/startups')`, o Laravel segue este caminho em ordem:

```
1. routes/api.php
   └── Encontra a rota: Route::post('/startups', [StartupController::class, 'store'])
                                                       ↓
2. Middleware de Autenticação
   └── auth:sanctum verifica o Token Bearer no cabeçalho
   └── Se inválido → retorna 401 automaticamente
                                                       ↓
3. Form Request (Validação)
   └── StoreStartupRequest::rules() verifica todos os campos
   └── Se inválido → retorna 422 com os erros automaticamente
                                                       ↓
4. Controller (método store())
   └── Acessa os dados validados: $request->validated()
   └── Chama o Model para salvar no banco
   └── Retorna response()->json(...)
                                                       ↓
5. JSON de Resposta volta ao Frontend
```

---

## 4. Backend — O CRUD Completo Passo a Passo

Um CRUD (Create, Read, Update, Delete) segue sempre a **mesma sequência de 4 arquivos**. Aprenda uma vez, repita para todos os módulos.

### PASSO 1: Migration (estrutura da tabela no banco)

> 💡 **O que é?** Uma Migration é um arquivo PHP que diz ao banco de dados "crie esta tabela com estas colunas". Em vez de abrir o phpMyAdmin e criar tabelas manualmente, você escreve código e roda `php artisan migrate`. Isso garante que o banco de todos na equipe seja idêntico.

**Criar o arquivo:**
```bash
php artisan make:migration create_startups_table
```

**Editar o arquivo gerado em `database/migrations/...create_startups_table.php`:**
```php
public function up(): void
{
    Schema::create('startups', function (Blueprint $table) {
        $table->id();                                           // coluna id auto-incremento
        $table->foreignId('user_id')->constrained()            // chave estrangeira para users
              ->cascadeOnDelete();                             // se o user for deletado, deleta as startups
        $table->string('cnpj', 18)->unique();                  // texto, max 18 chars, único
        $table->string('nome_fantasia');                       // texto obrigatório
        $table->string('faturamento_anual')->nullable();       // nullable = pode ser nulo
        $table->string('trl_nivel')->nullable();
        $table->json('cnaes')->nullable();                     // armazena array como JSON
        $table->text('pitch')->nullable();                     // texto longo
        $table->timestamps();                                  // cria created_at e updated_at
    });
}
```

**Executar a migration:**
```bash
php artisan migrate
```

---

### PASSO 2: Model (representa a tabela em PHP)

> 💡 **O que é?** O Model é a "ponte" entre o PHP e o banco de dados. Quando você chama `Startup::create([...])`, o Eloquent traduz isso para um `INSERT INTO startups...` automaticamente. Você nunca escreve SQL puro.

**Criar o arquivo:**
```bash
php artisan make:model Startup
```

**Editar `app/Models/Startup.php`:**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Startup extends Model
{
    protected $table = 'startups'; // nome da tabela no banco

    /**
     * $fillable: lista de campos que podem ser preenchidos pelo código.
     * SEGURANÇA: sem isso, o Laravel bloqueia qualquer atribuição em massa.
     * Nunca coloque 'role' ou 'is_admin' aqui.
     */
    protected $fillable = [
        'user_id',
        'cnpj',
        'nome_fantasia',
        'faturamento_anual',
        'trl_nivel',
        'cnaes',
        'pitch',
    ];

    /**
     * $casts: converte automaticamente tipos de dados.
     * O campo 'cnaes' é armazenado como string JSON no banco,
     * mas ao ler, o Laravel o converte para array PHP.
     */
    protected $casts = [
        'cnaes' => 'array',
    ];

    // RELACIONAMENTOS (para a banca: "hasMany" = tem muitos, "belongsTo" = pertence a)

    /** Uma Startup pertence a um User (o consultor que a cadastrou) */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Uma Startup tem muitas Propostas */
    public function propostas()
    {
        return $this->hasMany(Proposta::class);
    }
}
```

---

### PASSO 3: Form Request (validação dos dados)

> 💡 **Por que não validar no Controller?** Para não poluir o Controller. A regra do Laravel é: cada classe tem uma responsabilidade. O Form Request valida; o Controller executa a lógica. Se a validação falhar, o Laravel retorna 422 automaticamente, sem você escrever nada.

**Criar o arquivo:**
```bash
php artisan make:request StoreStartupRequest
```

**Editar `app/Http/Requests/StoreStartupRequest.php`:**
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStartupRequest extends FormRequest
{
    /**
     * authorize(): define QUEM pode fazer esta requisição.
     * true = qualquer usuário autenticado (o middleware já garante isso).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * rules(): define as regras de validação de cada campo.
     *
     * Principais regras:
     *   required    → campo obrigatório
     *   nullable    → pode ser nulo/vazio
     *   string      → deve ser texto
     *   max:255     → máximo 255 caracteres
     *   unique:tabela,coluna → deve ser único na tabela
     *   array       → deve ser um array JSON
     *   min:8       → mínimo 8 caracteres
     */
    public function rules(): array
    {
        return [
            'cnpj'             => 'required|string|max:18|unique:startups,cnpj',
            'nome_fantasia'    => 'required|string|max:255',
            'faturamento_anual'=> 'nullable|string',
            'trl_nivel'        => 'nullable|string',
            'cnaes'            => 'nullable|array',
            'pitch'            => 'nullable|string|max:2000',
        ];
    }

    /** messages(): personaliza as mensagens de erro em português. */
    public function messages(): array
    {
        return [
            'cnpj.required'          => 'O CNPJ é obrigatório.',
            'cnpj.unique'            => 'Este CNPJ já está cadastrado.',
            'nome_fantasia.required' => 'O nome fantasia é obrigatório.',
        ];
    }
}
```

---

### PASSO 4: Controller (os 5 métodos REST)

> 💡 **O que é REST?** É uma convenção sobre quais métodos HTTP usar para cada ação. Aprenda de cor:
> - `GET /startups` → **listar** todas (método `index`)
> - `POST /startups` → **criar** uma nova (método `store`)
> - `GET /startups/{id}` → **exibir** uma específica (método `show`)
> - `PUT /startups/{id}` → **atualizar** uma existente (método `update`)
> - `DELETE /startups/{id}` → **excluir** uma (método `destroy`)

**Criar o arquivo:**
```bash
php artisan make:controller StartupController
```

**Editar `app/Http/Controllers/StartupController.php`:**
```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStartupRequest;
use App\Models\Startup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StartupController extends Controller
{
    /**
     * INDEX — GET /api/startups
     * Lista todas as startups do consultor logado.
     */
    public function index(): JsonResponse
    {
        // auth()->id() → ID do usuário que fez a requisição (lido do Token)
        // where() → filtra só as startups deste consultor (segurança: sem isso, mostraria de todos)
        // latest() → ordena do mais recente para o mais antigo
        // paginate(10) → retorna 10 por página (não retorna tudo de uma vez)
        $startups = Startup::where('user_id', auth()->id())
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data'    => $startups,
            'message' => 'Startups carregadas com sucesso.',
        ], 200); // 200 = OK
    }

    /**
     * STORE — POST /api/startups
     * Cria uma nova startup.
     */
    public function store(StoreStartupRequest $request): JsonResponse
    {
        // $request->validated() → retorna SOMENTE os campos que passaram na validação
        // Nunca use $request->all() para salvar — ele pega TUDO, incluindo campos maliciosos
        $startup = Startup::create([
            ...$request->validated(),   // espalha os campos validados
            'user_id' => auth()->id(),  // adiciona o ID do consultor logado
        ]);

        return response()->json([
            'success' => true,
            'data'    => $startup,
            'message' => 'Startup cadastrada com sucesso!',
        ], 201); // 201 = Created (diferente de 200!)
    }

    /**
     * SHOW — GET /api/startups/{id}
     * Exibe uma startup específica.
     */
    public function show(string $id): JsonResponse
    {
        // findOrFail() → busca pelo ID. Se não encontrar, lança 404 automaticamente.
        // where('user_id') → garante que o consultor só vê as SUAS startups
        $startup = Startup::where('user_id', auth()->id())->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $startup,
        ], 200);
    }

    /**
     * UPDATE — PUT /api/startups/{id}
     * Atualiza uma startup existente.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $startup = Startup::where('user_id', auth()->id())->findOrFail($id);

        // 'sometimes' → valida o campo APENAS se ele foi enviado na requisição
        // Útil para atualização parcial: o usuário não precisa reenviar todos os campos
        $startup->update($request->validate([
            'nome_fantasia'    => 'sometimes|string|max:255',
            'faturamento_anual'=> 'sometimes|nullable|string',
            'trl_nivel'        => 'sometimes|nullable|string',
            'cnaes'            => 'sometimes|nullable|array',
            'pitch'            => 'sometimes|nullable|string|max:2000',
        ]));

        return response()->json([
            'success' => true,
            'data'    => $startup->fresh(), // .fresh() recarrega os dados atualizados do banco
            'message' => 'Startup atualizada com sucesso!',
        ], 200);
    }

    /**
     * DESTROY — DELETE /api/startups/{id}
     * Exclui uma startup.
     */
    public function destroy(string $id): JsonResponse
    {
        $startup = Startup::where('user_id', auth()->id())->findOrFail($id);
        $startup->delete();

        return response()->json([
            'success' => true,
            'message' => 'Startup removida com sucesso.',
        ], 200);
    }
}
```

---

### PASSO 5: Rotas (conecta URL ao Controller)

> 💡 **O arquivo `routes/api.php` é o "índice" da API.** Todo endpoint que o Frontend vai chamar deve estar registrado aqui. Sem rota, não existe endpoint.

**Editar `routes/api.php`:**
```php
<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\EditalController;
use App\Http\Controllers\StartupController;
use Illuminate\Support\Facades\Route;

// ===================================================
// ROTAS PÚBLICAS — Acessíveis sem autenticação
// ===================================================
Route::post('/auth/cadastro', [AuthController::class, 'cadastro']);
Route::post('/auth/login',    [AuthController::class, 'login']);

// ===================================================
// ROTAS PROTEGIDAS — Exigem Token Bearer válido
// O middleware 'auth:sanctum' verifica o token.
// Se inválido → retorna 401 automaticamente.
// ===================================================
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // apiResource() cria AUTOMATICAMENTE as 5 rotas REST:
    //   GET    /startups         → index
    //   POST   /startups         → store
    //   GET    /startups/{id}    → show
    //   PUT    /startups/{id}    → update
    //   DELETE /startups/{id}    → destroy
    Route::apiResource('startups', StartupController::class);

    Route::get('/editais',      [EditalController::class, 'index']);
    Route::get('/editais/{id}', [EditalController::class, 'show']);
});
```

**Verificar se as rotas foram criadas:**
```bash
php artisan route:list --path=api
```

---

## 5. Frontend — Design System e Estilização Padrão

> 💡 **Por que um Design System?** Sem um arquivo CSS centralizado, cada integrante inventará suas próprias cores, tamanhos e espaçamentos. O resultado: a tela parece feita por 4 pessoas diferentes. O `app.css` é a linguagem visual única do projeto.

> ⚠️ **Regra absoluta:** Todo HTML importa `app.css`. Ninguém cria estilos inline (`style="..."` no HTML) nem arquivos CSS separados. Se precisar de um estilo novo, adiciona uma variável ou classe em `app.css` e avisa a equipe.

**Arquivo:** `public/css/app.css`

```css
/* ===================================================
   RADAR DE EDITAIS — DESIGN SYSTEM
   Use as variáveis CSS abaixo. Não invente novas cores.
   =================================================== */

/* Importa a fonte Inter do Google (moderna e legível) */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

/* =====================
   VARIÁVEIS GLOBAIS
   Mude aqui e muda em todo o projeto.
   ===================== */
:root {
  /* Paleta de Cores — Tema Escuro Profissional */
  --color-bg:       #0f1117;   /* fundo da página */
  --color-surface:  #1a1d2e;   /* fundo de cards e painéis */
  --color-border:   #2a2d3e;   /* bordas sutis */
  --color-primary:  #6c63ff;   /* roxo — ações principais (botões, links) */
  --color-primary-h:#8b83ff;   /* roxo mais claro para hover */
  --color-success:  #22c55e;   /* verde — aprovado, sucesso */
  --color-warning:  #f59e0b;   /* amarelo — pendente, atenção */
  --color-danger:   #ef4444;   /* vermelho — erro, excluir */
  --color-text:     #e2e8f0;   /* texto principal */
  --color-muted:    #64748b;   /* texto secundário, placeholder */

  /* Tipografia */
  --font-base: 'Inter', system-ui, sans-serif;

  /* Bordas arredondadas */
  --radius-sm: 6px;
  --radius-md: 10px;
  --radius-lg: 16px;

  /* Sombra padrão de cards */
  --shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

/* =====================
   RESET E BASE
   Remove estilos padrão do navegador
   ===================== */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: var(--font-base);
  background-color: var(--color-bg);
  color: var(--color-text);
  line-height: 1.6;
  min-height: 100vh;
}

a { color: var(--color-primary); text-decoration: none; }
a:hover { color: var(--color-primary-h); }

/* =====================
   LAYOUT PRINCIPAL
   Sidebar + Conteúdo lado a lado
   ===================== */
.app-layout {
  display: grid;
  grid-template-columns: 240px 1fr;
  min-height: 100vh;
}

.main-content {
  padding: 2rem;
  overflow-y: auto;
}

/* =====================
   SIDEBAR
   ===================== */
.sidebar {
  background-color: var(--color-surface);
  border-right: 1px solid var(--color-border);
  padding: 1.5rem 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.sidebar-logo {
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--color-primary);
  padding: 0.75rem;
  margin-bottom: 1rem;
  letter-spacing: -0.5px;
}

.sidebar-link {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.6rem 0.75rem;
  border-radius: var(--radius-sm);
  color: var(--color-muted);
  font-size: 0.875rem;
  font-weight: 500;
  transition: background-color 0.15s ease, color 0.15s ease;
}

.sidebar-link:hover,
.sidebar-link.active {
  background-color: rgba(108, 99, 255, 0.12);
  color: var(--color-primary);
}

/* =====================
   CARDS
   ===================== */
.card {
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 1.5rem;
  box-shadow: var(--shadow);
}

.card-title { font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem; }
.card-meta  { font-size: 0.8rem; color: var(--color-muted); margin-bottom: 0.75rem; }

/* Grade automática de cards */
.cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1.25rem;
}

/* =====================
   BOTÕES
   Uso: <button class="btn btn-primary">Salvar</button>
   ===================== */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.6rem 1.2rem;
  border-radius: var(--radius-sm);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.15s ease;
  font-family: var(--font-base);
}

.btn-primary { background-color: var(--color-primary); color: #fff; }
.btn-primary:hover { background-color: var(--color-primary-h); }

.btn-outline {
  background-color: transparent;
  border: 1px solid var(--color-border);
  color: var(--color-text);
}
.btn-outline:hover { border-color: var(--color-primary); color: var(--color-primary); }

.btn-danger { background-color: var(--color-danger); color: #fff; }
.btn-danger:hover { opacity: 0.85; }

.btn:disabled { opacity: 0.5; cursor: not-allowed; }

/* =====================
   FORMULÁRIOS
   Uso: <div class="form-group">
          <label class="form-label">Nome</label>
          <input class="form-input" />
          <span class="form-error" id="erro-nome"></span>
        </div>
   ===================== */
.form-group   { display: flex; flex-direction: column; gap: 0.4rem; margin-bottom: 1rem; }
.form-label   { font-size: 0.85rem; font-weight: 500; color: var(--color-muted); }
.form-error   { font-size: 0.78rem; color: var(--color-danger); min-height: 1rem; }

.form-input,
.form-select,
.form-textarea {
  background-color: var(--color-bg);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  color: var(--color-text);
  padding: 0.65rem 0.85rem;
  font-size: 0.875rem;
  font-family: var(--font-base);
  width: 100%;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(108, 99, 255, 0.15);
}

/* =====================
   BADGES DE STATUS
   Uso: <span class="badge badge-success">Aprovado</span>
   ===================== */
.badge {
  display: inline-block;
  padding: 0.2rem 0.65rem;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 600;
}
.badge-success { background: rgba(34,197,94,.15); color: var(--color-success); }
.badge-warning { background: rgba(245,158,11,.15); color: var(--color-warning); }
.badge-primary { background: rgba(108,99,255,.15); color: var(--color-primary); }
.badge-danger  { background: rgba(239,68,68,.15);  color: var(--color-danger);  }

/* =====================
   TABELA
   ===================== */
.table-wrapper { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
thead th {
  text-align: left;
  padding: 0.75rem 1rem;
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--color-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  border-bottom: 1px solid var(--color-border);
}
tbody td { padding: 0.875rem 1rem; border-bottom: 1px solid var(--color-border); }
tbody tr:hover { background-color: rgba(255,255,255,0.02); }

/* =====================
   TOAST / NOTIFICAÇÃO
   Aparece no canto inferior direito.
   Adicionado via JavaScript (veja api.js)
   ===================== */
#toast-container {
  position: fixed;
  bottom: 1.5rem;
  right: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  z-index: 9999;
}
.toast {
  padding: 0.85rem 1.2rem;
  border-radius: var(--radius-sm);
  font-size: 0.875rem;
  font-weight: 500;
  color: #fff;
  box-shadow: var(--shadow);
  animation: slideIn 0.25s ease;
  max-width: 320px;
}
.toast-success { background-color: var(--color-success); }
.toast-error   { background-color: var(--color-danger);  }
.toast-info    { background-color: var(--color-primary); }

@keyframes slideIn {
  from { transform: translateX(110%); opacity: 0; }
  to   { transform: translateX(0);   opacity: 1; }
}

/* =====================
   SPINNER DE CARREGAMENTO
   Uso: <span class="spinner"></span>
   ===================== */
.spinner {
  width: 16px; height: 16px;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
  display: inline-block;
  vertical-align: middle;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* =====================
   MODAL
   Uso: <div class="modal-overlay" id="meu-modal">
          <div class="modal-box">...</div>
        </div>
   ===================== */
.modal-overlay {
  display: none; /* mostrar via JS: modal.style.display = 'flex' */
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.65);
  z-index: 1000;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}
.modal-box {
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 2rem;
  width: 100%;
  max-width: 520px;
  box-shadow: var(--shadow);
}
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}
.modal-title { font-size: 1.1rem; font-weight: 700; }
.modal-close {
  background: none; border: none; cursor: pointer;
  color: var(--color-muted); font-size: 1.25rem;
}
.modal-close:hover { color: var(--color-text); }

/* =====================
   RESPONSIVIDADE MOBILE
   ===================== */
@media (max-width: 768px) {
  .app-layout { grid-template-columns: 1fr; }
  .sidebar { display: none; }
  .sidebar.open { display: flex; position: fixed; inset: 0; z-index: 500; }
  .main-content { padding: 1rem; }
}
```

---

## 6. Frontend — A Função Central de Comunicação com a API

> 💡 **Por que centralizar o fetch()?** Para não repetir o código de token, tratamento de erro e redirecionamento para login em cada arquivo. Crie uma vez aqui, use em todos os módulos.

**Arquivo:** `public/js/api.js` — **Todo mundo importa este arquivo antes do seu próprio `.js`.**

```javascript
/**
 * URL base da API. Em produção, mude para o domínio real.
 * Ex: 'https://api.radareditais.com.br/api'
 */
const API_URL = 'http://localhost:8000/api';

/**
 * apiRequest() — Função central de comunicação com a API REST.
 *
 * Como usar:
 *   const resultado = await apiRequest('/startups', 'GET');
 *   const resultado = await apiRequest('/startups', 'POST', { cnpj: '...', nome_fantasia: '...' });
 *   const resultado = await apiRequest('/startups/5', 'PUT', { pitch: '...' });
 *   const resultado = await apiRequest('/startups/5', 'DELETE');
 *
 * @param {string} endpoint  - Caminho da rota. Ex: '/startups', '/editais/10'
 * @param {string} method    - Método HTTP: 'GET', 'POST', 'PUT', 'DELETE'
 * @param {object|null} body - Dados a enviar (null para GET e DELETE)
 * @returns {Promise<object>} - JSON retornado pela API: { success, data, message }
 */
async function apiRequest(endpoint, method = 'GET', body = null) {
    // 1. Pega o token salvo no login
    const token = localStorage.getItem('auth_token');

    // 2. Monta os cabeçalhos HTTP
    const headers = {
        'Content-Type': 'application/json',
        'Accept':        'application/json',
    };

    // 3. Adiciona o token ao cabeçalho (se existir)
    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }

    // 4. Monta as opções da requisição
    const opcoes = { method, headers };
    if (body) {
        opcoes.body = JSON.stringify(body); // converte objeto JS para string JSON
    }

    // 5. Faz a requisição e trata erros
    try {
        const resposta   = await fetch(`${API_URL}${endpoint}`, opcoes);
        const resultado  = await resposta.json();

        // Se o token expirou → limpa o localStorage e volta para o login
        if (resposta.status === 401) {
            localStorage.clear();
            window.location.href = '/pages/login.html';
            return null;
        }

        return resultado;

    } catch (erro) {
        // Erro de rede (sem conexão com o servidor)
        console.error('[apiRequest] Erro:', erro.message);
        mostrarToast('Erro de conexão. Verifique se o servidor está rodando.', 'error');
        return { success: false, message: 'Erro de conexão.' };
    }
}

/**
 * mostrarToast() — Exibe uma notificação temporária na tela.
 *
 * Como usar:
 *   mostrarToast('Salvo com sucesso!', 'success');
 *   mostrarToast('Algo deu errado.', 'error');
 *   mostrarToast('Processando...', 'info');
 *
 * @param {string} mensagem
 * @param {'success'|'error'|'info'} tipo
 */
function mostrarToast(mensagem, tipo = 'info') {
    let container = document.getElementById('toast-container');
    // Cria o container se não existir na página
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${tipo}`;
    toast.textContent = mensagem;
    container.appendChild(toast);

    // Remove o toast depois de 4 segundos
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

/**
 * setarBotaoCarregando() — Desativa/ativa um botão e exibe spinner.
 * Evita que o usuário clique duas vezes enquanto aguarda a resposta.
 *
 * Como usar:
 *   setarBotaoCarregando('btn-salvar', true);   // antes da requisição
 *   setarBotaoCarregando('btn-salvar', false);  // depois da requisição
 */
function setarBotaoCarregando(idBotao, carregando) {
    const btn = document.getElementById(idBotao);
    if (!btn) return;

    if (carregando) {
        btn.disabled = true;
        btn.dataset.textoOriginal = btn.innerHTML; // guarda o texto original
        btn.innerHTML = '<span class="spinner"></span> Aguarde...';
    } else {
        btn.disabled = false;
        btn.innerHTML = btn.dataset.textoOriginal || 'Salvar';
    }
}
```

---

## 7. Frontend — Página Completa (HTML + CSS + JS)

> 💡 **Estrutura de toda página do projeto:**
> 1. HTML define a estrutura visual.
> 2. CSS (`app.css`) define a aparência.
> 3. JS consome a API e manipula o DOM.

### HTML (`public/pages/startups.html`):
```html
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Carteira de Startups — Radar de Editais</title>
  <link rel="stylesheet" href="../css/app.css">
</head>
<body>

<div class="app-layout">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-logo">🎯 Radar</div>
    <a href="editais.html"  class="sidebar-link">📋 Editais</a>
    <a href="startups.html" class="sidebar-link active">🏢 Startups</a>
    <a href="kanban.html"   class="sidebar-link">📊 Kanban</a>
    <a href="perfil.html"   class="sidebar-link">👤 Perfil</a>
    <div style="margin-top:auto">
      <button class="btn btn-outline" style="width:100%" onclick="fazerLogout()">Sair</button>
    </div>
  </aside>

  <!-- Conteúdo -->
  <main class="main-content">

    <!-- Cabeçalho da Página -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem">
      <div>
        <h1 style="font-size:1.5rem; font-weight:700">Carteira de Startups</h1>
        <p style="color:var(--color-muted); font-size:0.875rem">Startups sob gestão da consultoria</p>
      </div>
      <button class="btn btn-primary" onclick="abrirModalCriar()">
        + Nova Startup
      </button>
    </div>

    <!-- Grade de Cards (preenchida pelo JavaScript) -->
    <div class="cards-grid" id="lista-startups">
      <p style="color:var(--color-muted)">Carregando...</p>
    </div>

  </main>
</div>

<!-- Modal de Criar/Editar -->
<div class="modal-overlay" id="modal-startup">
  <div class="modal-box">
    <div class="modal-header">
      <h2 class="modal-title" id="modal-titulo">Nova Startup</h2>
      <button class="modal-close" onclick="fecharModal()">✕</button>
    </div>

    <form id="form-startup">
      <div class="form-group">
        <label class="form-label">CNPJ *</label>
        <input type="text" id="input-cnpj" class="form-input" placeholder="00.000.000/0001-00">
        <span class="form-error" id="erro-cnpj"></span>
      </div>

      <div class="form-group">
        <label class="form-label">Nome Fantasia *</label>
        <input type="text" id="input-nome" class="form-input" placeholder="Ex: Tech Solutions Ltda">
        <span class="form-error" id="erro-nome"></span>
      </div>

      <div class="form-group">
        <label class="form-label">Nível de TRL</label>
        <select id="input-trl" class="form-select">
          <option value="">Selecionar...</option>
          <option value="TRL 1">TRL 1 — Princípios básicos</option>
          <option value="TRL 4">TRL 4 — Validado em laboratório</option>
          <option value="TRL 7">TRL 7 — Demonstrado em ambiente real</option>
          <option value="TRL 9">TRL 9 — Aprovado e operacional</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Pitch (Resumo do Negócio)</label>
        <textarea id="input-pitch" class="form-textarea" rows="4"
          placeholder="Descreva o negócio em até 500 caracteres..."></textarea>
        <span class="form-error" id="erro-pitch"></span>
      </div>

      <div style="display:flex; gap:0.75rem; justify-content:flex-end; margin-top:0.5rem">
        <button type="button" class="btn btn-outline" onclick="fecharModal()">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="btn-salvar">Salvar Startup</button>
      </div>
    </form>
  </div>
</div>

<!-- Scripts: api.js SEMPRE antes do módulo específico -->
<script src="../js/api.js"></script>
<script src="../js/auth.js"></script>
<script src="../js/startups.js"></script>

</body>
</html>
```

### JavaScript do Módulo (`public/js/startups.js`):
```javascript
// ========================================================
// STARTUPS.JS — Lógica da página de Carteira de Startups
// Requer: api.js (deve ser importado antes no HTML)
// ========================================================

let idEditando = null; // guarda o ID da startup em edição (null = modo criação)

// ========================================================
// INICIALIZAÇÃO — Roda quando a página termina de carregar
// ========================================================
document.addEventListener('DOMContentLoaded', () => {
    verificarLogin();
    carregarStartups();
    document.getElementById('form-startup').addEventListener('submit', salvarStartup);
});

/** Redireciona para login se não houver token salvo */
function verificarLogin() {
    if (!localStorage.getItem('auth_token')) {
        window.location.href = '/pages/login.html';
    }
}

// ========================================================
// LISTAR — GET /api/startups
// ========================================================
async function carregarStartups() {
    const lista = document.getElementById('lista-startups');
    lista.innerHTML = '<p style="color:var(--color-muted)">Carregando...</p>';

    const resultado = await apiRequest('/startups');

    if (!resultado || !resultado.success) {
        lista.innerHTML = '<p style="color:var(--color-danger)">Erro ao carregar startups.</p>';
        return;
    }

    const startups = resultado.data.data; // paginação: data.data

    if (startups.length === 0) {
        lista.innerHTML = `
            <div style="grid-column:1/-1; text-align:center; padding:3rem; color:var(--color-muted)">
                <p style="font-size:1.5rem; margin-bottom:0.5rem">🏢</p>
                <p>Nenhuma startup cadastrada.</p>
                <button class="btn btn-primary" style="margin-top:1rem" onclick="abrirModalCriar()">
                    + Cadastrar primeira startup
                </button>
            </div>
        `;
        return;
    }

    // Monta os cards dinamicamente com template literals
    lista.innerHTML = startups.map(s => `
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.75rem">
                <div>
                    <div class="card-title">${s.nome_fantasia}</div>
                    <div class="card-meta">CNPJ: ${s.cnpj}</div>
                </div>
                ${s.trl_nivel ? `<span class="badge badge-primary">${s.trl_nivel}</span>` : ''}
            </div>
            <p style="font-size:0.85rem; color:var(--color-muted); margin-bottom:1rem; line-height:1.5">
                ${s.pitch ? s.pitch.substring(0, 120) + (s.pitch.length > 120 ? '...' : '') : 'Sem pitch cadastrado.'}
            </p>
            <div style="display:flex; gap:0.5rem">
                <button class="btn btn-outline" style="flex:1" onclick="abrirModalEditar(${s.id})">
                    ✏️ Editar
                </button>
                <button class="btn btn-danger" onclick="excluirStartup(${s.id}, '${s.nome_fantasia}')">
                    🗑️
                </button>
            </div>
        </div>
    `).join('');
}

// ========================================================
// CRIAR — Abre modal vazio
// ========================================================
function abrirModalCriar() {
    idEditando = null;
    document.getElementById('modal-titulo').textContent = 'Nova Startup';
    document.getElementById('form-startup').reset();
    limparErros();
    document.getElementById('modal-startup').style.display = 'flex';
}

// ========================================================
// EDITAR — GET /api/startups/{id} → Preenche e abre modal
// ========================================================
async function abrirModalEditar(id) {
    idEditando = id;
    document.getElementById('modal-titulo').textContent = 'Editar Startup';

    const resultado = await apiRequest(`/startups/${id}`);
    if (!resultado || !resultado.success) {
        mostrarToast('Não foi possível carregar os dados.', 'error');
        return;
    }

    const s = resultado.data;
    document.getElementById('input-cnpj').value  = s.cnpj;
    document.getElementById('input-nome').value  = s.nome_fantasia;
    document.getElementById('input-trl').value   = s.trl_nivel  || '';
    document.getElementById('input-pitch').value = s.pitch      || '';

    document.getElementById('modal-startup').style.display = 'flex';
}

function fecharModal() {
    document.getElementById('modal-startup').style.display = 'none';
}

// ========================================================
// SALVAR — POST ou PUT dependendo do idEditando
// ========================================================
async function salvarStartup(evento) {
    evento.preventDefault(); // impede o reload padrão do formulário
    limparErros();

    const payload = {
        cnpj:          document.getElementById('input-cnpj').value.trim(),
        nome_fantasia: document.getElementById('input-nome').value.trim(),
        trl_nivel:     document.getElementById('input-trl').value  || null,
        pitch:         document.getElementById('input-pitch').value.trim() || null,
    };

    setarBotaoCarregando('btn-salvar', true); // desativa botão + spinner

    let resultado;
    if (idEditando) {
        resultado = await apiRequest(`/startups/${idEditando}`, 'PUT', payload);
    } else {
        resultado = await apiRequest('/startups', 'POST', payload);
    }

    setarBotaoCarregando('btn-salvar', false); // reativa botão

    if (!resultado || !resultado.success) {
        // Exibe erros de validação nos campos específicos
        if (resultado?.errors) {
            if (resultado.errors.cnpj)          document.getElementById('erro-cnpj').textContent = resultado.errors.cnpj[0];
            if (resultado.errors.nome_fantasia)  document.getElementById('erro-nome').textContent = resultado.errors.nome_fantasia[0];
            if (resultado.errors.pitch)          document.getElementById('erro-pitch').textContent = resultado.errors.pitch[0];
        }
        mostrarToast(resultado?.message || 'Erro ao salvar.', 'error');
        return;
    }

    mostrarToast(resultado.message, 'success');
    fecharModal();
    carregarStartups(); // atualiza a lista na tela
}

// ========================================================
// EXCLUIR — DELETE /api/startups/{id}
// ========================================================
async function excluirStartup(id, nome) {
    if (!confirm(`Excluir "${nome}"? Esta ação não pode ser desfeita.`)) return;

    const resultado = await apiRequest(`/startups/${id}`, 'DELETE');

    if (resultado?.success) {
        mostrarToast('Startup removida com sucesso.', 'success');
        carregarStartups();
    } else {
        mostrarToast('Erro ao excluir startup.', 'error');
    }
}

// ========================================================
// UTILITÁRIOS
// ========================================================
function limparErros() {
    document.querySelectorAll('.form-error').forEach(el => el.textContent = '');
}
```

---

## 8. Autenticação (Login, Cadastro e Token)

### Backend (`app/Http/Controllers/Auth/AuthController.php`):
```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /** POST /api/auth/cadastro */
    public function cadastro(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|email|unique:users,email',
            'password'              => 'required|string|min:8|confirmed',
            // 'confirmed' exige um campo 'password_confirmation' igual ao 'password'
        ]);

        $user  = User::create([
            'name'     => $dados['name'],
            'email'    => $dados['email'],
            'password' => Hash::make($dados['password']), // NUNCA salve senha em texto puro
        ]);

        // Gera o Token de Acesso (string longa e aleatória)
        $token = $user->createToken('radar-editais')->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => ['token' => $token, 'user' => $user],
            'message' => 'Cadastro realizado! Bem-vindo ao Radar.',
        ], 201);
    }

    /** POST /api/auth/login */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        // Hash::check() compara a senha digitada com o hash do banco
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'E-mail ou senha incorretos.',
            ], 401);
        }

        // Apaga tokens antigos e cria um novo (garante apenas 1 sessão ativa)
        $user->tokens()->delete();
        $token = $user->createToken('radar-editais')->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => ['token' => $token, 'user' => $user],
            'message' => 'Login realizado com sucesso!',
        ], 200);
    }

    /** POST /api/auth/logout (rota protegida) */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete(); // revoga o token atual
        return response()->json(['success' => true, 'message' => 'Logout realizado.'], 200);
    }
}
```

### Frontend (`public/js/auth.js`):
```javascript
/** POST /api/auth/login */
async function fazerLogin(evento) {
    evento.preventDefault();
    limparErros();

    const email    = document.getElementById('input-email').value.trim();
    const password = document.getElementById('input-password').value;

    setarBotaoCarregando('btn-login', true);
    const resultado = await apiRequest('/auth/login', 'POST', { email, password });
    setarBotaoCarregando('btn-login', false);

    if (resultado?.success) {
        // Persiste o token e os dados do usuário no navegador
        localStorage.setItem('auth_token', resultado.data.token);
        localStorage.setItem('auth_user',  JSON.stringify(resultado.data.user));
        window.location.href = '/pages/editais.html';
    } else {
        document.getElementById('erro-login').textContent = resultado?.message || 'Erro ao fazer login.';
    }
}

/** POST /api/auth/logout */
async function fazerLogout() {
    await apiRequest('/auth/logout', 'POST');
    localStorage.clear();
    window.location.href = '/pages/login.html';
}

/** Retorna os dados do usuário logado (salvo no localStorage) */
function getUsuarioLogado() {
    const raw = localStorage.getItem('auth_user');
    return raw ? JSON.parse(raw) : null;
}
```

---

## 9. Filtros, Busca e Paginação

### Backend:
```php
public function index(Request $request): JsonResponse
{
    $query = Edital::query();

    // Busca textual: ?search=inovação
    if ($request->filled('search')) {
        $query->where(function ($q) use ($request) {
            $q->where('titulo',   'like', "%{$request->search}%")
              ->orWhere('objetivo', 'like', "%{$request->search}%");
        });
    }

    // Filtro por fonte: ?fonte[]=FINEP&fonte[]=FAPESC
    if ($request->filled('fonte') && is_array($request->fonte)) {
        $query->whereIn('fonte', $request->fonte);
    }

    return response()->json([
        'success' => true,
        'data'    => $query->latest('data_publicacao')->paginate(12)->withQueryString(),
    ], 200);
}
```

### Frontend:
```javascript
let paginaAtual = 1;

async function carregarEditais() {
    const search = document.getElementById('input-busca')?.value.trim() ?? '';
    const fontes = [...document.querySelectorAll('input[name="fonte"]:checked')]
                    .map(el => el.value);

    // URLSearchParams monta a query string corretamente: ?search=...&fonte[]=...
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    fontes.forEach(f => params.append('fonte[]', f));
    params.append('page', paginaAtual);

    const resultado = await apiRequest(`/editais?${params}`);
    if (!resultado?.success) return;

    renderizarCards(resultado.data.data);
    renderizarPaginacao(resultado.data);
}

function renderizarPaginacao(paginacao) {
    document.getElementById('paginacao').innerHTML = `
        <button class="btn btn-outline"
            onclick="irPara(${paginacao.current_page - 1})"
            ${paginacao.current_page <= 1 ? 'disabled' : ''}>← Anterior</button>
        <span style="color:var(--color-muted)">
            ${paginacao.current_page} / ${paginacao.last_page}
        </span>
        <button class="btn btn-outline"
            onclick="irPara(${paginacao.current_page + 1})"
            ${paginacao.current_page >= paginacao.last_page ? 'disabled' : ''}>Próxima →</button>
    `;
}

function irPara(pagina) { paginaAtual = pagina; carregarEditais(); }
```

---

## 10. Upload de Arquivos (PDF e Imagens)

### Backend:
```php
public function uploadPdf(Request $request): JsonResponse
{
    $request->validate([
        'arquivo' => 'required|file|mimes:pdf|max:10240', // máx 10MB
    ]);

    // store() salva em storage/app/public/editais_pdf/
    $caminho = $request->file('arquivo')->store('editais_pdf', 'public');

    return response()->json([
        'success' => true,
        'data'    => ['caminho' => $caminho],
    ], 201);
}
```

### Frontend:
```javascript
async function enviarPdf() {
    const arquivo = document.getElementById('input-pdf').files[0];
    if (!arquivo) { mostrarToast('Selecione um arquivo PDF.', 'error'); return; }

    const formData = new FormData();
    formData.append('arquivo', arquivo);

    // ⚠️ Upload usa FormData, não JSON. NÃO defina Content-Type manualmente.
    const resposta = await fetch(`${API_URL}/editais/upload-pdf`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${localStorage.getItem('auth_token')}` },
        body: formData,
    });

    const resultado = await resposta.json();
    if (resultado.success) {
        mostrarToast('PDF enviado! Analisando em segundo plano...', 'info');
    }
}
```

---

## 11. WebSockets — Notificações em Tempo Real

### Backend — Evento:
```php
// php artisan make:event PropostaGeradaEvent
class PropostaGeradaEvent implements ShouldBroadcast
{
    public function __construct(
        public int    $userId,
        public string $mensagem,
        public string $textoChunk = ''
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("usuario.{$this->userId}");
    }

    public function broadcastAs(): string { return 'proposta.gerada'; }
}

// Disparar em qualquer lugar do backend:
broadcast(new PropostaGeradaEvent(auth()->id(), 'Minuta pronta!', $trecho));
```

### Frontend:
```javascript
function iniciarWebSocket(userId) {
    const echo = new Echo({
        broadcaster: 'reverb',
        key: window.REVERB_KEY,
        wsHost: 'localhost',
        wsPort: 8080,
        forceTLS: false,
    });

    echo.private(`usuario.${userId}`)
        .listen('.proposta.gerada', (evento) => {
            mostrarToast(evento.mensagem, 'success');
            if (evento.textoChunk) {
                document.getElementById('area-proposta').textContent += evento.textoChunk;
            }
        });
}
```

---

## 12. Filas e Jobs Assíncronos (Queues)

```php
// php artisan make:job ProcessarPdfEdital
class ProcessarPdfEdital implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        public int    $userId,
        public string $caminhoPdf
    ) {}

    public function handle(): void
    {
        // 1. Lê o PDF
        // 2. Chama IA para análise
        // 3. Salva resultado no banco
        // 4. Notifica usuário via WebSocket
        broadcast(new PropostaGeradaEvent($this->userId, 'PDF analisado com sucesso!'));
    }
}

// Disparar (o Controller responde imediatamente; o Job roda depois):
ProcessarPdfEdital::dispatch(auth()->id(), $caminhoPdf);

// Rodar o worker de filas:
// php artisan queue:work --tries=3
```

---

## 13. Testes Automatizados (PHPUnit)

> 💡 **Por que testar?** Para garantir que uma mudança no código não quebrou outra parte. Na banca, mostrar testes passa uma imagem de maturidade técnica.

```php
// php artisan make:test StartupTest
class StartupTest extends TestCase
{
    use RefreshDatabase; // reseta o banco antes de cada teste

    /** @test */
    public function usuario_pode_criar_startup(): void
    {
        $user = User::factory()->create();

        $resposta = $this->actingAs($user) // simula o usuário logado
            ->postJson('/api/startups', [
                'cnpj'          => '12.345.678/0001-99',
                'nome_fantasia' => 'Startup Teste',
            ]);

        $resposta->assertStatus(201)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('data.nome_fantasia', 'Startup Teste');

        $this->assertDatabaseHas('startups', ['cnpj' => '12.345.678/0001-99']);
    }

    /** @test */
    public function usuario_sem_token_recebe_401(): void
    {
        $this->getJson('/api/startups')->assertStatus(401);
    }

    /** @test */
    public function campos_obrigatorios_retornam_422(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
             ->postJson('/api/startups', [])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['cnpj', 'nome_fantasia']);
    }
}
```

**Rodar os testes:**
```bash
# Todos os testes
php artisan test

# Arquivo específico
php artisan test tests/Feature/StartupTest.php

# Com detalhes
php artisan test --verbose
```

---

## 14. Glossário Técnico para a Banca

Saiba explicar cada um destes termos com suas próprias palavras:

| Termo | Explicação para a Banca |
| --- | --- |
| **API REST Stateless** | "O servidor não guarda o estado da sessão. Cada requisição precisa enviar o Token de autenticação no cabeçalho. O servidor é 'sem memória'." |
| **Bearer Token (Sanctum)** | "É uma chave gerada pelo servidor no login. O Frontend a guarda no `localStorage` e a envia em toda requisição protegida via cabeçalho `Authorization: Bearer {token}`." |
| **Eloquent ORM** | "É a camada do Laravel que representa tabelas do banco como Classes PHP. `Startup::create([...])` equivale a um `INSERT INTO` em SQL, sem escrever SQL." |
| **`$fillable`** | "Lista de campos que o Eloquent permite preencher em massa. Protege contra que um usuário malicioso envie campos como `is_admin: true` e o sistema aceite." |
| **Migration** | "Arquivo PHP que descreve a estrutura de uma tabela. É o controle de versão do banco de dados. Roda com `php artisan migrate`." |
| **Form Request** | "Classe dedicada à validação de dados de um formulário. Separa a responsabilidade do Controller. Se falhar, retorna 422 automaticamente." |
| **Queue / Job** | "Fila de tarefas em segundo plano. Tarefas pesadas (IA, PDF) são enviadas para a fila; o Controller responde instantaneamente ao usuário sem esperar." |
| **WebSocket** | "Canal permanente e bidirecional. O servidor pode enviar dados ao navegador a qualquer momento sem o usuário precisar fazer nova requisição (diferente do HTTP)." |
| **CORS** | "Política do navegador que bloqueia requisições entre origens diferentes. Configurado no Laravel para autorizar o Frontend consumir a API." |
| **`updateOrCreate`** | "Busca um registro pela condição e o atualiza. Se não encontrar, cria. Garante idempotência: sem duplicatas ao rodar o scraping múltiplas vezes." |
| **Fetch API / `async/await`** | "`fetch()` é a forma moderna do JavaScript fazer requisições HTTP. `async/await` permite escrever código assíncrono de forma legível, sem callbacks aninhados." |
| **`response()->json()`** | "Método do Laravel que serializa dados PHP para JSON e define o cabeçalho `Content-Type: application/json` automaticamente." |
| **Status HTTP** | "Códigos numéricos que indicam o resultado: `200 OK`, `201 Created`, `401 Unauthorized`, `404 Not Found`, `422 Validation Error`, `500 Server Error`." |
| **Roach PHP** | "Framework de Web Scraping para Laravel inspirado no Scrapy do Python. Organiza a varredura em Spiders (que coletam) e Processors (que tratam e salvam os dados)." |
| **Generator / `yield`** | "Recurso do PHP para emitir valores um por vez sem carregar tudo na memória. O Spider emite um edital por `yield`; o Roach processa e chama o Processor antes de continuar." |
| **Browsershot** | "Biblioteca PHP que controla um Chrome real (via Puppeteer/Node.js) para acessar sites que usam JavaScript pesado ou que exigem sessão autenticada — impossível de raspar com HTTP simples." |
| **DomCrawler** | "Componente do Symfony que permite navegar no HTML de uma resposta usando seletores CSS (`.upk-title a`), similar ao jQuery. Usado nos Spiders de sites simples (FAPESC, FAPPR)." |
| **Idempotência** | "Propriedade que garante que executar a mesma operação N vezes produz o mesmo resultado. No scraping, `updateOrCreate` garante que rodar o Spider 10 vezes não cria 10 duplicatas." |
| **`external_id`** | "Chave única que identifica cada edital sem depender do auto-increment do banco. Pode ser o UUID do Liferay (FINEP) ou um `md5(título+link)` determinístico (FAPESC, FAPPR)." |

---

## 14. Web Scraping — Motor de Mineração de Editais

> 💡 **O que é Web Scraping?** É o processo de acessar sites automaticamente, ler o HTML (ou a API interna) e extrair dados estruturados. No nosso projeto, o scraping é o motor que alimenta a base de editais sem intervenção manual.

### 14.1 Arquitetura do Roach PHP

O **Roach PHP** é o framework de scraping do projeto. Ele organiza o processo em 3 peças:

```
┌──────────────────────────────────────────────────────────────┐
│                MOTOR DE SCRAPING (Roach PHP)                  │
│                                                              │
│  1. SPIDER                  2. ITEM               3. PROCESSOR │
│  (coleta os dados)  ──yield──> (pacote de dados) ──> (salva no BD) │
│                                                              │
│  app/Spiders/               $this->item([...])    Processors/  │
│  FinepSpider.php                                  SalvarNoBanco│
│  FapeScSpider.php                                 Processor.php│
│  FapprSpider.php                                              │
└──────────────────────────────────────────────────────────────┘
```

**Fluxo completo:**
1. O Artisan command dispara o Spider.
2. O Spider acessa a URL de entrada (`$startUrls`).
3. O método `parse()` extrai os dados e faz `yield $this->item([...])`.
4. O `yield` entrega o item ao `SalvarNoBancoProcessor`.
5. O Processor chama `Edital::updateOrCreate(...)` para salvar sem duplicar.
6. O ciclo repete para cada edital encontrado.

---

### 14.2 Dois Padrões de Spider (você vai implementar novos)

Existem dois tipos de portais que encontramos. Cada um tem uma abordagem diferente:

| Tipo de Portal | Exemplo | Estratégia |
| --- | --- | --- |
| **HTML estático / WordPress** | FAPESC, FAPPR | `DomCrawler` com seletores CSS |
| **SPA / Portal com API interna protegida** | FINEP (Liferay) | `Browsershot` + Chrome headless + `fetch()` no JS |

---

### 14.3 Padrão 1 — Spider HTML com DomCrawler (FAPESC)

> 💡 **Use este padrão quando:** O site renderiza o conteúdo diretamente no HTML. Você consegue ver os editais ao fazer "Ver código-fonte" no navegador (Ctrl+U).

**Arquivo:** `app/Spiders/FapeScSpider.php`

```php
<?php

namespace App\Spiders;

use RoachPHP\Http\Response;
use RoachPHP\Spider\BasicSpider;
use Symfony\Component\DomCrawler\Crawler; // Permite navegar no HTML com seletores CSS

class FapeScSpider extends BasicSpider
{
    // 1. URL de entrada: o Roach começa por aqui
    public array $startUrls = [
        'https://fapesc.sc.gov.br/chamadas-abertas/'
    ];

    // 2. Concorrência 1 = uma requisição por vez (não sobrecarrega o servidor do portal)
    public int $concurrency = 1;

    // 3. Destino dos dados: após cada yield, os dados vão para este Processor
    public array $itemProcessors = [
        \App\Spiders\Processors\SalvarNoBancoProcessor::class,
    ];

    /**
     * 4. Método principal — chamado automaticamente pelo Roach
     *    com o HTML da $startUrl já baixado.
     *    É um Generator: usa yield para emitir um edital por vez.
     */
    public function parse(Response $response): \Generator
    {
        // A FAPESC usa WordPress com o plugin "Ultimate Post Kit".
        // Os editais ficam em cards com as classes .upk-list-wrap e .upk-item.
        // filter() seleciona TODOS os nós que casam com o seletor CSS.
        $cards = $response->filter('.upk-list-wrap .upk-item');

        dump("FAPESC: Encontrados " . $cards->count() . " editais.");

        // Itera sobre cada card de edital
        foreach ($cards as $node) {
            // $node é um DOMElement puro — precisamos criar um novo Crawler em cima dele
            // para usar ->filter() restrito a este card (evita pegar dados de outros cards)
            $card = new Crawler($node);

            // Pula se o seletor não existir (proteção contra mudança de layout)
            if (!$card->filter('.upk-title a')->count()) {
                continue;
            }

            $titulo = $card->filter('.upk-title a')->text();  // texto visível do link
            $link   = $card->filter('.upk-title a')->attr('href'); // URL do edital
            $data   = $card->filter('.upk-meta')->count()
                ? $card->filter('.upk-meta')->text()
                : date('Y-m-d'); // fallback: hoje

            // Limpa espaços extras, tabs e quebras de linha
            $tituloLimpo = trim(preg_replace('/\s+/', ' ', $titulo));

            // yield emite o item para o Pipeline e PAUSA aqui.
            // O Processor salva no banco antes de o loop continuar.
            yield $this->item([
                // md5(título+link) = hash única e determinística.
                // Mesma combinação título/link → mesmo hash → sem duplicatas.
                'external_id'            => md5($tituloLimpo . $link),
                'titulo'                 => $tituloLimpo,
                'data_publicacao'        => trim($data),
                'link'                   => $link,
                'fonte'                  => 'FAPESC',
                'objetivo'               => '', // preenchido pelo comando editais:detalhar
                'condicao_financiamento' => '',
                'operacao'               => '',
                'publico'                => '',
            ]);
        }
    }
}
```

---

### 14.4 Padrão 2 — Spider com Browsershot (FINEP/Liferay)

> 💡 **Use este padrão quando:** O site é uma SPA (Single Page Application) — o HTML carregado não contém os editais. Eles aparecem somente após o JavaScript rodar. Portais que usam Liferay, React, Vue ou Angular precisam deste padrão.

**Como funciona internamente:**
1. O `Browsershot` abre o Chrome **de verdade** na URL do portal.
2. O Chrome executa o JavaScript do site normalmente (cookies, sessão, tudo).
3. Nosso script JS é **injetado dentro do Chrome** e faz `fetch()` para a API interna do portal — aproveitando a sessão já estabelecida.
4. O resultado (JSON) é serializado e devolvido para o PHP.

**Arquivo:** `app/Spiders/FinepSpider.php`

```php
<?php

namespace App\Spiders;

use App\Traits\LimpaTextoTrait;
use Generator;
use RoachPHP\Http\Response;
use RoachPHP\Spider\BasicSpider;
use Spatie\Browsershot\Browsershot; // Controla o Chrome via Puppeteer/Node.js

class FinepSpider extends BasicSpider
{
    use LimpaTextoTrait; // Trait de limpeza de texto do projeto

    public array $startUrls = ['https://www.finep.gov.br/oportunidades'];
    public int   $concurrency = 1; // NUNCA aumentar: Chrome consome muita memória

    public array $itemProcessors = [
        \App\Spiders\Processors\SalvarNoBancoProcessor::class,
    ];

    public function parse(Response $response): Generator
    {
        $url = (string) $response->getUri(); // URL atual (usada pelo Browsershot para autenticar)

        dump("FINEP: Abrindo Chrome e consultando API interna Liferay...");

        try {
            /*
             * Script JavaScript executado DENTRO do Chrome.
             * Usa a API REST interna do Liferay (rota relativa: /o/c/chamadapublicas).
             * Retorna uma Promise porque fetch() é assíncrono.
             */
            $script = "
                new Promise(async (resolve, reject) => {
                    try {
                        const PAGE_SIZE = 250;          // Máx de itens por página
                        const API_BASE  = '/o/c/chamadapublicas';
                        const SORT      = 'sort=dataDePublicacao:desc';

                        // ── Passo 1: busca a 1ª página para saber o total ──
                        const primeiraResp = await fetch(
                            API_BASE + '?' + SORT + '&search=&page=1&pageSize=' + PAGE_SIZE,
                            { headers: { 'Accept': 'application/json' } }
                        );

                        if (!primeiraResp.ok) { reject('HTTP ' + primeiraResp.status); return; }

                        const primeiroJson = await primeiraResp.json();
                        const lastPage     = primeiroJson.lastPage || 1;
                        let   todosItens   = primeiroJson.items    || [];

                        // ── Passo 2: busca as páginas restantes ──
                        for (let pagina = 2; pagina <= lastPage; pagina++) {
                            const resp = await fetch(
                                API_BASE + '?' + SORT + '&search=&page=' + pagina + '&pageSize=' + PAGE_SIZE,
                                { headers: { 'Accept': 'application/json' } }
                            );
                            if (!resp.ok) { continue; } // pula páginas com erro
                            const dados = await resp.json();
                            todosItens = todosItens.concat(dados.items || []);
                        }

                        resolve(JSON.stringify(todosItens)); // retorna JSON para o PHP

                    } catch (err) { reject(err.toString()); }
                });
            ";

            // Abre o Chrome, aguarda a rede ficar ociosa e executa o script JS.
            // O resultado do resolve() volta como string PHP ($jsonString).
            $jsonString = Browsershot::url($url)
                ->setNodeBinary('C:/nodejs/node.exe')
                ->setNpmBinary('C:/nodejs/npm.cmd')
                ->setChromePath('C:/Program Files/Google/Chrome/Application/chrome.exe')
                ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu'])
                ->setOption('protocolTimeout', 600000) // 10 minutos (API pode ser lenta)
                ->timeout(600)                         // 10 minutos total
                ->waitUntilNetworkIdle()               // espera a sessão carregar
                ->evaluate($script);                   // injeta e executa o JS

            $itens = json_decode($jsonString, true);

            if (!is_array($itens) || empty($itens)) {
                dump("AVISO: API retornou vazio. Resposta bruta:", $jsonString);
                return;
            }

            dump("FINEP: " . count($itens) . " editais encontrados.");

            foreach ($itens as $item) {
                // O UUID nativo do Liferay é o external_id (mais confiável que md5)
                $externalId = $item['externalReferenceCode'] ?? '';
                $titulo     = $this->limpaTexto($item['titulo'] ?? 'Sem Título');

                // Monta a URL do edital via âncora (FINEP usa SPA com hash routing)
                $slug = \Illuminate\Support\Str::slug($item['titulo'] ?? '', '-');
                $link = 'https://www.finep.gov.br/financiamento-via-credito#' . $slug;

                // Público pode ser array de objetos [{key, name}]
                $publicoAlvo = $item['publicoAlvo'] ?? [];
                $publico = is_array($publicoAlvo) && !empty($publicoAlvo)
                    ? $this->limpaTexto(implode(', ', array_filter(array_column($publicoAlvo, 'name'))))
                    : 'Não especificado';

                yield $this->item([
                    'external_id'            => $externalId,
                    'titulo'                 => $titulo,
                    'data_publicacao'        => isset($item['dataDePublicacao'])
                        ? date('Y-m-d', strtotime($item['dataDePublicacao']))
                        : date('Y-m-d'),
                    'link'                   => $link,
                    'fonte'                  => 'FINEP',
                    'objetivo'               => $this->limpaTexto($item['descricaoRawText'] ?? ''),
                    'condicao_financiamento' => $this->limpaTexto($item['tipoCooperacao']['key'] ?? ''),
                    'operacao'               => $this->limpaTexto($item['tipoDeOportunidade']['name'] ?? ''),
                    'publico'                => $publico,
                ]);
            }

        } catch (\Throwable $e) {
            // \Throwable captura Exception E erros fatais do PHP
            dump("ERRO FinepSpider: " . $e->getMessage());
            dump($e->getTraceAsString());
        }
    }
}
```

---

### 14.5 O Processor — Onde os Dados São Salvos

> 💡 **Por que separar o Processor do Spider?** Porque a mesma lógica de salvar no banco serve para TODOS os Spiders (FINEP, FAPESC, FAPPR). Em vez de repetir o código em cada Spider, todos apontam para o mesmo Processor.

**Arquivo:** `app/Spiders/Processors/SalvarNoBancoProcessor.php`

```php
<?php

namespace App\Spiders\Processors;

use App\Models\Edital;
use RoachPHP\ItemPipeline\ItemInterface;
use RoachPHP\ItemPipeline\Processors\ItemProcessorInterface;

class SalvarNoBancoProcessor implements ItemProcessorInterface
{
    // Obrigatório pela interface. Deixe vazio se não precisar de configurações.
    public function configure(array $options): void {}

    /**
     * Chamado automaticamente pelo Roach para cada item emitido pelos Spiders.
     * DEVE retornar o $item (mesmo sem modificar) para não quebrar o pipeline.
     */
    public function processItem(ItemInterface $item): ItemInterface
    {
        // Converte o objeto Item em array PHP simples
        $dados = $item->all();

        /*
         * updateOrCreate — a peça-chave da IDEMPOTÊNCIA:
         *
         * 1º argumento: condição de BUSCA
         *   → "Existe um edital com este external_id?"
         *
         * 2º argumento: dados a CRIAR (se não existe) ou ATUALIZAR (se existe)
         *
         * Resultado: rodar o Spider 10 vezes NÃO cria 10 cópias.
         * Se o edital existe → atualiza. Se não → cria. Sempre 1 registro por edital.
         */
        Edital::updateOrCreate(
            ['external_id' => $dados['external_id']],
            [
                'titulo'                 => $dados['titulo'],
                'link'                   => $dados['link'],
                'objetivo'               => $dados['objetivo']               ?? null,
                'data_publicacao'        => $dados['data_publicacao']        ?? null,
                'condicao_financiamento' => $dados['condicao_financiamento'] ?? null,
                'operacao'               => $dados['operacao']               ?? null,
                'publico'                => $dados['publico']                ?? null,
                'fonte'                  => $dados['fonte'],
            ]
        );

        return $item; // Obrigatório: retorna o item para que outros Processors possam processar
    }
}
```

---

### 14.6 Como Adicionar um Novo Spider (Checklist)

Quando precisar raspar um novo portal, siga estes passos:

1. **Inspecione o portal** no navegador:
   - Ctrl+U → Vê o edital no HTML? → Use **Padrão 1** (DomCrawler)
   - Ctrl+U → Não vê nada / precisa de JS? → Use **Padrão 2** (Browsershot)

2. **Crie o arquivo** `app/Spiders/NomeDoOrgaoSpider.php`

3. **Defina os 3 campos obrigatórios:**
   ```php
   public array $startUrls   = ['https://url-do-portal.gov.br/editais'];
   public int   $concurrency = 1;
   public array $itemProcessors = [SalvarNoBancoProcessor::class];
   ```

4. **Implemente o `parse()`:** extraia título, link, data e `external_id`.

5. **Registre o Spider no comando Artisan** (ver seção abaixo).

6. **Teste isolado** antes de registrar:
   ```bash
   php artisan roach:run "App\Spiders\NomeDoOrgaoSpider"
   ```

---

### 14.7 Comando Artisan de Varredura

> **Arquivo:** `app/Console/Commands/VarrerEditaisCommand.php`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RoachPHP\Roach;

class VarrerEditaisCommand extends Command
{
    // Nome e assinatura do comando (roda com: php artisan editais:varrer)
    protected $signature = 'editais:varrer
                            {--spider= : Roda apenas um Spider específico (ex: finep, fapesc, fappr)}
                            {--all    : Roda todos os Spiders em sequência}';

    protected $description = 'Dispara os Spiders do Roach PHP para coletar editais dos portais configurados.';

    // Mapa de spiders disponíveis: apelido => classe
    protected array $spiders = [
        'finep'  => \App\Spiders\FinepSpider::class,
        'fapesc' => \App\Spiders\FapeScSpider::class,
        'fappr'  => \App\Spiders\FapprSpider::class,
        // ↑ Adicione novos spiders aqui
    ];

    public function handle(): void
    {
        if ($this->option('all')) {
            $this->rodarTodos();
            return;
        }

        $apelido = $this->option('spider');

        if (!$apelido) {
            // Menu interativo: lista os spiders disponíveis
            $apelido = $this->choice('Qual Spider deseja rodar?', array_keys($this->spiders));
        }

        if (!isset($this->spiders[$apelido])) {
            $this->error("Spider '{$apelido}' não encontrado.");
            return;
        }

        $this->rodarSpider($apelido, $this->spiders[$apelido]);
    }

    protected function rodarTodos(): void
    {
        $this->info('Iniciando varredura completa em ' . count($this->spiders) . ' portais...');
        foreach ($this->spiders as $apelido => $classe) {
            $this->rodarSpider($apelido, $classe);
        }
        $this->info('✅ Varredura completa finalizada!');
    }

    protected function rodarSpider(string $apelido, string $classe): void
    {
        $this->info("🕷️  Iniciando Spider: {$apelido} ({$classe})");
        $inicio = now();

        Roach::startSpider($classe); // dispara o Spider de forma síncrona

        $tempo = now()->diffInSeconds($inicio);
        $this->info("✅ {$apelido} finalizado em {$tempo}s");
    }
}
```

**Registrar o comando em `app/Console/Kernel.php` (ou Bootstrap/Console):**
```php
protected $commands = [
    \App\Console\Commands\VarrerEditaisCommand::class,
];
```

**Formas de executar:**
```bash
# Menu interativo
php artisan editais:varrer

# Spider específico direto
php artisan editais:varrer --spider=finep
php artisan editais:varrer --spider=fapesc

# Todos em sequência
php artisan editais:varrer --all

# Agendar varredura diária via Scheduler (em app/Console/Kernel.php):
$schedule->command('editais:varrer --all')->dailyAt('03:00');
```

---

## 15. Glossário Técnico para a Banca
