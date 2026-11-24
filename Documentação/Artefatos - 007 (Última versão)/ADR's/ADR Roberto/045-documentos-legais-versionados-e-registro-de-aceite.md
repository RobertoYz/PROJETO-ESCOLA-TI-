# ADR 045: Documentos legais versionados e registro de aceite no cadastro (`EDTAN-286`)

## Status

Implementado

## Data

24 de novembro de 2026

## Contexto e Problema (Jira EDTAN-286)

O titular contrata um plano em nome da agência após confirmar o acesso à caixa postal. Para responder a uma contestação, precisamos recuperar quais textos estavam disponíveis quando ele confirmou a escolha, inclusive após mudança de versão ou encerramento. A identificação jurídica da plataforma Anjo Nexus (MVP) será complementada com razão social e CNPJ antes da operação comercial.

**Auditoria da Fase 0.** As duas rotas de cadastro chegam a `AuthController::registrarComPlano`. A ADR 044 descreve o OTP de seis dígitos (15 minutos, cinco tentativas, reenvio após 60 segundos), o token descartável (60 minutos, SHA-256 no banco) e a limpeza diária de verificações vencidas ou consumidas há mais de 24 horas. As rotas limitam envio e confirmação a 10 e 15 requisições por minuto, respectivamente. Antes da confirmação, nome, agência e senha ficam no formulário. Depois, o wizard guarda também a senha e o token em `sessionStorage` para concluir o checkout. A senha persistida no servidor recebe hash. Abandonar a aba pode prolongar a presença dos dados do formulário enquanto durar a sessão do navegador.

O cadastro consome o token sob bloqueio, cria a agência e seu titular e solicita checkout à AbacatePay com nome, e-mail, produto, modalidade, identificador externo e URLs de retorno. O webhook verifica segredo e atualiza a assinatura. Seus logs podem conter identificadores da agência/transação, status e erros. Existem renovação, carência, suspensão, upgrade com saldo convertido em dias, downgrade programado sujeito a cotas e exclusão física. A rotina de assinaturas também remove startups excluídas há mais de 30 dias. O encerramento voluntário exige titular, senha e confirmação específica de exclusão. O cancelamento do cadastro pendente e a limpeza agendada também excluem dados. A exclusão física das entidades operacionais e a pseudonimização da evidência têm efeitos distintos.

O remetente local configurado e o valor padrão do Compose coincidem em `projetoanjonexus@gmail.com`, canal de privacidade escolhido para esta publicação. O `back/.env.example` ilustra o transporte com outro endereço. O código usa Laravel Mail com transporte configurável. A disponibilidade da caixa, a entrega, os países de processamento e os contratos dependem da operação. O Compose persistente chamava só `PlanoSeeder` e agora executa o catálogo de seeders também nesse caminho.

### Alternativas Consideradas e Rejeitadas

- Uma coluna booleana no usuário perderia o texto aceito e desapareceria no encerramento.
- Reconstruir documentos com parâmetros atuais de e-mail ou selecionar novamente a publicação após o checkout poderia associar ao evento um texto diferente.
- Usar o e-mail em claro, SHA-256 simples ou a chave geral da aplicação ampliaria a exposição ou acoplaria rotações independentes.
- Contar cinco anos desde o cadastro permitiria descartar evidência durante a vigência do contrato.

---

## Decisão Arquitetural

1. `DocumentoLegalSeeder` publica os dois textos 1.0.0, incluindo o canal de contato literal. A inicialização verifica o histórico de cada tipo dentro da transação. `TermoAceiteService::publicarNovaVersao` bloqueia as publicações do tipo, libera o marcador anterior e insere a nova linha. A unicidade de `(tipo, versao)` e de `vigente_tipo` decide inclusive disputas na primeira publicação, quando ainda inexiste linha para bloquear. O serviço repete transações em deadlock e traduz colisões de unicidade após rollback. Um marcador nulo representa histórico.
2. O SHA-256 usa os bytes UTF-8 de um array JSON posicional com tipo, versão, título e seções. Mapas internos têm chaves ordenadas como strings, listas preservam ordem, Unicode e barras permanecem literais, sem espaços de formatação. Ativação e datas ficam fora do cálculo. Os eventos do Eloquent bloqueiam edição do conteúdo e exclusão de snapshots e aceites. Atualizações em massa, SQL direto e DBA ultrapassam essa proteção. As FKs restringem exclusão de documentos referenciados e anulam referências à conta excluída. A operação deve restringir privilégios e controlar alterações no banco.
3. O endpoint público exige ambos os documentos e responde 503 se o conjunto estiver incompleto. Nos dois endpoints de registro, o servidor exige booleano verdadeiro, IDs numéricos e versões exatas das categorias correspondentes antes do gateway e do consumo do token. As mesmas instâncias validadas fornecem IDs, versões e hashes ao evento dentro da transação local. O histórico via Sanctum filtra pelo ID do próprio usuário e omite HMAC e identificador da chave. A versão anterior continua recuperável.
4. O e-mail normalizado por `strtolower(trim($email))` dá origem a HMAC-SHA-256 com chave dedicada. O banco guarda o resultado e `hmac_chave_id`. Gere 32 bytes aleatórios (por exemplo, `bin2hex(random_bytes(32))`) fora de logs, atribua um ID distinto e proteja a chave em cofre com acesso restrito. Para rotacionar, provisione novo par, reconstrua o cache de configuração e reinicie os processos. Guarde chaves antigas com seu ID enquanto forem necessárias à verificação de eventos retidos, inclusive sob preservação excepcional. Ao final, retire as cópias sob controle da operação. Proteja também o cache do Laravel e backups. Produção falha na inicialização se a chave estiver ausente, curta ou for a chave de exemplo ou da aplicação. O HMAC permanece dado pessoal pseudonimizado. OTP, IP, agente do navegador e hash de conteúdo fornecem contexto com limites de atribuição de identidade e de comprovação de leitura.
5. Durante a conta ativa, `retencao_ate` fica nulo. Os três caminhos de encerramento marcam a data na mesma transação da exclusão, antes da perda das FKs. A suspensão reversível por falta de pagamento mantém a retenção de conta ativa. A escolha de cinco anos pós-encerramento é política do projeto, sujeita à revisão de necessidade. O serviço permite alterar exclusivamente a preservação excepcional ou iniciar a retenção. Às 03h25 UTC o comando `termos:expurgar-aceites-vencidos` percorre lotes por ID e revalida prazo e preservação no DELETE. `--dry-run` conta os elegíveis. Os logs do comando contêm contagem ou falha. A execução depende de scheduler ativo e monitorado. Infraestrutura deve definir descarte de logs e backups, inclusive tratamento após restauração.
6. Os cinco arquivos do wizard são espelhados byte a byte no Capacitor. O diálogo nativo tem nome, fechamento por Escape, X e fundo, foco contido e devolvido ao acionador, abas com setas, rolagem e estados de consulta. Os nós de texto exibem os dados da API. Só ação explícita grava o aceite da sessão. A alteração de versão invalida a escolha. Renovação e troca seguem seus fluxos próprios. Homologação de contraste, teclado, leitor de tela e WebView integra a etapa visual manual.

## Justificativa

Aceitar os Termos expressa adesão às condições contratuais. Declarar ciência da Política documenta a apresentação das informações. O consentimento específico, quando utilizado como base legal, exige manifestação demonstrável para determinada finalidade, e incumbe ao controlador demonstrar sua obtenção (LGPD, arts. 7º, I, e 8º, § 2º). O cadastro fundamenta os tratamentos nas hipóteses descritas a seguir.

| Cláusula da publicação | Fundamento aplicável | Risco tratado | Comportamento e limite |
| --- | --- | --- | --- |
| Termos · Quem presta o serviço | CDC art. 6º, III | Identificação insuficiente | MVP de consultorias. Identificação societária pendente antes da operação comercial. |
| Termos · Conta e equipe | LGPD arts. 6º e 46 | Uso indevido de credenciais | OTP e titularidade por owner_id, com gestão de permissões (ADRs 040, 042 e 044). A caixa postal pode ser compartilhada. |
| Termos · Propostas e IA | CDC arts. 6º, III, e 14 quando aplicáveis | Decisão baseada em sugestão incorreta | As minutas e correspondências exigem revisão humana e decisão externa sobre o edital. |
| Termos · Plano e pagamento | CDC arts. 6º, III, e 46 | Custo ou redução de cota inesperados | Checkout, confirmação, pro-rata em dias e downgrade condicionado às cotas (ADR 043). A confirmação depende da integração. |
| Termos · Vencimento e encerramento | CDC art. 6º, III | Perda de acesso ou de documentos | Carência de dois dias, suspensão e exclusão periódica, com limites configuráveis para contas nunca pagas ou vencidas. |
| Termos · Direitos e responsabilidades | CDC arts. 14, 49 e 51 | Restrição indevida de reparação e arrependimento | Canal de atendimento e direito quando cabível. Não foi identificado fluxo automatizado de estorno. |
| Termos · Criações e uso permitido | LGPD arts. 6º e 18 | Uso de material ou dados de terceiros sem autorização | Agência controla entradas e acessos. A legitimidade de cada material depende do contexto. |
| Termos · Versões e foro | CDC arts. 46, 51 e 101, I, quando aplicáveis | Perda de histórico e obstáculo à defesa | Publicações imutáveis, foro de Maringá – PR com ressalvas legais. A v1 registra o cadastro, sem fluxo geral de reaceite da base existente. |
| Política · Quem trata os dados | LGPD arts. 5º, VI e VII, e 9º | Confusão sobre papéis | Controlador em conta, autenticação, logs e assinatura. Pode operar dados inseridos por agências. Papéis efetivos dependem de atos, instruções e contratos. |
| Política · Do pré-cadastro à assinatura | LGPD arts. 6º e 7º, V | Coleta precoce e guarda excessiva | OTP e prova transitória antes do cadastro. A senha transita pelo armazenamento de sessão do wizard. |
| Política · Por que tratamos | LGPD arts. 7º, II, V e IX, e 10. Marco Civil arts. 5º, VIII, e 15 | Base genérica para logs | Contrato ou fase preliminar quando o titular é parte. Segurança e prevenção a abuso demandam necessidade e expectativa legítima. O prazo de seis meses do Marco Civil trata de registros de acesso e depende do enquadramento do provedor. A implantação deverá verificar se os registros exigíveis estão disponíveis. |
| Política · Registro da escolha | LGPD arts. 7º, VI, e 16, I, este último para obrigação legal ou regulatória aplicável | Contestação contratual e excesso de retenção | Vínculo com snapshots, HMAC, hora UTC e contexto técnico. Exercício regular de direitos demanda avaliação concreta. O art. 16, I não institui prazo geral de cinco anos. |
| Política · Serviços externos | LGPD arts. 33 a 36 | Divulgação ou transferência internacional sem salvaguardas | AbacatePay pode decidir finalidades próprias. BrasilAPI e ReceitaWS recebem consultas de CNPJ. Serviços de IA incluem DeepSeek, Groq e Google Gemini, conforme configuração. SMTP envia mensagens. Região de processamento e suboperadores dependem dos prestadores. |
| Política · Segurança e registros | LGPD arts. 6º, VII e VIII, e 46 | Acesso indevido | Hash de senha, limitação de tentativas, tokens, permissões e webhook autenticado. Logs podem conter identificadores e detalhes operacionais. |
| Política · Prazos e descarte | LGPD arts. 6º, III, 15 e 16 | Expurgo prematuro ou guarda indefinida | Limpeza de OTP, exclusão operacional e retenção do aceite seguem ciclos próprios. Backup e descarte por terceiros dependem da infraestrutura. |
| Política · Direitos e atualizações | LGPD arts. 9º, 18 e 19 | Impossibilidade de acesso ou correção | Canal de privacidade e consulta a versões. Atendimento e verificação proporcional da solicitação dependem de procedimento operacional. |

---

## Consequências

### Positivas

- Cada cadastro concluído identifica o par de publicações que passou pela validação mesmo se houver nova versão durante a cobrança.
- A exclusão operacional preserva evidências com prazo e controle de preservação. O histórico atende o titular por autenticação individual.

### Negativas / Trade-offs

- O rollback local protege usuário, agência, verificação e aceite. Uma cobrança já criada externamente pode sobreviver à falha local. A reconciliação com a AbacatePay e o tratamento operacional de estorno seguem pendentes.
- Antes de usar dados pessoais reais ou iniciar operação comercial, completar razão social/CNPJ, revisão jurídica das minutas, bases e prazos, análise da atuação de cada agente, contratos/DPA, suboperadores, países e mecanismo de transferência aplicável (LGPD arts. 33–36 e Resolução CD/ANPD 19). Verificar a obrigação de indicar Encarregado à luz do enquadramento e da Resolução 18. O e-mail de atendimento não constitui ato de nomeação. Validar gestões de chave, backup, legal hold, direitos dos titulares e execução dos agendamentos.
- A suíte de aplicação utiliza SQLite. Bloqueios de intervalo, concorrência entre conexões MySQL e comportamento do WebView exigem homologação no ambiente alvo.

---

## Rastreabilidade

- Jira `EDTAN-286`. ADRs 033, 040, 042, 043 e 044. Cobertura em `tests/Feature/TermoAceiteTest.php`.
- Fontes oficiais consultadas em 24/11/2026. [LGPD compilada](https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709compilado.htm), [Código de Defesa do Consumidor](https://www.planalto.gov.br/ccivil_03/leis/l8078compilado.htm), [Marco Civil da Internet](https://www.planalto.gov.br/ccivil_03/_ato2011-2014/2014/lei/l12965.htm).
- Regulamentação de Encarregado e de transferências internacionais, consultada em 24/11/2026. [Atos normativos da ANPD, Resoluções 18 e 19](https://www.gov.br/anpd/pt-br/acesso-a-informacao/institucional/atos-normativos/regulamentacoes_anpd/), [Regulamento de transferência internacional](https://www.gov.br/anpd/pt-br/acesso-a-informacao/institucional/atos-normativos/regulamentacoes_anpd/resolucao-cd-anpd-no-19-de-23-de-agosto-de-2024).
