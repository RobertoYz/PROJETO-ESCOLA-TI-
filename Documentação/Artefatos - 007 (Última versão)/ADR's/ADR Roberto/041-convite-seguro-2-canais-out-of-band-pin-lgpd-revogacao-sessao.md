# ADR 041: Fluxo de Convite Seguro em 2 Canais (Out-of-Band: Link no E-mail + PIN de 6 Dígitos Criptografado), Identidade Individual (LGPD Art. 46) e Revogação Imediata de Sessão (`EDTAN-163`)

## Status
Implementado

## Data
Novembro de 2026

## Contexto e Problema (Jira EDTAN-163)
Ao adicionar consultores secundários a uma agência no **Anjo Nexus**, o processo de provisionamento da conta, definição de senha e desligamento de membros precisa resolver cinco desafios críticos de segurança da informação, experiência do usuário (UX) e conformidade regulatória:

1. **Risco de Erro de Digitação de E-mail (Typosquatting / E-mail Errado em Domínios Públicos como `@gmail.com`):** Se o convite dependesse apenas de um link clicável enviado por e-mail (*Single-Channel Magic Link*) e o titular errasse uma letra do endereço ao digitar o Gmail do consultor, um terceiro desconhecido que recebesse o e-mail poderia clicar no link, definir uma senha e acessar toda a carteira de startups e minutas confidenciais da consultoria.
2. **Violação de Privacidade se o Titular Definir ou Receber a Senha do Consultor:** Se o titular criasse a senha inicial do consultor ou se o sistema enviasse uma senha em texto puro por e-mail, haveria compartilhamento de credencial, violando o **Art. 46 da LGPD** (medidas técnicas de segurança e rastreabilidade individual) e comprometendo o princípio de **não-repúdio** na auditoria de autoria das minutas (`minutas.user_id` e `minutas.autor_nome`).
3. **Continuidade Operacional na Consulta do PIN Enquanto Pendente:** Em fluxos reais B2B, o titular pode fechar o modal pós-convite antes de enviar o PIN no WhatsApp do convidado, ou o consultor pode pedir o PIN horas depois, quando já estiver com o link do e-mail aberto. Se o PIN fosse armazenado como hash irreversível (`bcrypt`), o titular seria obrigado a clicar em *"Reenviar Convite"* para descobrir o código, o que invalidaria o link que o consultor já abriu no e-mail. Por outro lado, armazenar o PIN em texto puro no banco seria inseguro.
4. **Verificação Automática de E-mail (`email_verified_at`) para Notificações Críticas:** O ecossistema possui disparos automáticos de alertas de prazos críticos de editais (`EDTAN-171`) e futuras notificações operacionais (`EDTAN-283`). É indispensável garantir que o endereço de e-mail do consultor convidado realmente existe e pertence a ele no ato da ativação.
5. **Ciclo de Vida e Troca de Vaga sem Perda de Acervo nem Sessão Fantasma:** Quando um consultor deixa a empresa ou é substituído, reciclar a mesma conta trocando o e-mail/senha deturparia o histórico de auditoria. Por outro lado, manter o token Sanctum ativo após a desativação ou remoção permitiria acesso indevido pós-desligamento.

### Alternativas Consideradas e Rejeitadas
- **Titular cadastrar diretamente a senha do consultor no modal de criação:** Rejeitado por violar a individualidade da credencial (LGPD Art. 46), impedir a comprovação de posse do e-mail (`email_verified_at`) e fragilizar a auditoria de autoria.
- **Armazenar o PIN como hash irreversível (`bcrypt`) sem possibilidade de consulta pelo titular:** Rejeitado por atrito de UX: obrigar o titular a regenerar o convite inteiro apenas para visualizar novamente o PIN invalidaria o link já entregue na caixa de entrada do convidado.
- **Armazenar o PIN em texto puro no banco de dados:** Rejeitado por expor credenciais temporárias de ativação em caso de leitura direta de snapshot/dump de banco.
- **Enviar o link de ativação e o código PIN juntos no mesmo e-mail:** Rejeitado porque, se o e-mail tiver sido digitado com erro, o destinatário incorreto receberia ambos os fatores no mesmo canal, anulando a proteção *Out-of-Band*.

---

## Decisão Arquitetural
1. **Fluxo de Convite em 2 Canais com Criptografia Simétrica Autenticada (`convite_codigo_criptografado` via `AES-256-CBC` / `APP_KEY`):**
   - Ao convidar um membro (`POST /api/equipe` ➔ `EquipeService::convidar`), o sistema cria o registro em `users` com `status = 'pendente'`, `email_verified_at = null`, `convite_token = Str::random(64)` (único), `convite_expira_em = now()->addHours(48)` e `convite_tentativas = 0`;
   - Simultaneamente, gera um **código PIN numérico criptograficamente aleatório de 6 dígitos** (`random_int(100000, 999999)`) e o persiste na coluna `convite_codigo_criptografado` (`TEXT NULL`) utilizando o cast nativo `'convite_codigo_criptografado' => 'encrypted'` do Laravel 12 (`AES-256-CBC` assinado com HMAC-SHA256 via `APP_KEY`), mantendo `convite_token` e `convite_codigo_criptografado` em `User::$hidden`;
   - **Canal 1 (E-mail Best-Effort):** O backend dispara um e-mail HTML (protegido por `try/catch` com log de aviso) contendo **exclusivamente o link de ativação** (`.../paginas/aceitar-convite.html?token={64_chars}`), sem jamais incluir o PIN de 6 dígitos nem senha;
   - **Canal 2 (Modal Pós-Convite + Exibição Controlada na Lista `GET /api/equipe`):**
     - Além do modal pós-convite imediato, o endpoint `GET /api/equipe` expõe `codigo_ativacao` e `link_convite` **somente** enquanto o convite estiver estritamente válido (`status === 'pendente'`, `convite_token !== null`, `convite_codigo_criptografado !== null`, `convite_expira_em->isFuture()`, `convite_tentativas < 5`) **E** o usuário autenticado possuir autorização `$actor->can('manageMember', $membro)` pela `EquipePolicy`;
     - Dessa forma, o Titular visualiza (mascarado por padrão `••••••` com botão **Ver/Ocultar** e **Copiar Código**) o PIN de qualquer convite pendente, enquanto um Gerente Operacional (`equipe.gerenciar`) visualiza apenas os convites de consultores comuns, recebendo `null` para convites de outros gerentes criados pelo Titular;
     - A rota pública `GET /api/convites/{token}` **jamais** expõe `codigo_ativacao`.
2. **Ativação Pública em Tempo Constante (`hash_equals`), Proteção Anti-Brute-Force e Expurgo Imediato do PIN (`POST /api/convites/aceitar`):**
   - Para ativar a conta, o convidado submete `token`, `codigo` (6 dígitos), `password` (mín. 8 caracteres) e `password_confirmation`;
   - O `EquipeService::aceitarConvite` verifica a validade temporal (48h) e o limite estrito de **5 tentativas** (`convite_tentativas < 5`), comparando o PIN em tempo constante contra ataques de timing: `hash_equals((string) $user->convite_codigo_criptografado, (string) $codigo)`:
     - Se incorreto, incrementa `convite_tentativas`; ao atingir **5 falhas** (ou se detectado expirado), limpa imediatamente `convite_token = null`, `convite_codigo_criptografado = null` e `convite_expira_em = null`, invalidando o convite no banco;
     - Se válido, grava o hash da senha escolhida pelo próprio consultor, altera `status = 'ativo'`, preenche `email_verified_at = now()` e apaga definitivamente `convite_token = null`, `convite_codigo_criptografado = null`, `convite_expira_em = null` e `convite_tentativas = 0`.
3. **Bloqueio de Login para Contas Não Ativas (`AuthController@login`):**
   - Tentativas de login em `POST /api/auth/login` por usuários com `status === 'pendente'` ou `status === 'inativo'` são rejeitadas imediatamente com **HTTP `403 Forbidden`** e mensagens claras (*"Convite pendente de ativação"* / *"Acesso desativado pela consultoria"*).
4. **Revogação Imediata de Sessão Sanctum e Preservação do Acervo na Troca de Vaga:**
   - Ao desativar (`PATCH /api/equipe/{id}/status` ➔ `inativo`) ou remover (`DELETE /api/equipe/{id}`) um consultor, o `EquipeService::revogarSessoesETokens` apaga instantaneamente todos os registros do usuário em `personal_access_tokens` (`$membro->tokens()->delete()`) e `sessions`;
   - Qualquer requisição subsequente com o Bearer token antigo recebe **HTTP `401 Unauthenticated`**, enquanto todas as startups, cards do Kanban e minutas permanecem 100% intactos na consultoria.

---

## Justificativa
1. **Equilíbrio Ideal entre Segurança Criptográfica e Usabilidade (UX):** A criptografia simétrica autenticada (`AES-256-CBC` via `APP_KEY`) garante que o PIN nunca repouse em texto puro no MySQL, mas permite que o Titular autorizado (`EquipePolicy::manageMember`) consulte e copie o código a qualquer momento durante a janela de 48h sem invalidar o link já enviado ao e-mail do consultor.
2. **Eliminação Definitiva Após o Uso (Zero Residual Secret):** Assim que o convite é ativado, expira ou atinge 5 tentativas, `convite_codigo_criptografado` e `convite_token` são sobrescritos com `NULL` no banco.
3. **Mitigação contra Erro de Destinatário e Conformidade LGPD (Art. 46):** O destinatário do e-mail precisa do PIN de 6 dígitos entregue por fora (com bloqueio em 5 tentativas), e a senha definitiva é criada exclusivamente pelo próprio convidado.

---

## Consequências

### Positivas
- O Titular (ou Gerente autorizado pela `EquipePolicy`) pode consultar, revelar (`👁️ Ver`) ou copiar (`📋 Copiar Código` / `🔗 Copiar Link`) o convite pendente diretamente na listagem de `equipe.html` até que a ativação seja concluída;
- Isolamento hierárquico preservado: gerentes não conseguem visualizar o PIN de convites pendentes de outros gerentes;
- Comparação criptográfica em tempo constante (`hash_equals`) e limpeza automática no banco após ativação, expiração ou bloqueio.

### Negativas / Trade-offs
- Como o cast `'encrypted'` depende da `APP_KEY` da aplicação, uma rotação manual de `APP_KEY` em produção invalidaria convites pendentes ainda não aceitos (basta clicar em *"Reenviar Convite"* para gerar um novo par).

---

## Rastreabilidade
- **Demanda Jira:** `EDTAN-163` (Módulo de Gestão de Equipe, RBAC Granular e Convite Seguro)
- **ADRs Relacionadas:** `ADR 005 (Roberto)` (Autenticação Stateless Sanctum), `ADR 024 (Roberto)` (Segurança Criptográfica de Tokens), `ADR 040 (Roberto)` (RBAC Granular e `EquipePolicy`)
- **Artefatos Vinculados:** `database/migrations/2026_09_15_000003_create_users_table.php`, `app/Models/User.php`, `app/Services/EquipeService.php`, `app/Http/Controllers/EquipeController.php`, `front/paginas/equipe.html`, `front/scripts/paginas/equipe.js`, `front/estilos/equipe.css`, `tests/Feature/EquipeManagementAndInviteTest.php`
