# ADR 041: Fluxo de Convite Seguro em 2 Canais (Out-of-Band: Link no E-mail + PIN de 6 Dígitos), Identidade Individual (LGPD Art. 46) e Revogação Imediata de Sessão (`EDTAN-163`)

## Status
Implementado

## Data
Novembro de 2026

## Contexto e Problema (Jira EDTAN-163)
Ao adicionar consultores secundários a uma agência no **Anjo Nexus**, o processo de provisionamento da conta, definição de senha e desligamento de membros precisa resolver quatro riscos críticos de segurança da informação e conformidade regulatória:

1. **Risco de Erro de Digitação de E-mail (Typosquatting / E-mail Errado em Domínios Públicos como `@gmail.com`):** Se o convite dependesse apenas de um link clicável enviado por e-mail (*Single-Channel Magic Link*) e o titular errasse uma letra do endereço ao digitar o Gmail do consultor, um terceiro desconhecido que recebesse o e-mail poderia clicar no link, definir uma senha e acessar toda a carteira de startups e minutas confidenciais da consultoria.
2. **Violação de Privacidade se o Titular Definir ou Receber a Senha do Consultor:** Se o titular criasse a senha inicial do consultor ou se o sistema enviasse uma senha em texto puro por e-mail, haveria compartilhamento de credencial, violando o **Art. 46 da LGPD** (medidas técnicas de segurança e rastreabilidade individual) e comprometendo o princípio de **não-repúdio** na auditoria de autoria das minutas (`minutas.user_id` e `minutas.autor_nome`).
3. **Verificação Automática de E-mail (`email_verified_at`) para Notificações Críticas:** O ecossistema possui disparos automáticos de alertas de prazos críticos de editais (`EDTAN-171`) e futuras notificações operacionais (`EDTAN-283`). É indispensável garantir que o endereço de e-mail do consultor convidado realmente existe e pertence a ele no ato da ativação.
4. **Ciclo de Vida e Troca de Vaga sem Perda de Acervo nem Sessão Fantasma:** Quando um consultor deixa a empresa ou é substituído, reciclar a mesma conta trocando o e-mail/senha deturparia o histórico de auditoria. Por outro lado, manter o token Sanctum ativo após a desativação ou remoção permitiria acesso indevido pós-desligamento.

### Alternativas Consideradas e Rejeitadas
- **Titular cadastrar diretamente a senha do consultor no modal de criação:** Rejeitado por violar a individualidade da credencial (LGPD Art. 46), impedir a comprovação de posse do e-mail (`email_verified_at`) e fragilizar a auditoria de autoria.
- **Enviar o link de ativação e o código PIN juntos no mesmo e-mail:** Rejeitado porque, se o e-mail tiver sido digitado com erro, o destinatário incorreto receberia ambos os fatores no mesmo canal, anulando a proteção *Out-of-Band*.
- **Excluir em cascata as minutas e cards criados pelo consultor ao removê-lo da equipe:** Rejeitado porque a propriedade intelectual do acervo (startups, cards do Kanban e minutas de fomento) pertence ao Tenant (`Agencia`), e não ao indivíduo desligado.

---

## Decisão Arquitetural
1. **Fluxo de Convite e Ativação Segura em 2 Canais (*Out-of-Band Verification*):**
   - Ao convidar um membro (`POST /api/equipe` ➔ `EquipeService::convidar`), o sistema cria o registro em `users` com `status = 'pendente'`, `email_verified_at = null`, `convite_token = Str::random(64)` (único), `convite_expira_em = now()->addHours(48)` e `convite_tentativas = 0`;
   - Simultaneamente, gera um **código PIN numérico criptograficamente aleatório de 6 dígitos** (`random_int(100000, 999999)`) e persiste no banco **apenas o seu hash bcrypt** (`convite_codigo_hash = Hash::make($pin)`), ocultando `convite_token` e `convite_codigo_hash` na serialização do model (`User::$hidden`);
   - **Canal 1 (E-mail Best-Effort):** O backend dispara um e-mail HTML (protegido por `try/catch` com log de aviso para nunca quebrar a requisição caso o SMTP não esteja configurado no ambiente local) contendo **exclusivamente o link de ativação** (`.../paginas/aceitar-convite.html?token={64_chars}`), sem jamais incluir o PIN de 6 dígitos nem senha;
   - **Canal 2 (Exibição Única em Tela / Repasse Externo):** A resposta HTTP `201` devolve `link_convite` e `codigo_ativacao` (o PIN de 6 dígitos) para exibição única no modal pós-convite do titular/gerente, que repassa o PIN ao consultor por canal secundário (ex.: WhatsApp, Slack ou verbalmente).
2. **Ativação Pública com Proteção Anti-Brute-Force e Validação Automática de E-mail (`POST /api/convites/aceitar`):**
   - A página pública `aceitar-convite.html` consulta `GET /api/convites/{token}` (que valida o token e retorna apenas o nome da consultoria, nome do convidado e e-mail mascarado `ca***@dominio.com`);
   - Para ativar a conta, o convidado submete `token`, `codigo` (6 dígitos), `password` (mín. 8 caracteres) e `password_confirmation`;
   - O `EquipeService::aceitarConvite` verifica a validade temporal (48h) e o limite estrito de **5 tentativas** (`convite_tentativas < 5`):
     - Se `Hash::check($codigo, $user->convite_codigo_hash)` falhar, incrementa `convite_tentativas`; ao atingir **5 falhas**, anula imediatamente `convite_token` e `convite_codigo_hash` e expira o convite, exigindo que o titular gere um novo convite (`POST /api/equipe/{id}/reenviar-convite`);
     - Se válido, grava o hash da senha escolhida pelo próprio consultor, altera `status = 'ativo'`, preenche `email_verified_at = now()` (comprovando posse do e-mail pelo Canal 1 + validação pelo Canal 2) e limpa `convite_token`, `convite_codigo_hash`, `convite_expira_em` e `convite_tentativas`.
3. **Bloqueio de Login para Contas Não Ativas (`AuthController@login`):**
   - Tentativas de login em `POST /api/auth/login` por usuários com `status === 'pendente'` ou `status === 'inativo'` são rejeitadas imediatamente com **HTTP `403 Forbidden`** e mensagens claras (*"Convite pendente de ativação"* / *"Acesso desativado pela consultoria"*), antes mesmo de emitir qualquer token Sanctum.
4. **Revogação Imediata de Sessão Sanctum e Preservação do Acervo na Troca de Vaga:**
   - Ao desativar (`PATCH /api/equipe/{id}/status` ➔ `inativo`) ou remover (`DELETE /api/equipe/{id}`) um consultor, o `EquipeService::revogarSessoesETokens` apaga instantaneamente todos os registros do usuário em `personal_access_tokens` (`$membro->tokens()->delete()`) e `sessions`;
   - Qualquer requisição subsequente com o Bearer token antigo recebe **HTTP `401 Unauthenticated`**;
   - Todas as startups (`startups.agencia_id`), quadros/cards do Kanban (`kanban_boards.agencia_id`) e minutas (`minutas.user_id` com `ON DELETE SET NULL` e `autor_nome` preservado) permanecem 100% intactos na consultoria.

---

## Justificativa
1. **Mitigação Total contra Erro de Destinatário (2 Canais Independentes):** Mesmo que um link de convite caia em uma caixa de e-mail errada, o destinatário não possui o PIN de 6 dígitos (Canal 2); e caso tente adivinhar, o bloqueio em 5 tentativas em um universo de $900.000$ combinações torna a força bruta inviável ($P \le 5 / 900.000 \approx 0,00055\%$).
2. **Conformidade com a LGPD (Art. 6º e Art. 46) e Auditoria de Não-Repúdio:** Cada consultor possui identidade própria, e-mail verificado (`email_verified_at`) e senha exclusiva definida por ele mesmo. Na rotatividade de equipe, o titular desativa ou remove o ex-membro e convida o novo profissional sem reciclar credenciais.
3. **Corte Instantâneo de Acesso (Zero Trust Session Revocation):** A limpeza imediata da tabela `personal_access_tokens` no Sanctum garante que um consultor desligado perca acesso às rotas da API no mesmo milissegundo.

---

## Consequências

### Positivas
- Segurança de nível corporativo (Out-of-Band 2FA no onboarding de equipe) com UX simples de copiar e colar no WhatsApp;
- Resiliência operacional em ambientes locais ou de homologação sem SMTP configurado (o link e o PIN são exibidos no modal pós-convite para ativação imediata);
- Preservação integral do histórico de minutas e cards da agência após o desligamento de qualquer consultor.

### Negativas / Trade-offs
- O código PIN de 6 dígitos só é exibido uma única vez no momento da criação ou do reenvio do convite (pois apenas o hash bcrypt é salvo no banco); caso o titular feche o modal sem copiar, basta clicar em **"Reenviar Convite"** para gerar um novo par Link + PIN.

---

## Rastreabilidade
- **Demanda Jira:** `EDTAN-163` (Módulo de Gestão de Equipe, RBAC Granular e Convite Seguro)
- **ADRs Relacionadas:** `ADR 005 (Roberto)` (Autenticação Stateless Sanctum), `ADR 024 (Roberto)` (Segurança Criptográfica de Tokens), `ADR 040 (Roberto)` (RBAC Granular e `EquipePolicy`)
- **Artefatos Vinculados:** `database/migrations/2026_09_15_000003_create_users_table.php`, `app/Services/EquipeService.php`, `app/Http/Controllers/EquipeController.php`, `app/Http/Controllers/AuthController.php`, `front/paginas/equipe.html`, `front/scripts/paginas/equipe.js`, `front/paginas/aceitar-convite.html`, `front/scripts/paginas/aceitarConvite.js`, `tests/Feature/EquipeManagementAndInviteTest.php`
