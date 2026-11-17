# ADR 004: Interface Reativa do Kanban em Vanilla JS com Gestão de Estado Local e Drag-and-Drop Otimizado

## Status
Aceito

## Contexto (Jira EDTAN-91 / EDTAN-41)
O frontend da plataforma **Anjo Nexus** foi concebido sob uma diretriz arquitetural desacoplada, leve e sem ferramentas pesadas de compilação (*Zero-Build*, conforme estabelecido nas ADRs 001 e 010 do Ademar - Arquitetura Separada e Vanilla JS).

No entanto, construir um quadro Kanban profissional utilizando exclusivamente **HTML5, CSS moderno e JavaScript Puro (Vanilla JS)** impôs desafios críticos de ergonomia, controle de eventos e integridade visual:
1. **Conflito entre Eventos de Arrastar e Clicar:** Cada cartão do Kanban precisa suportar tanto a ação de arrastar para reordenação/troca de coluna quanto o clique com o mouse para abrir o modal de detalhes do edital e geração de minutas (`abrirModalDetalhesEdital`). Nos navegadores, o ato de soltar o botão do mouse ao término de um arrasto invariavelmente dispara um evento sintético de `click`, abrindo o modal involuntariamente na frente do usuário.
2. **Latência Percebida e Fluidez de Interface:** Se a interface aguardar a resposta da chamada HTTP (`PATCH /api/kanban/cards/{id}/mover`) para reposicionar visualmente o cartão, o usuário sentirá uma lentidão incômoda de 150ms a 400ms. A movimentação física precisa ser instantânea.
3. **Resiliência e Reversão de Falhas (*Rollback*):** Caso a chamada assíncrona ao servidor falhe por instabilidade de rede ou erro HTTP 500, a interface não pode manter o cartão na coluna de destino, sob pena de gerar uma ilusão de estado incompatível com o banco de dados.

### Alternativas Descartadas:
- **Importação de Bibliotecas Externas de Drag-and-Drop (SortableJS, Dragula via CDN):** Descartada para manter a independência de pacotes de terceiros, evitar vulnerabilidades na cadeia de suprimentos e aderir à arquitetura pura da Fase 1.
- **Recarregamento Completo do Quadro a cada Movimento (`fetchBoard()` síncrono):** Descartado por gerar piscamento de tela (*screen flicker*), perda de foco do usuário e reset desagradável da posição do scroll vertical.

---

## Decisão

Implementou-se no front-end (`front/paginas/kanban.html`) um mecanismo reativo nativo estruturado em três técnicas de engenharia de software:

```
                  Ação do Usuário (Drop)
                            │
                            ▼
     ┌──────────────────────────────────────────────┐
     │  1. Movimentação Imediata no DOM (0ms)       │ <--- Optimistic UI
     │     - Insere no container de destino         │
     │     - Atualiza contadores numéricos de coluna│
     └──────────────────────┬───────────────────────┘
                            │
                            ▼
     ┌──────────────────────────────────────────────┐
     │  2. Chamada Assíncrona via Fetch API         │
     │     PATCH /api/kanban/cards/{id}/mover       │
     └──────────────┬───────────────────────────────┘
                    │
         ┌──────────┴──────────┐
         ▼                     ▼
     [Sucesso 200]         [Erro / Falha]
         │                     │
         ▼                     ▼
Sincroniza nova posição   Executa Rollback Seguro
no dataset do elemento    (Invoca fetchBoard() e restaura)
```

### 1. Desacoplamento Estrito entre Arrastar e Clicar com Flag Temporal
Para eliminar a abertura indevida de modais ao soltar o cartão, implementou-se uma flag booleana `wasDragged` com liberação atrasada (*debounce* de 100ms) no evento `dragend`:
```javascript
function initCardDragEvents(cardEl, cardObj) {
    let wasDragged = false;

    cardEl.addEventListener('dragstart', () => {
        wasDragged = true;
        cardEl.classList.add('dragging');
    });

    cardEl.addEventListener('dragend', () => {
        cardEl.classList.remove('dragging');
        updateCounters();
        // Delay para impedir que o 'click' subsequente seja processado
        setTimeout(() => { wasDragged = false; }, 100);
    });

    cardEl.addEventListener('click', (e) => {
        if (wasDragged) return; // Ignora se o evento originou-se de um arrasto
        if (e.target.closest('.btn-card-del')) return;

        const cardId = parseInt(cardEl.dataset.cardId);
        const targetCard = cardObj || todosCardsKanban.find(c => c.id === cardId);
        if (targetCard) {
            abrirModalDetalhesEdital(targetCard);
        }
    });
}
```

### 2. Posicionamento Dinâmico em Tempo Real (`getDragAfterElement`)
Durante o evento `dragover`, calcula-se a posição exata de inserção no DOM comparando a coordenada vertical do cursor (`e.clientY`) com o ponto médio vertical (`offset`) dos cartões irmãos:
```javascript
function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.kanban-card:not(.dragging)')];

    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        } else {
            return closest;
        }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}
```

### 3. Padrão de Atualização Otimista com Rollback (*Optimistic UI*)
O nó HTML é inserido fisicamente na coluna de destino antes da resposta da API. Em caso de sucesso, o novo índice fracionário retornado é persistido no atributo `dataset.position`. Em caso de falha de rede ou validação, a função `fetchBoard()` é acionada, restaurando a verdade do banco de dados na tela de forma automática:
```javascript
colBody.addEventListener('drop', async () => {
    // ... move elemento no DOM ...
    try {
        const res = await fetch(`${API_BASE}/kanban/cards/${cardId}/mover`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ target_column_id: targetColId, prev_position: prevPos, next_position: nextPos })
        });

        if (res.ok) {
            const json = await res.json();
            dragging.dataset.position = json.data.position;
        } else {
            fetchBoard(); // Reverte o estado visual em caso de erro HTTP
        }
    } catch (err) {
        fetchBoard(); // Reverte o estado visual em caso de perda de conexão
    }
});
```

---

## Justificativa Acadêmica (TCC / Banca Avaliadora)

- **Padrão de Interface Otimista (*Optimistic UI Pattern*):** Reduz a latência de percepção do usuário para **$0\text{ms}$**, entregando uma experiência ágil semelhante a aplicações desktop, atendendo rigorosamente ao Requisito Não-Funcional de Usabilidade e Eficiência.
- **Princípio da Tolerância a Falhas e Consistência Eventual:** O mecanismo de reversão via *Rollback* garante que o estado da interface nunca permaneça em descompasso com o estado de persistência do backend.
- **Engenharia de Software Zero-Build:** Prova de conceito de que componentes complexos e ricos em interação podem ser construídos com alta performance sem o peso de frameworks reativos ou dependências voláteis de NPM.

---

## Consequências

### Positivas:
- **Experiência de Uso Fluida:** Transições instantâneas entre colunas e feedback visual imediato ao usuário.
- **Eliminação de Falsos Disparos:** Fim absoluto da abertura indesejada de modais após o término do arrasto.
- **Código Enxuto:** Manutenção direta no arquivo estático sem necessidade de passos de build ou transpilação.

### Negativas / Limitações Futuras:
- A HTML5 Drag and Drop API é nativamente projetada para mouses (desktop). Para telas de toque em dispositivos móveis, a plataforma adota controles táteis adaptativos e botões direcionais integrados, detalhados na ADR 017 do Ademar (Resiliência Touch e Ergonomia Mobile).
