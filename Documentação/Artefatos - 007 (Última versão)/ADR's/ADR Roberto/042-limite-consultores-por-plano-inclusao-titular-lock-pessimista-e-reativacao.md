# ADR 042: Governança de Limite de Consultores por Plano, Inclusão do Titular na Cota, Lock Pessimista Anti-Race Condition e Reativação sem Cooldown (`EDTAN-309`)

## Status
Implementado

## Data
Novembro de 2026

## Contexto e Problema (Jira EDTAN-309)
No ecossistema B2B SaaS **Anjo Nexus**, a precificação e a sustentabilidade operacional são alicerçadas na métrica de valor (*Value Metric*) contratada pela consultoria, conforme formalizado na **ADR 020 do Ademar** (`020-modelo-de-custos-precificacao-e-benchmark-competitivo.md`). A matriz oficial de planos define os seguintes limites de capacidade de colaboradores por agência:
- **Plano Standard (R$ 49,90/mês):** 1 Usuário Consultor (`limite_membros_equipe = 1`);
- **Plano Pro (R$ 99,90/mês):** Até 5 Membros de Equipe (`limite_membros_equipe = 5`);
- **Plano Enterprise (R$ 299,90/mês):** Até 15 Membros de Equipe (`limite_membros_equipe = 15`).

Após a entrega do módulo de convites seguros em 2 canais e RBAC granular (`EDTAN-163` / `ADR 040` e `ADR 041`), identificou-se a ausência de um enforcement rigoroso de cota no Back-end para o número total de membros vinculados a uma consultoria. Sem essa trava transacional, consultorias poderiam cadastrar consultores e gerentes indefinidamente, furando a franquia comercial contratada e desestabilizando o modelo de precificação.

Quatro desafios críticos precisavam de resolução arquitetural definitiva:
1. **Definição da Base de Cálculo da Cota (O Titular Consome Vaga?):** Em ferramentas SaaS operacionais, o fundador/titular opera ativamente o sistema (cria startups, gera minutas, move cards no Kanban). Excluí-lo da contagem permitiria que um plano de 1 usuário (Standard) tivesse 2 usuários ativos (Titular + 1 Convidado), fraudando a premissa de custos unitários da ADR 020.
2. **Impacto de Convites Pendentes no Consumo de Vagas:** Ao disparar um convite seguro, uma credencial de acesso (`User`) com validade de 48 horas é reservada. Se o convite pendente não consumisse vaga, o titular poderia disparar dezenas de convites simultâneos e depois ativá-los todos, excedendo o teto.
3. **Prevenção de Race Conditions em Convites Concorrentes:** Duas requisições simultâneas de convite (`POST /api/equipe`) poderiam ler a mesma contagem de vagas disponíveis e inserir novos usuários em concorrência se não houvesse bloqueio no banco de dados.
4. **Ciclo de Reativação e Alternativas contra Abuso de Vagas:** Quando um consultor é inativado, ele libera sua vaga. Contudo, ao tentar reativá-lo com a cota cheia, o sistema precisa impedir a reativação de forma consistente com a criação de novos convites, decidindo entre um *cooldown* artificial (ex.: trava de 24h) ou a validação estrita em tempo real da cota ativa.

### Alternativas Consideradas e Rejeitadas
- **Excluir o Titular (`owner_id`) da contagem de licenças:** Rejeitado porque no plano Standard (R$ 49,90), concebido para consultor autônomo individual, a consultoria passaria a operar com 2 colaboradores concorrentes pelo valor de 1, violando o unit economics da ADR 020.
- **Implementar Cooldown Artificial de 24h/48h para Reativação de Membros:** Rejeitado pelo excesso de atrito e complexidade (KISS). Caso o titular inative um consultor por engano ou para teste rápido, seria punido com um bloqueio rígido de 24 horas. Como a desativação já revoga imediatamente todos os tokens Sanctum e sessões (`ADR 041`), não há risco de uso concorrente da mesma vaga.
- **Validação de Cota Apenas no Front-end (desabilitando botões):** Rejeitado por não oferecer segurança real, permitindo bypass via chamadas diretas com ferramentas como Postman, curl ou scripts.

---

## Decisão Arquitetural

1. **Inclusão Fechada do Titular e Convites Pendentes na Franquia (`PlanoQuotaService`):**
   - O Titular (`agencias.owner_id`) opera a plataforma e **consome 1 vaga ativa** na consultoria;
   - A fórmula de vagas ocupadas é unificada: todos os registros da tabela `users` com `agencia_id = $agencia->id` cujo `status` seja `'ativo'` (ou nulo em contas legadas) **OU** `'pendente'`:
     $$\text{vagas\_usadas} = \text{ativos} + \text{pendentes}$$
   - Membros com `status = 'inativo'` **não consomem vaga**, liberando a franquia instantaneamente;
   - Planos customizados ou ilimitados são representados formalmente por `planos.limite_membros_equipe = NULL`, onde `ilimitado = true`, `restantes = null` e `atingiu_limite = false`.

2. **Motor Centralizado de Cotas de Equipe (`PlanoQuotaService`):**
   - Método `obterResumoCotaEquipe(Agencia $agencia): array`: Retorna estrutura padronizada contendo `usados`, `ativos`, `pendentes`, `inativos`, `limite`, `restantes`, `ilimitado`, `atingiu_limite`, `plano_nome` e `inclui_titular = true`;
   - Método `validarLimiteEquipe(Agencia $agencia, string $acao = 'convidar'): void`: Caso `atingiu_limite === true`, interrompe a execução disparando a exceção tipada `PlanLimitExceededException` com código HTTP `403` e código semântico `'LIMITE_CONSULTORES_ATINGIDO'`, com mensagens contextuais para `'convidar'` e `'reativar'`.

3. **Lock Pessimista Anti-Race Condition com `lockForUpdate()`:**
   - No `EquipeService::convidar()`, a validação de cota e a persistência do novo usuário são encapsuladas em uma transação ACID (`DB::transaction`) adquirindo lock pessimista exclusivo na linha da agência:
     ```php
     $agencia = Agencia::where('id', $actor->agencia_id)->lockForUpdate()->firstOrFail();
     $this->quotaService->validarLimiteEquipe($agencia, 'convidar');
     ```
   - Impede que cliques duplos ou requisições concorrentes ultrapassem a capacidade da cota.

4. **Reativação Transacional sem Cooldown Artificial (Opção 1 — KISS):**
   - No `EquipeService::atualizarStatus()`, ao transicionar de `inativo` para `ativo`, o sistema executa a transação com `lockForUpdate()` e validação da cota antes de salvar a alteração;
   - Se o limite estiver cheio, rejeita a reativação com HTTP `403 LIMITE_CONSULTORES_ATINGIDO`; se houver vaga liberada, a reativação ocorre em tempo real sem qualquer espera de 24 horas;
   - Ao inativar (`status = 'inativo'`), revoga instantaneamente todos os tokens Sanctum e sessões no banco, eliminando concorrência residual.

5. **Reenvio de Convite Seguro (`reenviarConvite`):**
   - Membros com `status === 'pendente'` já consomem vaga desde a emissão do convite inicial. Portanto, o reenvio de convite **não valida limite**, renovando unicamente o token de 64 caracteres, o PIN de 6 dígitos e a validade de 48 horas.

6. **Experiência do Usuário (UX) e Isolamento Financeiro:**
   - No topo de `equipe.html` (Web e Mobile Capacitor), um card informativo apresenta a barra de progresso, contador numérico (`X / Y membros utilizados`) e detalhamento de ativos, pendentes e inativos;
   - Ao atingir 100% da cota, o botão *"+ Convidar Consultor"* é desabilitado preventivamente e um banner orientativo é exibido;
   - **Diferenciação por Papel:** Caso o usuário logado seja o Titular (`ator.is_owner === true`), exibe botão CTA direto para abertura do modal de Assinatura/Planos (`abrirModalMinhaAssinatura()`). Se for um Gerente Operacional sem poder financeiro, oculta o CTA de faturamento e orienta a solicitar o upgrade ao Titular, preservando o princípio de isolamento financeiro da `ADR 040`.

---

## Justificativa
1. **Conformidade Rígida com a ADR 020:** Garante que a métrica de valor dos planos (1 consultor no Standard, 5 no Pro e 15 no Enterprise) seja respeitada na camada de banco de dados e transações, mantendo as margens brutas superiores a 97%.
2. **Resiliência Transacional:** O lock pessimista (`lockForUpdate`) elimina vetores de invasão ou falhas de concorrência onde requisições assíncronas poderiam inflar a equipe além do plano pago.
3. **Simplicidade e Usabilidade Operacional (KISS):** A validação baseada em estado ativo dispensa tabelas auxiliares de cooldown ou cron jobs de desbloqueio temporal, garantindo flexibilidade total para o gestor sem brechas de segurança.

---

## Consequências

### Positivas
- Governança absoluta sobre o número de usuários ativos e convidados em cada plano;
- Transações atômicas seguras contra *race conditions* no momento de convite e de reativação;
- Feedback transparente para Titulares (com upsell para planos superiores) e Gerentes Operacionais (informativo de governança);
- Espelhamento 100% sincronizado entre o Front-end Web e o aplicativo Android Capacitor.

### Negativas / Trade-offs
- O lock pessimista na linha da agência impõe serialização momentânea durante o commit de novos convites, com impacto irrelevante devido ao baixo volume concorrente de contratação de equipe por agência (< 15 membros).

---

## Rastreabilidade
- **Demanda Jira:** `EDTAN-309` (Limitar Quantidade de Consultores/Gerentes por Plano)
- **ADRs Relacionadas:** `ADR 020 (Ademar)` (Modelo de Custos e Precificação SaaS), `ADR 038 (Roberto)` (Motor Centralizado de Cotas), `ADR 040 (Roberto)` (RBAC Granular e Isolamento do Titular), `ADR 041 (Roberto)` (Convite Seguro e Revogação de Sessão)
- **Artefatos Vinculados:** `app/Services/PlanoQuotaService.php`, `app/Exceptions/PlanLimitExceededException.php`, `app/Services/EquipeService.php`, `app/Http/Controllers/EquipeController.php`, `app/Http/Controllers/AssinaturaController.php`, `database/migrations/2026_09_15_000001_create_planos_table.php`, `front/paginas/equipe.html`, `front/estilos/equipe.css`, `front/scripts/paginas/equipe.js`, `mobile/www/paginas/equipe.html`, `mobile/www/estilos/equipe.css`, `mobile/www/scripts/paginas/equipe.js`, `tests/Feature/EquipeQuotaLimitTest.php`
