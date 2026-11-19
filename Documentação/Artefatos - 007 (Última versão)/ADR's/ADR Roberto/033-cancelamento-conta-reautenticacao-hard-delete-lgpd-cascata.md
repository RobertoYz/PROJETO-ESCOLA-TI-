# ADR 033: Encerramento de Conta com Reautenticação Obrigatória, Exclusão Física em Cascata (Hard Delete LGPD) e Liberação de Chaves Únicas

## Status
Implementado

## Data
Novembro de 2026

## Contexto e Problema (Jira EDTAN-158)
O ecossistema **Anjo Nexus** precisava viabilizar o fluxo de encerramento definitivo de conta e cancelamento de assinatura pelo próprio cliente através do painel de configurações da consultoria. Esse fluxo exigiu uma análise crítica de arquitetura de banco de dados e de conformidade com a legislação brasileira de proteção de dados (Lei Geral de Proteção de Dados — LGPD, Lei nº 13.709/2018).

Durante a modelagem da funcionalidade, identificaram-se dois dilemas centrais:
1. **Conflito de Restrições de Unicidade (*Unique Constraints*):** A persistência de contas inativas utilizando a estratégia de *Soft Delete* (`deleted_at`) preservaria as linhas nas tabelas `users` e `startups`. Como as tabelas possuem índices únicos rígidos (`users.email` e `UNIQUE(agencia_id, cnpj)` em `startups`), se um consultor encerrasse sua conta e tentasse futuramente cadastrar-se novamente com o mesmo e-mail institucional ou reinserir os mesmos CNPJs, a tentativa resultaria em falha de chave duplicada (`SQLSTATE[23000]: Integrity constraint violation 1062`), exigindo suporte manual de TI para alteração de e-mails legados.
2. **Direito do Titular à Eliminação de Dados (Art. 18, Inciso VI da LGPD):** Ao solicitar a rescisão do contrato e a exclusão da conta, o titular dos dados exige a eliminação completa dos dados pessoais tratados, incluindo dados societários, pitch de negócios, planos de inovação, minutas de propostas geradas por IA e anexos submetidos.
3. **Risco Operacional e Segurança em Sessões Abertas:** Uma funcionalidade destrutiva de cancelamento não poderia ser acionada por engano ou por usuários desautorizados com perfis secundários em uma sessão aberta de terminal.

### Alternativas Consideradas e Rejeitadas
- **Adoção de *Soft Delete* tradicional (`use SoftDeletes`):** Rejeitado porque, além de violar a solicitação formal de exclusão definitiva sob a LGPD, causaria colisão perpétua nos índices únicos do banco de dados MySQL para o mesmo e-mail de usuário e CNPJs das startups.
- **Anonimização parcial (Data Masking) mantendo os registros:** Rejeitado pelo alto custo de implementação e testes para ofuscar dezenas de tabelas correlatas (Kanban, Minutas, Alertas), sendo desnecessário em virtude da ausência de pendências fiscais ou tributárias emitidas pela própria aplicação SaaS.

---

## Decisão Arquitetural
Optou-se pela implementação do encerramento de conta por meio de **Exclusão Física em Cascata (*Hard Delete*) dentro de Transação ACID (`DB::transaction`)**, respaldada por uma **Tripla Trava de Segurança** no endpoint híbrido `DELETE|POST /api/conta/cancelar`:

1. **Trava 1 — Controle de Acesso Baseado em Papéis (RBAC Estrito):**
   - O endpoint verifica estritamente o papel do usuário autenticado: apenas `$usuario->perfil_acesso === 'consultor_admin'` possui autorização para acionar o encerramento da agência;
   - Perfis como `consultor_padrao` ou contas de `startup` recebem imediatamente HTTP **`403 Forbidden`**.
2. **Trava 2 — Reautenticação Criptográfica e Aceite Expresso LGPD:**
   - Exigência do envio da senha atual do administrador no payload (`'senha' => 'required|string'`), validada via `Hash::check($request->senha, $usuario->password)`; caso inválida, retorna HTTP **`422 Unprocessable Entity`**;
   - Exigência da flag explícita `'ciente_lgpd' => 'required|accepted'`, assegurando o consentimento livre e informado sobre a irreversibilidade do procedimento;
   - Interface de usuário governada por Modal Customizado em Dark Mode (dispensando `confirm` ou `alert` nativos do browser), no qual o botão de exclusão permanece bloqueado até o preenchimento de todos os requisitos de segurança.
3. **Trava 3 — Exclusão Física Transacional em Cascata (`DB::transaction`):**
   - Execução atômica e ordenada que garante que, se qualquer operação falhar, ocorra *rollback* completo:
     1. Revogação de todos os tokens de autenticação Sanctum de todos os usuários daquela agência (`$usuario->tokens()->delete()`);
     2. Exclusão física das minutas de propostas de editais geradas pela IA;
     3. Exclusão física dos alertas de prazos, notificações e editais favoritados;
     4. Exclusão física dos cartões (`kanban_cards`), colunas (`kanban_columns`) e quadros (`kanban_boards`);
     5. Exclusão das empresas cadastradas (`startups`);
     6. Exclusão de sessões ativas e tokens de redefinição de senha;
     7. Exclusão física dos colaboradores (`users`) vinculados à agência;
     8. Exclusão física da entidade inquilina principal (`agencias`).

---

## Justificativa
1. **Conformidade Legal Rigorosa (LGPD):** O sistema atende formalmente ao princípio da necessidade e ao direito do titular à eliminação dos seus dados (Art. 18, VI da LGPD), expurgando qualquer dado confidencial ou estratégico de inovação da empresa.
2. **Reversibilidade de Acesso e Recadastro Limpo:** Com a exclusão das linhas físicas, as constraints `UNIQUE(email)` e `UNIQUE(agencia_id, cnpj)` são liberadas instantaneamente no MySQL. Caso o consultor decida contratar a plataforma novamente no futuro, poderá fazê-lo sem qualquer conflito cadastral ou dependência de suporte técnico.
3. **Imunidade contra Erros Acidentais:** A combinação de restrição ao `consultor_admin`, verificação criptográfica da senha em tempo de execução e marcação ativa de checkbox mitiga integralmente o risco de exclusão inadvertida decorrente de cliques acidentais ou sessões deixadas ativas em máquinas compartilhadas.

---

## Consequências

### Positivas
- Total conformidade jurídica com os preceitos da LGPD sobre exclusão definitiva de dados;
- Reutilização desimpedida de credenciais (e-mail institucional e CNPJs) em cadastros futuros;
- Revogação imediata de credenciais de todos os membros da agência, impedindo acessos fantasmas via tokens órfãos.

### Negativas / Trade-offs
- A exclusão física é irreversível: não há recuperação de histórico, cartões de editais ou minutas geradas após a confirmação bem-sucedida da transação.

---

## Rastreabilidade
- **Demanda Jira:** `EDTAN-158` (Cancelamento de Conta do Consultor e Hard Delete LGPD)
- **Requisitos Funcionais e Legais:** `RF-10` (Cancelamento de Conta e Assinatura), `RF-11` (Conformidade com LGPD - Art. 18, VI), `RNF-03` (Segurança da Informação e Criptografia)
- **Artefatos Vinculados:** `app/Http/Controllers/ContaController.php`, `app/Models/Agencia.php`, `public/js/perfil.js`
