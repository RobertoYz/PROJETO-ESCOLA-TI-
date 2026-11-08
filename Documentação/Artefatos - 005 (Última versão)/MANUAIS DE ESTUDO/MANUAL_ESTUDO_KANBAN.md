# 📘 Manual de Estudo de Código: Módulo Pipeline Kanban B2B
> **Nível:** Guia Didático para Estagiários e Desenvolvedores Júnior  
> **Módulo:** Gestão Visual de Editais e Funil de Propostas (EDTAN-91 / ADRs 001 a 004 do Pedro)  
> **Tecnologias:** PHP 8.2, Laravel 11 (Eloquent, Transações, Eventos de Domínio), MySQL, HTML5 Drag & Drop, Vanilla JavaScript puro.

---

## 🎯 1. O que é este módulo e para que serve? (Visão Geral de Negócio)

Imagine que você trabalha em uma consultoria de inovação que atende 50 empresas e startups. Todos os meses saem dezenas de editais públicos (governo dando dinheiro a fundo perdido ou crédito barato via FINEP, FAPESC, etc.).

Como a consultoria não se perde no meio de tantos editais e prazos?  
Ela usa um **Pipeline Kanban** (um quadro no estilo Trello/Jira com colunas: *Backlog*, *A fazer*, *Em elaboração*, *Bloqueado*, *Pronto para Submissão* e *Submetido*).

Neste módulo, o consultor:
1. Enxerga os editais organizados em colunas.
2. Filtra os cartões pela startup que ele está atendendo no momento.
3. Clica e arrasta um edital de uma coluna para a outra (ex: tirar de *A fazer* e soltar em *Em elaboração*).
4. Ao soltar o cartão em *Em elaboração*, o sistema dispara um evento automático para avisar o motor de Inteligência Artificial para começar a rascunhar o projeto técnico (a minuta da proposta).
5. O consultor pode clicar no cartão para ver os detalhes do edital, anexar documentos (até 10MB) e salvar notas.

---

## 🗺️ 2. Como as peças se conversam? (O Caminho da Requisição)

Quando o usuário arrasta um cartão na tela, veja o caminho que a informação faz:

```
[ Navegador do Usuário (Vanilla JS) ]
   │
   │ 1. O usuário solta o cartão (evento 'drop').
   │    O JavaScript move o elemento HTML na hora (Optimistic UI).
   │    Calcula as posições dos vizinhos de cima e de baixo.
   │
   ▼
[ Requisição HTTP: PATCH /api/kanban/cards/{id}/mover ]
   │
   ▼
[ KanbanController.php (Controlador) ]
   │ 2. Valida se os dados enviados existem e são números válidos.
   │    Chama o serviço de negócios (KanbanService).
   │
   ▼
[ KanbanService.php -> CardMovementService.php ]
   │ 3. Aplica a matemática do Fractional Indexing (calcula o ponto médio).
   │    Salva no banco de dados com uma única query atômica O(1).
   │    Se a coluna for "Em elaboração", dispara o evento CardMovedToProposal.
   │
   ▼
[ Banco de Dados MySQL (InnoDB) ]
   │ 4. Atualiza apenas a linha do cartão afetado na tabela kanban_cards.
   │
   ▼
[ Resposta JSON 200 OK ] ──> O navegador recebe a confirmação e finaliza.
```

---

## 🗄️ 3. Banco de Dados e Migrations (Comentado Linha por Linha)

Para o Kanban funcionar, precisamos de 3 tabelas interligadas em cascata:
1. `kanban_boards` (o quadro da agência)
2. `kanban_columns` (as colunas do quadro)
3. `kanban_cards` (os cartões que ficam dentro das colunas)

### 3.1 Migration da Tabela `kanban_boards`
Arquivo: `database/migrations/2026_09_20_054026_create_kanban_boards_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Retorna uma classe anônima de migração do Laravel
return new class extends Migration
{
    /**
     * O método 'up' é executado quando rodamos: php artisan migrate
     * Ele é responsável por criar a tabela no banco de dados.
     */
    public function up(): void
    {
        Schema::create('kanban_boards', function (Blueprint $table) {
            // Cria a chave primária auto-incremento (id: 1, 2, 3...)
            $table->id();

            // Chave estrangeira ligando o quadro à agência/consultoria proprietária.
            // ->nullable(): permite criar um quadro geral se não houver agência vinculada.
            // ->constrained('agencias'): diz que aponta para a tabela 'agencias'.
            // ->cascadeOnDelete(): se a agência for deletada do sistema, apaga seus quadros automaticamente.
            $table->foreignId('agencia_id')->nullable()->constrained('agencias')->cascadeOnDelete();

            // Nome do quadro (ex: 'Pipeline de Editais', 'Projetos FINEP 2026')
            $table->string('title')->default('Pipeline de Editais');

            // Cria automaticamente duas colunas: created_at e updated_at
            $table->timestamps();
        });
    }

    /**
     * O método 'down' é executado se você der rollback (desfazer a migração).
     */
    public function down(): void
    {
        Schema::dropIfExists('kanban_boards');
    }
};
```

---

### 3.2 Migration da Tabela `kanban_columns`
Arquivo: `database/migrations/2026_09_20_054033_create_kanban_columns_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kanban_columns', function (Blueprint $table) {
            // ID único da coluna
            $table->id();

            // Chave estrangeira apontando para qual quadro (board) esta coluna pertence.
            // Se o quadro for excluído, todas as suas colunas somem juntas (cascadeOnDelete).
            $table->foreignId('board_id')->constrained('kanban_boards')->cascadeOnDelete();

            // Título visível na tela (ex: 'Backlog', 'Em elaboração', 'Submetido')
            $table->string('title');

            // Posição numérica da coluna na tela para podermos ordenar horizontalmente da esquerda para a direita.
            // Usamos 'double' para poder colocar colunas entre outras no futuro se quisermos.
            $table->double('position')->default(1.0);

            // Um identificador de texto em minúsculas sem espaços (slug) para o código identificar a etapa
            // Ex: 'backlog', 'doing', 'review', 'done'
            $table->string('slug')->nullable();

            // Carimbos de data/hora (created_at e updated_at)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_columns');
    }
};
```

---

### 3.3 Migration da Tabela `kanban_cards`
Arquivo: `database/migrations/2026_09_20_054039_create_kanban_cards_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kanban_cards', function (Blueprint $table) {
            // ID único do cartão
            $table->id();

            // Vincula o cartão ao quadro onde ele existe
            $table->foreignId('board_id')->constrained('kanban_boards')->cascadeOnDelete();

            // Vincula o cartão à coluna atual onde ele está pousado (ex: coluna 'Em elaboração')
            $table->foreignId('column_id')->constrained('kanban_columns')->cascadeOnDelete();

            // Vincula o cartão ao edital público de onde vieram as informações.
            // ATENÇÃO DE ESTAGIÁRIO: Repare no ->nullOnDelete()!
            // Por que NÃO usamos cascadeOnDelete aqui?
            // Porque se alguém apagar o edital original raspado da web, NÃO queremos perder
            // o cartão de trabalho da consultoria! O edital_id fica NULL, mas o cartão continua salvo.
            $table->foreignId('edital_id')->nullable()->constrained('editals')->nullOnDelete();

            // Título do cartão (geralmente o nome do edital, ex: "FINEP Mulheres Inovadoras 2026")
            $table->string('title');

            // Descrição ou resumo do objetivo do edital
            $table->text('description')->nullable();

            // Nome da startup cliente que está concorrendo a este edital
            $table->string('startup_name')->nullable();

            // A POSIÇÃO FRACIONÁRIA (Fractional Indexing):
            // Em vez de 1, 2, 3... usamos números flutuantes com gap inicial de 65536.0!
            // Isso permite calcular pontos médios (dividir por 2) centenas de vezes sem travar o banco.
            $table->double('position')->default(65536.0);

            // Campo JSON flexível (Schema-less):
            // Aqui guardamos notas de texto, valor estimado do projeto (R$) e a lista de arquivos anexos
            // sem precisar criar novas tabelas relacionais para cada detalhe menor.
            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_cards');
    }
};
```

---

## 🧩 4. Modelos Eloquent (Comentados Linha por Linha)

Os Modelos são as classes PHP que representam as tabelas e gerenciam as relações de pai e filho.

### 4.1 Modelo `KanbanBoard.php`
Arquivo: `app/Models/KanbanBoard.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KanbanBoard extends Model
{
    // Informa ao Eloquent qual é o nome exato da tabela no MySQL
    protected $table = 'kanban_boards';

    // Lista branca de atributos que podem ser preenchidos em massa via KanbanBoard::create([...])
    protected $fillable = [
        'agencia_id',
        'title'
    ];

    /**
     * RELAÇÃO: Um Quadro pertence a uma Agência (Many-to-One).
     */
    public function agencia(): BelongsTo
    {
        return $this->belongsTo(Agencia::class, 'agencia_id');
    }

    /**
     * RELAÇÃO: Um Quadro tem muitas Colunas (One-to-Many).
     * O ->orderBy('position') garante que, sempre que pedirmos as colunas,
     * o Laravel já as traga ordenadas da esquerda para a direita.
     */
    public function columns(): HasMany
    {
        return $this->hasMany(KanbanColumn::class, 'board_id')->orderBy('position');
    }

    /**
     * RELAÇÃO: Um Quadro tem muitos Cartões (One-to-Many).
     */
    public function cards(): HasMany
    {
        return $this->hasMany(KanbanCard::class, 'board_id')->orderBy('position');
    }
}
```

---

### 4.2 Modelo `KanbanColumn.php`
Arquivo: `app/Models/KanbanColumn.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KanbanColumn extends Model
{
    protected $table = 'kanban_columns';

    protected $fillable = [
        'board_id',
        'title',
        'position',
        'slug'
    ];

    /**
     * Conversão automática de tipos (Casts):
     * Garante que quando o PHP ler o campo 'position' do banco,
     * ele seja convertido em um número float/double puro, e não em uma string de texto.
     */
    protected $casts = [
        'position' => 'double'
    ];

    /**
     * RELAÇÃO: A Coluna pertence a um Quadro pai.
     */
    public function board(): BelongsTo
    {
        return $this->belongsTo(KanbanBoard::class, 'board_id');
    }

    /**
     * RELAÇÃO: A Coluna possui muitos Cartões.
     * Traz os cartões ordenados de cima para baixo pela posição vertical fracionária.
     */
    public function cards(): HasMany
    {
        return $this->hasMany(KanbanCard::class, 'column_id')->orderBy('position');
    }
}
```

---

### 4.3 Modelo `KanbanCard.php`
Arquivo: `app/Models/KanbanCard.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KanbanCard extends Model
{
    protected $table = 'kanban_cards';

    protected $fillable = [
        'board_id',
        'column_id',
        'edital_id',
        'title',
        'description',
        'startup_name',
        'position',
        'metadata'
    ];

    /**
     * Casts importantes:
     * - 'position' vira número decimal (double) para a matemática de Fractional Indexing.
     * - 'metadata' vira array nativo do PHP! Quando você faz $card->metadata['anexos'],
     *   o Laravel automaticamente converte a string JSON do banco em array e vice-versa.
     */
    protected $casts = [
        'position' => 'double',
        'metadata' => 'array'
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(KanbanBoard::class, 'board_id');
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(KanbanColumn::class, 'column_id');
    }

    /**
     * Permite acessar os dados do edital oficial raspado diretamente pelo cartão:
     * Exemplo: $card->edital->deadline ou $card->edital->min_budget
     */
    public function edital(): BelongsTo
    {
        return $this->belongsTo(Edital::class, 'edital_id');
    }
}
```

---

## ⚡ 5. A Camada de Serviços: A Lógica de Negócio

Aqui está onde a engenharia de software brilha. Nós não colocamos regras pesadas dentro do Controller; criamos **Services** dedicados para cada responsabilidade.

### 5.1 O Algoritmo de Fractional Indexing (`CardMovementService.php`)
Arquivo: `app/Services/CardMovementService.php`

> **Problema que ele resolve:** Em um Kanban tradicional, se você tem 100 cartões e move um para o topo, você teria que somar `+1` na posição de todos os outros 99 cartões. Isso trava o banco de dados ($O(N)$ updates).  
> **A Solução:** Usar frações! Se você quer colocar um cartão entre o cartão de posição 10 e o cartão de posição 20, a nova posição dele é a média: $(10 + 20) / 2 = 15$. **Apenas 1 registro é atualizado no banco ($O(1)$)!**

```php
<?php

namespace App\Services;

use App\Models\KanbanCard;
use App\Models\KanbanColumn;
use App\Events\CardMovedToProposal;

class CardMovementService
{
    /**
     * Move o cartão para a coluna de destino e recalcula sua posição vertical.
     *
     * @param KanbanCard $card          O cartão que está sendo arrastado
     * @param int        $targetColumnId ID da coluna para onde ele foi solto
     * @param float|null $prevPosition   Posição do cartão vizinho que ficou ACIMA dele (ou null se foi pro topo)
     * @param float|null $nextPosition   Posição do cartão vizinho que ficou ABAIXO dele (ou null se foi pra base)
     * @return KanbanCard
     */
    public function move(KanbanCard $card, int $targetColumnId, ?float $prevPosition = null, ?float $nextPosition = null): KanbanCard
    {
        // Guarda a coluna original onde o cartão estava antes de mudar
        $originalColumnId = $card->column_id;

        // 1. Calcula a nova posição fracionária exata usando os vizinhos
        $newPosition = $this->calculateNewPosition($prevPosition, $nextPosition);

        // 2. Atualiza os dados no objeto do cartão
        $card->column_id = $targetColumnId;
        $card->position = $newPosition;

        // 3. Salva no banco de dados.
        // Isso executa um único comando SQL: UPDATE kanban_cards SET column_id = ?, position = ? WHERE id = ?
        $card->save();

        // 4. VERIFICAÇÃO INTELIGENTE DE TRANSIÇÃO DE FLUXO:
        // Buscamos a coluna de destino para inspecionar o título dela
        $targetColumn = KanbanColumn::find($targetColumnId);
        
        // Se mudou de coluna de verdade (não foi só reordenação interna na mesma coluna):
        if ($targetColumn && $originalColumnId !== $targetColumnId) {
            $colTitle = mb_strtolower($targetColumn->title);

            // Se o usuário moveu para uma coluna que fala de "proposta" ou "elaboração":
            if (str_contains($colTitle, 'proposta') || str_contains($colTitle, 'elaboração')) {
                // DISPARA O EVENTO DE DOMÍNIO!
                // O CardMovementService não precisa saber o que a IA vai fazer;
                // ele apenas "grita": "O cartão X entrou em fase de elaboração!".
                event(new CardMovedToProposal($card));
            }
        }

        return $card;
    }

    /**
     * A FÓRMULA MATEMÁTICA DO FRACTIONAL INDEXING:
     * Calcula o ponto médio flutuante.
     */
    private function calculateNewPosition(?float $prevPosition, ?float $nextPosition): float
    {
        // Nosso gap padrão inicial é 65536.0 (2 elevado a 16).
        // Por que 65536? Porque é um número que pode ser dividido por 2 dezenas de vezes antes de chegar perto de zero!
        $step = 65536.0;

        // Caso 1: A coluna estava completamente vazia! O cartão é o primeiro de todos.
        if ($prevPosition === null && $nextPosition === null) {
            return $step; // Fica na posição 65536.0
        }

        // Caso 2: O cartão foi solto no TOPO da coluna (não tem ninguém antes dele).
        // Pegamos a posição do que era o primeiro e dividimos por 2.
        if ($prevPosition === null) {
            return $nextPosition / 2.0;
        }

        // Caso 3: O cartão foi solto na BASE da coluna (não tem ninguém depois dele).
        // Pegamos a posição do último e somamos 65536.0.
        if ($nextPosition === null) {
            return $prevPosition + $step;
        }

        // Caso 4: O cartão foi solto no MEIO entre dois cartões existentes.
        // Calculamos a média aritmética simples entre o de cima e o de baixo!
        return ($prevPosition + $nextPosition) / 2.0;
    }
}
```

---

### 5.2 O Gerenciador de Quadros e Criação (`KanbanService.php`)
Arquivo: `app/Services/KanbanService.php`

```php
<?php

namespace App\Services;

use App\Models\KanbanBoard;
use App\Models\KanbanColumn;
use App\Models\KanbanCard;
use App\Models\Edital;
use Illuminate\Support\Facades\DB;

class KanbanService
{
    // Injeção de dependência: recebe o serviço de movimentação pronto
    protected CardMovementService $movementService;

    public function __construct(CardMovementService $movementService)
    {
        $this->movementService = $movementService;
    }

    /**
     * Busca o quadro da agência. Se o consultor for novo e ainda não tiver quadro,
     * o método cria o quadro e as 6 colunas padrão automaticamente em uma transação atômica.
     */
    public function getOrCreateBoard(?int $agenciaId = null, ?string $startupName = null): KanbanBoard
    {
        $query = KanbanBoard::query();

        // Se informou agência, filtra pelo ID dela (Multi-tenant)
        if ($agenciaId) {
            $query->where('agencia_id', $agenciaId);
        }

        // EAGER LOADING (Carregamento Otimizado para não gerar lentidão N+1):
        // Carrega o quadro -> com suas colunas -> com seus cartões -> com o edital de cada cartão!
        $board = $query->with([
            'columns.cards' => function ($q) use ($startupName) {
                // Se o usuário selecionou uma startup no seletor global do topo da página,
                // filtra apenas os cartões pertencentes àquela startup!
                if (!empty($startupName)) {
                    $q->where(function ($sub) use ($startupName) {
                        $sub->where('startup_name', $startupName)
                            ->orWhere('startup_name', 'like', "%{$startupName}%");
                    });
                }
                // Ordena os cartões verticalmente pela posição fracionária
                $q->orderBy('position');
            },
            'columns.cards.edital'
        ])->first();

        // Se o quadro ainda não existe, cria a estrutura inicial padrão:
        if (!$board) {
            // DB::transaction garante que ou TUDO é criado com sucesso, ou NADA é salvo.
            // Evita criar um quadro pela metade se o servidor cair no meio do loop.
            $board = DB::transaction(function () use ($agenciaId) {
                // 1. Cria o quadro principal
                $newBoard = KanbanBoard::create([
                    'agencia_id' => $agenciaId,
                    'title' => 'Pipeline de Editais'
                ]);

                // 2. Define as 6 colunas do fluxo de fomento com gaps de 10000.0
                $colunasPadrao = [
                    ['title' => 'Backlog', 'slug' => 'backlog', 'position' => 10000.0],
                    ['title' => 'A fazer', 'slug' => 'todo', 'position' => 20000.0],
                    ['title' => 'Em elaboração', 'slug' => 'doing', 'position' => 30000.0],
                    ['title' => 'Bloqueado', 'slug' => 'blocked', 'position' => 40000.0],
                    ['title' => 'Pronto para Submissão', 'slug' => 'review', 'position' => 50000.0],
                    ['title' => 'Submetido', 'slug' => 'done', 'position' => 60000.0],
                ];

                // 3. Cria cada coluna vinculada ao novo quadro
                foreach ($colunasPadrao as $col) {
                    $newBoard->columns()->create($col);
                }

                return $newBoard;
            });

            // Carrega as relações para retornar o objeto completo ao front-end
            $board->load(['columns.cards.edital']);
        }

        return $board;
    }

    /**
     * Cria um novo cartão no quadro (pode ser avulso ou vindo de um edital do Radar).
     */
    public function createCard(array $dados): KanbanCard
    {
        $boardId = $dados['board_id'] ?? null;
        if (!$boardId) {
            $board = $this->getOrCreateBoard($dados['agencia_id'] ?? null);
            $boardId = $board->id;
        }

        // Se não informou coluna, coloca automaticamente na primeira (ex: Backlog)
        $columnId = $dados['column_id'] ?? null;
        if (!$columnId) {
            $firstCol = KanbanColumn::where('board_id', $boardId)->orderBy('position')->first();
            $columnId = $firstCol ? $firstCol->id : null;
        }

        $editalId = $dados['edital_id'] ?? null;
        $title = $dados['title'] ?? null;
        $description = $dados['description'] ?? null;
        $metadata = $dados['metadata'] ?? [];

        // PREENCHIMENTO AUTOMÁTICO INTELIGENTE:
        // Se o cartão está sendo criado a partir de um edital existente,
        // puxa os dados do edital para o cartão se o usuário não digitou manualmente
        if ($editalId) {
            $edital = Edital::find($editalId);
            if ($edital) {
                if (!$title) {
                    $title = $edital->title;
                }
                if (!$description) {
                    $description = $edital->objetivo;
                }
                // Mescla metadados úteis (orçamento máximo, prazo, fonte FINEP/FAPESC)
                $metadata = array_merge([
                    'fonte' => $edital->fonte,
                    'max_budget' => $edital->max_budget,
                    'deadline' => $edital->deadline,
                    'ai_match' => $edital->ai_match,
                    'publico' => $edital->publico,
                ], $metadata);
            }
        }

        // Calcula a posição do novo cartão para entrar no FINAL da coluna:
        $lastCard = KanbanCard::where('column_id', $columnId)->orderByDesc('position')->first();
        $position = $lastCard ? $lastCard->position + 65536.0 : 65536.0;

        // Persiste o cartão no MySQL
        return KanbanCard::create([
            'board_id' => $boardId,
            'column_id' => $columnId,
            'edital_id' => $editalId,
            'title' => $title ?: 'Novo Card',
            'description' => $description,
            'startup_name' => $dados['startup_name'] ?? 'Startup X',
            'position' => $position,
            'metadata' => $metadata
        ]);
    }

    /**
     * Delega a movimentação para o CardMovementService.
     */
    public function moveCard(int $cardId, int $targetColumnId, ?float $prevPosition = null, ?float $nextPosition = null): KanbanCard
    {
        $card = KanbanCard::findOrFail($cardId);
        return $this->movementService->move($card, $targetColumnId, $prevPosition, $nextPosition);
    }

    /**
     * Remove um cartão do banco de dados.
     */
    public function deleteCard(int $cardId): bool
    {
        $card = KanbanCard::findOrFail($cardId);
        return $card->delete();
    }
}
```

---

## 📢 6. O Evento de Domínio (`CardMovedToProposal.php`)
Arquivo: `app/Events/CardMovedToProposal.php`

> **O que é um Evento de Domínio?** É como um alerta de rádio no navio. O timoneiro (o serviço de mover cards) apenas avisa pelo rádio: *"O cartão foi para a proposta!"*. Ele não sabe se quem está ouvindo vai mandar um e-mail, ligar a IA ou gerar um relatório. Isso mantém o código desacoplado e fácil de manter (Princípio Open/Closed).

```php
<?php

namespace App\Events;

use App\Models\KanbanCard;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CardMovedToProposal
{
    // Dispatchable: permite disparar com event(new CardMovedToProposal($card))
    // SerializesModels: permite enviar o modelo com segurança para filas em segundo plano
    use Dispatchable, SerializesModels;

    /**
     * O construtor recebe o cartão afetado e o torna uma propriedade pública.
     */
    public function __construct(public KanbanCard $card)
    {
    }
}
```

---

## 🚪 7. O Controlador HTTP (`KanbanController.php`)
Arquivo: `app/Http/Controllers/KanbanController.php`

O Controller é a porta de entrada da API. Ele recebe a requisição HTTP do navegador, valida se os parâmetros vieram certos e devolve uma resposta em formato JSON padronizado.

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\KanbanService;
use Exception;

class KanbanController extends Controller
{
    protected KanbanService $kanbanService;

    // Recebe o KanbanService pronto via injeção de dependência
    public function __construct(KanbanService $kanbanService)
    {
        $this->kanbanService = $kanbanService;
    }

    /**
     * GET /api/kanban
     * Retorna todo o quadro montado com colunas e cartões em formato JSON.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Obtém parâmetros opcionais da URL: ?agencia_id=1&startup_name=Nexus
            $agenciaId = $request->query('agencia_id');
            $startupName = $request->query('startup_name');

            // Chama o serviço
            $board = $this->kanbanService->getOrCreateBoard(
                $agenciaId ? (int)$agenciaId : null,
                $startupName ? trim($startupName) : null
            );

            // Responde HTTP 200 OK
            return response()->json([
                'status' => 'sucesso',
                'data' => $board
            ]);
        } catch (Exception $e) {
            // Em caso de erro inesperado, responde HTTP 500 sem quebrar a aplicação
            return response()->json([
                'status' => 'erro',
                'mensagem' => 'Erro ao carregar o quadro Kanban.',
                'detalhe' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/kanban/cards
     * Cria um novo cartão no quadro.
     */
    public function storeCard(Request $request): JsonResponse
    {
        // Validação dos dados de entrada
        $dados = $request->validate([
            'board_id' => 'nullable|integer|exists:kanban_boards,id',
            'column_id' => 'nullable|integer|exists:kanban_columns,id',
            'edital_id' => 'nullable|integer|exists:editals,id',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'startup_name' => 'nullable|string|max:255',
            'metadata' => 'nullable|array'
        ]);

        // Regra de validação: tem que ter ou o título ou o ID do edital
        if (empty($dados['title']) && empty($dados['edital_id'])) {
            return response()->json([
                'status' => 'erro',
                'mensagem' => 'O título do card ou o edital_id deve ser informado.'
            ], 422); // HTTP 422: Unprocessable Entity
        }

        try {
            $card = $this->kanbanService->createCard($dados);
            $card->load('edital');

            // Retorna HTTP 201 Created com o cartão recém-criado
            return response()->json([
                'status' => 'sucesso',
                'mensagem' => 'Card criado com sucesso!',
                'data' => $card
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'erro',
                'mensagem' => 'Falha ao criar card no Kanban.',
                'detalhe' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PATCH /api/kanban/cards/{id}/mover
     * O ENDPOINT DO ARRASTAR E SOLTAR (Drag & Drop):
     * Recebe para qual coluna o card foi e as posições dos vizinhos.
     */
    public function moveCard(Request $request, int $id): JsonResponse
    {
        // Validação dos parâmetros numéricos
        $dados = $request->validate([
            'target_column_id' => 'required|integer|exists:kanban_columns,id',
            'prev_position' => 'nullable|numeric',
            'next_position' => 'nullable|numeric'
        ]);

        try {
            // Executa o recálculo via Fractional Indexing
            $card = $this->kanbanService->moveCard(
                $id,
                (int)$dados['target_column_id'],
                isset($dados['prev_position']) ? (float)$dados['prev_position'] : null,
                isset($dados['next_position']) ? (float)$dados['next_position'] : null
            );

            // Responde 200 OK com a nova posição confirmada
            return response()->json([
                'status' => 'sucesso',
                'mensagem' => 'Card movimentado com sucesso!',
                'data' => $card
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'erro',
                'mensagem' => 'Falha ao movimentar card.',
                'detalhe' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /api/kanban/cards/{id}
     * Remove o cartão do quadro.
     */
    public function destroyCard(int $id): JsonResponse
    {
        try {
            $this->kanbanService->deleteCard($id);

            return response()->json([
                'status' => 'sucesso',
                'mensagem' => 'Card removido com sucesso!'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'erro',
                'mensagem' => 'Falha ao remover card.',
                'detalhe' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/kanban/cards/{id}/dados-anexos
     * Permite salvar anotações, valor estimado do projeto e upload de arquivos até 10MB.
     */
    public function updateCard(Request $request, int $id): JsonResponse
    {
        try {
            $card = \App\Models\KanbanCard::findOrFail($id);

            // Validação estrita (inclusive tamanho do arquivo: máx 10240 KB = 10MB)
            $dados = $request->validate([
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'metadata' => 'nullable|array',
                'arquivo' => 'nullable|file|max:10240' // até 10MB
            ]);

            if (isset($dados['title'])) $card->title = $dados['title'];
            if (isset($dados['description'])) $card->description = $dados['description'];

            $metaAtual = $card->metadata ?? [];

            // Se enviou metadados de texto (anotações, valores, etc)
            if (isset($dados['metadata'])) {
                $metaAtual = array_merge($metaAtual, $dados['metadata']);
            }

            // Se enviou um arquivo para anexar:
            if ($request->hasFile('arquivo')) {
                $arquivo = $request->file('arquivo');
                // Salva no disco público do Laravel em storage/app/public/kanban_anexos
                $caminho = $arquivo->store('kanban_anexos', 'public');

                $novoAnexo = [
                    'id' => uniqid(),
                    'nome' => $arquivo->getClientOriginalName(),
                    'caminho' => '/storage/' . $caminho,
                    'tamanho' => $arquivo->getSize(),
                    'enviado_em' => now()->toIso8601String()
                ];

                $anexos = $metaAtual['anexos'] ?? [];
                $anexos[] = $novoAnexo;
                $metaAtual['anexos'] = $anexos;
            }

            $card->metadata = $metaAtual;
            $card->save();

            return response()->json([
                'status' => 'sucesso',
                'mensagem' => 'Card atualizado com sucesso!',
                'data' => $card
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'erro',
                'mensagem' => 'Falha ao atualizar dados do card.',
                'detalhe' => $e->getMessage()
            ], 500);
        }
    }
}
```

---

## 🎨 8. O Frontend em Vanilla JS: Como o Drag & Drop Funciona na Prática
Arquivo: `front/paginas/kanban.html`

O front-end não usa React nem bibliotecas externas pesadas; ele usa a **HTML5 Drag and Drop API** nativa do navegador com duas técnicas essenciais de ergonomia:

### 8.1 Evitando Falsos Cliques com a Flag `wasDragged`
> **O Problema:** Quando o usuário clica e arrasta um cartão e solta o mouse, o navegador acha que ele deu um "clique" e abre a janela de detalhes do edital sem querer.  
> **A Solução:** Usar uma variável booleana `wasDragged` que bloqueia o clique por 100 milissegundos após o término do arrasto!

```javascript
function initCardDragEvents(cardEl, cardObj) {
    let wasDragged = false;

    // Quando o usuário começa a arrastar com o mouse:
    cardEl.addEventListener('dragstart', () => {
        wasDragged = true; // Marca que estamos arrastando!
        cardEl.classList.add('dragging'); // Fica translúcido visualmente
    });

    // Quando o usuário solta o mouse ao terminar o arrasto:
    cardEl.addEventListener('dragend', () => {
        cardEl.classList.remove('dragging');
        updateCounters(); // Atualiza a contagem de cards no topo das colunas

        // Só libera o clique 100ms depois, para não abrir o modal na cara do usuário!
        setTimeout(() => { 
            wasDragged = false; 
        }, 100);
    });

    // Quando o usuário CLICA de verdade no cartão para ver detalhes:
    cardEl.addEventListener('click', (e) => {
        // Se acabou de arrastar, IGNORE o clique!
        if (wasDragged) return;

        // Se clicou no botãozinho de excluir a lixeira, não abre o modal
        if (e.target.closest('.btn-card-del')) return;

        const cardId = parseInt(cardEl.dataset.cardId);
        const targetCard = cardObj || todosCardsKanban.find(c => c.id === cardId);
        if (targetCard) {
            abrirModalDetalhesEdital(targetCard);
        }
    });
}
```

---

### 8.2 Descobrindo Onde Encaixar o Cartão (`getDragAfterElement`)
Enquanto o usuário move o mouse por cima da coluna, precisamos saber exatamente em qual posição vertical soltar o cartão (antes ou depois de qual irmão):

```javascript
function getDragAfterElement(container, y) {
    // Pega todos os cartões da coluna, menos o que está sendo arrastado no momento
    const draggableElements = [...container.querySelectorAll('.kanban-card:not(.dragging)')];

    return draggableElements.reduce((closest, child) => {
        // Pega a caixa geométrica do cartão irmão na tela (coordenadas x, y, altura)
        const box = child.getBoundingClientRect();
        // Calcula a distância entre o cursor do mouse e o meio vertical do cartão irmão
        const offset = y - box.top - box.height / 2;

        // Se o mouse está logo acima do meio deste cartão:
        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        } else {
            return closest;
        }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}
```

---

### 8.3 O Padrão Optimistic UI com Rollback Seguro
> **O que é Optimistic UI?** O JavaScript é "otimista": ele já move o cartão na tela na mesma hora (latência percebida = 0ms!). Só depois ele manda a requisição via internet. Se a internet falhar ou der erro 500 no servidor, ele reverte (*Rollback*) chamando `fetchBoard()`.

```javascript
colBody.addEventListener('drop', async () => {
    colBody.parentElement.classList.remove('drag-over');
    const dragging = document.querySelector('.dragging');
    if (!dragging) return;

    const cardId = dragging.dataset.cardId;
    const targetColId = colBody.dataset.colId;

    // Pega os elementos vizinhos imediatos para saber a posição fracionária
    const prevCard = dragging.previousElementSibling;
    const nextCard = dragging.nextElementSibling;

    const prevPos = prevCard ? parseFloat(prevCard.dataset.position) : null;
    const nextPos = nextCard ? parseFloat(nextCard.dataset.position) : null;

    try {
        // Envia para a API PATCH
        const res = await fetch(`${API_BASE}/kanban/cards/${cardId}/mover`, {
            method: 'PATCH',
            headers: { 
                'Content-Type': 'application/json', 
                'Accept': 'application/json' 
            },
            body: JSON.stringify({
                target_column_id: targetColId,
                prev_position: prevPos,
                next_position: nextPos
            })
        });

        if (res.ok) {
            const json = await res.json();
            // Sucesso! Atualiza o atributo data-position com o número novo calculado pelo servidor
            dragging.dataset.position = json.data.position;
        } else {
            // Deu erro no servidor! Rollback: recarrega o quadro do banco de dados
            console.error('Falha ao mover card no backend');
            fetchBoard(); 
        }
    } catch (err) {
        // Caiu a internet! Rollback também
        console.error('Erro de conexão ao mover card', err);
        fetchBoard(); 
    }
});
```

---

## 💡 9. Perguntas Frequentes de Estagiário (FAQ Rápido)

1. **"Por que não usar um número inteiro simples tipo 1, 2, 3 para a posição?"**  
   *Resposta:* Porque se você mover um cartão para o topo de uma lista de 50 cartões, você teria que somar `+1` na posição de todos os outros 49 cartões. Isso executa 50 updates no banco, trava tabelas e causa lentidão. Com Fractional Indexing, atualizamos **apenas 1 registro ($O(1)$)** calculando a média flutuante.

2. **"O que significa aquele `with(['columns.cards.edital'])`?"**  
   *Resposta:* É o *Eager Loading* do Laravel. Sem ele, para listar 10 cartões e seus editais, o Laravel faria 1 query para o quadro + 1 query para colunas + 10 queries para cartões + 10 queries para editais (o famoso problema $N+1$). Com o `with()`, ele faz apenas 3 queries otimizadas e traz tudo de uma vez.

3. **"Se eu apagar o edital original, o cartão do Kanban é excluído?"**  
   *Resposta:* Não! Graças ao `nullOnDelete()` na migration, o campo `edital_id` vira `NULL`, mas o cartão e os anexos de trabalho da consultoria continuam intactos.
