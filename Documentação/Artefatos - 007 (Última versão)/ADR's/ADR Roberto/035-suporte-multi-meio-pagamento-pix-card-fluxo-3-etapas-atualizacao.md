# ADR 035: Suporte Multi-Meio de Pagamento (`PIX` e `CARD`), Fluxo de Cadastro em 3 Etapas e Atualização de Forma de Pagamento (`EDTAN-311`)

## Status
Implementado

## Data
Novembro de 2026

## Contexto e Problema (Jira EDTAN-311)
O sistema suportava inicialmente apenas cobranças via PIX com persistência imediata ao selecionar o plano. Com a evolução do gateway AbacatePay API v2 (que aceita `PIX` e `CARD` via checkout hospedado) e a demanda por flexibilidade de pagamento para consultorias B2B, tornou-se necessário permitir a escolha entre PIX e Cartão de Crédito sem manipular dados sensíveis de cartão no servidor (conformidade PCI-DSS), além de permitir a troca de método no modal "Minha Assinatura".

### Alternativas Consideradas e Rejeitadas
- **Captura direta de dados de cartão de crédito (PAN/CVV) no formulário próprio do Anjo Nexus:** Rejeitado por impor escopo completo de auditoria e certificação PCI-DSS ao servidor da aplicação e ampliar a superfície de risco em caso de vazamento.
- **Persistência da Agência e Usuário no banco de dados já na tela de seleção de plano (`selecionar-plano.html`):** Rejeitado porque gerava registros órfãos prematuros antes mesmo de o usuário decidir o meio de pagamento na etapa final do wizard.
- **Criação de migration incremental (`add_forma_pagamento_to_agencias_table.php`):** Rejeitado por violar a diretriz arquitetural de *Schema Squashing* (ADR 031), que exige consolidação 1:1 diretamente nas migrations originais de criação.

---

## Decisão Arquitetural
1. **Fluxo de Cadastro em 3 Etapas (Wizard):** `cadastro.html` (dados em `sessionStorage`) ➔ `selecionar-plano.html` (salva `plano_selecionado` em `sessionStorage`) ➔ `escolher-pagamento.html` (escolha entre `PIX` ou `CARD`). A persistência transacional no MySQL (`Agencia` + `User`) só ocorre no clique final de ida ao checkout, evitando registros órfãos.
2. **Schema Squashing e Padrão Adapter:** A coluna `forma_pagamento` (`ENUM('PIX', 'CARD') DEFAULT 'PIX'`) foi consolidada diretamente na migration `create_agencias_table.php` (sem migrations incrementais). O `AbacatePayAdapter::paraPayloadCriacao()` passa dinamicamente `'methods' => [$formaPagamento]` à API v2.
3. **Atualização de Forma de Pagamento:** Criado o endpoint `POST /api/assinatura/atualizar-forma-pagamento` (restrito a `consultor_admin`), que altera a preferência para o próximo ciclo se a conta tiver `> 5 dias` vigentes ou gera imediatamente novo checkout no gateway caso a conta esteja na janela de renovação, em carência ou suspensa. A regra de idempotência de 60 minutos em `renovar()` só reaproveita a `url_checkout` se a `forma_pagamento` não tiver sido alterada.

---

## Justificativa
1. **Conformidade PCI-DSS Nativa via Hosted Checkout:** Ao delegar a coleta dos dados do cartão para a página hospedada do AbacatePay API v2 (`'methods' => ['CARD']`), o Anjo Nexus opera com risco zero de exposição de dados financeiros sensíveis.
2. **Prevenção de Abandono no Banco de Dados:** O adiamento do `INSERT` transacional para o botão *"Finalizar e Ir para o Pagamento"* em `escolher-pagamento.html` assegura que a navegação entre planos e métodos ocorra inteiramente em memória (`sessionStorage`) no cliente.
3. **Transparência Contratual no Painel SaaS:** A atualização do modal *"Minha Assinatura"* com datas padronizadas em `DD/MM/AAAA` (vencimento do ciclo e próximo pagamento/repetição), remoção de itens obsoletos (*"Configurações"*) e seletor interativo entre PIX e Cartão entrega autonomia financeira completa ao `consultor_admin`.

---

## Consequências

### Positivas
- Zero exposição a dados sensíveis de cartão (checkout hospedado), redução de abandono de banco no onboarding e transparência total das datas de vencimento e repetição (`DD/MM/AAAA`) no modal "Minha Assinatura";
- Reaproveitamento idempotente inteligente em `POST /api/assinatura/renovar`, invalidando o checkout anterior apenas quando há troca explícita entre `PIX` e `CARD`.

### Negativas / Trade-offs
- O fluxo de onboarding ganha uma tela intermediária adicional (`escolher-pagamento.html`), exigindo que o `sessionStorage` mantenha `dados_pre_cadastro` e `plano_selecionado` sincronizados até a submissão final.

---

## Rastreabilidade
- **Demanda Jira:** `EDTAN-311` (Módulo de Forma de Pagamento Cartão + PIX, Wizard 3 Etapas e Atualização em Minha Assinatura)
- **Requisitos Funcionais e Não-Funcionais:** `RF-02` (Onboarding e Contratação de Plano), `RF-08` (Gestão de Assinatura e Meio de Pagamento), `RNF-03` (Segurança e Conformidade PCI-DSS)
- **Artefatos Vinculados:** `database/migrations/2026_09_15_000002_create_agencias_table.php`, `app/Adapters/AbacatePayAdapter.php`, `app/Services/AbacatePayService.php`, `app/Http/Controllers/AuthController.php`, `app/Http/Controllers/AssinaturaController.php`, `front/paginas/escolher-pagamento.html`, `front/scripts/paginas/escolherPagamento.js`, `front/scripts/componentes/seletorGlobalStartup.js`
