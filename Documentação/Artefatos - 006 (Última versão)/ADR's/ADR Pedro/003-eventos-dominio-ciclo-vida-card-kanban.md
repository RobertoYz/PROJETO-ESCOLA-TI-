# ADR 003: Arquitetura Orientada a Eventos no Ciclo de Vida do Card (CardMovedToProposal)

## Status
Aceito

## Contexto (Jira EDTAN-91)
No fluxo de submissão e assessoria de editais de inovação, o painel Kanban não atua meramente como um mural visual passivo; a transição de um cartão entre colunas representa uma mudança concreta de estado no ciclo de vida da oportunidade de fomento.

Especificamente, quando um consultor move um cartão da etapa preliminar (*Backlog* ou *A Fazer*) para colunas focadas na estruturação da candidatura (*Em elaboração* ou *Elaboração de Proposta*), diversas ações secundárias de negócio devem ser disparadas:
1. **Engatilhamento do Módulo de IA:** Pré-aquecer o contexto da proposta técnica (modelo oficial de 11 seções, RF-29) conectando os dados cadastrais da startup ao escopo do edital.
2. **Auditoria e Rastreabilidade Gerencial:** Registrar carimbo de data/hora da entrada do edital na esteira ativa de produção técnica.
3. **Gestão de Prazos Críticos:** Alertar a equipe caso a data limite de encerramento do edital (`deadline`) esteja a menos de 15 dias da data corrente.

Se essas chamadas forem executadas diretamente dentro do serviço de movimentação ou no próprio controlador HTTP, a arquitetura sofrerá com acoplamento rígido, lentidão no fechamento da conexão HTTP com o navegador e quebra dos princípios de design de software.

### Alternativas Descartadas:
- **Chamadas Procedurais Encadeadas no Controller:** Inserir a criação de rascunhos, envio de e-mails e logs diretamente no `KanbanController::moveCard()`. Descartado por violar o Princípio da Responsabilidade Única (SRP) e aumentar o tempo de resposta da ação de arrastar do usuário.
- **Varredura Periódica por Agendador de Tarefas (*Cron/Polling*):** Criar um comando agendado a cada 5 minutos procurando cartões que mudaram de coluna. Descartado por introduzir latência perceptível e consumir recursos de CPU e banco de dados de forma redundante.

---

## Decisão

Adotou-se o padrão **Eventos de Domínio (*Domain Events / Publish-Subscribe*)**, nativo do ecossistema Laravel, desacoplando o ato físico de movimentação do cartão da execução de regras de negócio subsequentes.

```
┌─────────────────────────────────┐
│     CardMovementService::move() │
│  (Persiste nova coluna e pos)   │
└────────────────┬────────────────┘
                 │ Dispara evento se coluna == 'proposta' / 'elaboração'
                 ▼
┌─────────────────────────────────┐
│      CardMovedToProposal        │  <--- Evento de Domínio Imutável
└────────┬───────────────┬────────┘
         │               │
         ▼               ▼
┌─────────────────┐ ┌─────────────────┐
│ MinutaListener  │ │ AuditListener   │  <--- Ouvintes Independentes
│ (Prepara minuta)│ │ (Grava trilha)  │
└─────────────────┘ └─────────────────┘
```

### 1. Definição do Evento de Domínio
Criou-se a classe de evento desacoplada `CardMovedToProposal.php` (em `app/Events`), responsável por transportar com segurança o modelo do cartão afetado:
```php
namespace App\Events;

use App\Models\KanbanCard;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CardMovedToProposal
{
    use Dispatchable, SerializesModels;

    public function __construct(public KanbanCard $card)
    {
    }
}
```

### 2. Disparo Condicional Baseado na Semântica da Coluna
No método `move()` da classe `CardMovementService.php`, após efetivar a gravação do registro no banco com Fractional Indexing, avalia-se a transição da coluna de destino:
```php
// Verifica se mudou para a coluna de geração de proposta / elaboração
$targetColumn = KanbanColumn::find($targetColumnId);

if ($targetColumn && $originalColumnId !== $targetColumnId) {
    $colTitle = mb_strtolower($targetColumn->title);
    if (str_contains($colTitle, 'proposta') || str_contains($colTitle, 'elaboração')) {
        // Dispara o evento de domínio caso haja listeners registrados
        event(new CardMovedToProposal($card));
    }
}
```

### 3. Execução Assíncrona via Filas
Os ouvintes (*Listeners*) associados ao evento podem implementar a interface `ShouldQueue`, permitindo que operações pesadas (como integrações com IA ou disparos de notificações) sejam processadas em segundo plano pela esteira assíncrona do Laravel (ADR 002 do Ademar - Processamento Background / Filas), sem impactar os milissegundos de resposta do Kanban.

---

## Justificativa Acadêmica (TCC / Banca Avaliadora)

- **Princípio Aberto/Fechado (OCP - SOLID):** O serviço `CardMovementService` permanece fechado para alterações e aberto para extensões. Novos requisitos de negócio (ex.: integrar webhook com CRM externo) exigem apenas a criação de um novo *Listener*, sem risco de regressão no código de movimentação dos cards.
- **Princípio da Responsabilidade Única (SRP - SOLID):** O serviço de movimentação preocupa-se exclusivamente com a integridade posicional e referencial do cartão no quadro.
- **Padrão Observer / Publish-Subscribe (GoF):** Promove baixo acoplamento (*low coupling*) e alta coesão (*high cohesion*) entre os módulos de workflow e inteligência artificial da plataforma.

---

## Consequências

### Positivas:
- **Resposta Instantânea para o Usuário:** A requisição HTTP do usuário não fica travada aguardando o processamento de regras secundárias.
- **Testabilidade Aprimorada:** Facilidade para escrever testes automatizados utilizando `Event::fake([CardMovedToProposal::class])`, validando se o evento foi despachado sob as condições corretas sem depender de serviços externos.
- **Arquitetura Pronta para Evolução:** Permite conectar múltiplos consumidores de evento conforme novas fases do produto forem desenvolvidas.

### Negativas / Limitações Futuras:
- Aumenta a necessidade de documentação e observabilidade, pois o fluxo de execução torna-se indireto, exigindo que os desenvolvedores consultem os mapeamentos de eventos no `EventServiceProvider`.
