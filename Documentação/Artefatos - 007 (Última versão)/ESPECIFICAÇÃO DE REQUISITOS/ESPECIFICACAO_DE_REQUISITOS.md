# ESPECIFICAÇÃO DE REQUISITOS - ANJO NEXUS

## NÍVEL 1: AUTENTICAÇÃO, CONTAS E GESTÃO DE PLANOS (SaaS B2B)

### Módulo 1: Autenticação, Perfis e Segurança
- RF-01: O sistema deve permitir o pré-cadastro em duas etapas coletando obrigatoriamente o nome da consultoria, o nome completo do usuário titular, o e-mail e a senha na primeira etapa, validando previamente a disponibilidade do e-mail e retendo os dados temporariamente na sessão do navegador para só persistir o Usuário e a Agência no banco após a escolha efetiva do plano.
- RF-02: O sistema deve atribuir automaticamente o perfil de "Consultor Administrador" (titular) ao usuário que realizar o cadastro inicial.
- RF-03: O sistema deve permitir o login de usuários registrados utilizando e-mail e senha.
- RF-04: O sistema deve direcionar o usuário para o painel correspondente ao seu nível de acesso após o login bem-sucedido.
- RF-05: O sistema deve permitir a solicitação de redefinição de senha esquecida informando o e-mail cadastrado.
- RF-06: O sistema deve enviar um token ou link temporário e seguro para o e-mail do usuário para recuperação de senha, após a validação estrita da existência do e-mail na base de dados, com validade de expiração de 60 minutos.
- RF-07: O sistema deve bloquear a recuperação de senha autônoma para contas secundárias (Consultor Padrão e Startup).
- RF-08: O sistema deve permitir que o Consultor Administrador redefina ou altere a senha de usuários secundários criados por ele.
- RF-09: O sistema deve permitir a inserção e edição dos dados fiscais e cadastrais da agência de consultoria.
- RF-10: O sistema deve permitir o upload e edição do logotipo da consultoria.
- RF-11: O sistema deve permitir o encerramento voluntário e definitivo da conta e assinatura exclusivamente pelo Consultor Administrador, exigindo a confirmação da sua senha atual e o aceite expresso de ciência em janela de confirmação própria do sistema.
- RF-12: O sistema deve realizar a exclusão definitiva e irreversível de todos os dados da agência de consultoria, startups cadastradas, quadros Kanban, minutas, favoritos e alertas vinculados ao confirmar o encerramento da conta, em conformidade com a Lei Geral de Proteção de Dados (LGPD), liberando imediatamente o e-mail e os CNPJs para eventuais novos cadastros futuros.
- RF-13: O sistema deve revogar imediatamente todas as sessões e credenciais de acesso de todos os usuários vinculados à consultoria no ato do encerramento da conta.

### Módulo 2: Modelos de Assinatura, Cotas e Pagamentos
- RF-14: O sistema deve disponibilizar diferentes modelos de planos de assinatura SaaS para seleção.
- RF-15: O sistema deve atrelar ao plano de assinatura contratado uma cota máxima para a criação de perfis de Startups, definindo limites operacionais específicos para a consultoria.
- RF-16: O sistema deve atrelar ao plano de assinatura contratado uma cota máxima para a criação de Consultores Padrão (assentos de equipe), definindo limites operacionais específicos para a consultoria.
- RF-17: O sistema deve bloquear a criação de novas Startups caso a cota máxima do plano vigente seja atingida.
- RF-18: O sistema deve bloquear a criação de novos usuários de equipe caso a cota máxima do plano seja atingida.
- RF-19: O sistema deve exibir sugestões visuais automáticas de upgrade de plano ao atingir limites de cotas.
- RF-20: O sistema deve permitir a solicitação de upgrade para um plano superior.
- RF-21: O sistema deve permitir a solicitação de downgrade para um plano inferior.
- RF-22: O sistema deve calcular se o uso atual (startups e usuários ativos) excede as cotas do novo plano durante uma tentativa de downgrade.
- RF-23: O sistema deve bloquear o downgrade e exigir a exclusão de cadastros excedentes caso as regras da nova cota não sejam respeitadas.
- RF-24: O sistema deve integrar-se ao gateway de pagamento para gerenciar cobranças de assinatura com ciclo de vigência mensal de 30 dias, gerando links de pagamento com validade de 60 minutos, controle de idempotência para reaproveitar cobranças abertas (evitando duplicidade de transações) e liberação da renovação antecipada quando restarem 5 dias ou menos para o vencimento da assinatura.
- RF-25: O sistema deve calcular e gerenciar cobranças de diferenças de valores (pró-rata) em transações de upgrade.
- RF-26: O sistema deve receber e processar de forma assíncrona as confirmações de pagamento enviadas pelo gateway financeiro.
- RF-27: O sistema deve liberar, renovar de forma acumulativa (+30 dias somados à data de vencimento vigente sem perda de dias restantes) ou suspender automaticamente o acesso e as cotas da consultoria com base na vigência da assinatura e no retorno das transações financeiras, preservando o acesso ativo durante os dias finais enquanto o usuário realiza a renovação.

## NÍVEL 2: HIERARQUIA, CARTEIRA DE CLIENTES E WEB SCRAPING

### Módulo 3: Gestão de Equipe e Acessos Secundários (RBAC)
- RF-28: O sistema deve permitir a criação de usuários com o perfil "Consultor Padrão" pelo Consultor Administrador.
- RF-29: O sistema deve permitir a criação de credenciais com o perfil "Startup" (Cliente) pelo Consultor Administrador.
- RF-30: O sistema deve liberar ao Consultor Padrão o acesso de leitura e escrita às ferramentas de Kanban, Minutas e Editais.
- RF-31: O sistema deve bloquear ao Consultor Padrão o acesso à gestão de assinaturas e faturamento.
- RF-32: O sistema deve bloquear ao Consultor Padrão a capacidade de excluir a conta matriz da consultoria.
- RF-33: O sistema deve bloquear ao Consultor Padrão a capacidade de criar ou excluir outros acessos de usuários.
- RF-34: O sistema deve prover um portal web exclusivo para os usuários com o perfil "Startup".
- RF-35: O sistema deve restringir as permissões do portal da Startup a apenas visualização (modo somente leitura).
- RF-36: O sistema deve aplicar dinamicamente o logotipo e a identidade visual da consultoria no portal da Startup.
- RF-37: O sistema deve restringir a visão da Startup para que ela acesse apenas o seu respectivo quadro Kanban e Histórico de Minutas.

### Módulo 4: Gestão da Carteira de Startups
- RF-38: O sistema deve permitir o cadastro de múltiplos perfis de Startups atrelados a uma única Consultoria.
- RF-39: O sistema deve armazenar e permitir edição do porte e faturamento da startup.
- RF-40: O sistema deve armazenar e permitir edição da área de atuação e nível de maturidade tecnológica (TRL) da startup.
- RF-41: O sistema deve armazenar e permitir edição do texto livre de apresentação institucional (Pitch) no perfil da startup.
- RF-42: O sistema deve consumir automaticamente uma vaga da cota do plano para cada nova startup cadastrada.
- RF-43: O sistema deve possuir uma ferramenta de busca automatizada de dados em serviços públicos de dados abertos da Receita Federal utilizando apenas o CNPJ, retornando Razão Social, Nome Fantasia, CNAE, Porte, Endereço e Situação Cadastral.
- RF-44: O sistema deve autopreencher as informações de atividade econômica principal (CNAE) e Razão Social com os dados extraídos pela consulta de CNPJ.
- RF-45: O sistema deve autopreencher o indicador de situação cadastral (Ativa/Baixada) através da consulta via CNPJ.
- RF-46: O sistema deve exibir uma barra de progresso gráfica calculando o percentual de preenchimento do perfil da startup.
- RF-47: O sistema deve permitir a inserção e configuração de marcadores (tags) de nichos de mercado para a startup.
- RF-48: O sistema deve permitir a configuração de preferência do regime tributário (ex: MEI) da startup.

### Módulo 5: Mineração e Central de Editais
- RF-49: O sistema deve possuir rotinas automatizadas de mineração (web scraping) para varrer portais de fomento nacionais e estaduais (abrangendo FINEP e Fundações Estaduais de Amparo à Pesquisa como SC, SP, MG, RJ, RS, BA, PE, GO e DF).
- RF-50: O sistema deve gerar uma chave unívoca para cada edital captado.
- RF-51: O sistema deve validar a chave unívoca para impedir o cadastramento de editais duplicados na base de dados.
- RF-52: O sistema deve aplicar procedimentos de limpeza e padronização no texto bruto extraído dos editais.
- RF-53: O sistema deve exibir todos os editais processados em uma listagem global centralizada.
- RF-54: O sistema deve possuir um filtro para buscar editais por termos específicos contidos em seus títulos.
- RF-55: O sistema deve possuir um filtro para buscar editais por termos contidos no campo de objetivo.
- RF-56: O sistema deve disponibilizar filtros combinados por região geográfica, órgão emissor e status do edital.
- RF-57: O sistema deve disponibilizar um filtro de seleção para isolar apenas editais que possuam orçamento explícito.
- RF-58: O sistema deve permitir que o usuário adicione o edital visualizado à lista de Favoritos.
- RF-59: O sistema deve salvar o edital favoritado vinculando-o exclusivamente à Startup que estiver ativada no ambiente de trabalho no momento da ação.

## NÍVEL 3: WORKFLOW, IA E GERAÇÃO DE PROPOSTAS

### Módulo 6: Kanban e Prazos Processuais
- RF-60: O sistema deve gerar automaticamente um quadro Kanban exclusivo e isolado para cada Startup cadastrada.
- RF-61: O sistema deve permitir a criação de cartões representando processos de inscrição distintos dentro do mesmo Kanban.
- RF-62: O sistema deve permitir a alteração de fase de um processo movimentando o cartão entre as colunas do quadro.
- RF-63: O sistema deve permitir a edição de descrições e títulos dentro de um cartão existente no Kanban.
- RF-64: O sistema deve possuir uma área de upload dentro dos cartões para anexar arquivos comprobatórios como documentos e certidões.
- RF-65: O sistema deve permitir a configuração de uma data limite de encerramento para cada cartão do Kanban.
- RF-66: O sistema deve monitorar o calendário e disparar alertas multicanal 72 horas antes do esgotamento do prazo do ticket, integrado ao monitoramento contínuo de prazos críticos (com classificação escalonada de atenção, urgência e criticidade em até 15 dias do vencimento).
- RF-67: O sistema deve disparar alertas multicanal 24 horas antes do esgotamento do prazo do ticket, integrado ao monitoramento contínuo de prazos críticos (com classificação escalonada de atenção, urgência e criticidade em até 15 dias do vencimento).

### Módulo 7: Inteligência Artificial, Reconhecimento de Documentos e Minutas
- RF-68: O sistema deve aplicar técnicas de extração textual e processamento de linguagem natural por meio de Inteligência Artificial sobre arquivos de editais.
- RF-69: O sistema deve extrair de forma estruturada as regras, cronogramas e contrapartidas contidas no edital analisado.
- RF-70: O sistema deve processar as obrigações extraídas do edital e gerar uma lista interativa de checagem da documentação exigida na interface.
- RF-71: O sistema deve cruzar ativamente as características do perfil das startups com as informações dos novos editais minerados.
- RF-72: O sistema deve calcular um índice de compatibilidade percentual medindo a adequação entre a startup e o edital, cruzando nicho de atuação, porte empresarial e teto orçamentário, gerando listas de pontos fortes e pontos de atenção que justifiquem o resultado.
- RF-73: O sistema deve disparar notificações de compatibilidade quando o índice de elegibilidade for satisfatório.
- RF-74: O sistema deve exibir uma janela de contexto exigindo a inserção ou confirmação da apresentação institucional antes de iniciar a geração da minuta de proposta.
- RF-75: O sistema deve enviar o Pitch fornecido pelo usuário junto com as regras do edital a um roteador inteligente de múltiplos modelos de Inteligência Artificial, com balanceamento entre múltiplas chaves por sorteio aleatório e contingência automática em cascata entre os provedores.
- RF-76: O sistema deve exibir a redação da minuta formulada pela Inteligência Artificial na interface do usuário.
- RF-77: O sistema deve fornecer um editor de texto interativo em tela contendo o texto gerado.
- RF-78: O sistema deve permitir que o usuário digite, remova, formate e salve alterações no texto através do editor interativo.
- RF-79: O sistema deve disponibilizar um recurso para cópia rápida com um clique do texto integral da minuta.
- RF-80: O sistema deve possuir funcionalidade para exportar e descarregar o texto do editor no formato de documento Word (.docx).
- RF-81: O sistema deve possuir funcionalidade de exportação do texto do editor para o formato PDF com formatação estruturada e otimizada para impressão.
- RF-82: O sistema deve armazenar todas as minutas geradas e editadas em um histórico associado ao perfil da startup em foco.
- RF-83: O sistema deve permitir a reabertura de uma minuta armazenada no histórico para novas edições.
- RF-84: O sistema deve permitir a exclusão definitiva de um rascunho de minuta listado no histórico.

## NÍVEL 4: DASHBOARDS E REQUISITOS NÃO-FUNCIONAIS

### Módulo 8: Administração e Back-Office
- RF-85: O sistema deve possuir um painel protegido e exclusivo para o nível de acesso de Administrador Global.
- RF-86: O painel de administração global deve exibir indicador gráfico sumarizando o volume total de requisições de Inteligência Artificial consumidas na plataforma.
- RF-87: O painel de administração global deve exibir indicador quantitativo do volume de assinaturas ativas de consultorias.
- RF-88: O painel de administração global deve exibir um sumário do faturamento e projeção financeira da plataforma.

### Módulo 9: Evolução do MVP - Requisitos Adicionais
- RF-89: O sistema deve verificar a disponibilidade do endereço de e-mail antes da seleção de plano, impedindo a gravação de registros incompletos na base de dados.
- RF-90: O sistema deve identificar, no momento do login, contas cujo pagamento inicial ainda esteja pendente, emitindo credencial de acesso com permissão restrita e redirecionando o usuário para interface de regularização financeira.
- RF-91: O sistema deve permitir que o usuário com pagamento pendente consulte se a cobrança gerada anteriormente ainda está ativa (validade de 60 minutos) ou se expirou.
- RF-92: O sistema deve permitir que o usuário com pagamento pendente selecione um novo plano e gere uma nova cobrança caso a anterior tenha expirado.
- RF-93: O sistema deve permitir que o usuário cancele o processo de pagamento pendente e exclua permanentemente seu registro inicial não pago, liberando o e-mail para futuro recadastramento.
- RF-94: O sistema deve disponibilizar um seletor de contexto no menu principal para alternar dinamicamente a Startup em foco, de modo que as operações seguintes considerem a empresa selecionada sem necessidade de novo login.
- RF-95: O sistema deve permitir que o Consultor autenticado visualize e atualize seus próprios dados de cadastro (nome, e-mail e alteração opcional de senha) por meio de uma janela modal global.
- RF-96: O sistema deve disponibilizar, na tela de edição de perfil da startup, um botão de sincronização instantânea que atualiza os dados cadastrais e de endereço a partir do CNPJ cadastrado junto à base oficial da Receita Federal.
- RF-97: Ao editar e salvar uma minuta gerada por Inteligência Artificial, o sistema deve criar automaticamente uma nova versão sequencial e encadeada à versão anterior, preservando o histórico de versões e registrando o autor e o modelo utilizado.
- RF-98: O sistema deve disponibilizar uma página interna dedicada contendo link direto para download do aplicativo móvel para Android, geração dinâmica de QR Code para abertura no smartphone e guia de instalação.
- RF-99: O sistema deve realizar a validação e confirmação do endereço de e-mail informado pelo usuário no momento do cadastro por meio do envio de mensagem eletrônica de verificação.
- RF-100: O sistema deve exigir o aceite explícito dos Termos de Uso e da Política de Privacidade e Proteção de Dados (LGPD) durante o registro de novas contas na plataforma.
- RF-101: O sistema deve disponibilizar ao usuário a visualização detalhada da sua assinatura atual, exibindo o plano contratado, o consumo das cotas (startups, membros da equipe e minutas geradas por IA no mês), a forma de pagamento ativa, a data de vencimento, a contagem de dias restantes e a opção de gerar novo pagamento de renovação habilitada a partir de 5 dias antes do vencimento.
- RF-102: O sistema deve emitir alertas e notificações automáticas avisando o Consultor Administrador quando a assinatura mensal da consultoria atingir os últimos 5 dias antes do vencimento ou encontrar-se vencida.
- RF-103: O sistema deve disponibilizar a opção de pagamento e renovação de assinatura mensal por meio de Cartão de Crédito, além do pagamento via PIX.
- RF-104: O sistema deve limitar e validar a quantidade máxima de minutas geradas por Inteligência Artificial no mês e a capacidade de armazenamento em megabytes (MB) de documentos anexados no quadro Kanban de acordo com a cota estabelecida pelo plano de assinatura da consultoria.
- RF-105: O sistema deve permitir a integração com o WhatsApp para envio automatizado de alertas de prazos e oportunidades e extração de dados operacionais.

---

## REQUISITOS NÃO-FUNCIONAIS (RNF)

- RNF-01: A arquitetura do banco de dados deve utilizar design isolado (Multi-tenant) para as informações das agências de consultoria.
- RNF-02: O sistema deve impedir tecnicamente o acesso a dados de uma consultoria por usuários de outra consultoria via requisições de API.
- RNF-03: O sistema deve impedir o vazamento do contexto geracional de Inteligência Artificial (apresentação institucional, projetos e patentes) de uma startup para o contexto de processamento de outra startup.
- RNF-04: O sistema deve utilizar camadas de proteção e controle de rotas validando requisições contra permissões baseadas em papéis de usuário (RBAC).
- RNF-05: O tempo de resposta para carregamento das listas de editais no navegador não deve ultrapassar 5 segundos em condições normais de rede.
- RNF-06: A execução do robô de Mineração de Editais (Scraping) deve ocorrer de forma assíncrona em segundo plano por meio de filas de processamento.
- RNF-07: O processamento de leitura de anexos e extração de informações documentais deve ocorrer de forma assíncrona em segundo plano.
- RNF-08: O envio de e-mails, notificações e mensagens transacionais deve ser processado de forma assíncrona.
- RNF-09: A infraestrutura de retaguarda (back-end) do sistema deve ser construída na linguagem PHP (versão 8.2 ou superior).
- RNF-10: A infraestrutura de back-end deve utilizar o framework Laravel (versão 12 ou superior) com autenticação baseada em tokens de acesso seguro (Sanctum).
- RNF-11: A comunicação do sistema com o Roteador Multi-LLM (Groq, DeepSeek e Gemini) deve implementar camada de cache e persistência para evitar requisições redundantes e custos indevidos, de modo que o diagnóstico gerado pela IA seja armazenado diretamente nos registros dos editais e das minutas, garantindo custo zero de IA na listagem de editais já analisados. O balanceamento entre as múltiplas chaves de acesso deve ser realizado por sorteio aleatório.
- RNF-12: A comunicação de extração de dados cadastrais via CNPJ deve utilizar a BrasilAPI com contingência automática para a ReceitaWS e implementar rotinas de armazenamento temporário (Cache) com validade de 7 dias para contornar limites de taxa de requisições, além de validação matemática dos dígitos verificadores do CNPJ.
- RNF-13: A aplicação web deve implementar infraestrutura de comunicação bidirecional em tempo real (WebSockets) para suportar reatividade na interface do usuário.
- RNF-14: Mudanças no status e na posição dos cartões no quadro Kanban devem refletir em tempo real nos navegadores de outros membros da equipe conectados simultaneamente.
- RNF-15: A geração textual das minutas pela Inteligência Artificial deve ser transmitida em tempo real via fluxo contínuo (stream) na interface do usuário.
- RNF-16: O sistema deve possuir mecanismo de trilha de auditoria (Audit Trail) para registrar logs inalteráveis de ações de exclusão e atualizações de processos críticos.
- RNF-17: Os logs de auditoria devem obrigatoriamente registrar a data e hora do evento, o endereço IP de origem e o identificador e papel do usuário executor.
- RNF-18: O portal de acompanhamento da Startup (visão do cliente com personalização visual da consultoria) deve possuir layout fluido com abordagem desenvolvida prioritariamente para dispositivos móveis (Mobile-First).
- RNF-19: A exportação de arquivos no formato de documento Word (.docx) deve preservar marcações essenciais e uma formatação base de documento profissional pré-configurada.
- RNF-20: O sistema deve possuir empacotamento híbrido nativo para dispositivos móveis Android (Capacitor), com esteira de integração contínua (GitHub Actions) para compilação automatizada do pacote de instalação.
- RNF-21: O cliente de interface (front-end) deve detectar automaticamente o ambiente de execução, direcionando as chamadas para o serviço local durante o desenvolvimento e para o servidor em nuvem em ambiente de produção ou no aplicativo mobile.
- RNF-22: O motor de Inteligência Artificial deve operar com múltiplas chaves de acesso selecionadas por sorteio aleatório e com contingência automática em cascata de quatro níveis entre provedores distintos (Groq, DeepSeek e Gemini) e um mecanismo local de emergência (motor determinístico ou resposta simulada), garantindo alta disponibilidade caso uma chave ou provedor atinja limite de requisições. A ordem de prioridade entre os provedores pode variar conforme a funcionalidade, e a cascata deve sempre finalizar no mecanismo local de emergência caso todos os provedores falhem.
- RNF-23: Em cumprimento ao Direito à Eliminação de Dados Pessoais previsto na Lei Geral de Proteção de Dados (LGPD - Lei nº 13.709/2018), o encerramento voluntário de uma conta ativa deve executar a exclusão física e irreversível de todos os registros da consultoria e de suas entidades dependentes dentro de uma única transação atômica de banco de dados, garantindo que nenhum dado pessoal ou estratégico permaneça retido e liberando imediatamente as restrições de unicidade cadastral.
- RNF-24: Operações críticas de exclusão definitiva de conta ativa devem exigir controle de acesso estrito ao perfil de administrador titular da consultoria, confirmação explícita de ciência sobre a irreversibilidade da ação em interface modal própria da aplicação (vedado o uso de alertas nativos do navegador) e reautenticação síncrona mediante validação criptográfica da senha atual no servidor.
- RNF-25: Todas as integrações com gateways de pagamento e serviços governamentais externos devem ser encapsuladas por uma camada de adaptação e tradução de dados (Padrão Adapter), isolando o modelo de domínio interno de nomenclaturas de terceiros e padronizando todas as respostas de mensagens da API sob um contrato JSON uniforme.
