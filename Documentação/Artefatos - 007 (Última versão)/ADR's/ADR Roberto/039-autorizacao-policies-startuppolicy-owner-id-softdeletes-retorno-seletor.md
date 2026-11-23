# ADR 039: Autorização Desacoplada por Policies (`StartupPolicy`), Titularidade Financeira (`owner_id`) e Exclusão Segura (`SoftDeletes` + Retorno ao Seletor de Contexto) (`EDTAN-249`)

## Status
Implementado

## Data
Novembro de 2026

## Contexto e Problema (Jira EDTAN-249)
Para que o limite de startups por plano (`ADR 038`) ofereça uma jornada funcional completa, a consultoria precisa conseguir **gerenciar a ocupação das suas vagas**, excluindo uma startup da carteira quando desejar substituí-la por outra sem necessariamente fazer upgrade imediato de plano.

Durante o desenho arquitetural dessa operação, identificamos três desafios críticos de segurança, governança multi-tenant e experiência do usuário:
1. **Acoplamento entre Cargo Operacional (`perfil_acesso`) e Titularidade Financeira (`owner_id`):** Em uma consultoria B2B com múltiplos membros, identificar explicitamente o fundador/titular financeiro da agência (`agencias.owner_id`) separado apenas do enum `perfil_acesso` garante rastreabilidade contratual e protege operações destrutivas.
2. **Autorização Inline nos Controllers vs. Laravel Policies:** Verificar permissões e isolamento multi-tenant (`agencia_id`) com blocos `if` espalhados dentro dos métodos do `StartupController` dificulta a auditoria de segurança Anti-IDOR e polui a camada HTTP.
3. **Risco de Perda Acidental e Contexto Órfão na Exclusão de Startup:**
   - Um `DELETE` físico imediato (`Hard Delete`) na tabela `startups` dispararia `ON DELETE CASCADE` irreversível em minutas e alertas no mesmo segundo, sem janela de recuperação em caso de clique acidental;
   - No Front-end, se o consultor excluir uma startup em `minhas-startups.html` e o sistema selecionar silenciosamente outra startup qualquer no `localStorage`, o usuário corre o risco de operar no Radar ou no Kanban achando que está em uma empresa quando na verdade está em outra.

### Alternativas Consideradas e Rejeitadas
- **Hard Delete imediato no `DELETE /api/startups/{id}`:** Rejeitado por impedir retenção temporária de segurança de 30 dias e destruir instantaneamente o histórico em caso de erro operacional do consultor.
- **Auto-selecionar a primeira startup restante da lista após a exclusão:** Rejeitado por causar ambiguidade de contexto ativo (`X-Startup-Id`) e induzir o consultor a submeter cards ou minutas para a empresa errada.
- **Criação de migration incremental `add_owner_id_to_agencias` e `add_deleted_at_to_startups`:** Rejeitada pela diretriz estrita de *Schema Squashing* (`ADR 031`), que determina a manutenção das migrations originais `create_*_table.php` compatíveis com `migrate:fresh --seed` no MySQL 8.0.

---

## Decisão Arquitetural
1. **Titularidade Financeira (`agencias.owner_id`) via Schema Squashing Compatível com MySQL 8.0:**
   - Adicionada a coluna `$table->unsignedBigInteger('owner_id')->nullable()->index();` diretamente em `2026_09_15_000002_create_agencias_table.php` (sem `constrained('users')` na criação de `agencias`, pois a tabela `users` é criada na migration seguinte `000003`, garantindo execução limpa de `migrate:fresh` no MySQL 8.0);
   - Adicionado o relacionamento `Agencia::owner(): BelongsTo` e preenchido automaticamente `$agencia->owner_id = $usuario->id` no fluxo transacional de registro (`AuthController@registrarComPlano` e `RegistroService`).
2. **Autorização Desacoplada com Laravel Policy (`App\Policies\StartupPolicy`):**
   - Registrada via `Gate::policy(Startup::class, StartupPolicy::class)` no `AppServiceProvider` e acionada nos endpoints de `StartupController` via `Gate::authorize(...)`:
     - `create(User $user)`: Exige vínculo com agência ativa e perfil operacional autorizado;
     - `view(User $user, Startup $startup)` e `update(User $user, Startup $startup)`: Impõem proteção estrita **Anti-IDOR** validando `(int) $user->agencia_id === (int) $startup->agencia_id`;
     - `delete(User $user, Startup $startup)`: Valida o isolamento multi-tenant (`agencia_id`) **E** exige que o usuário seja o titular financeiro (`$agencia->owner_id === $user->id`) ou possua cargo administrativo (`consultor_admin` / `admin_global`), bloqueando com HTTP `403` usuários `consultor_padrao` não titulares ou tentativas cross-tenant.
3. **Exclusão Lógica com Liberação Imediata de Cota (`SoftDeletes`) e Expurgo LGPD em 30 Dias:**
   - Adicionado `$table->softDeletes()` em `2026_10_10_033507_create_startups_table.php` e a trait `SoftDeletes` no model `Startup`;
   - O endpoint `DELETE /api/startups/{id}` (`StartupController@destroy`) executa `$startup->delete()`, preenchendo `deleted_at`. Como o Eloquent filtra automaticamente registros com `deleted_at IS NOT NULL`, **1 vaga é liberada imediatamente** na contagem de `PlanoQuotaService::getUsageSummary($agencia)`;
   - Caso a mesma agência recadastre posteriormente um CNPJ que estava em *Soft Delete*, o `StartupController@store` restaura e atualiza o registro (`restore()` + `update()`), evitando colisão no índice composto `UNIQUE(agencia_id, cnpj)`;
   - No comando diário `ProcessarCicloAssinaturas` (`assinaturas:processar-ciclo`), adicionada a etapa de expurgo definitivo LGPD: `Startup::onlyTrashed()->where('deleted_at', '<=', now()->subDays(30))->forceDelete()`.
4. **Reset de Contexto e Retorno Obrigatório para `selecionar-startup.html`:**
   - Na página `minhas-startups.html`, a seção **"Zona de Perigo"** (exibida apenas quando `pode_excluir_startup === true`) exige confirmação explícita em modal antes de chamar `DELETE /api/startups/{id}`;
   - Concluída a exclusão, o Front-end executa `limparStartupAtiva()` (removendo `startup_ativa_id` e `startup_ativa_nome` do `localStorage`) e redireciona obrigatoriamente para `selecionar-startup.html?excluida=1`, exibindo o banner de confirmação da vaga liberada e forçando uma escolha consciente da próxima empresa a assessorar.

---

## Justificativa
1. **Segurança em Profundidade (Anti-IDOR + RBAC + Ownership):** Centralizar as regras na `StartupPolicy` garante que nenhuma rota de leitura, edição ou exclusão possa ser explorada por IDOR entre agências concorrentes ou por membros sem privilégio administrativo.
2. **Equilíbrio entre Usabilidade Comercial e Conformidade LGPD:** O *Soft Delete* libera a vaga do plano no mesmo instante para o cliente continuar trabalhando, enquanto a rotina agendada de 30 dias cumpre o princípio de limitação de armazenamento da LGPD.
3. **Prevenção de Erro Humano de Contexto:** Limpar o `localStorage` e retornar para `selecionar-startup.html` elimina 100% do risco de estado inconsistente no Header Global ou no Pipeline Kanban após uma exclusão.

---

## Consequências

### Positivas
- Liberação instantânea de vaga no plano ao excluir uma startup, sem conflito de chave única (`UNIQUE(agencia_id, cnpj)`) caso a empresa seja recadastrada no futuro;
- Código do `StartupController` limpo e aderente aos padrões oficiais do Laravel 12 (`Gate::authorize`);
- Proteção completa contra exclusão acidental ou não autorizada na interface Web e Mobile (Capacitor).

### Negativas / Trade-offs
- Operações de *Hard Delete* total de conta (`cancelarConta` e `executarHardDeleteEmCascata`) passam a utilizar `withTrashed()->forceDelete()` para garantir que também limpem startups em lixeira antes de remover a agência.

---

## Rastreabilidade
- **Demanda Jira:** `EDTAN-249` (Módulo Limitar Quantidade de Empresas por Usuário)
- **ADRs Relacionadas:** `ADR 026 (Roberto)` (Modelagem da Tabela `startups`), `ADR 031 (Roberto)` (Schema Squashing), `ADR 033 (Roberto)` (Hard Delete LGPD), `ADR 038 (Roberto)` (`PlanoQuotaService`)
- **Artefatos Vinculados:** `database/migrations/2026_09_15_000002_create_agencias_table.php`, `database/migrations/2026_10_10_033507_create_startups_table.php`, `app/Models/Agencia.php`, `app/Models/Startup.php`, `app/Policies/StartupPolicy.php`, `app/Http/Controllers/StartupController.php`, `app/Console/Commands/ProcessarCicloAssinaturas.php`, `front/paginas/minhas-startups.html`, `front/scripts/paginas/minhasStartups.js`, `front/paginas/selecionar-startup.html`, `front/scripts/paginas/selecionarStartup.js`
