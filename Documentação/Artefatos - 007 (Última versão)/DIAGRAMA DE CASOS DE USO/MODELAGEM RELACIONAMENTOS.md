# Documentação Técnica: Justificativa dos Relacionamentos `<<include>>` e `<<extend>>` no Diagrama de Casos de Uso

**Projeto:** Plataforma SaaS B2B - Anjo Nexus  
**Artefato:** Modelagem Comportamental UML — Diagrama de Casos de Uso (`UC-01` a `UC-54`)  
**Versão:** Artefatos - 007 (Última versão)

---

## 1. Fundamentação Conceitual (Padrão UML 2.5)

Na modelagem UML 2.5, a direção das setas tracejadas e a escolha entre os estereótipos `<<include>>` e `<<extend>>` obedecem a regras formais de obrigatoriedade e condicionalidade:

1. **Relacionamento `<<include>>` (Inclusão Obrigatória):**
   * **Regra de Negócio:** O Caso de Uso Base é incompleto por si só e **exige obrigatoriamente** a execução do Caso de Uso Incluído para atingir seu objetivo principal, ou reutiliza um comportamento comum compartilhado por múltiplos casos de uso.
   * **Direção da Seta na UML:** `Caso de Uso Base` $\dashrightarrow$ `Caso de Uso Incluído` (o caso base "puxa" a execução do caso incluído em 100% das vezes).

2. **Relacionamento `<<extend>>` (Extensão Condicional / Opcional):**
   * **Regra de Negócio:** O Caso de Uso Base é completo e autossuficiente sozinho, mas sob uma **condição específica** (Ponto de Extensão / Fluxo Alternativo ou decisão opcional do ator), seu comportamento pode ser expandido pelo Caso de Uso Estendido.
   * **Direção da Seta na UML:** `Caso de Uso Estendido` $\dashrightarrow$ `Caso de Uso Base` (o caso opcional "aponta" para o caso principal que ele complementa).

---

## 2. Matriz Resumo de Rastreabilidade (`<<include>>` vs. `<<extend>>`)

| # | Estereótipo | Origem da Seta | Destino da Seta | Condição de Disparo (Ponto de Extensão / Regra) |
| :-: | :---: | :--- | :--- | :--- |
| **01** | `<<include>>` | `UC-01` (Pré-Cadastro) | `UC-02` (Escolher Plano e Cobrança) | Fluxo contínuo obrigatório do funil de registro SaaS em duas etapas. |
| **02** | `<<include>>` | `UC-01` (Pré-Cadastro) | `UC-50` (Confirmar E-mail e Aceite LGPD) | Exigência legal e cadastral obrigatória na criação de qualquer nova conta. |
| **03** | `<<include>>` | `UC-11` (Busca Automatizada por CNPJ) | `UC-29` (Extrair e Cachear Dados Receita) | Toda busca de CNPJ na interface aciona obrigatoriamente o motor de extração e cache. |
| **04** | `<<include>>` | `UC-48` (Ressincronizar Dados da Startup) | `UC-29` (Extrair e Cachear Dados Receita) | Reutilização do mesmo motor de consulta externa com invalidação de cache. |
| **05** | `<<include>>` | `UC-39` (Mineração Automatizada de Editais) | `UC-26` (Processar PDF e Gerar Checklist) | Todo edital novo captado que possui edital em PDF passa pela extração estruturada. |
| **06** | `<<include>>` | `UC-16` (Visualizar Detalhes de um Edital) | `UC-26` (Processar PDF e Gerar Checklist) | A visão detalhada consolida e exibe obrigatoriamente o checklist e parâmetros extraídos. |
| **07** | `<<include>>` | `UC-21` (Gerar Minuta via Multi-LLM) | `UC-20` (Inserir ou Revisar PITCH) | Nenhuma minuta pode ser gerada pela IA sem antes confirmar o escopo/Pitch do projeto. |
| **08** | `<<include>>` | `UC-53` (Login Exclusivo da Startup) | `UC-41` (Acessar Portal White-Label) | A autenticação exclusiva da Startup conduz obrigatoriamente ao ambiente White-Label. |
| **09** | `<<extend>>` | `UC-45` (Consultar Pagamento Anterior) | `UC-04` (Realizar Login) | Disparado somente se o login detectar conta com `[pagamento pendente]`. |
| **10** | `<<extend>>` | `UC-46` (Gerar Nova Cobrança na Pendência) | `UC-04` (Realizar Login) | Disparado somente se `[pagamento pendente]` e janela anterior de 60 min expirada. |
| **11** | `<<extend>>` | `UC-47` (Cancelar Pagamento e Excluir Conta) | `UC-04` (Realizar Login) | Disparado somente se `[pagamento pendente]` e o titular desistir da contratação. |
| **12** | `<<extend>>` | `UC-44` (Mudar Plano: Upgrade/Downgrade) | `UC-43` (Visualizar Assinatura e Cotas) | Ação opcional acionada a partir da visualização do plano vigente. |
| **13** | `<<extend>>` | `UC-51` (Notificar Vencimento da Assinatura) | `UC-43` (Visualizar Assinatura e Cotas) | Disparado condicionalmente quando restam `[<= 5 dias do vencimento]` ou vencida. |
| **14** | `<<extend>>` | `UC-52` (Pagamento via Cartão de Crédito) | `UC-02` (Escolher Plano e Cobrança Inicial) | Modalidade alternativa ao PIX selecionada opcionalmente na contratação inicial. |
| **15** | `<<extend>>` | `UC-52` (Pagamento via Cartão de Crédito) | `UC-43` (Visualizar e Renovar Assinatura) | Modalidade alternativa ao PIX selecionada opcionalmente na renovação mensal. |
| **16** | `<<extend>>` | `UC-09` (Cadastrar Startup) | `UC-11` (Busca Automatizada por CNPJ) | Autopreenchimento opcional (permitido preenchimento manual em caso de falha externa). |
| **17** | `<<extend>>` | `UC-48` (Ressincronizar Dados com Receita) | `UC-10` (Gerenciar Perfil da Startup) | Ação opcional sob demanda durante a edição do perfil de uma startup já existente. |

---

## 3. Justificativa Detalhada dos Relacionamentos `<<include>>` (Obrigatórios)

### 3.1. `UC-01 (Realizar Pré-Cadastro)` $\dashrightarrow$ `<<include>>` $\dashrightarrow$ `UC-02 (Escolher Plano de Assinatura e Gerar Cobrança Inicial)`
* **Por que é `<<include>>`?** No modelo de negócio SaaS B2B do Anjo Nexus, não existe conta gratuita sem plano vinculado. A primeira etapa (`UC-01`) apenas valida a disponibilidade do e-mail e retém os dados na sessão do navegador para evitar a criação precoce de registros órfãos. Para que o registro da Consultoria e do Consultor Administrador passe a existir fisicamente no banco de dados, o fluxo **inclui obrigatoriamente** a escolha do plano e a emissão da cobrança inicial (`UC-02`).

### 3.2. `UC-01 (Realizar Pré-Cadastro)` $\dashrightarrow$ `<<include>>` $\dashrightarrow$ `UC-50 (Confirmar E-mail no Cadastro e Registrar Aceite LGPD)`
* **Por que é `<<include>>`?** Por imposição legal da Lei Geral de Proteção de Dados (Lei nº 13.709/2018) e por segurança cadastral, nenhum novo titular pode concluir seu ingresso na plataforma sem registrar o aceite expresso dos Termos de Uso/Política de Privacidade e passar pela validação de titularidade do endereço de e-mail informado. Sendo um requisito inegociável de todo novo registro, configura uma inclusão obrigatória.

### 3.3. `UC-11 (Fazer Busca Automatizada de Dados por CNPJ)` e `UC-48 (Ressincronizar Dados da Startup)` $\dashrightarrow$ `<<include>>` $\dashrightarrow$ `UC-29 (Extrair e Cachear Dados Empresariais da Receita Federal)`
* **Por que é `<<include>>`?** Este é o exemplo clássico de **reutilização de subfunção comum** na UML. Tanto a busca de CNPJ no momento do cadastro inicial (`UC-11`) quanto o botão de ressincronização sob demanda na tela de perfil (`UC-48`) dependem 100% da execução do motor interno que valida os dígitos verificadores (Módulo 11), verifica o cache de 7 dias e consulta a BrasilAPI com contingência para a ReceitaWS (`UC-29`). Isolar o `UC-29` como caso incluído evita duplicar a especificação da regra de extração e cache em dois lugares diferentes.

### 3.4. `UC-39 (Realizar Mineração Automatizada de Editais)` e `UC-16 (Visualizar Detalhes de um Edital)` $\dashrightarrow$ `<<include>>` $\dashrightarrow$ `UC-26 (Processar Anexo PDF do Edital e Gerar Checklist)`
* **Por que é `<<include>>`?** Quando os robôs de mineração (`UC-39`) captam um edital governamental, o texto bruto da página web raramente contém todas as exigências documentais; por isso, o fluxo de ingestão inclui obrigatoriamente o processamento do PDF anexo para extrair prazos, tetos orçamentários e checklist de certidões (`UC-26`). De forma complementar, quando o consultor abre a visão detalhada de um edital (`UC-16`), a apresentação da tela inclui obrigatoriamente a exibição estruturada dos parâmetros e do checklist produzidos pelo `UC-26`.

### 3.5. `UC-21 (Gerar Minuta via Roteador Inteligente Multi-LLM)` $\dashrightarrow$ `<<include>>` $\dashrightarrow$ `UC-20 (Inserir ou Revisar PITCH da Startup na Geração de Minuta)`
* **Por que é `<<include>>`?** O motor de Inteligência Artificial não pode redigir uma proposta técnica de subvenção às cegas, apenas com o texto do edital. Antes de disparar qualquer chamada ao Roteador Multi-LLM (`UC-21`), o sistema **obriga** a abertura da janela de confirmação/revisão do Pitch e da ideia do projeto voltada àquele edital (`UC-20`), garantindo que o contexto tecnológico da startup ativa esteja presente no prompt.

### 3.6. `UC-53 (Realizar Login Exclusivo da Startup no Portal White-Label)` $\dashrightarrow$ `<<include>>` $\dashrightarrow$ `UC-41 (Acessar Portal do Cliente Startup White-Label Somente Leitura)`
* **Por que é `<<include>>`?** Diferentemente do consultor (que pode navegar entre múltiplos módulos administrativos), o ator `Startup (Cliente)` possui escopo estrito e exclusivo. Ao autenticar-se pela interface dedicada (`UC-53`), o sistema carrega obrigatoriamente a identidade visual da consultoria responsável e o insere na visão somente leitura do Portal White-Label (`UC-41`).

---

## 4. Justificativa Detalhada dos Relacionamentos `<<extend>>` (Condicionais / Opcionais)

### 4.1. `UC-45 (Consultar Janela de Pagamento Anterior)`, `UC-46 (Gerar Nova Cobrança)` e `UC-47 (Cancelar Pagamento Pendente)` $\dashrightarrow$ `<<extend>>` $\dashrightarrow$ `UC-04 (Realizar Login e Validar Status Financeiro)`
* **Por que são `<<extend>>`?** No caminho feliz do `UC-04 (Realizar Login)`, o consultor está com a mensalidade em dia e entra diretamente no painel principal — ou seja, os casos `UC-45`, `UC-46` e `UC-47` **não são executados** na maioria dos logins. Eles só entram em cena quando a condição de guarda **`[pagamento pendente]`** (Fluxo Alternativo `A2` do `UC-04`) é verdadeira, retendo o usuário na Central de Regularização Financeira, onde ele escolhe opcionalmente qual das três ações deseja executar.

### 4.2. `UC-44 (Mudar Plano de Assinatura: Upgrade e Downgrade)` $\dashrightarrow$ `<<extend>>` $\dashrightarrow$ `UC-43 (Visualizar Plano de Assinatura e Consumo de Cotas)`
* **Por que é `<<extend>>`?** O Consultor Administrador pode abrir o modal "Minha Assinatura" (`UC-43`) apenas para consultar quantos dias restam no seu ciclo mensal ou verificar quantas minutas de IA sua equipe já consumiu no mês, fechando a janela em seguida sem alterar nada. A mudança de plano (`UC-44` — Upgrade com cálculo pró-rata ou Downgrade validado contra o uso atual) é um desdobramento **opcional** disparado apenas quando o administrador decide alterar sua faixa contratual.

### 4.3. `UC-51 (Notificar Vencimento Próximo da Assinatura Mensal)` $\dashrightarrow$ `<<extend>>` $\dashrightarrow$ `UC-43 (Visualizar Plano de Assinatura, Consumo de Cotas e Renovar Ciclo Mensal)`
* **Por que é `<<extend>>`?** Durante os primeiros 25 dias do ciclo mensal de 30 dias, a assinatura está regular e nenhuma notificação de vencimento ou ação de renovação é acionada. O `UC-51` estende o controle de assinatura exclusivamente quando a condição temporal **`[<= 5 dias do vencimento ou vencida]`** é atingida, exibindo o alerta de expiração próxima e conduzindo o administrador diretamente ao fluxo de renovação do `UC-43`.

### 4.4. `UC-52 (Realizar Pagamento ou Renovação via Cartão de Crédito)` $\dashrightarrow$ `<<extend>>` $\dashrightarrow$ `UC-02 (Escolher Plano e Cobrança Inicial)` e `UC-43 (Renovar Ciclo Mensal)`
* **Por que é `<<extend>>`?** O método padrão nativo de liquidação instantânea da plataforma é o PIX. Entretanto, tanto no momento da contratação inicial (`UC-02`) quanto na renovação mensal dentro da janela de 5 dias (`UC-43`), o Consultor Administrador pode optar condicionalmente por realizar o checkout via **Cartão de Crédito** (`UC-52`), estendendo os fluxos de cobrança com uma modalidade de pagamento alternativa.

### 4.5. `UC-09 (Cadastrar Startup)` e `UC-11 (Fazer Busca Automatizada de Dados por CNPJ)`
* **Por que o autopreenchimento é `<<extend>>` no cadastro da startup?** Ao cadastrar uma startup (`UC-09`), o autopreenchimento via Receita Federal (`UC-11`) acelera o processo, mas o sistema prevê que as APIs governamentais externas (BrasilAPI e ReceitaWS) podem sofrer indisponibilidade temporária simultânea ou o CNPJ recém-constituído pode ainda não constar na base pública (Fluxo Alternativo `A1` do `UC-11`). Nesses cenários, o cadastro da startup (`UC-09`) continua podendo ser concluído normalmente por preenchimento manual, caracterizando uma extensão opcional/condicional e não um bloqueio impeditivo.

### 4.6. `UC-48 (Ressincronizar Dados da Startup sob Demanda com a Receita Federal)` $\dashrightarrow$ `<<extend>>` $\dashrightarrow$ `UC-10 (Gerenciar Perfil da Startup)`
* **Por que é `<<extend>>`?** Na grande maioria das vezes em que o consultor abre a tela de edição de perfil de uma startup (`UC-10`), ele deseja apenas refinar o texto do **Pitch institucional**, ajustar o **Nicho de Atuação** ou atualizar o telefone de contato, clicando em "Salvar" sem precisar consultar a Receita Federal novamente. O clique no botão *"Sincronizar com a Receita Federal"* (`UC-48`) ocorre apenas sob demanda (quando houve mudança de endereço ou desenquadramento de porte de ME para EPP na Junta Comercial), sendo portanto um comportamento estendido opcional do `UC-10`.