# ADR 017: Resiliência Touch e Ergonomia Mobile no Pipeline Kanban e Radar

## Status
Aceito

## Contexto (Jira EDTAN-210)
Com o empacotamento híbrido mobile via Capacitor 7 (ADR 014), a aplicação passou a ser utilizada intensamente em smartphones Android de telas pequenas (360px a 412px de largura). Durante os testes de usabilidade em campo, foram identificados impedimentos graves na experiência do usuário:
1. **Incompatibilidade de Drag-and-Drop em Telas Touch:** A especificação nativa HTML5 Drag and Drop (`dragstart`, `dragover`, `drop`) é desenhada para ponteiros de mouse e não responde a toques na tela sensível (*touch events*), impossibilitando o usuário móvel de transicionar cards entre as colunas do Kanban.
2. **Transbordamento Horizontal em Modais:** O modal de minuta técnica e diagnóstico de aderência possuía restrições de largura fixa (`min-width: 250px` em grids duplos), causando corte da interface e necessidade de rolagem horizontal desajeitada.
3. **Comportamento de Salto de Rolagem no Radar:** Ao clicar para abrir detalhes de um edital no smartphone, a tela realizava um salto (*jump*) brusco para o rodapé da página. Ao fechar, voltava ao topo, desorientando o consultor.

---

## Decisão

### 1. Botões de Navegação Direcional Touch nos Cards do Kanban
Implementamos controles touch dedicados dentro de cada card móvel:
- Botões de toque rápido: `◀ Anterior` e `Avançar ▶`.
- O clique no botão identifica a coluna adjacente permitida e dispara diretamente a requisição `PATCH /api/kanban/cards/{id}/mover`.
- Isso garante **100% de acessibilidade motora** em smartphones, sem dependência de bibliotecas de touch polyfill pesadas.

### 2. Viewport Estrito para Modais em Telas Pequenas
No arquivo `mobile-adaptive.css`, estabelecemos:
- A largura de modais e painéis em dispositivos móveis passa a obedecer rigorosamente a `calc(100vw - 16px)` com `max-width: 100vw`.
- As grades duplas e triplas foram convertidas para `grid-template-columns: 1fr`, forçando empilhamento vertical ergonômico.
- Inputs de formulários compostos (CEP, Cidade, UF, Data, Porte) agora fluem verticalmente sem estrangulamento de layout.

### 3. Painel do Radar como Modal Direto em Viewport Fixo
O painel de detalhes do edital em telas menores foi refatorado para operar como um sheet modal em camada fixa (`position: fixed; inset: 0; z-index: 1000`).
- Ao tocar no edital, os detalhes se abrem diretamente na frente do usuário, eliminando a rolagem para o final da página.
- O botão "Voltar para lista de editais" fecha o modal preservando a exata posição de scroll em que o usuário se encontrava na listagem.

---

## Justificativa Acadêmica para a Banca
- **Atendimento estrito ao RNF-06 (Ergonomia e Usabilidade Mobile):** Adequação às diretrizes de Human Interface Guidelines e Material Design 3 para zonas de toque mínimas (48x48px).
- **Inclusão e Resiliência Operacional:** Proporciona paridade de funcionalidades entre a versão desktop e o binário Android nativo (`.apk`).

