# ADR 040: RBAC Granular por Permissões Atômicas, Isolamento Financeiro do Titular (`owner_id`) e Proibição de Delegação Transitiva (`EDTAN-163`)

## Status
Implementado

## Data
Novembro de 2026

## Contexto e Problema (Jira EDTAN-163)
Em uma plataforma B2B SaaS voltada a consultorias de captação de recursos e subvenção econômica (**Anjo Nexus**), a agência contratante (`Agencia`) frequentemente opera com múltiplos profissionais atuando em papéis distintos: sócios/gerentes operacionais, consultores técnicos que redigem minutas e alimentam o Kanban, e analistas/estagiários que apenas acompanham editais e portfólio em modo leitura.

O modelo inicial baseado exclusivamente na coluna `perfil_acesso` (`consultor_admin` vs `consultor_padrao`) apresentava três limitações arquiteturais importantes:
1. **Granularidade Insuficiente para Operação Real:** Não era possível configurar, por exemplo, um consultor que pudesse editar startups e movimentar o Kanban, mas que estivesse impedido de excluir startups da carteira (`Soft Delete`) ou de consumir a cota mensal de geração de minutas de IA (`EDTAN-250`).
2. **Risco de Exposição Financeira e Contratual:** Operações críticas de faturamento — como consultar dados financeiros da assinatura, gerar cobrança de renovação (`PIX`/`CARD`), alterar meio de pagamento ou encerrar definitivamente a conta sob a LGPD (`Hard Delete`) — exigem isolamento estrito no **Titular Financeiro** (`agencias.owner_id`), não podendo ser delegadas a membros convidados.
3. **Vulnerabilidade de Escalada de Privilégio por Delegação Transitiva:** Quando o Titular delega a um Gerente Operacional a permissão de gerenciar a equipe (`equipe.gerenciar`), esse gerente não pode ter poder para:
   - Alterar permissões, desativar ou remover o próprio Titular (`owner_id`);
   - Alterar, desativar ou remover outros gerentes que também possuam `equipe.gerenciar`;
   - Conceder a terceiros a permissão `equipe.gerenciar` (criando uma cadeia transitiva descontrolada de sub-gerentes) ou conceder qualquer permissão que ele próprio não possua.

### Alternativas Consideradas e Rejeitadas
- **Criar dezenas de valores fixos no `ENUM('perfil_acesso')`:** Rejeitado por rigidez estrutural (exigiria alteração de DDL a cada nova combinação de acesso) e por violar o Princípio Aberto-Fechado (OCP).
- **Tabelas relacionais pesadas de `roles`, `permissions`, `role_has_permissions` e `model_has_permissions` (estilo Spatie Permission):** Rejeitado por complexidade e overhead de *joins* desnecessários para o escopo determinístico de 6 capacidades de domínio do MVP, onde uma coluna nativa `JSON` (`users.permissoes`) no MySQL 8.0 combinada a um Backed Enum tipado em PHP 8.2 entrega performance $O(1)$ sem *joins* extras.
- **Permitir que qualquer membro com `equipe.gerenciar` promova novos gerentes:** Rejeitado por quebrar a governança hierárquica da consultoria e permitir escalada horizontal/vertical de privilégios sem anuência do Titular.

---

## Decisão Arquitetural
1. **Catálogo Oficial de Permissões Atômicas (`App\Enums\Permissao`) e Presets de UX:**
   - Criado o *Backed String Enum* `App\Enums\Permissao` contendo as 6 capacidades atômicas do domínio:
     - `STARTUPS_CRIAR_EDITAR = 'startups.criar_editar'`
     - `STARTUPS_EXCLUIR = 'startups.excluir'`
     - `KANBAN_GERENCIAR = 'kanban.gerenciar'`
     - `MINUTAS_GERENCIAR = 'minutas.gerenciar'`
     - `EQUIPE_GERENCIAR = 'equipe.gerenciar'`
     - `ASSINATURA_GERENCIAR = 'assinatura.gerenciar'` *(exclusiva do Titular `owner_id`, nunca delegável)*
   - Para agilizar a experiência na interface (`equipe.html`) sem abrir mão da customização individual por checkbox, `Permissao::presets()` expõe 3 perfis rápidos:
     - **Gerente Operacional** *(selecionável apenas pelo Titular)*: todas as 5 permissões operacionais (`startups.criar_editar`, `startups.excluir`, `kanban.gerenciar`, `minutas.gerenciar`, `equipe.gerenciar`);
     - **Consultor Pleno**: `startups.criar_editar`, `kanban.gerenciar`, `minutas.gerenciar`;
     - **Visualizador**: `[]` (acesso somente leitura ao Radar de Editais, Startups, Kanban e Minutas).
2. **Persistência via Schema Squashing (`users.permissoes` e `users.status`) e Resolução no Model `User`:**
   - Adicionada a coluna `$table->json('permissoes')->nullable()` e `$table->enum('status', ['pendente', 'ativo', 'inativo'])->default('ativo')` diretamente em `2026_09_15_000003_create_users_table.php`;
   - Implementados no model `User`:
     - `isOwner(): bool`: verifica se `status === 'ativo'` e se `$this->agencia->owner_id === $this->id` (com fallback retrocompatível para contas legadas sem `owner_id` onde `perfil_acesso === 'consultor_admin'`);
     - `temPermissao(string|Permissao $permissao): bool`: retorna `false` imediatamente se `status !== 'ativo'`; retorna `true` para todas as permissões se `isOwner()`; retorna `false` para qualquer não-titular quando a permissão consultada for `assinatura.gerenciar`; e verifica a presença da permissão no array `permissoesEfetivas()`;
     - `permissoesEfetivas(): array`: retorna todas as permissões para o Titular ou a lista sanitizada de permissões delegáveis atribuídas ao consultor.
3. **Autorização Desacoplada via Policies (`EquipePolicy` e `StartupPolicy`) e Proibição de Delegação Transitiva:**
   - **`EquipePolicy`:**
     - `viewAny` e `create`: exigem `$user->isOwner() || $user->temPermissao(Permissao::EQUIPE_GERENCIAR)`;
     - `manageMember(User $actor, User $target)`: impõe 4 travas cumulativas:
       1. **Anti-IDOR Multi-Tenant:** `$actor->agencia_id === $target->agencia_id`;
       2. **Anti-Autoalteração:** `$actor->id !== $target->id`;
       3. **Proteção Absoluta do Titular:** `!$target->isOwner()` e `$agencia->owner_id !== $target->id`;
       4. **Bloqueio de Ação Horizontal entre Gerentes:** se `!$actor->isOwner()`, bloqueia qualquer edição, reenvio de convite, inativação ou remoção de outro membro que possua `equipe.gerenciar`;
     - `assignPermissions(User $actor, array $permissoesSolicitadas, ?User $target)`: rejeita sempre `assinatura.gerenciar` e, quando `$actor` não é `isOwner()`, rejeita `equipe.gerenciar` e qualquer permissão que o próprio `$actor` não possua.
   - **Enforcement Transversal nos Módulos:**
     - `StartupPolicy`: `create` e `update` exigem `temPermissao('startups.criar_editar')`; `delete` exige `temPermissao('startups.excluir')`;
     - `KanbanController`: rotas de escrita (`storeCard`, `moveCard`, `updateCard`, `destroyCard`) exigem `temPermissao('kanban.gerenciar')`;
     - `MinutaController`: rotas de geração e edição (`gerar`, `update`) exigem `temPermissao('minutas.gerenciar')`;
     - `AssinaturaController`, `PlanoController@selecionarPlano`, `PagamentoController` e `AuthController@cancelarConta`: exigem exclusivamente `isOwner()` (`assinatura.gerenciar`), retornando HTTP `403` para qualquer consultor secundário.

---

## Justificativa
1. **Princípio do Menor Privilégio (PoLP) e Segregação de Funções (SoD):** Separa claramente a governança financeira/contratual do Titular (`owner_id`) da gestão operacional delegável.
2. **Prevenção de Escalada de Privilégios:** A proibição matemática de delegação transitiva garante que apenas o dono do contrato (`owner_id`) possa nomear ou destituir gerentes de equipe (`equipe.gerenciar`).
3. **Aderência a SOLID (SRP e OCP):** Toda a lógica de autorização fica encapsulada no enum `Permissao` e nas Policies (`EquipePolicy`, `StartupPolicy`), mantendo os controllers enxutos e livres de condicionais de cargo espalhadas.

---

## Consequências

### Positivas
- Flexibilidade total para a consultoria combinar permissões por membro ou utilizar presets de 1 clique;
- Proteção 100% testada contra IDOR entre agências, autoexclusão e escalada transitiva de privilégios;
- O Front-end Web e Mobile recebe `is_owner`, `status` e `permissoes` em `/api/auth/login`, `/api/me` e `/api/auth/perfil`, ocultando preventivamente menus e botões não autorizados.

### Negativas / Trade-offs
- Exige que o Front-end sincronize o cache local (`usuario_logado`) com `/api/auth/perfil` (`/api/me`) ao inicializar o header global para refletir alterações de permissão em tempo real.

---

## Rastreabilidade
- **Demanda Jira:** `EDTAN-163` (Módulo de Gestão de Equipe, RBAC Granular e Convite Seguro)
- **ADRs Relacionadas:** `ADR 031 (Roberto)` (Schema Squashing), `ADR 033 (Roberto)` (Cancelamento de Conta LGPD), `ADR 039 (Roberto)` (`StartupPolicy` e `owner_id`)
- **Artefatos Vinculados:** `app/Enums/Permissao.php`, `app/Models/User.php`, `app/Policies/EquipePolicy.php`, `app/Policies/StartupPolicy.php`, `app/Services/EquipeService.php`, `app/Http/Controllers/EquipeController.php`, `app/Http/Controllers/AssinaturaController.php`, `app/Http/Controllers/KanbanController.php`, `app/Http/Controllers/MinutaController.php`, `tests/Feature/EquipeManagementAndInviteTest.php`
