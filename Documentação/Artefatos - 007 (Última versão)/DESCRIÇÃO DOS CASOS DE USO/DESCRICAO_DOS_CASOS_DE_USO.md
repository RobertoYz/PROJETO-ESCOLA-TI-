# DESCRIÇÃO DOS CASOS DE USO - ANJO NEXUS

## LISTA GERAL DE CASOS DE USO:

- UC-01: Realizar Pré-Cadastro e Validar Disponibilidade de E-mail
- UC-02: Escolher Plano de Assinatura e Gerar Cobrança Inicial
- UC-03: Processar Pagamento via Webhook e Ativar Assinatura
- UC-04: Realizar Login e Validar Status Financeiro
- UC-05: Recuperar e Redefinir Senha via Mensagem Eletrônica
- UC-06: Selecionar e Alternar Startup em Uso (Seletor de Contexto)
- UC-07: Fazer Logoff
- UC-08: Gerenciar Perfil do Consultor e Dados da Consultoria
- UC-09: Cadastrar Startup
- UC-10: Gerenciar Perfil da Startup (Empresas)
- UC-11: Fazer Busca Automatizada de Dados por CNPJ
- UC-12: Configurar Preferências e Tags de Nicho da Startup
- UC-13: Mostrar Editais (Listagem Global Centralizada)
- UC-14: Buscar Editais por Termos e Palavras-Chave
- UC-15: Filtrar Editais por Metadados Combinados
- UC-16: Visualizar Detalhes de um Edital
- UC-17: Favoritar Edital por Startup Ativa
- UC-18: Visualizar Editais Favoritos da Startup
- UC-19: Ir para o Site Oficial do Edital
- UC-20: Inserir ou Revisar PITCH da Startup na Geração de Minuta
- UC-21: Gerar Minuta via Roteador Inteligente Multi-LLM
- UC-22: Editar Minuta Gerada com Versionamento Imutável
- UC-23: Exportar Minuta (Formatos Word e PDF)
- UC-24: Deletar Minuta do Histórico
- UC-25: Visualizar Histórico e Versões de Minutas Geradas
- UC-26: Processar Anexo do Edital e Gerar Checklist
- UC-27: Calcular Score de Elegibilidade (%) e Notificar Oportunidades (Match)
- UC-28: Monitorar e Notificar Prazos Críticos de Editais e Kanban
- UC-29: Extrair e Armazenar em Cache Dados Empresariais da Receita Federal
- UC-30: Verificar Notificações no Painel
- UC-31: Visualizar Quadro KANBAN por Startup
- UC-32: Gerenciar e Movimentar Tickets no KANBAN
- UC-33: Criar Tickets e Definir Prazos no KANBAN
- UC-34: Anexar Arquivos e Certidões no KANBAN
- UC-35: Visualizar Tabela de Usuários Secundários
- UC-36: Criar Perfil de Acesso Secundário (RBAC)
- UC-37: Editar Perfil de Acesso Secundário
- UC-38: Excluir Perfil de Acesso Secundário
- UC-39: Realizar Mineração Automatizada de Editais (Web Scraping)
- UC-40: Acessar Dashboard Administrativo Global (Back-Office ADM)
- UC-41: Acessar Portal do Cliente Startup (White-Label Modo Leitura)
- UC-42: Cancelamento Voluntário de Conta Ativa e Exclusão Definitiva (LGPD)
- UC-43: Visualizar Plano de Assinatura, Consumo de Cotas e Renovar Ciclo Mensal
- UC-44: Mudar Plano de Assinatura (Upgrade e Downgrade)
- UC-45: Consultar Janela de Pagamento Anterior (Conta Pendente)
- UC-46: Gerar Nova Cobrança ou Trocar Plano na Pendência
- UC-47: Cancelar Pagamento Pendente e Excluir Conta Inicial
- UC-48: Ressincronizar Dados da Startup sob Demanda com a Receita Federal
- UC-49: Acessar Portal de Distribuição Mobile e QR Code
- UC-50: Confirmar E-mail no Cadastro e Registrar Aceite LGPD
- UC-51: Notificar Vencimento Próximo da Assinatura Mensal
- UC-52: Realizar Pagamento ou Renovação de Assinatura via Cartão de Crédito
- UC-53: Realizar Login Exclusivo da Startup no Portal White-Label
- UC-54: Integrar e Enviar Alertas Operacionais via WhatsApp

---

## UC-01: Realizar Pré-Cadastro e Validar Disponibilidade de E-mail
• **Finalidade/Objetivo:** Iniciar a criação de uma nova conta na plataforma, validando previamente se o endereço de e-mail está disponível e retendo os dados temporariamente na memória da sessão do navegador, evitando a gravação precoce de registros incompletos no banco de dados.
• **Ator(es):** Consultor Administrador.
• **Pré-Condições:** O endereço de e-mail informado não deve estar cadastrado no sistema.
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário acessa a tela de cadastro da plataforma.
2. `[Ação do Ator]` O usuário preenche obrigatoriamente o Nome da Consultoria, o Nome Completo do titular, o E-mail e a Senha (mínimo de 8 caracteres) e solicita o avanço para a escolha de plano.
3. `[Ação do Sistema]` O sistema consulta a disponibilidade do endereço de e-mail informado.
4. `[Ação do Sistema]` Confirmada a disponibilidade, o sistema armazena os dados temporariamente na memória da sessão do navegador (sem persistência no banco de dados) e direciona o usuário para a tela de seleção de planos de assinatura.
• **Fluxos Alternativos:**
- **A1 - Endereço de e-mail já cadastrado:**
  1. `[Ação do Sistema]` O sistema recusa a operação, informa que o e-mail já se encontra registrado na base e apresenta um atalho direto para a tela de login, permitindo o acesso ou a regularização de eventual pendência financeira.
- **A2 - Retorno à tela de cadastro:**
  1. `[Ação do Ator]` O usuário aciona a opção de voltar à tela de cadastro.
  2. `[Ação do Sistema]` O sistema restaura automaticamente os dados previamente informados a partir da memória temporária da sessão do navegador, permitindo a edição sem bloqueio de duplicidade.

## UC-02: Escolher Plano de Assinatura e Gerar Cobrança Inicial
• **Finalidade/Objetivo:** Permitir que o usuário visualize os planos de assinatura disponíveis com seus respectivos limites operacionais, selecione o modelo desejado, efetive a criação da conta com situação pendente e seja direcionado ao ambiente seguro de cobrança via PIX.
• **Ator(es):** Consultor Administrador, Gateway de Pagamento.
• **Pré-Condições:** O usuário deve possuir dados válidos de pré-cadastro retidos na memória temporária da sessão do navegador (UC-01).
• **Fluxo Principal:**
1. `[Ação do Sistema]` O sistema exibe os cartões dos planos de assinatura disponíveis (Standard, Pro e Enterprise) apresentando seus respectivos preços e limites operacionais de startups cadastradas, membros de equipe, minutas mensais geradas por Inteligência Artificial e capacidade de armazenamento de documentos no Kanban.
2. `[Ação do Ator]` O usuário seleciona o plano desejado.
3. `[Ação do Sistema]` O sistema inicia uma transação segura, cria o registro do usuário com o perfil de Consultor Administrador e a agência vinculada com status de pagamento pendente e validade de expiração de 60 minutos.
4. `[Ação do Sistema]` O sistema comunica-se com o gateway de pagamento enviando chave única de controle de transação (evitando duplicidades) e gera uma cobrança avulsa via PIX.
5. `[Ação do Sistema]` O sistema salva a identificação e o endereço seguro da cobrança na conta da agência, limpa os dados temporários do pré-cadastro no navegador e redireciona o usuário para o ambiente de pagamento do gateway financeiro.
• **Fluxos Alternativos:**
Não se aplica.

## UC-03: Processar Pagamento via Webhook e Ativar Assinatura
• **Finalidade/Objetivo:** Receber e processar de forma assíncrona as notificações de confirmação de pagamento emitidas pelo gateway financeiro, ativando ou renovando cumulativamente a assinatura mensal da consultoria.
• **Ator(es):** Gateway de Pagamento, Sistema.
• **Pré-Condições:** Existir uma cobrança pendente gerada no gateway financeiro para a consultoria (UC-02 ou UC-46).
• **Fluxo Principal:**
1. `[Ação do Ator]` O gateway de pagamento envia uma notificação assíncrona informando a confirmação da liquidação financeira do PIX ou do cartão de crédito.
2. `[Ação do Sistema]` O sistema autentica a procedência da notificação, valida a integridade dos dados e localiza a conta da consultoria vinculada à cobrança.
3. `[Ação do Sistema]` O sistema altera a situação financeira da consultoria para ativa e define a vigência de 30 dias da assinatura de forma acumulativa (somando +30 dias à data de vencimento vigente caso a renovação tenha sido paga antes de expirar, garantindo zero perda de dias restantes, ou definindo 30 dias a partir da data atual caso seja o primeiro pagamento ou a assinatura estivesse vencida), encerrando a cobrança aberta.
4. `[Ação do Sistema]` O sistema libera integralmente as cotas operacionais de startups, assentos de equipe, minutas de Inteligência Artificial e armazenamento permitidas pelo plano contratado.
• **Fluxos Alternativos:**
- **A1 - Notificação de pagamento expirado ou cancelado:**
  1. `[Ação do Sistema]` O sistema registra o cancelamento da cobrança no histórico financeiro e mantém a conta com status pendente até que uma nova cobrança seja gerada (UC-46) ou a conta seja cancelada pelo usuário (UC-47).

## UC-04: Realizar Login e Validar Status Financeiro
• **Finalidade/Objetivo:** Autenticar usuários registrados na plataforma, verificar suas permissões de acesso e validar a situação da assinatura mensal da consultoria, direcionando o usuário para o painel operacional correspondente ou para a regularização financeira.
• **Ator(es):** Usuário (Geral).
• **Pré-Condições:** Possuir cadastro prévio na plataforma.
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário informa seu e-mail e senha na tela de acesso e confirma a autenticação.
2. `[Ação do Sistema]` O sistema valida as credenciais informadas, confere a senha criptografada, identifica o perfil de acesso e verifica se a consultoria possui assinatura ativa e dentro do prazo de vigência de 30 dias.
3. `[Ação do Sistema]` Confirmada a situação ativa e regular, o sistema emite a credencial de sessão segura, define as permissões do usuário e o redireciona para o painel principal de trabalho.
• **Fluxos Alternativos:**
- **A1 - Credenciais de acesso incorretas:**
  1. `[Ação do Sistema]` O sistema recusa o acesso, informa a incompatibilidade de e-mail ou senha e solicita nova digitação.
- **A2 - Conta com pagamento inicial pendente:**
  1. `[Ação do Sistema]` O sistema detecta que o cadastro possui pendência financeira inicial, gera uma credencial de acesso temporário restrito e redireciona o usuário para a tela dedicada de regularização financeira, onde ele poderá consultar a cobrança anterior (UC-45), gerar nova cobrança (UC-46) ou cancelar a conta (UC-47).
- **A3 - Acesso de Usuário do tipo Startup (Cliente):**
  1. `[Ação do Sistema]` O sistema identifica o perfil de cliente Startup e direciona o usuário exclusivamente para o ambiente de visualização do Portal White-Label (UC-41 / UC-53).

## UC-05: Recuperar e Redefinir Senha via Mensagem Eletrônica
• **Finalidade/Objetivo:** Permitir a recuperação de senha esquecida exclusivamente pelo titular da consultoria, mediante validação estrita da existência do e-mail na base de dados e envio de link temporário seguro com validade de 60 minutos.
• **Ator(es):** Consultor Administrador, Administrador Global.
• **Pré-Condições:** O usuário titular deve possuir e-mail previamente cadastrado na plataforma.
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário acessa a opção de redefinição de senha na tela de login, informa seu endereço de e-mail e solicita a recuperação.
2. `[Ação do Sistema]` O sistema verifica a existência do e-mail na base de dados e valida se o perfil do usuário possui permissão para recuperação autônoma (Consultor Administrador ou Administrador Global).
3. `[Ação do Sistema]` O sistema gera um código de recuperação seguro com validade de 60 minutos e envia uma mensagem eletrônica ao endereço do solicitante contendo o link de redefinição.
4. `[Ação do Ator]` O usuário acessa sua caixa postal, aciona o link de recuperação, informa sua nova senha e a confirma.
5. `[Ação do Sistema]` O sistema valida a autenticidade e a vigência do código temporário, atualiza a senha de forma criptografada, invalida o código utilizado e redireciona o usuário para o login.
• **Fluxos Alternativos:**
- **A1 - Endereço de e-mail não localizado na base:**
  1. `[Ação do Sistema]` O sistema recusa o envio e informa que o endereço informado não possui cadastro ativo na plataforma.
- **A2 - Tentativa de recuperação autônoma por usuário secundário (Consultor Padrão ou Startup):**
  1. `[Ação do Sistema]` O sistema identifica que a conta solicitante pertence a um perfil secundário, bloqueia o envio da mensagem de recuperação e orienta o usuário a solicitar a redefinição diretamente ao Consultor Administrador da sua consultoria (UC-37).
- **A3 - Código de recuperação expirado ou inválido:**
  1. `[Ação do Sistema]` O sistema informa que o link de recuperação perdeu a validade de 60 minutos e solicita a emissão de um novo pedido.

## UC-06: Selecionar e Alternar Startup em Uso (Seletor de Contexto)
• **Finalidade/Objetivo:** Permitir ao consultor definir qual startup de sua carteira será o foco operacional da sessão ou alternar dinamicamente a empresa ativa a partir do seletor global do menu principal, sem necessidade de realizar novo login.
• **Ator(es):** Consultor Administrador, Consultor Padrão.
• **Pré-Condições:** Estar autenticado na plataforma e possuir ao menos uma startup cadastrada na carteira da consultoria.
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário aciona o seletor de contexto de startups localizado na barra superior de navegação.
2. `[Ação do Sistema]` O sistema lista todas as startups cadastradas na carteira da consultoria.
3. `[Ação do Ator]` O usuário seleciona a startup desejada como foco de trabalho.
4. `[Ação do Sistema]` O sistema atualiza o contexto ativo na sessão, ajustando dinamicamente todas as telas subsequentes (Kanban, Favoritos, Histórico de Minutas e Indicadores) para apresentarem exclusivamente as informações da empresa selecionada.
• **Fluxos Alternativos:**
Não se aplica.

## UC-07: Fazer Logoff
• **Finalidade/Objetivo:** Encerrar a sessão de trabalho do usuário de forma segura, revogando as credenciais de acesso ativas.
• **Ator(es):** Usuário (Geral).
• **Pré-Condições:** Possuir uma sessão autenticada ativa no sistema.
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário aciona a opção de sair no menu da interface.
2. `[Ação do Sistema]` O sistema revoga a credencial da sessão atual, limpa todos os dados temporários e contextos armazenados no navegador e redireciona o usuário para a tela inicial de autenticação.
• **Fluxos Alternativos:**
Não se aplica.

## UC-08: Gerenciar Perfil do Consultor e Dados da Consultoria
• **Finalidade/Objetivo:** Permitir que o consultor autenticado visualize e atualize seus próprios dados cadastrais e altere sua senha de acesso por meio de janela modal global, bem como possibilite ao administrador titular atualizar dados fiscais e o logotipo da consultoria.
• **Ator(es):** Consultor Administrador, Consultor Padrão.
• **Pré-Condições:** Estar autenticado na plataforma.
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário acessa a opção de gerenciamento de perfil no cabeçalho da aplicação.
2. `[Ação do Sistema]` O sistema exibe a interface com os dados cadastrais do consultor (nome, e-mail e campos para alteração voluntária de senha) e, caso seja o Consultor Administrador, exibe também os campos fiscais e cadastrais da agência de consultoria e a área para upload de logotipo.
3. `[Ação do Ator]` O usuário modifica as informações desejadas, informa a senha atual se for alterar a credencial e confirma o salvamento.
4. `[Ação do Sistema]` O sistema valida os campos preenchidos, persiste as atualizações cadastrais e reflete a nova identidade visual na aplicação.
• **Fluxos Alternativos:**
- **A1 - Senha atual divergente na alteração de credencial:**
  1. `[Ação do Sistema]` O sistema recusa a alteração de senha, preserva a credencial vigente e informa que a senha atual informada é inválida.

## UC-09: Cadastrar Startup
• **Finalidade/Objetivo:** Incluir uma nova empresa ou startup na carteira de clientes da consultoria, consumindo uma vaga da cota do plano contratado.
• **Ator(es):** Consultor Administrador, Consultor Padrão.
• **Pré-Condições:** Estar autenticado e possuir saldo disponível na cota de startups do plano de assinatura ativo.
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário acessa a carteira de startups e aciona a opção de adicionar nova empresa.
2. `[Ação do Ator]` O usuário preenche os dados iniciais da empresa (CNPJ, Razão Social, Nome Fantasia e área de atuação) e confirma a criação.
3. `[Ação do Sistema]` O sistema verifica se a cota do plano vigente comporta o novo cadastro, persiste a startup vinculada exclusivamente à consultoria, deduz uma vaga da cota operacional e inicializa automaticamente um quadro Kanban isolado para a nova empresa.
• **Fluxos Alternativos:**
- **A1 - Cota máxima de startups atingida:**
  1. `[Ação do Sistema]` O sistema bloqueia a criação da nova startup e exibe recomendação visual de upgrade de plano para ampliação da carteira.

## UC-10: Gerenciar Perfil da Startup (Empresas)
• **Finalidade/Objetivo:** Inserir ou atualizar dados estratégicos e fiscais detalhados da ficha da startup (porte, faturamento, área de atuação, nível de maturidade tecnológica TRL, regime tributário e apresentação institucional Pitch), atualizando o indicador de completude do perfil.
• **Ator(es):** Consultor Administrador, Consultor Padrão.
• **Pré-Condições:** Ter selecionado uma startup ativa na carteira de clientes (UC-06).
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário acessa a tela de edição do perfil da startup em foco.
2. `[Ação do Ator]` O usuário preenche ou modifica informações como porte, faturamento estimado, setor econômico, nível TRL, regime tributário e o texto da apresentação institucional (Pitch) e confirma.
3. `[Ação do Sistema]` O sistema salva os dados atualizados, recalcula o percentual de completude cadastral e atualiza a barra gráfica de progresso no painel da empresa.
• **Fluxos Alternativos:**
Não se aplica.

## UC-11: Fazer Busca Automatizada de Dados por CNPJ
• **Finalidade/Objetivo:** Autopreencher os campos cadastrais da startup utilizando exclusivamente o número do CNPJ por meio de integração com bases públicas de dados da Receita Federal.
• **Ator(es):** Consultor Administrador, Consultor Padrão.
• **Pré-Condições:** Estar na tela de cadastro (UC-09) ou de edição do perfil da startup (UC-10).
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário digita o número do CNPJ da empresa no campo correspondente e solicita a busca automática.
2. `[Ação do Sistema]` O sistema realiza a validação matemática prévia dos dígitos verificadores do CNPJ e aciona o serviço de extração de dados empresariais (UC-29).
3. `[Ação do Sistema]` O sistema autopreenche automaticamente os campos do formulário com Razão Social, Nome Fantasia, Atividade Econômica Principal (CNAE), Porte, Endereço Completo e Situação Cadastral (Ativa ou Baixada).
• **Fluxos Alternativos:**
- **A1 - CNPJ com formato inválido ou não localizado na base governamental:**
  1. `[Ação do Sistema]` O sistema exibe mensagem de alerta informando a inconsistência do documento ou indisponibilidade temporária do serviço externo, permitindo o preenchimento manual dos campos.

## UC-12: Configurar Preferências e Tags de Nicho da Startup
• **Finalidade/Objetivo:** Definir marcadores de nicho de mercado e preferências setoriais e tributárias no perfil da startup para alimentar o algoritmo de cruzamento e cálculo de compatibilidade com editais.
• **Ator(es):** Consultor Administrador, Consultor Padrão.
• **Pré-Condições:** Estar na tela de edição do perfil da startup (UC-10).
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário navega até a área de nichos e preferências dentro do perfil da empresa.
2. `[Ação do Ator]` O usuário insere marcadores de nicho de atuação e seleciona preferências tributárias (como MEI, Simples Nacional ou Lucro Presumido) e confirma.
3. `[Ação do Sistema]` O sistema armazena as preferências vinculadas à startup para utilização contínua no motor de elegibilidade e notificações (UC-27).
• **Fluxos Alternativos:**
Não se aplica.

## UC-13: Mostrar Editais (Listagem Global Centralizada)
• **Finalidade/Objetivo:** Exibir a listagem centralizada contendo todos os editais de fomento minerados e higienizados disponíveis na plataforma, com tempo de resposta ágil e aproveitando as análises de compatibilidade previamente persistidas sem custo computacional repetido.
• **Ator(es):** Usuário (Geral).
• **Pré-Condições:** O usuário deve estar autenticado na plataforma.
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário acessa a central de editais no menu principal.
2. `[Ação do Sistema]` O sistema recupera a relação de editais disponíveis junto com seus metadados essenciais, prazos, órgãos emissores e diagnósticos de compatibilidade já processados, renderizando as oportunidades na interface.
• **Fluxos Alternativos:**
Não se aplica.

## UC-14: Buscar Editais por Termos e Palavras-Chave
• **Finalidade/Objetivo:** Localizar oportunidades específicas de fomento através de busca textual por palavras-chave aplicadas ao título ou ao objetivo do edital.
• **Ator(es):** Usuário (Geral).
• **Pré-Condições:** Estar na visualização da listagem de editais (UC-13).
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário digita os termos desejados no campo de pesquisa textual.
2. `[Ação do Sistema]` O sistema filtra os editais verificando correspondências nos títulos e objetivos das oportunidades e atualiza os resultados exibidos em tela.
• **Fluxos Alternativos:**
- **A1 - Nenhuma oportunidade localizada:**
  1. `[Ação do Sistema]` O sistema exibe um aviso informando que não foram encontrados editais correspondentes aos termos pesquisados.

## UC-15: Filtrar Editais por Metadados Combinados
• **Finalidade/Objetivo:** Refinar a visualização de editais combinando critérios de abrangência geográfica, instituição promotora, situação do certame e indicador de orçamento explícito.
• **Ator(es):** Usuário (Geral).
• **Pré-Condições:** Estar na visualização da listagem de editais (UC-13).
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário seleciona parâmetros nos filtros de Região, Órgão Emissor, Status do Edital ou assinala a opção para exibir exclusivamente editais com valores orçamentários definidos.
2. `[Ação do Sistema]` O sistema processa os critérios combinados e atualiza a relação de oportunidades correspondentes.
• **Fluxos Alternativos:**
Não se aplica.

## UC-16: Visualizar Detalhes de um Edital
• **Finalidade/Objetivo:** Inspecionar as informações completas de um edital específico, incluindo objetivo integral, cronograma, dotação orçamentária, regras de elegibilidade, lista de checagem documental e ações para elaboração de propostas.
• **Ator(es):** Usuário (Geral).
• **Pré-Condições:** Estar visualizando a listagem global (UC-13) ou a lista de favoritos (UC-18).
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário seleciona o edital que deseja analisar detalhadamente.
2. `[Ação do Sistema]` O sistema apresenta a visão completa do certame com suas diretrizes normativas, prazos, contrapartidas, documentação exigida (UC-26) e botões de ação para favoritar, acessar o portal emissor ou iniciar a elaboração da minuta por Inteligência Artificial.
• **Fluxos Alternativos:**
Não se aplica.

## UC-17: Favoritar Edital por Startup Ativa
• **Finalidade/Objetivo:** Marcar ou desmarcar um edital como favorito, associando a oportunidade exclusivamente à startup selecionada no ambiente de trabalho no momento da ação.
• **Ator(es):** Consultor Administrador, Consultor Padrão.
• **Pré-Condições:** Estar autenticado e com uma startup ativa selecionada no contexto operacional (UC-06).
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário aciona a opção de favoritar no cartão ou na página de detalhes do edital.
2. `[Ação do Sistema]` O sistema registra o vínculo do edital exclusivamente com a startup selecionada na sessão de trabalho.
• **Fluxos Alternativos:**
- **A1 - Remover dos favoritos:**
  1. `[Ação do Ator]` O usuário aciona novamente a opção de favoritar em um edital já marcado.
  2. `[Ação do Sistema]` O sistema desfaz a associação daquele edital com a startup ativa.

## UC-18: Visualizar Editais Favoritos da Startup
• **Finalidade/Objetivo:** Acessar a relação exclusiva de editais favoritados para a startup que estiver em foco no ambiente de trabalho.
• **Ator(es):** Consultor Administrador, Consultor Padrão.
• **Pré-Condições:** Estar autenticado e com uma startup ativa selecionada no contexto (UC-06).
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário seleciona a aba de favoritos na central de editais.
2. `[Ação do Sistema]` O sistema apresenta a listagem das oportunidades salvas especificamente para aquela empresa, permitindo consultar detalhes ou iniciar minutas de projeto.
• **Fluxos Alternativos:**
Não se aplica.

## UC-19: Ir para o Site Oficial do Edital
• **Finalidade/Objetivo:** Encaminhar o usuário para a página oficial da agência de fomento ou entidade governamental responsável pela publicação do edital.
• **Ator(es):** Usuário (Geral).
• **Pré-Condições:** Estar visualizando os detalhes de um edital (UC-16).
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário clica na opção de acessar a fonte oficial do edital.
2. `[Ação do Sistema]` O sistema abre uma nova aba de navegação direcionando o usuário para o endereço eletrônico oficial da oportunidade.
• **Fluxos Alternativos:**
Não se aplica.

## UC-20: Inserir ou Revisar PITCH da Startup na Geração de Minuta
• **Finalidade/Objetivo:** Apresentar interface de contexto exigindo a inserção ou confirmação do resumo institucional e mercadológico da startup antes de disparar a geração da minuta de proposta por Inteligência Artificial.
• **Ator(es):** Consultor Administrador, Consultor Padrão.
• **Pré-Condições:** Estar visualizando um edital (UC-16) com uma startup ativa selecionada (UC-06) e acionar a elaboração de minuta.
• **Fluxo Principal:**
1. `[Ação do Sistema]` Ao ser solicitada a elaboração de proposta, o sistema exibe uma janela de contexto carregando o texto institucional salvo no cadastro da startup (caso existente) ou solicitando sua digitação.
2. `[Ação do Ator]` O usuário insere, complementa ou ajusta a apresentação do projeto voltada aos objetivos daquele edital específico e confirma o início da redação.
• **Fluxos Alternativos:**
Não se aplica.

## UC-21: Gerar Minuta via Roteador Inteligente Multi-LLM
• **Finalidade/Objetivo:** Redigir automaticamente a proposta técnica de subvenção econômica combinando a apresentação e os dados isolados da startup ativa com as normas extraídas do edital, utilizando um roteador de múltiplos modelos de Inteligência Artificial com sorteio de chaves e contingência automática em quatro níveis.
• **Ator(es):** Consultor Administrador, Consultor Padrão, Roteador de Modelos de Inteligência Artificial.
• **Pré-Condições:** Ter confirmado a apresentação institucional (UC-20) e possuir saldo disponível na cota mensal de geração de minutas por Inteligência Artificial definida pelo plano contratado (Standard: 3, Pro: 15, Enterprise: 50).
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário confirma o comando para geração da proposta com Inteligência Artificial.
2. `[Ação do Sistema]` O sistema verifica previamente o saldo disponível na cota mensal de geração de minutas por Inteligência Artificial da consultoria, isola estritamente o contexto da startup ativa (impedindo qualquer compartilhamento de dados entre empresas) e estrutura as instruções da proposta técnica associando a apresentação da empresa às regras e exigências do edital.
3. `[Ação do Sistema]` O roteador inteligente seleciona uma chave de acesso por sorteio aleatório e submete a requisição operando sob uma cascata de contingência automática de quatro níveis (Provedor Primário -> Provedor Secundário -> Provedor Terciário -> Mecanismo Determinístico Local de Emergência).
4. `[Ação do Sistema]` O sistema debita 1 unidade da cota mensal de minutas por IA do plano da consultoria, persiste a primeira versão da proposta no histórico (registrando data, autor e modelo utilizado) e disponibiliza o texto formatado no editor interativo em tela, com opção para cópia com um clique.
• **Fluxos Alternativos:**
- **A1 - Limite de cota mensal de minutas atingido:**
  1. `[Ação do Sistema]` O sistema bloqueia a geração antes de acionar os provedores de Inteligência Artificial e exibe aviso sugerindo o upgrade de plano.

## UC-22: Editar Minuta Gerada com Versionamento Imutável
• **Finalidade/Objetivo:** Permitir ao consultor realizar modificações textuais e formatações diretamente na proposta gerada através de editor em tela e, ao salvar, registrar automaticamente uma nova versão encadeada que preserve o histórico anterior.
• **Ator(es):** Consultor Administrador, Consultor Padrão.
• **Pré-Condições:** Possuir uma minuta aberta no editor de texto da plataforma (UC-21 ou UC-25).
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário edita o conteúdo da proposta dentro do editor de texto, aplicando correções, inclusões ou formatações.
2. `[Ação do Ator]` O usuário solicita o salvamento das alterações realizadas.
3. `[Ação do Sistema]` O sistema cria automaticamente um novo registro de versão sequencial encadeado à versão anterior, preservando o histórico imutável das versões passadas e registrando o autor da edição, sem descontar da cota mensal de gerações de Inteligência Artificial da consultoria.
• **Fluxos Alternativos:**
Não se aplica.

## UC-23: Exportar Minuta (Formatos Word e PDF)
• **Finalidade/Objetivo:** Disponibilizar a exportação do conteúdo da minuta para arquivo editável no formato Word (.docx), preservando a estrutura de documentos profissionais, ou para formato PDF padronizado para impressão.
• **Ator(es):** Consultor Administrador, Consultor Padrão.
• **Pré-Condições:** Estar visualizando uma minuta gerada ou salva na plataforma (UC-21, UC-22 ou UC-25).
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário aciona a opção de exportação escolhendo entre o formato Word (.docx) ou PDF.
2. `[Ação do Sistema]` Caso selecionado o formato Word, o sistema gera o arquivo estruturado preservando títulos, parágrafos e formatações e inicia o descarregamento imediato no dispositivo do usuário.
3. `[Ação do Sistema]` Caso selecionado o formato PDF, o sistema renderiza o documento estruturado e otimizado para folha no padrão A4, disponibilizando o arquivo pronto para download ou impressão.
• **Fluxos Alternativos:**
Não se aplica.

## UC-24: Deletar Minuta do Histórico
• **Finalidade/Objetivo:** Excluir em caráter permanente um rascunho de minuta registrado no histórico de uma startup.
• **Ator(es):** Consultor Administrador, Consultor Padrão.
• **Pré-Condições:** Estar na tela de histórico de minutas da startup ativa (UC-25).
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário identifica a proposta que deseja excluir e aciona o comando de remoção.
2. `[Ação do Sistema]` O sistema apresenta mensagem de confirmação alertando sobre o caráter irreversível da exclusão.
3. `[Ação do Ator]` O usuário confirma a operação de exclusão.
4. `[Ação do Sistema]` O sistema remove definitivamente o registro da minuta da base de dados e atualiza a listagem do histórico.
• **Fluxos Alternativos:**
Não se aplica.

## UC-25: Visualizar Histórico e Versões de Minutas Geradas
• **Finalidade/Objetivo:** Apresentar a relação completa de propostas e versões encadeadas vinculadas exclusivamente à startup ativa, permitindo sua reabertura para leitura, edição ou exportação.
• **Ator(es):** Consultor Administrador, Consultor Padrão, Startup (Modo Somente Leitura no Portal White-Label).
• **Pré-Condições:** Estar autenticado e com uma startup ativa selecionada no contexto operacional (UC-06).
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário acessa a seção de histórico de minutas no menu da aplicação.
2. `[Ação do Sistema]` O sistema recupera e exibe exclusivamente as minutas vinculadas à startup ativa, exibindo título do edital, identificação sequencial das versões, autor da última alteração, tecnologia de inteligência artificial empregada e data.
3. `[Ação do Ator]` O usuário seleciona uma versão do documento para visualizá-la ou reabri-la no editor de texto.
• **Fluxos Alternativos:**
Não se aplica.

## UC-26: Processar Anexo do Edital e Gerar Checklist
• **Finalidade/Objetivo:** Realizar a leitura automatizada e processamento de linguagem natural nos documentos oficiais dos editais de forma assíncrona para extrair regras, prazos, contrapartidas e disponibilizar uma lista interativa de checagem documental.
• **Ator(es):** Sistema (Processamento Assíncrono de Fila), Modelos de Inteligência Artificial.
• **Pré-Condições:** O edital captado possuir documento normativo disponível para análise (UC-39).
• **Fluxo Principal:**
1. `[Ação do Sistema]` O sistema executa em segundo plano a extração textual do documento oficial anexado ao edital.
2. `[Ação do Sistema]` O sistema processa o conteúdo textual através de inteligência artificial para estruturar regras de enquadramento, cronograma de marcos, contrapartidas e obrigações documentais.
3. `[Ação do Sistema]` O sistema persiste as informações estruturadas diretamente no registro do edital na base de dados (evitando reprocessamentos) e disponibiliza a lista interativa de checagem documental na visualização detalhada da oportunidade (UC-16).
• **Fluxos Alternativos:**
Não se aplica.

## UC-27: Calcular Score de Elegibilidade (%) e Notificar Oportunidades (Match)
• **Finalidade/Objetivo:** Realizar o cruzamento das informações da startup ativa (setor, porte, maturidade tecnológica e orçamento) com as diretrizes do edital, calculando a porcentagem de compatibilidade, apontando pontos fortes e de atenção, e emitindo alertas de oportunidade.
• **Ator(es):** Consultor Administrador, Consultor Padrão, Sistema.
• **Pré-Condições:** Existir startup cadastrada com perfil e preferências configuradas (UC-10 e UC-12) e editais processados no sistema.
• **Fluxo Principal:**
1. `[Ação do Sistema]` De forma automática ao minerar novos certames ou quando solicitado na análise da oportunidade, o sistema cruza os dados estratégicos da startup ativa com as exigências do edital.
2. `[Ação do Sistema]` O sistema calcula o índice percentual de compatibilidade e estrutura a justificativa composta pela lista de pontos fortes de aderência e lista de pontos de atenção que requerem cuidado, persistindo o resultado no banco.
3. `[Ação do Sistema]` Ao constatar compatibilidade satisfatória, o sistema destaca visualmente a oportunidade na interface e registra alerta aos consultores vinculados à empresa.
• **Fluxos Alternativos:**
Não se aplica.

## UC-28: Monitorar e Notificar Prazos Críticos de Editais e Kanban
• **Finalidade/Objetivo:** Monitorar continuamente os prazos de editais e cartões em andamento no Kanban em uma janela de até 15 dias, classificando-os de forma escalonada e disparando alertas internos na central de notificações e por mensagem eletrônica aos consultores responsáveis.
• **Ator(es):** Sistema (Rotina Automática de Monitoramento), Consultor Administrador, Consultor Padrão.
• **Pré-Condições:** Existir cartão no Kanban ou edital com data limite definida.
• **Fluxo Principal:**
1. `[Ação do Sistema]` O sistema executa rotina contínua de monitoramento verificando as datas limites de todos os cartões e editais vinculados às startups.
2. `[Ação do Sistema]` O sistema classifica os prazos em uma janela de até 15 dias de forma escalonada em Atenção (8 a 15 dias), Urgente (4 a 7 dias) e Crítico (72 horas e 24 horas finais).
3. `[Ação do Sistema]` O sistema dispara alertas internos na central de notificações (com aviso flutuante em tela) e envia mensagens eletrônicas aos consultores responsáveis pela startup.
• **Fluxos Alternativos:**
Não se aplica.

## UC-29: Extrair e Armazenar em Cache Dados Empresariais da Receita Federal
• **Finalidade/Objetivo:** Realizar a integração com serviços de consulta cadastral pública a partir do CNPJ, armazenando as respostas em cache temporário por 7 dias para evitar sobrecarga e contornar limites de requisições.
• **Ator(es):** Sistema (Serviço de Consulta Cadastral).
• **Pré-Condições:** Ser acionado pelo fluxo de busca cadastral (UC-11) ou pela ressincronização voluntária (UC-48).
• **Fluxo Principal:**
1. `[Ação do Sistema]` O serviço verifica se as informações daquele CNPJ já constam armazenadas no cache com validade de até 7 dias.
2. `[Ação do Sistema]` Caso não esteja em cache (ou trate-se de uma atualização forçada sob demanda), o sistema consulta a fonte cadastral principal e, em caso de indisponibilidade ou lentidão, recorre automaticamente ao provedor secundário de contingência.
3. `[Ação do Sistema]` O sistema padroniza as informações recebidas (Razão Social, Nome Fantasia, CNAE, Porte, Endereço e Situação Cadastral), grava o registro no cache por 7 dias e disponibiliza os dados para o preenchimento automático.
• **Fluxos Alternativos:**
Não se aplica.

## UC-30: Verificar Notificações no Painel
• **Finalidade/Objetivo:** Permitir ao usuário consultar a central de alertas sobre compatibilidade de editais, prazos críticos de encerramento no Kanban e avisos operacionais de limites de cotas.
• **Ator(es):** Usuário (Geral).
• **Pré-Condições:** Estar autenticado na plataforma.
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário clica no indicador de notificações na barra superior do sistema.
2. `[Ação do Sistema]` O sistema exibe o painel com as notificações recentes organizadas por relevância, permitindo que o usuário clique em um item para ser direcionado diretamente ao edital ou cartão do Kanban envolvido.
• **Fluxos Alternativos:**
Não se aplica.

## UC-31: Visualizar Quadro KANBAN por Startup
• **Finalidade/Objetivo:** Apresentar o painel visual Kanban estruturado e isolado da startup ativa selecionada, permitindo o acompanhamento de todos os processos de inscrição em editais divididos por fases.
• **Ator(es):** Consultor Administrador, Consultor Padrão, Startup (Modo Somente Leitura).
• **Pré-Condições:** Estar autenticado com uma startup ativa selecionada no ambiente de trabalho (UC-06).
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário acessa a opção de Kanban no menu da aplicação.
2. `[Ação do Sistema]` O sistema identifica a startup selecionada no contexto e renderiza o quadro Kanban exclusivo daquela empresa, exibindo as colunas de etapas do fluxo e os cartões de processos em tramitação.
• **Fluxos Alternativos:**
Não se aplica.

## UC-32: Gerenciar e Movimentar Tickets no KANBAN
• **Finalidade/Objetivo:** Modificar o estágio de um processo de submissão arrastando o cartão correspondente entre as colunas do quadro Kanban, além de possibilitar a edição de informações descritivas dos cartões.
• **Ator(es):** Consultor Administrador, Consultor Padrão.
• **Pré-Condições:** Estar visualizando o quadro Kanban da startup ativa (UC-31).
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário arrasta um cartão de processo de uma coluna e o solta em outra coluna de fase (ou abre o cartão para editar seu título, anotações ou valor estimado).
2. `[Ação do Sistema]` O sistema atualiza imediatamente a nova etapa e as informações editadas na base de dados e reflete a mudança visualmente na tela de todos os membros que estejam acompanhando o quadro.
• **Fluxos Alternativos:**
- **A1 - Exclusão de cartão do Kanban:**
  1. `[Ação do Ator]` O usuário solicita a exclusão de um cartão específico e confirma a ação.
  2. `[Ação do Sistema]` O sistema remove o cartão do quadro e elimina os registros e referências vinculados àquele processo.

## UC-33: Criar Tickets e Definir Prazos no KANBAN
• **Finalidade/Objetivo:** Adicionar novos cartões representando processos distintos de submissão a editais no quadro da startup ativa, vinculando datas limites e informações orçamentárias.
• **Ator(es):** Consultor Administrador, Consultor Padrão.
• **Pré-Condições:** Estar na visualização do quadro Kanban da startup ativa (UC-31).
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário aciona o botão para adicionar novo processo no quadro.
2. `[Ação do Ator]` O usuário preenche o título, descrição, vincula opcionalmente ao edital correspondente, define o valor estimado do projeto, estabelece a data limite de encerramento e confirma.
3. `[Ação do Sistema]` O sistema cria o cartão vinculado exclusivamente àquela startup e o posiciona na coluna inicial de tramitação do quadro.
• **Fluxos Alternativos:**
Não se aplica.

## UC-34: Anexar Arquivos e Certidões no KANBAN
• **Finalidade/Objetivo:** Realizar o envio de documentos comprobatórios (como certidões, declarações e relatórios) vinculados a um cartão do Kanban, respeitando os formatos aceitos, limites de tamanho e cota de armazenamento da consultoria.
• **Ator(es):** Consultor Administrador, Consultor Padrão.
• **Pré-Condições:** Estar com os detalhes de um cartão do Kanban abertos na interface (UC-31/UC-32).
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário acessa a área de anexos dentro do cartão do Kanban e seleciona os arquivos desejados no seu computador.
2. `[Ação do Sistema]` O sistema valida o formato do documento, confere se o tamanho está dentro do limite máximo de 10 megabytes por arquivo e verifica se a consultoria possui espaço suficiente na sua cota de armazenamento total estabelecida pelo plano.
3. `[Ação do Sistema]` O sistema armazena o arquivo com segurança, vincula suas informações ao cartão do Kanban e disponibiliza a opção de visualização e download para a equipe.
• **Fluxos Alternativos:**
- **A1 - Arquivo inválido ou limite de armazenamento atingido:**
  1. `[Ação do Sistema]` O sistema recusa o envio do arquivo, informa o motivo da rejeição (formato incompatível, arquivo superior a 10 megabytes ou cota de armazenamento do plano esgotada) e orienta o usuário sobre o espaço restante ou a possibilidade de realizar um upgrade de plano.

## UC-35: Visualizar Tabela de Usuários Secundários
• **Finalidade/Objetivo:** Permitir ao titular da consultoria visualizar e gerenciar todos os membros de sua equipe (Consultores Padrão) e acessos de clientes (Startups) criados sob a sua conta.
• **Ator(es):** Consultor Administrador.
• **Pré-Condições:** Estar autenticado com o perfil exclusivo de Consultor Administrador.
• **Fluxo Principal:**
1. `[Ação do Ator]` O administrador acessa a área de gestão de equipe e acessos.
2. `[Ação do Sistema]` O sistema confere as permissões de administrador titular e exibe a tabela com a relação de todos os usuários secundários criados, informando nomes, e-mails, papéis atribuídos e empresas vinculadas.
• **Fluxos Alternativos:**
- **A1 - Tentativa de acesso por usuário secundário:**
  1. `[Ação do Sistema]` O sistema bloqueia a visualização da tela de gestão de usuários e apresenta aviso de permissão insuficiente.

## UC-36: Criar Perfil de Acesso Secundário (RBAC)
• **Finalidade/Objetivo:** Permitir ao Consultor Administrador cadastrar novos acessos para membros de equipe com perfil de Consultor Padrão ou para representantes de clientes com perfil de Startup, respeitando as cotas contratadas no plano.
• **Ator(es):** Consultor Administrador.
• **Pré-Condições:** Estar na área de gestão de equipe (UC-35) e possuir saldo disponível na cota de assentos de equipe do plano de assinatura.
• **Fluxo Principal:**
1. `[Ação do Ator]` O administrador aciona o comando para criar novo acesso secundário, informa nome, e-mail, senha inicial e seleciona o perfil desejado (Consultor Padrão para equipe interna ou Startup associada a uma empresa específica da carteira).
2. `[Ação do Sistema]` O sistema valida se o plano contratado possui assentos disponíveis, cria a credencial vinculada à consultoria e debita uma unidade da cota de equipe.
• **Fluxos Alternativos:**
- **A1 - Cota máxima de usuários atingida:**
  1. `[Ação do Sistema]` O sistema impede a inclusão do novo usuário e exibe alerta sugerindo a realização de upgrade de plano.

## UC-37: Editar Perfil de Acesso Secundário
• **Finalidade/Objetivo:** Permitir ao Consultor Administrador alterar os dados cadastrais ou redefinir diretamente a senha de acesso de usuários secundários criados por ele.
• **Ator(es):** Consultor Administrador.
• **Pré-Condições:** Estar na listagem de usuários secundários (UC-35).
• **Fluxo Principal:**
1. `[Ação do Ator]` O administrador localiza o usuário secundário na tabela e seleciona a opção de edição.
2. `[Ação do Ator]` O administrador altera os dados cadastrais ou informa uma nova senha para a conta selecionada e salva as modificações.
3. `[Ação do Sistema]` O sistema atualiza o cadastro e registra a nova credencial criptografada no banco de dados.
• **Fluxos Alternativos:**
Não se aplica.

## UC-38: Excluir Perfil de Acesso Secundário
• **Finalidade/Objetivo:** Revogar o acesso de um consultor padrão ou de uma credencial de startup, liberando a respectiva vaga na cota de usuários do plano.
• **Ator(es):** Consultor Administrador.
• **Pré-Condições:** Estar na listagem de usuários secundários (UC-35).
• **Fluxo Principal:**
1. `[Ação do Ator]` O administrador localiza o usuário secundário, aciona a opção de exclusão e confirma a ação.
2. `[Ação do Sistema]` O sistema invalida imediatamente todas as credenciais ativas daquela conta, remove o registro e restabelece uma vaga disponível na cota do plano de assinatura da consultoria.
• **Fluxos Alternativos:**
Não se aplica.

## UC-39: Realizar Mineração Automatizada de Editais (Web Scraping)
• **Finalidade/Objetivo:** Varrer portais de fomento nacionais e estaduais (incluindo FINEP e Fundações Estaduais de Amparo à Pesquisa como SC, SP, MG, RJ, RS, BA, PE, GO e DF) por meio de rotinas automatizadas em segundo plano para captar oportunidades, higienizar textos e evitar duplicidades via chave unívoca.
• **Ator(es):** Sistema (Rotina Automatizada de Coleta de Dados).
• **Pré-Condições:** Rotinas programadas e infraestrutura de execução ativas no servidor.
• **Fluxo Principal:**
1. `[Ação do Sistema]` O robô de coleta de dados é executado de forma assíncrona em segundo plano, varrendo os portais oficiais de fomento em âmbito nacional e estadual.
2. `[Ação do Sistema]` O sistema extrai títulos, objetivos, prazos, orçamentos, links de acesso e documentos anexos, aplicando rotinas de higienização e padronização do texto.
3. `[Ação do Sistema]` O sistema gera uma chave unívoca de identificação para cada edital captado, verifica na base se já existe para impedir duplicidades e persiste apenas registros novos ou atualizados, enfileirando os documentos para extração de regras e checklist (UC-26).
• **Fluxos Alternativos:**
Não se aplica.

## UC-40: Acessar Dashboard Administrativo Global (Back-Office ADM)
• **Finalidade/Objetivo:** Disponibilizar visão gerencial exclusiva para administradores da plataforma com indicadores do volume global de assinaturas, consumo consolidado de inteligência artificial e projeção de faturamento da plataforma.
• **Ator(es):** Administrador Global.
• **Pré-Condições:** Possuir credenciais autenticadas de nível de Administrador Global.
• **Fluxo Principal:**
1. `[Ação do Ator]` O administrador global acessa o painel de retaguarda da plataforma.
2. `[Ação do Sistema]` O sistema valida o privilégio de administrador global e renderiza os gráficos de chamadas de inteligência artificial, volume de assinaturas ativas de consultorias e projeção financeira consolidada.
• **Fluxos Alternativos:**
Não se aplica.

## UC-41: Acessar Portal do Cliente Startup (White-Label Modo Leitura)
• **Finalidade/Objetivo:** Disponibilizar uma interface dedicada e otimizada para dispositivos móveis para que representantes de startups acompanhem, em modo exclusivamente de leitura, os cartões do seu Kanban e o histórico de minutas, exibindo a identidade visual e logotipo da consultoria responsável.
• **Ator(es):** Startup (Cliente).
• **Pré-Condições:** Possuir credencial com perfil de Startup criada pelo Consultor Administrador (UC-36).
• **Fluxo Principal:**
1. `[Ação do Ator]` O representante da startup realiza a autenticação na plataforma (UC-04 / UC-53).
2. `[Ação do Sistema]` O sistema identifica o perfil restrito de Startup, aplica dinamicamente o logotipo e as cores da consultoria titular e oculta todas as ferramentas de edição, faturamento e navegação global.
3. `[Ação do Ator]` O cliente acompanha o andamento dos processos no quadro Kanban e consulta os documentos de minutas elaborados para a sua empresa.
• **Fluxos Alternativos:**
Não se aplica.

## UC-42: Cancelamento Voluntário de Conta Ativa e Exclusão Definitiva (LGPD)
• **Finalidade/Objetivo:** Permitir ao Consultor Administrador encerrar voluntariamente e em caráter definitivo a conta e a assinatura da sua consultoria, executando a eliminação física e irreversível em cascata de todos os dados vinculados em conformidade com a Lei Geral de Proteção de Dados (LGPD).
• **Ator(es):** Consultor Administrador.
• **Pré-Condições:** Estar autenticado no sistema com o perfil exclusivo de Consultor Administrador.
• **Fluxo Principal:**
1. `[Ação do Ator]` O Consultor Administrador acessa as configurações de seu perfil e aciona a opção de encerramento de conta e assinatura na Zona de Perigo.
2. `[Ação do Sistema]` O sistema exibe uma janela modal própria apresentando o termo de ciência da Lei Geral de Proteção de Dados (LGPD), alertando sobre a exclusão irreversível de todos os dados da consultoria, startups, quadros Kanban, minutas e membros da equipe.
3. `[Ação do Ator]` O Consultor Administrador digita sua senha atual para reautenticação, marca a caixa de seleção confirmando estar ciente da exclusão definitiva e confirma a operação.
4. `[Ação do Sistema]` O sistema valida a permissão de administrador titular, confere criptograficamente a senha informada e executa, em uma única transação atômica, a revogação imediata de todas as credenciais de acesso e a exclusão definitiva em cascata de todas as minutas, alertas, favoritos, cartões e quadros Kanban, startups, usuários da equipe e o registro da própria agência de consultoria.
5. `[Ação do Sistema]` O sistema encerra a sessão, limpa os dados temporários do navegador e redireciona o usuário para a tela inicial, deixando o e-mail e os CNPJs totalmente livres para eventuais novos cadastros futuros.
• **Fluxos Alternativos:**
- **A1 - Senha atual incorreta ou termo de ciência não marcado:**
  1. `[Ação do Sistema]` O sistema bloqueia o encerramento da conta, mantém todos os dados intactos e solicita a correção da senha ou a marcação obrigatória da confirmação de ciência.
- **A2 - Tentativa de cancelamento por usuário secundário:**
  1. `[Ação do Sistema]` O sistema impede o acesso à função de encerramento de conta para perfis de Consultor Padrão ou Startup.

## UC-43: Visualizar Plano de Assinatura, Consumo de Cotas e Renovar Ciclo Mensal
• **Finalidade/Objetivo:** Permitir ao Consultor Administrador visualizar os detalhes do plano contratado, o consumo atual das cotas operacionais (Startups, Membros de Equipe e Minutas geradas por IA no mês), a forma de pagamento ativa, a data de vencimento da assinatura e realizar a renovação mensal dentro da janela de 5 dias anteriores ao vencimento.
• **Ator(es):** Consultor Administrador, Gateway de Pagamento.
• **Pré-Condições:** Estar autenticado na plataforma.
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário acessa a opção "Visualizar Assinatura" no menu de perfil do cabeçalho global.
2. `[Ação do Sistema]` O sistema exibe a janela modal contendo o nome e valor do plano vigente, a forma de pagamento ativa (PIX ou Cartão), o status da assinatura, a data de expiração do ciclo de 30 dias, a contagem de dias restantes e os indicadores de consumo atual frente ao limite de Startups, Consultores Padrão e Minutas de IA no mês.
3. `[Ação do Ator]` Quando faltam 5 dias ou menos para o vencimento (ou caso a assinatura esteja vencida), o Consultor Administrador aciona o botão para gerar o pagamento de renovação.
4. `[Ação do Sistema]` O sistema preserva o acesso ativo da consultoria durante os dias restantes de vigência, verifica de forma idempotente se já existe uma cobrança de renovação aberta dentro da validade de 60 minutos (reaproveitando-a caso exista ou gerando uma nova no gateway financeiro) e direciona o administrador para concluir o pagamento.
• **Fluxos Alternativos:**
- **A1 - Assinatura com mais de 5 dias restantes para o vencimento:**
  1. `[Ação do Sistema]` O sistema mantém o botão de renovação antecipada desabilitado e informa a data exata em que a janela de renovação será liberada (5 dias antes do vencimento).

## UC-44: Mudar Plano de Assinatura (Upgrade e Downgrade)
• **Finalidade/Objetivo:** Permitir ao Consultor Administrador solicitar a alteração de plano de assinatura, calculando a cobrança proporcional de valores no upgrade ou validando a compatibilidade de uso das cotas no downgrade.
• **Ator(es):** Consultor Administrador, Gateway de Pagamento.
• **Pré-Condições:** Estar na visualização da área de planos e assinaturas da plataforma (UC-43).
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário escolhe um novo plano de assinatura (superior ou inferior) e confirma o pedido de alteração.
2. `[Ação do Sistema]` Em caso de upgrade para plano superior, o sistema calcula o valor proporcional da diferença de mensalidade, gera a cobrança correspondente no gateway financeiro e, após a confirmação do pagamento, expande imediatamente os limites de cotas da consultoria.
3. `[Ação do Sistema]` Em caso de downgrade para plano inferior, o sistema valida se a quantidade atual de startups e membros de equipe respeita os limites da nova cota, atualiza a contratação e ajusta os limites máximos.
• **Fluxos Alternativos:**
- **A1 - Tentativa de Downgrade com uso atual superior às cotas do novo plano:**
  1. `[Ação do Sistema]` O sistema constata que o número de startups cadastradas ou membros da equipe excede a capacidade do plano inferior selecionado, bloqueia a operação de alteração e orienta o administrador a excluir previamente os cadastros excedentes.

## UC-45: Consultar Janela de Pagamento Anterior (Conta Pendente)
• **Finalidade/Objetivo:** Permitir ao usuário com cadastro retido por pendência financeira verificar se a cobrança via PIX gerada anteriormente continua dentro do prazo de validade de 60 minutos e reabrir o link de pagamento sem necessidade de emitir nova fatura.
• **Ator(es):** Consultor Administrador (com pagamento pendente), Gateway de Pagamento.
• **Pré-Condições:** O usuário ter sido direcionado para a tela de regularização de pendência financeira após a tentativa de login (UC-04 - Fluxo A2).
• **Fluxo Principal:**
1. `[Ação do Ator]` Na tela de regularização de pagamento, o usuário aciona a opção para consultar a cobrança emitida anteriormente.
2. `[Ação do Sistema]` O sistema verifica o prazo de validade e consulta a situação da cobrança junto ao gateway financeiro.
3. `[Ação do Sistema]` Ao constatar que a cobrança permanece ativa e dentro do limite de 60 minutos, o sistema reabre a tela segura de pagamento do gateway para conclusão da transação.
• **Fluxos Alternativos:**
- **A1 - Pagamento anterior já compensado no gateway:**
  1. `[Ação do Sistema]` Caso a consulta identifique que a cobrança já foi quitada, o sistema ativa imediatamente a conta da consultoria e libera o acesso aos painéis de trabalho.
- **A2 - Cobrança anterior vencida ou cancelada:**
  1. `[Ação do Sistema]` Caso o prazo de 60 minutos tenha expirado ou a cobrança tenha sido cancelada, o sistema informa a expiração e orienta o usuário a selecionar o plano e emitir uma nova cobrança (UC-46).

## UC-46: Gerar Nova Cobrança ou Trocar Plano na Pendência
• **Finalidade/Objetivo:** Permitir ao usuário que possui conta pendente (ou cuja cobrança tenha expirado) escolher novamente o plano de assinatura desejado e emitir uma nova cobrança única para regularização do acesso.
• **Ator(es):** Consultor Administrador (com pagamento pendente), Gateway de Pagamento.
• **Pré-Condições:** Estar na tela de regularização financeira portando credencial de acesso restrito.
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário aciona a opção para gerar nova cobrança e escolher plano.
2. `[Ação do Sistema]` O sistema direciona o usuário para a seleção de planos de assinatura.
3. `[Ação do Ator]` O usuário seleciona o plano desejado (Standard, Pro ou Enterprise) e confirma a contratação.
4. `[Ação do Sistema]` O sistema atualiza o plano da agência, renova a janela de tolerância de 60 minutos, gera uma nova cobrança com chave única no gateway de pagamento e encaminha o usuário para o ambiente de pagamento.
• **Fluxos Alternativos:**
Não se aplica.

## UC-47: Cancelar Pagamento Pendente e Excluir Conta Inicial
• **Finalidade/Objetivo:** Permitir que o usuário desista de um cadastro cuja assinatura inicial não tenha sido paga, cancelando o processo financeiro e realizando a exclusão definitiva e irreversível dos registros pendentes para liberar imediatamente o e-mail para novos cadastros.
• **Ator(es):** Consultor Administrador (com pagamento pendente).
• **Pré-Condições:** Estar na tela de regularização de pendência financeira e a conta não possuir assinatura aprovada.
• **Fluxo Principal:**
1. `[Ação do Ator]` Na tela de regularização de pendência, o usuário clica na opção para cancelar o pagamento e excluir o cadastro.
2. `[Ação do Sistema]` O sistema apresenta janela de confirmação alertando sobre a eliminação definitiva do cadastro inicial.
3. `[Ação do Ator]` O usuário confirma a exclusão da conta.
4. `[Ação do Sistema]` O sistema valida que a conta realmente encontra-se pendente, cancela eventuais cobranças abertas no gateway, revoga as credenciais temporárias e elimina definitivamente o registro do usuário e da agência da base de dados.
5. `[Ação do Sistema]` O sistema limpa a memória da sessão do navegador e direciona o usuário para a tela inicial de cadastro, liberando o e-mail para novo cadastramento.
• **Fluxos Alternativos:**
Não se aplica.

## UC-48: Ressincronizar Dados da Startup sob Demanda com a Receita Federal
• **Finalidade/Objetivo:** Permitir ao consultor atualizar instantaneamente os dados cadastrais, CNAE, porte, situação cadastral e endereço de uma empresa da carteira consultando novamente a base da Receita Federal com um clique.
• **Ator(es):** Consultor Administrador, Consultor Padrão.
• **Pré-Condições:** Estar na ficha cadastral de uma startup que possua número de CNPJ preenchido (UC-10).
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário clica na opção de sincronizar dados com a Receita Federal na tela de edição do perfil da startup.
2. `[Ação do Sistema]` O sistema executa a consulta cadastral utilizando o CNPJ registrado (UC-29).
3. `[Ação do Sistema]` O sistema atualiza automaticamente os dados cadastrais, situação e endereço na tela e salva as informações renovadas no perfil da empresa.
• **Fluxos Alternativos:**
- **A1 - Indisponibilidade momentânea nos serviços públicos de consulta:**
  1. `[Ação do Sistema]` Caso os provedores governamentais estejam temporariamente inacessíveis, o sistema preserva os dados existentes da startup e apresenta mensagem informativa.

## UC-49: Acessar Portal de Distribuição Mobile e QR Code
• **Finalidade/Objetivo:** Permitir ao usuário acessar a página dedicada de distribuição do aplicativo móvel para baixar diretamente o pacote de instalação para Android, escanear o código QR pelo smartphone ou consultar o guia de instruções de instalação.
• **Ator(es):** Usuário (Geral).
• **Pré-Condições:** Acionar o atalho de acesso ao aplicativo móvel disponível na interface ou na tela de autenticação.
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário aciona a opção de baixar o aplicativo móvel.
2. `[Ação do Sistema]` O sistema exibe o guia passo a passo de instalação no dispositivo Android, apresenta dinamicamente o código QR para leitura direta pela câmera do smartphone e fornece o botão para descarregamento direto do pacote do aplicativo.
3. `[Ação do Ator]` O usuário lê o código QR ou inicia o download do pacote em seu dispositivo para concluir a instalação.
• **Fluxos Alternativos:**
Não se aplica.

## UC-50: Confirmar E-mail no Cadastro e Registrar Aceite LGPD
• **Finalidade/Objetivo:** Garantir que o usuário titular aceite explicitamente os Termos de Uso e a Política de Privacidade (LGPD) durante o registro e confirme a validade e titularidade do endereço de e-mail informado por meio de mensagem eletrônica de verificação.
• **Ator(es):** Consultor Administrador, Sistema de E-mail.
• **Pré-Condições:** Estar realizando o cadastro inicial de uma nova consultoria na plataforma (UC-01 / UC-02).
• **Fluxo Principal:**
1. `[Ação do Ator]` Durante o preenchimento dos dados de registro, o usuário lê e marca o aceite obrigatório dos Termos de Uso e da Política de Privacidade e Proteção de Dados (LGPD).
2. `[Ação do Sistema]` O sistema registra o consentimento legal do titular e envia uma mensagem eletrônica contendo o código ou link de confirmação para o endereço de e-mail informado.
3. `[Ação do Ator]` O usuário acessa sua caixa de entrada e aciona a confirmação de titularidade do e-mail.
4. `[Ação do Sistema]` O sistema valida a confirmação e registra o endereço de e-mail como verificado no cadastro da consultoria.
• **Fluxos Alternativos:**
- **A1 - Não marcação do aceite dos Termos e LGPD:**
  1. `[Ação do Sistema]` O sistema impede o avanço do cadastro e solicita a confirmação obrigatória dos termos legais.

## UC-51: Notificar Vencimento Próximo da Assinatura Mensal
• **Finalidade/Objetivo:** Emitir alertas visuais no painel e notificações automáticas ao Consultor Administrador quando a assinatura mensal da consultoria atingir os últimos 5 dias de vigência antes da expiração ou encontrar-se vencida.
• **Ator(es):** Sistema, Consultor Administrador.
• **Pré-Condições:** Existir uma consultoria cadastrada cuja data de expiração da assinatura mensal esteja a 5 dias ou menos do vencimento.
• **Fluxo Principal:**
1. `[Ação do Sistema]` O sistema verifica a data de expiração do ciclo mensal de 30 dias da assinatura da consultoria ao carregar o painel ou em rotina programada.
2. `[Ação do Sistema]` Ao identificar que restam 5 dias ou menos para o vencimento, o sistema registra e exibe um alerta de vencimento próximo para o Consultor Administrador com atalho direto para a janela de renovação da assinatura (UC-43).
3. `[Ação do Ator]` O Consultor Administrador visualiza a notificação e acessa diretamente a área de renovação para quitar o próximo ciclo mensal sem interrupção do serviço.
• **Fluxos Alternativos:**
- **A1 - Assinatura expirada sem renovação:**
  1. `[Ação do Sistema]` Caso a data de vencimento seja ultrapassada sem confirmação de pagamento, o sistema suspende temporariamente o acesso operacional ao painel principal e direciona o administrador para a tela de regularização financeira.

## UC-52: Realizar Pagamento ou Renovação de Assinatura via Cartão de Crédito
• **Finalidade/Objetivo:** Permitir que o Consultor Administrador contrate ou renove o plano de assinatura mensal da consultoria utilizando Cartão de Crédito como forma de pagamento, além da modalidade via PIX.
• **Ator(es):** Consultor Administrador, Gateway de Pagamento.
• **Pré-Condições:** Estar na etapa de contratação inicial de plano (UC-02), renovação de ciclo mensal (UC-43) ou regularização de pendência (UC-46).
• **Fluxo Principal:**
1. `[Ação do Ator]` Na tela ou modal de pagamento da assinatura, o Consultor Administrador seleciona a forma de pagamento "Cartão de Crédito".
2. `[Ação do Ator]` O usuário preenche os dados do cartão no ambiente seguro de checkout e confirma a transação.
3. `[Ação do Sistema]` O gateway de pagamento processa a autorização da cobrança junto à operadora do cartão e notifica o sistema.
4. `[Ação do Sistema]` O sistema registra a forma de pagamento ativa como Cartão de Crédito, ativa ou soma +30 dias à vigência da assinatura da consultoria e libera o acesso integral aos recursos do plano.
• **Fluxos Alternativos:**
- **A1 - Transação recusada pela operadora do cartão:**
  1. `[Ação do Sistema]` O sistema informa a recusa da transação e permite ao usuário tentar outro cartão ou alternar imediatamente para o pagamento instantâneo via PIX.

## UC-53: Realizar Login Exclusivo da Startup no Portal White-Label
• **Finalidade/Objetivo:** Permitir que o representante de uma empresa cliente (Startup) realize autenticação através de uma interface de acesso dedicada para acompanhar, em modo somente leitura, o progresso de suas submissões e minutas sob a identidade visual da consultoria.
• **Ator(es):** Startup (Cliente).
• **Pré-Condições:** Possuir credencial de acesso com perfil "Startup" previamente criada e vinculada à empresa pelo Consultor Administrador (UC-36).
• **Fluxo Principal:**
1. `[Ação do Ator]` O usuário representante da Startup acessa a tela de login exclusivo do cliente e informa seu e-mail e senha.
2. `[Ação do Sistema]` O sistema autentica as credenciais, identifica o vínculo restrito com a empresa da carteira e carrega a identidade visual personalizada da consultoria responsável (White-Label).
3. `[Ação do Sistema]` O sistema direciona o usuário para o Portal White-Label (UC-41), exibindo exclusivamente o quadro Kanban e o histórico de minutas daquela startup em modo somente leitura.
• **Fluxos Alternativos:**
- **A1 - Credenciais inválidas ou acesso revogado:**
  1. `[Ação do Sistema]` O sistema recusa o login e orienta o cliente a entrar em contato com a consultoria responsável.

## UC-54: Integrar e Enviar Alertas Operacionais via WhatsApp
• **Finalidade/Objetivo:** Realizar o disparo automatizado de alertas urgentes de prazos de editais, oportunidades de alto índice de compatibilidade e avisos operacionais diretamente para o WhatsApp dos consultores cadastrados, bem como permitir a integração de dados pelo canal.
• **Ator(es):** Sistema, Consultor Administrador, Consultor Padrão.
• **Pré-Condições:** Consultoria possuir número de telefone/WhatsApp configurado e integração ativa.
• **Fluxo Principal:**
1. `[Ação do Sistema]` Ao detectar um evento crítico (como prazo de submissão atingindo 72h/24h, novo edital com alta compatibilidade ou aviso de vencimento de assinatura), o sistema formata a mensagem de alerta com o resumo da oportunidade ou pendência.
2. `[Ação do Sistema]` O sistema dispara a notificação automatizada para o WhatsApp vinculado ao consultor responsável.
3. `[Ação do Ator]` O consultor recebe o alerta em seu dispositivo móvel e acessa a plataforma para executar a ação necessária.
• **Fluxos Alternativos:**
- **A1 - Número de WhatsApp ausente ou falha de entrega no canal externo:**
  1. `[Ação do Sistema]` O sistema registra a ocorrência e garante que o alerta permaneça visível na central interna de notificações do painel e no envio por e-mail.
