# ADR 038: Motor Centralizado de Cotas de Plano (`PlanoQuotaService` + `PlanLimitExceededException`) e Governança de Limite de Startups (`EDTAN-249`)

## Status
Implementado

## Data
Novembro de 2026

## Contexto e Problema (Jira EDTAN-249)
A arquitetura Multi-Tenant do **Anjo Nexus** estrutura sua precificação B2B SaaS por métricas de valor contratadas pela consultoria na tabela `planos`. Conforme documentado na **ADR 020 do Ademar** (`020-modelo-de-custos-precificacao-e-benchmark-competitivo.md` e `DOCUMENTO_DE_CUSTOS_PRECIFICACAO_E_BENCHMARK.md`), a matriz oficial de planos define tetos estritos de empresas gerenciadas no portfólio (`planos.limite_startups`), já semeados de forma idempotente pelo `PlanoSeeder.php`:
- **Plano Standard (R$ 49,90/mês):** Até **2 Startups** no portfólio (`limite_startups = 2`);
- **Plano Pro (R$ 99,90/mês):** Até **10 Startups** no portfólio (`limite_startups = 10`);
- **Plano Enterprise (R$ 299,90/mês):** Até **50 Startups** no portfólio (`limite_startups = 50`).

Embora a coluna `limite_startups` já existisse na tabela `planos` e fosse exibida de forma informativa no modal *"Minha Assinatura"*, o endpoint `POST /api/startups` (`StartupController@store`) validava apenas a unicidade do CNPJ por agência, não impondo o bloqueio transacional quando a consultoria atingia o teto contratado. Além disso, espalhar verificações manuais (`if ($agencia->startups()->count() >= $plano->limite_startups)`) dentro de controllers violaria os princípios **SRP (Single Responsibility Principle)** e **DRY (Don't Repeat Yourself)**, dificultando a expansão para as próximas entregas de governança de cotas (`EDTAN-309` e `EDTAN-161`).

### Alternativas Consideradas e Rejeitadas
- **Validação condicional inline diretamente no `StartupController@store`:** Rejeitada por acoplar regras de faturamento e cálculo de franquia à camada HTTP, duplicando queries entre `StartupController`, `AssinaturaController` e futuros módulos de limites de equipe.
- **Hardcoding dos limites (`2`, `10`, `50`) em constantes PHP ou no Front-end:** Rejeitada por violar a Fonte Única da Verdade no banco de dados (`planos.limite_startups`) e impedir planos customizados ou ilimitados (`limite_startups = NULL`).
- **Retorno de erro genérico HTTP `422` ou `500` sem código de domínio tipado:** Rejeitada por impedir que o Front-end diferencie um erro de validação cadastral (ex.: CNPJ inválido) de um bloqueio comercial de limite de plano que exige exibir o modal de upgrade (*"Minha Assinatura"*) ou exclusão de startup.

---

## Decisão Arquitetural
1. **Serviço de Domínio Centralizado (`App\Services\PlanoQuotaService`):**
   - `getUsageSummary(Agencia $agencia): array`: Calcula dinamicamente a ocupação ativa da carteira (`startups_atual`, contabilizando apenas registros ativos e ignorando startups com *Soft Delete*), o teto contratual (`startups_limite`, `int|null` lido de `$agencia->plano->limite_startups`), a flag booleana `pode_criar_startup` (`$startupsLimite === null || $startupsAtual < $startupsLimite`) e `plano_nome`;
   - `assertCanCreateStartup(Agencia $agencia): void`: Inspeciona o resumo de uso antes da persistência em `StartupController@store` e, caso `$limite !== null && $atual >= $limite`, interrompe imediatamente o fluxo lançando a exceção tipada `PlanLimitExceededException`.
2. **Exceção de Domínio Tipada e Renderização Global (`App\Exceptions\PlanLimitExceededException` em `bootstrap/app.php`):**
   - Carrega os metadados estruturados `errorCode` (`"LIMITE_STARTUPS_ATINGIDO"`), `atual` (`int`), `limite` (`int`), `planoAtual` (`string`) e a mensagem orientativa amigável;
   - Registrada globalmente no handler `withExceptions` de `bootstrap/app.php`, convertendo qualquer lançamento de `PlanLimitExceededException` em uma resposta padronizada **HTTP `403 Forbidden`**:
     ```json
     {
       "message": "Limite de startups do plano Standard atingido (2/2). Faça upgrade do seu plano em 'Minha Assinatura' ou exclua uma startup existente para liberar uma vaga.",
       "code": "LIMITE_STARTUPS_ATINGIDO",
       "codigo": "LIMITE_STARTUPS_ATINGIDO",
       "atual": 2,
       "limite": 2,
       "plano_atual": "Standard"
     }
     ```
3. **Exposição Reativa da Cota na API e na UX (`GET /api/startups` e `POST /api/startups`):**
   - O endpoint `GET /api/startups` preserva 100% da retrocompatibilidade com os consumidores existentes e injeta os metadados de `cota` (`{ startups_atual, startups_limite, pode_criar_startup, plano_nome }`) e `pode_excluir_startup`, permitindo que `selecionar-startup.html` exiba o contador `X / Y EMPRESAS` e acione preventivamente o modal de limite atingido com atalho direto para *"Minha Assinatura"*.

---

## Justificativa
1. **Alinhamento Integral com a Estratégia de Precificação (ADR 020 do Ademar):** Garante no nível de banco e domínio que a métrica de valor principal (*Value Metric* de `2`, `10` e `50` startups) seja rigorosamente respeitada sem necessidade de alterar o `PlanoSeeder`.
2. **Fundação Extensível e DRY (`EDTAN-309` / `EDTAN-161`):** O `PlanoQuotaService` e a `PlanLimitExceededException` estabelecem um padrão arquitetural único que pode ser estendido com novos métodos (`assertCanAddTeamMember`, `getTeamUsageSummary`) sem modificar a estrutura de tratamento de exceções do Laravel 12.
3. **Experiência do Usuário Orientada à Conversão (Upsell):** Em vez de um erro técnico bloqueante, o contrato JSON tipado (`LIMITE_STARTUPS_ATINGIDO`) guia o consultor para duas ações claras: realizar o upgrade de plano diretamente pelo modal *"Minha Assinatura"* na própria tela de seleção de startups ou liberar uma vaga excluindo uma empresa inativa.

---

## Consequências

### Positivas
- Proteção absoluta contra cadastro acima do limite contratado pela agência tanto pela interface gráfica quanto por chamadas diretas à API REST;
- Código dos controllers enxuto, testável unitariamente e desacoplado de cálculos de cota;
- Suporte nativo a planos futuros com startups ilimitadas (`limite_startups = null`).

### Negativas / Trade-offs
- Adiciona uma consulta `COUNT(*)` indexada por `agencia_id` (`deleted_at IS NULL`) na listagem e antes da criação de startups, com impacto de performance desprezível (< 1ms).

---

## Rastreabilidade
- **Demanda Jira:** `EDTAN-249` (Módulo Limitar Quantidade de Empresas por Usuário)
- **ADRs Relacionadas:** `ADR 020 (Ademar)` (Modelo de Custos, Precificação e Benchmark Competitivo), `ADR 002 (Roberto)` (`PlanoSeeder`), `ADR 039 (Roberto)` (`StartupPolicy`, `owner_id` e `SoftDeletes`)
- **Artefatos Vinculados:** `app/Services/PlanoQuotaService.php`, `app/Exceptions/PlanLimitExceededException.php`, `bootstrap/app.php`, `app/Http/Controllers/StartupController.php`, `front/scripts/paginas/selecionarStartup.js`, `front/scripts/componentes/seletorGlobalStartup.js`
