🎓 Manual Definitivo de Estudo para Banca Avaliadora
Anjo Nexus — Radar Inteligente de Editais e Plataforma de Subvenção à Inovação
Finalidade do Documento:
Este manual foi estruturado especificamente para a defesa acadêmica perante a Banca Examinadora do TCC / Projeto Integrador.
Ele sintetiza todos os artefatos de engenharia de software (diagramas UML, casos de uso, requisitos, DER), os 12 Registros de Decisões Arquiteturais (ADRs), as escolhas de stack técnica, as justificativas acadêmicas de projeto e o passo a passo detalhado do código-fonte (Backend Laravel 12 e Frontend Vanilla JS desacoplado).
📌 Sumário Executivo para Apresentação
1. A Visão Geral do Produto (O Problema e a Solução)
2. Arquitetura Geral do Sistema e Princípios Adotados
3. Decisões Arquiteturais Fundamentais (Matriz de ADRs)
4. Engenharia de Dados e Modelagem (DER e Classes)
5. Módulo a Módulo: Explicação Didática e Defesa de Código
5.1. Autenticação e Assinatura de Planos (SaaS)
5.2. Motor de Web Scraping Híbrido com Custo Zero de IA
5.3. Extração e Processamento de PDFs de Editais
5.4. Esteira Kanban e Gestão do Pipeline de Submissão
5.5. Módulo de IA: Diagnóstico de Aderência (Match RF-47) e Minutas Técnicas (RF-29 a RF-34)
5.6. Motor Nativo de Exportação OpenXML (.docx) e PDF
5.7. Frontend Desacoplado: Vanilla JS e Design System
6. Simulador de Perguntas & Respostas da Banca (Q&A de Arguição)
7. Glossário e Nomenclatura para Argumentação
1. A Visão Geral do Produto
1.1 O Problema Real de Mercado
Empresas de base tecnológica (Startups, Micro e Pequenas Empresas - MPEs) perdem anualmente milhões de reais em recursos de subvenção econômica não reembolsável (FAPESC, FINEP, FAPESP, CNPq). Os principais gargalos são:
Dispersão e Assimetria de Informações: Editais publicados em diários oficiais e portais isolados com layouts heterogêneos.
Incerteza de Elegibilidade: Editais possuem centenas de páginas em PDF; a leitura manual para verificar requisitos de porte, faturamento e TRL (Nível de Maturidade Tecnológica) consome dias de consultoria.
Barreira Técnica na Elaboração da Proposta: A montagem do Plano de Trabalho exige jargão técnico (ODS, TRL, matriz de riscos, plano de aplicação de recursos) que founders técnicos muitas vezes não dominam.
1.2 A Solução Anjo Nexus
Uma plataforma SaaS B2B que atua como Radar Contínuo e Co-Piloto de Submissão:
Raspa e unifica oportunidades governamentais diariamente.
Avalia a compatibilidade da startup com o edital (Match Inteligente).
Gerencia o ciclo de vida da oportunidade através de um Pipeline Kanban interativo.
Gera automaticamente Minutas de Proposta estruturadas em 11 seções oficiais, permitindo edição, versionamento e exportação para Word (.docx) e PDF.
2. Arquitetura Geral do Sistema
A arquitetura do Anjo Nexus foi concebida sob o paradigma API-First e Arquitetura Desacoplada (Stateless RESTful).

┌────────────────────────────────────────────────────────────────────────┐
│                        ARQUITETURA GERAL DO SISTEMA                    │
└────────────────────────────────────────────────────────────────────────┘

    [ CLIENTE: NAVEGADOR WEB ]
          │  HTML5 + Vanilla CSS + JavaScript Puro (SPA Leve)
          │  Zero build-tools, fetch API nativa, manipulação limpa do DOM
          ▼
    [ PROTOCOLO HTTP/REST (JSON) ] (Header: Authorization Bearer Token)
          │
          ▼
┌────────────────────────────────────────────────────────────────────────┐
│               BACKEND: LARAVEL 12 (API RESTful STATELESS)              │
├────────────────────────────────────────────────────────────────────────┤
│  routes/api.php                                                        │
│    └── Middleware (Sanctum / Token Guard / CORS)                       │
│          └── Controllers (Validação via Form Request / Injeção de Dep.)│
│                │                                                       │
│                ├──> Services Layer (Regras de Negócio e Algoritmos)    │
│                │      ├── MinutaAiService (Prompt 11 Seções + Fallback)│
│                │      ├── DocumentExportService (OpenXML .docx / PDF)  │
│                │      ├── KanbanService & CardMovementService          │
│                │      ├── AbacatePayService (Billing / Pix)            │
│                │      └── PdfExtractorService (Regex + smalot/pdfparser│
│                │                                                       │
│                ├──> Spiders Layer (Web Scraping Contínuo)              │
│                │      ├── FinepSpider (Browsershot / Headless Chrome)  │
│                │      ├── FapespSpider (RoachPHP + XPath DOM)          │
│                │      └── FapeScSpider (RoachPHP + XPath DOM)          │
│                │                                                       │
│                └──> Models Eloquent (Mapeamento Objeto-Relacional)     │
│                       ├── User, Startup, Edital, Agencia               │
│                       ├── KanbanBoard, KanbanColumn, KanbanCard        │
│                       └── Minuta (Árvore de Versões e Auditoria)       │
└────────────────────────────────────────────────────────────────────────┘
          │
          ▼
    [ BANCO DE DADOS RELACIONAL: PostgreSQL / MySQL / SQLite ]
│                │      ├── AbacatePayService (Billing / Pix)            │
Princípios de Engenharia de Software Aplicados
Single Responsibility Principle (SRP): Controllers apenas recebem a requisição e devolvem respostas JSON; toda a inteligência reside nas classes de Services.
Dependency Injection (DI): Injeção de dependências nativa do container do Laravel para serviços externos e modelos.
Loose Coupling (Baixo Acoplamento): O Frontend não conhece detalhes internos do banco de dados, comunicando-se estritamente através de contratos JSON documentados.
Fault Tolerance (Tolerância a Falhas): Política de contingência multinível na IA (Gemini ➔ DeepSeek ➔ Motor Determinístico Local), garantindo quase 100% de disponibilidade.
3. Decisões Arquiteturais Fundamentais (Matriz de ADRs)
A banca avaliadora valoriza intensamente a justificativa do porquê as decisões foram tomadas. A tabela abaixo resume os 13 Registros de Decisões Arquiteturais (ADRs):

ADR
Decisão Tomada
Alternativa Rejeitada
Justificativa Acadêmica para a Banca
O que significa na prática (Explicação Simples)
001
Backend Laravel API + Frontend Vanilla JS
Aplicação Monolítica com Laravel Blade
Desacoplamento estrito, separação clara de responsabilidades e independência de deploy.
Separar a cozinha do salão: O backend só cuida das regras e dados (API), e a tela (interface) funciona sozinha sem depender do servidor PHP para desenhar botões. Se um dia trocar a tela por um aplicativo mobile, o backend continua intacto.
002
Processamento Background (Queues) para PDFs
Processamento síncrono no request HTTP
Evita estouro de timeout (30s) e travamento de thread HTTP na leitura de PDFs com mais de 50 páginas.
Pegar uma senha no banco em vez de travar o caixa: Ao enviar um PDF grande, o usuário não fica esperando a tela congelada por minutos até dar erro; o sistema aceita o arquivo, põe numa fila de tarefas em segundo plano e avisa quando terminar.
003
Estratégia Custo Zero de IA no Pipeline
Uso de LLMs para ler todo HTML de editais
Viabilidade financeira. Usar IA em raspagem de 500 editais geraria custos proibitivos e imprevisíveis.
Usar filtro manual antes de chamar o especialista caro: Em vez de mandar a inteligência artificial ler centenas de páginas de sites inteiros (o que custaria caro por token), scripts rápidos filtram o texto e a IA só é chamada para tarefas cirúrgicas e pontuais sem estourar o orçamento.
004
Laravel PHP para Web Scraping
Microserviço isolado em Python (Scrapy)
Redução de complexidade operacional e overhead de infraestrutura: mantém toda a equipe em uma única linguagem backend.
Manter todo mundo falando a mesma língua: Em vez de criar um segundo servidor em Python só para coletar dados (tendo que gerenciar dois servidores, duas linguagens e deploys diferentes), resolveu-se tudo dentro do próprio Laravel PHP.
005
Trade-off de Scraping: APIs Internas + XPath
Raspagem puramente visual baseada em Regex
APIs internas e árvores XPath são imunes a pequenas alterações de cores, margens e layouts CSS.
Ir direto na fonte em vez de tentar adivinhar pelo visual: Em vez de copiar texto da tela tentando adivinhar padrões visuais (que quebram se o site mudar de cor ou fonte), o robô busca os dados estruturados por trás dos panos ou no endereço exato do elemento na página.
006
Extração de PDF determinística (Regex estruturado)
OCR por Visão Computacional / IA
Editais públicos são PDFs digitais pesquisáveis (com texto vetorizado); OCR adicionaria lentidão desnecessária.
Copiar texto com Ctrl+C em vez de tirar foto e passar scanner: Como os editais já vêm com texto digital selecionável, é muito mais rápido e leve extrair o texto diretamente por código do que gastar processamento rodando reconhecimento ótico de imagens (OCR).
007
Rotação Dinâmica de Chaves de API (getRandomKey)
Chave única estática no .env
Contorna os limites de requisições por minuto (Rate Limits) em camadas gratuitas de LLMs.
Revezar cartões para não travar na catraca: Provedores gratuitos de IA bloqueiam quem faz muitas perguntas por minuto. O sistema alterna entre várias chaves cadastradas para não ser bloqueado e manter a aplicação funcionando de graça.
008
Browsershot (Headless Chrome) para FINEP
cURL simples / GuzzleHTTP
O portal da FINEP é uma Single Page Application baseada em Liferay que exige execução de motor JavaScript.
Abrir um navegador invisível de verdade: O site da FINEP não carrega os dados no HTML inicial; ele precisa que o JavaScript rode no navegador para exibir o conteúdo. O robô abre um Chrome "sem cabeça" (invisível), espera o site carregar e aí sim coleta os dados.
009
XPath DOM para FAPESP e FAPESC
Quebra de strings (explode('\n'))
XPath navega semanticamente na árvore XML/HTML sem quebrar se uma linha em branco for adicionada.
Navegar pelo mapa da página em vez de contar linhas: Se cortar o texto linha por linha, qualquer espaço em branco a mais quebra o robô. O XPath funciona como um endereço GPS ("vá até a tabela X, na coluna Y"), garantindo precisão mesmo com mudanças de espaçamento.
010
Vanilla JS no Frontend (Fase 1)
Frameworks pesados (React, Angular, Vue)
Elimina pipeline complexo de build (npm run build), dependências frágeis e foca na validação da API REST.
Construir sem andaimes pesados: Usar JavaScript puro direto no navegador sem precisar instalar dezenas de bibliotecas externas, etapas demoradas de compilação ou risco de quebra de versões, focando na agilidade e leveza.
011
Arquitetura Híbrida de Scraping
Solução única genérica para todas as fontes
Respeita a heterogeneidade da web pública brasileira (Liferay na FINEP vs WordPress na FAPESC).
Usar a ferramenta certa para cada porta: Cada portal público foi feito com uma tecnologia diferente. Em vez de forçar um único método que falharia na metade deles, cada fonte tem seu coletor sob medida.
012
Gerador Nativo OpenXML (.docx) e Minutas em 11 Seções
Pacotes PHP externos pesados (phpoffice/phpword)
Geração leve e independente via ZipArchive do PHP; alinhamento estrito ao modelo Anjo Inovador/FAPESC.
Montar o arquivo Word direto na raiz: Um arquivo .docx é na verdade um conjunto de arquivos XML compactados em ZIP. O sistema gera essa estrutura diretamente com as ferramentas nativas do PHP, sem instalar pacotes pesados que consomem muita memória do servidor.
013
Metadados JSON e Storage Link para Anexos do Kanban
Tabelas relacionais rígidas para cada tipo de anexo
Flexibilidade schema-less sem migrações frequentes; download direto sem bloqueio de permissão (EDTAN-183/EDTAN-175).
Guardar a ficha em um envelope flexível: Em vez de ter que alterar o banco de dados toda vez que surgir um novo tipo de anexo ou documento, guarda-se um pacote flexível (JSON) e um link público seguro para o arquivo, evitando retrabalho e bloqueios de acesso.

💡 Glossário Rápido de Termos Complexos para a Banca
ADR (Architectural Decision Record): Documento formal que registra uma escolha de arquitetura importante, suas motivações, alternativas descartadas e consequências.
Desacoplamento: Fazer com que duas partes do sistema funcionem de forma independente; se uma mudar ou cair, a outra não é destruída.
Queues (Filas em Background): Tarefas enviadas para execução em segundo plano para liberar o usuário e evitar que a tela trave aguardando processos demorados.
Timeout: Tempo limite que um servidor espera por uma resposta antes de cancelar a operação por considerar que ela travou.
LLM (Large Language Model): Modelos de inteligência artificial generativa de linguagem (ex: GPT, Gemini, Claude).
Web Scraping: Técnica de automação para extrair e coletar informações de páginas web públicas.
XPath: Linguagem de consulta que permite localizar elementos específicos dentro da estrutura de tags (HTML/XML) de uma página web.
Headless Chrome (ex: Browsershot): Navegador Google Chrome completo executado em segundo plano, sem janela visual, usado para rodar JavaScript e simular um usuário real navegando.
Rate Limit: Restrição imposta por uma API sobre quantas requisições um usuário ou chave pode fazer em um determinado intervalo de tempo.
OCR (Optical Character Recognition): Tecnologia que reconhece caracteres de texto a partir de imagens ou documentos escaneados.
Vanilla JS: JavaScript puro, executado nativamente pelo navegador sem a utilização de frameworks ou bibliotecas auxiliares (como React, Vue ou Angular).
OpenXML: Padrão aberto de formato de arquivo compactado (ZIP contendo XMLs) utilizado para documentos modernos do Microsoft Office (.docx, .xlsx).
4. Engenharia de Dados e Modelagem
4.1 Diagrama Entidade-Relacionamento (DER) Sintético

erDiagram
    USERS ||--o{ STARTUPS : "gerencia"
    USERS ||--o{ MINUTAS : "autor de"
    STARTUPS ||--o{ KANBAN_BOARDS : "possui"
    STARTUPS ||--o{ MINUTAS : "proponente em"
    EDITAIS ||--o{ KANBAN_CARDS : "vinculado a"
    EDITAIS ||--o{ MINUTAS : "base para"
    KANBAN_BOARDS ||--o{ KANBAN_COLUMNS : "composto por"
    KANBAN_COLUMNS ||--o{ KANBAN_CARDS : "contem"
    KANBAN_CARDS ||--o{ MINUTAS : "possui minuta"
    MINUTAS ||--o{ MINUTAS : "versao filha de (parent_id)"

    USERS {
        bigint id PK
        string name
        string email
        string password
    }

    STARTUPS {
        bigint id PK
        bigint user_id FK
        string razao_social
        string nome_fantasia
        string cnpj
        string porte
        string nicho_atuacao
        decimal faturamento_anual
    }

    EDITAIS {
        bigint id PK
        string title
        string fonte
        text objetivo
        decimal max_budget
        date deadline
        string publico
        json documentos_exigidos
    }

    KANBAN_CARDS {
        bigint id PK
        bigint kanban_column_id FK
        bigint edital_id FK
        string title
        integer position
        json metadata
    }

    MINUTAS {
        bigint id PK
        bigint startup_id FK
        bigint edital_id FK
        bigint kanban_card_id FK
        bigint parent_minuta_id FK
        integer versao
        string titulo_projeto
        text conteudo_gerado
        json secoes_estruturadas
        string status
        string modelo_ia_usado
        string autor_nome
    }
4.2 Destaques de Modelagem para Argumentação
Auto-Relacionamento em minutas (parent_minuta_id): Implementa o padrão de Versionamento Imutável. Ao editar uma proposta, o sistema nunca sobrescreve o texto anterior; ele cria uma nova linha com versao = versao + 1 e aponta para a matriz, assegurando auditoria completa (RF-33 e RF-34).
Coluna JSON secoes_estruturadas: Permite armazenar as 11 seções como entidades atômicas pesquisáveis (ex: buscar todas as propostas que citam ODS 9 ou TRL 7), combinando a rigidez do modelo relacional com a flexibilidade NoSQL do formato JSON.
Coluna JSON metadata em kanban_cards (ADR 013): Permite armazenar dinamicamente listas de anexos submetidos (com nome original, tamanho, mime type e caminho no storage público), anotações operacionais internas da consultoria e valor do projeto orçado em R$, sem necessitar de migrações complexas de esquema relacional.
Isolamento de Entidades por Startup (Multi-tenancy Lógico): As tabelas kanban_boards, minutas e cruzamentos de aderência estão estritamente atreladas a startup_id. O frontend propaga o identificador da empresa selecionada em todas as requisições (X-Startup-Id / payload), garantindo que cada startup acesse exclusivamente o seu próprio pipeline de oportunidades e histórico de documentos.
5. Módulo a Módulo: Explicação Didática e Defesa de Código
5.1 Autenticação e Assinatura de Planos (SaaS)
Tecnologia: Laravel Sanctum (auth:sanctum), tokens Bearer, bcrypt.
Fluxo:
Usuário envia credenciais via POST /api/login.
AuthController::login valida via Hash::check e gera token pessoal.
Toda rota subsequente valida o cabeçalho Authorization: Bearer <token>.
Billing: Integração via AbacatePayService para geração e liquidação de Pix em tempo real, liberando acessos às funcionalidades do painel.
5.2 Motor de Web Scraping Híbrido com Custo Zero de IA
Como a FINEP é minerada (FinepSpider.php):
O portal da FINEP utiliza o CMS corporativo Liferay com renderização assíncrona.
A Solução: Ao invés de raspar HTML visual sujeito a falhas de paginação, usamos o Browsershot (Puppeteer/Chromium) para injetar um script que invoca a API interna /o/c/chamadapublicas.
Resultado: Captura limpa de mais de 470 editais em formato JSON nativo com 100% de precisão de datas e links.
Como FAPESP e FAPESC são mineradas (FapespSpider.php e FapeScSpider.php):
Estendem a classe abstrata BaseSpider.
Utilizam seletores semânticos XPath:

$nodes = $response->filterXPath('//div[contains(@class, "chamada-item")]');
Justificativa para a Banca: O XPath navega na árvore hierárquica do DOM independentemente de regras visuais de CSS, garantindo imunidade a mudanças de estilo do portal do governo.
5.3 Extração e Processamento de PDFs de Editais (PdfExtractorService.php)
Desafio: Editais de inovação são PDFs de 30 a 120 páginas.
Solução Custo Zero: Leitura do fluxo de bytes usando smalot/pdfparser. O texto bruto passa por Expressões Regulares (Regex) compiladas para identificar:
Teto Orçamentário: /(?:valor\s+global|recursos\s+de\s+at[ée])\s*(?:R\$\s*)?([\d\.,]+)/i
Prazo Final: /(?:prazo\s+final|submiss[ãa]o\s+at[ée])\s*:?\s*(\d{2}\/\d{2}\/\d{4})/i
Relação de Anexos e Documentos Exigidos.
5.4 Esteira Kanban e Gestão do Pipeline (KanbanService.php e kanban.html)
Objetivo: Permitir ao consultor acompanhar oportunidades em colunas de fluxo contínuo (Backlog, Em Análise, Minuta em Elaboração, Submetido, Aprovado).
Implementação:
Drag and Drop nativo em HTML5 (dragstart, dragover, drop).
Sincronização em tempo real via endpoint POST /api/kanban/cards/mover, recalculando as posições ordinais no banco de dados para persistência consistente.
Gestão Avançada de Cards e Documentos (ADR 013 / EDTAN-183 / EDTAN-175):
Upload de Anexos: Permite anexar arquivos complementares (PDF, DOCX, XLSX, imagens de até 10MB) por oportunidade. O upload é validado via POST /api/kanban/cards/{id}/anexos e gravado no disco público do Laravel (storage/app/public/kanban_anexos), gerando URLs acessíveis de download imediato via symlink (php artisan storage:link).
Dados Customizados & Anotações: Permite ao consultor salvar o valor pleiteado do projeto (R$) e notas internas de pendências e prazos (PUT /api/kanban/cards/{id}/dados).
Interface Responsiva & Não-Bloqueante: O modal de detalhamento do card divide-se em sub-abas dedicadas (Minuta / Plano de Trabalho, Diagnóstico de Aderência, Histórico de Versões e Anexos & Dados do Card), contando com layout estático que impede cortes de layout ou rolagem indesejada do cabeçalho.
5.5 Módulo de IA: Match Inteligente e Minutas Técnicas
O Algoritmo de Match Inteligente (RF-47)
Implementado em MinutaAiService::calcularMatchEditais(). Trata-se de uma análise multicritério de pontuação ponderada:
Aderência Temática de Nicho (+20 pontos): Cruza termos do nicho da startup (ex: Cidades Inteligentes, Software, Biotecnologia) com as diretrizes do edital.
Elegibilidade de Porte Jurídico (+15 pontos): Valida se o enquadramento fiscal da empresa (ME, EPP, Startup) é atendido pelo público-alvo oficial do edital.
Capacidade Financeira e Contrapartida (+10 pontos): Analisa a compatibilidade do teto orçamentário com a contrapartida mínima de 5% exigida em editais públicos.
Justificativa Textual: Gera um diagnóstico sintético destacando pontos fortes e pontos de atenção para subsidiar a tomada de decisão do consultor.
O Gerador de Minutas Técnicas (RF-29)
Seguindo o modelo oficial do edital Anjo Inovador / FAPESC, a IA sintetiza rigorosamente 11 seções estruturadas:
Identificação da Empresa Proponente
Enquadramento Temático e Alinhamento com ODS (Objetivos de Desenvolvimento Sustentável)
Resumo Executivo (Pitch da Proposta)
Problema, Dor de Mercado e Justificativa do Fomento
Objeto do Projeto e Objetivos Específicos
Grau de Inovação e Maturidade Tecnológica (TRL Inicial e Final)
Metas Quantitativas e Entregáveis Verificáveis
Metodologia e Plano de Trabalho Sintético (Cronograma Macro de 12 Meses)
Plano de Aplicação de Recursos (Custeio vs Investimento e 5% de Contrapartida)
Matriz de Riscos e Planos de Contingenciamento
Impactos Socioeconômicos e Ambientais
O Mecanismo de Fallback em Cascata (Resiliência Operacional)

flowchart TD
    Req[Consultor clica 'Estruturar Minuta'] --> Level1[Nível 1: Google Gemini 2.5 Flash]
    Level1 -->|Sucesso| Finish[Minuta Gerada com Sucesso]
    Level1 -->|Falha ou Rate Limit| Level2[Nível 2: DeepSeek / Groq]
    Level2 -->|Sucesso| Finish
    Level2 -->|Falha ou Sem Conexão| Level3[Nível 3: Motor Determinístico Local]
    Level3 --> Finish
Por que isso é fundamental na defesa? Demonstra maturidade de arquitetura de software: o sistema é imune a falhas de APIs de terceiros. A aplicação nunca exibe tela branca ou erro 500 para o cliente.
5.6 Motor Nativo de Exportação OpenXML (.docx) e PDF
Por que criar um gerador OpenXML nativo (DocumentExportService.php)?
A maioria dos projetos acadêmicos instala bibliotecas genéricas como phpoffice/phpword. Nós optamos por construir a estrutura OpenXML padrão ISO/IEC 29500 pura usando o ZipArchive do PHP:
Vantagem 1: Arquivos compactos gerados em menos de 10 milissegundos.
Vantagem 2: Zero vulnerabilidades ou dívidas técnicas de pacotes externos obsoletos.
Vantagem 3: Arquivos 100% compatíveis com Microsoft Word, LibreOffice Writer e Google Docs, com cabeçalhos institucionais, numeração de versão e metadados de autoria.
5.7 Frontend Desacoplado: Vanilla JS e Design System
Arquitetura: Pasta front/ 100% isolada da pasta do backend.
Zero Build: Nenhum Webpack, Vite ou Babel necessário para rodar o cliente.
Comunicação Centralizada: Todas as chamadas usam a fetch API com cabeçalho Accept: application/json.
Isolamento de Estado por Edital: O editor e o histórico sincronizam exclusivamente com o edital selecionado na tela, impedindo vazamento de dados ou mistura de minutas entre editais concorrentes.
5.8 Isolamento Multi-tenancy e Perfil da Startup (Autocadastro via CNPJ)
Multi-tenancy Lógico no Frontend e Backend:
O sistema opera com suporte a múltiplos clientes corporativos (startups). A empresa selecionada é mantida no localStorage do navegador e disponibilizada de forma reativa e consistente em todas as telas (seletorGlobalStartup.js).
Cada requisição de kanban (/api/kanban), minuta ou histórico injeta o cabeçalho X-Startup-Id ou parâmetro startup_id, garantindo que um consultor nunca visualize dados cruzados entre diferentes clientes.
Autocadastro e Integração com Receita Federal via CNPJ:
No módulo Perfil da Startup (cadastrarStartup.js), o usuário informa o CNPJ e o sistema consulta automaticamente a API pública (ex: ReceitaWS / BrasilAPI), preenchendo Razão Social, Nome Fantasia, CNAE, Porte, Endereço e Situação Cadastral em tempo real.
O consultor pode enriquecer a proposta de valor, diferencial competitivo, faturamento e tecnologias-chave, abastecendo o motor de IA para os futuros diagnósticos de aderência.
6. Simulador de Perguntas & Respostas da Banca (Q&A de Arguição)
P1: "Por que vocês não usaram um framework moderno no Frontend, como React ou Next.js?"
Resposta Ideal:
"Nesta primeira fase de validação tecnológica, nosso foco principal de pesquisa foi a robustez do pipeline de dados: Web Scraping resiliente, esteira Kanban e o orquestrador de IA com fallbacks. Adotar React traria overhead de build, configuração de Webpack e dependências desnecessárias para o objetivo central. Optamos por Vanilla JS puro seguindo estritamente as boas práticas do DOM moderno e da Fetch API. Como a nossa API Laravel é 100% RESTful e desacoplada, qualquer equipe de frontend pode plugar um aplicativo React ou Mobile no futuro sem alterar uma única linha de código do backend."
P2: "Se a chave da API do Gemini ou da Groq for bloqueada por limite de requisições, o sistema quebra?"
Resposta Ideal:
"Não. Conforme documentado na nossa ADR 003 e ADR 012, implementamos uma política de contingência em três níveis. Primeiramente, temos rotação automática de múltiplas chaves via .env. Caso o Gemini falhe, a requisição é interceptada e enviada ao DeepSeek. Se ambos estiverem indisponíveis ou sem internet, entra em ação o nosso Motor Determinístico Local, que compila as 11 seções da proposta usando cálculos orçamentários exatos e regras de negócio com os dados reais do banco. O usuário final nunca fica sem o documento."
P3: "Por que não usar IA generativa para ler os sites e raspar os editais?"
Resposta Ideal:
"Essa decisão foi formalizada na ADR 011. Realizar raspagem de 500 editais via LLM geraria um custo financeiro proibitivo e imprevisível, além de introduzir problemas de alucinação em datas e valores de orçamentos públicos. Editais exigem precisão jurídica absoluta. Por isso, isolamos a raspagem em uma arquitetura híbrida determinística com Browsershot e XPath com Custo Zero de IA, reservando os modelos LLMs exclusivamente para a etapa de redação de propostas e minutas onde a capacidade generativa agrega real valor."
P4: "Como vocês garantem a rastreabilidade e auditoria das propostas geradas?"
Resposta Ideal:
"Atendendo aos requisitos RF-33 e RF-34, adotamos o padrão de Versionamento Imutável. Toda edição em tela não sobrescreve o registro original; ela insere uma nova linha na tabela minutas incrementando o número da versão e referenciando o parent_minuta_id. Além disso, persistimos o carimbo de data/hora, o identificador do consultor responsável e qual modelo de IA originou a minuta inicial, garantindo governança total e conformidade com os princípios da LGPD."
P5: "Como vocês geram o arquivo .docx sem instalar o pacote PHPWord?"
Resposta Ideal:
"Arquivos .docx são, por especificação da ISO/IEC 29500, arquivos ZIP contendo uma estrutura de documentos XML (OpenXML). Nossa classe DocumentExportService instancia a extensão nativa ZipArchive do PHP e monta diretamente o pacote com os arquivos [Content_Types].xml, _rels/.rels e word/document.xml. Essa abordagem consome uma fração mínima de memória RAM, elimina dezenas de dependências de terceiros e entrega um documento leve e 100% formatado para o usuário."
P6: "Como vocês gerenciam anexos de documentos e notas nos cards sem criar tabelas intermediárias complexas?"
Resposta Ideal:
"Formalizamos essa escolha na ADR 013. Ao invés de criarmos tabelas relacionais rígidas e acopladas para notas operacionais e listas de anexos que sofrem mutação constante na esteira ágil, utilizamos a coluna json metadata nativa em kanban_cards. Os arquivos físicos são validados e salvos no storage público do Laravel (storage/app/public/kanban_anexos) e expostos via link simbólico web (storage:link). Apenas a referência semântica (nome, caminho, tamanho e data) é mantida no JSON. Isso garante alta performance, consultas diretas e zero necessidade de migrações estruturais no banco para novos atributos operacionais."
P7: "Como a plataforma garante o isolamento de dados entre diferentes startups clientes (Multi-tenancy)?"
Resposta Ideal:
"Adotamos a arquitetura de Multi-tenancy Lógico via chave estrangeira startup_id. O frontend dispõe de um módulo unificado de contexto de startup (seletorGlobalStartup.js) que sincroniza a empresa ativa e valida a existência desse contexto no carregamento das views. Todas as chamadas ao Kanban, pipeline de oportunidades, histórico de minutas e cálculo de aderência passam obrigatoriamente pelo identificador da startup ativa. No backend, as consultas Eloquent filtram com cláusulas where('startup_id', ...) garantindo que um consultor ou empresa nunca acesse minutas ou cards de outros clientes."
P8: "Como os diferentes Spiders funcionam codicamente na prática? Como o código lida com portais tão distintos?"
Resposta Ideal (com detalhes de código):
"Nossos robôs de coleta herdam do framework RoachPHP (BasicSpider / AbstractSpider) e resolvem a heterogeneidade da web com estratégias sob medida no código:
FINEP (FinepSpider.php) — Browsershot & Context Injection:
O desafio de código: O portal da FINEP roda sobre Liferay, gerando tokens de sessão CSRF dinâmicos e carregando dados via SPA assíncrona. Uma chamada HTTP padrão (cURL/Guzzle) recebe erro 403 Forbidden.
A solução em código: Instanciamos o Browsershot::url($url) (Headless Chrome via Puppeteer). Injetamos um script JavaScript assíncrono (new Promise(async (resolve) => ...)) que executa dentro do contexto do navegador autenticado, dispara os fetch() das APIs internas da FINEP paginadas e retorna um JSON unificado com os editais já resolvidos.
FAPESC (FapeScSpider.php) e FAPESP (FapespSpider.php) — DomCrawler & Seleção Semântica:
O desafio de código: São portais baseados em CMS (ex: WordPress com Elementor/Ultimate Post Kit), com marcação HTML direta sem necessidade de rodar JavaScript pesado.
A solução em código: O método parse(Response $response) recebe a resposta e utiliza o componente Symfony\Component\DomCrawler\Crawler. Realiza queries estruturadas como $response->filter('.upk-list-wrap .upk-item'). Em seguida, itera nó a nó (new Crawler($node)) extraindo título, link (attr('href')) e metadados de forma defensiva, validando nós com ->count() antes de tentar a leitura para evitar quebras em nós vazios.
Pipeline de Persistência e Idempotência (SalvarNoBancoProcessor.php):
Quando cada Spider faz o yield $this->item([...]), ele emite um Generator lazy (sem sobrecarregar a memória RAM).
O Roach encaminha o item para o SalvarNoBancoProcessor, que executa o Edital::updateOrCreate(['external_id' => $dados['external_id']], [...]), onde o external_id é gerado como um hash md5(título + link). Isso garante idempotência matemática estrita: rodar o spider 10 vezes atualiza dados sem criar duplicatas no banco de dados."
P9: "No diagrama de classes e ER, por que MINUTAS tem um auto-relacionamento (parent_minuta_id) e usa colunas JSON em vez de tabelas normalizadas para as seções?"
Resposta Ideal (Engenharia de Dados e Diagramas):
"Essa decisão de modelagem foi tomada para equilibrar integridade relacional com flexibilidade e auditoria jurídica estrita:
Auto-relacionamento (parent_minuta_id e versao):
Ao invés de usarmos um simples UPDATE que apagaria o histórico anterior, implementamos o Padrão de Versionamento Imutável (Event Sourcing simplificado). Cada refinamento gera um novo registro imutável com número de versão incremental, apontando para sua minuta-mãe. Isso viabiliza auditoria forense, comparação lado a lado entre versões (diff) e conformidade legal sem necessitar de tabelas de log separadas.
Coluna JSON secoes_estruturadas vs 11 Tabelas Normalizadas:
Se criássemos uma tabela separada para cada uma das 11 seções da minuta (problema, solução, TRL, cronograma, etc.), qualquer consulta ou exportação exigiria múltiplos JOINs pesados e migrações estruturais sempre que um novo edital demandasse um campo adicional.
A coluna JSON nativa do MySQL/PostgreSQL nos dá o melhor dos dois mundos: atomicidade em leitura rápida (um único SELECT), suporte a índices funcionais no banco de dados para buscas semânticas (ex: propostas com TRL >= 6) e flexibilidade para absorver diferentes templates de fomento (Finep, Fapesc, CNPq)."
P10: "Como foi pensada a arquitetura em camadas do Backend? Por que isolar a lógica em Services em vez de deixar nos Controllers ou Models?"
Resposta Ideal (Padrões Arquiteturais e SOLID):
"Seguimos os princípios do Domain-Driven Design (DDD) pragmático e os padrões SOLID, aplicando a arquitetura em 4 camadas bem definidas:
Controllers Magros (Thin Controllers): KanbanController, EditalController e MinutaController possuem responsabilidade única (SRP): apenas interceptam o request HTTP, validam a entrada (via FormRequests) e delegam a execução, retornando respostas JSON padronizadas.
Camada de Serviços Especializados (Service Layer):
GeminiService / DeepSeekService: isolam os contratos de IA, timeouts e tratamentos de fallback.
DocumentExportService: lida exclusivamente com a especificação OpenXML/ZIP do .docx.
KanbanService: orquestra regras de negócio, movimentação de raias e upload de anexos.
Vantagem Arquitetural: Isso impede o anti-padrão de Fat Controller (onde regras de negócio se misturam com rotas HTTP), permite reaproveitar os mesmos serviços em comandos de terminal Artisan ou Jobs de fila assíncronos (Queues) e facilita testes unitários automatizados com mocks sem subir requisições HTTP."
7. Glossário e Nomenclatura para Argumentação
Para se expressar com excelência técnica durante a apresentação, utilize os termos abaixo:
Subvenção Econômica: Recurso financeiro público não-reembolsável concedido pelo governo para apoiar projetos de P&D (Pesquisa e Desenvolvimento) e inovação nas empresas.
Contrapartida: Parcela financeira ou econômica que a empresa beneficiária deve aportar com recursos próprios no projeto (habitualmente de 5% a 10% do valor global).
TRL (Technology Readiness Level): Escala de 1 a 9 que mede a maturidade de uma tecnologia (do TRL 1 - princípios básicos ao TRL 9 - sistema provado em ambiente operacional real).
ODS (Objetivos de Desenvolvimento Sustentável): 17 metas globais estabelecidas pela ONU. O alinhamento com ODS pontua positivamente na avaliação de editais públicos.
Stateless: Arquitetura onde cada requisição HTTP carrega em si toda a informação necessária para ser atendida, sem dependência de estado de sessão guardado na memória do servidor.
Headless Chrome: Navegador Google Chrome executado em segundo plano sem interface gráfica, utilizado para renderizar páginas dinâmicas e capturar chamadas AJAX/SPA.
XPath (XML Path Language): Linguagem de consulta que permite navegar e selecionar nós específicos em documentos XML e HTML com alta precisão semântica.
Fallback em Cascata: Padrão de resiliência onde um sistema tenta uma operação primária e, em caso de erro, degrada suavemente para rotas secundárias pré-configuradas.
OpenXML: Padrão aberto baseado em XML e compactação ZIP utilizado pelo pacote Microsoft Office (.docx, .xlsx).

Processamento de leitura do documento de diretrizes arquiteturais concluído integralmente (linhas 1 a 500).
Processamento de leitura do documento de diretrizes arquiteturais concluído integralmente (linhas 501 a 742).

Este artefato acadêmico sintetiza a topologia de sistemas, os padrões de projeto aplicados e as justificativas técnicas do ecossistema Anjo Nexus, estruturado especialmente para a sustentação e defesa perante a Banca Examinadora.

---

📌 Sumário Executivo de Arquitetura e Engenharia de Software

A plataforma Anjo Nexus opera como uma solução SaaS B2B sob o paradigma de Arquitetura Desacoplada (Decoupled Client-Server / SPA RESTful API). A camada client-side é composta por uma aplicação web e mobile leve (Vanilla JS / SPA / Capacitor), enquanto a camada server-side é construída em PHP com Laravel, apoiada pelo banco relacional MySQL e serviços externos integrados (gateways de faturamento via Pix e conectores de dados cadastrais públicos).

---

1. Padrões Arquiteturais e de Projeto Fundamentais

1.1 Multi-Tenancy Lógico (Row-Level Tenant Segregation)
Conceito e Modelagem: Adota o padrão Shared Database, Shared Schema com segregação lógica por tenant. A entidade raiz do contrato é a Agência (agencias), associada ao plano comercial. Usuários (users) e startups (startups) possuem vínculo obrigatório via chave estrangeira agencia_id.
Justificativa Acadêmica para a Banca: Garante altíssima eficiência de infraestrutura e viabilidade econômica em escala sem a sobrecarga operacional de instanciar múltiplos bancos de dados, assegurando o isolamento de informações rigorosamente via regras de negócio e restrições de integridade.

1.2 Segurança em Profundidade (Defense in Depth) e Blindagem Anti-IDOR
Estratégia de Mitigação: Proteção ostensiva contra falhas do tipo IDOR (Insecure Direct Object Reference — OWASP Top 10) através de dupla camada defensiva:
1. Camada de Middleware (VerificarStartupAtiva): Intercepta a requisição via cabeçalho HTTP customizado X-Startup-Id e valida se a startup pertence estritamente ao agencia_id do usuário autenticado.
2. Camada de Persistência (Eloquent): Todas as rotas de consulta aplicam obrigatoriamente a cláusula where('agencia_id', $user->agencia_id). Em buscas complexas com cláusulas OR, utiliza-se agrupamento explicito de queries (funções anônimas no Eloquent) para conter os operadores lógicos e impedir vazamento de dados entre tenants.
Autenticação Stateless com Sanctum: Os tokens de acesso são armazenados com hash criptográfico unidirecional SHA-256 (jamais em texto claro) e utilizam controle de privilégios granulado (escopos/abilities), segregando requisições com pendência financeira (pagamento:gerenciar) das credenciais de acesso irrestrito (*).

1.3 Transacionalidade e Atomicidade (Propriedades ACID)
Controle de Integridade: O processo de registro cadastral integrado ao gateway financeiro (POST /api/auth/registrar-com-plano) é executado sob um bloco transacional estrito (DB::beginTransaction).
Aplicação Prática para Arguição: Caso a comunicação externa com a API do AbacatePay sofra interrupção ou timeout, o sistema dispara imediatamente um DB::rollBack(), impedindo a persistência de registros órfãos ou inconsistências na base de dados MySQL.

1.4 Idempotência e Arquitetura Guiada a Eventos com Contingência (Self-Healing)
Garantia de Idempotência: Geração de identificadores únicos concatenados com entropia temporal (agencia_{id}_{uniqid}) no parâmetro externalId, evitando duplicidade de cobranças em chamadas reprocessadas na API v2 da plataforma de pagamentos.
Processamento Assíncrono via Webhook: O endpoint de recepção de eventos (POST /api/webhook/abacatepay) valida o token de assinatura compartilhada (webhookSecret), rejeitando requisições não autorizadas de terceiros.
Mecanismo de Auto-recuperação (Self-Healing): Diante de oscilações na entrega assíncrona dos webhooks (como em cenários de desenvolvimento com tunelamento), a aplicação aciona uma rotina síncrona de verificação sob demanda (consultarPagamentoAnterior), realizando o polling pontual no gateway e atualizando imediatamente o estado da conta no banco.

1.5 Padrão Adapter e Resiliência na Integração de Terceiros
Padrão Adapter (CnpjService): Normaliza payloads heterogêneos originados de múltiplos provedores públicos (BrasilAPI e ReceitaWS) em um DTO canônico perfeitamente compatível com o domínio da aplicação.
Resiliência com Fallback Automático: Em cenários de degradação da API primária (erros HTTP 5xx ou timeout superior a 8 segundos), a camada de serviço redireciona a requisição instantaneamente para o provedor secundário de contingência.

1.6 Otimizações de Desempenho: Fail-Fast e Cache-Aside
Validação Local Fail-Fast: A regra customizada CnpjValidationRule realiza a verificação aritmética matemática dos dígitos verificadores (Módulo 11) diretamente em memória, descartando padrões inválidos antes de realizar requisições desnecessárias aos webservices públicos.
Estratégia Cache-Aside: As respostas de dados cadastrais fiscais são retidas em cache com tempo de vida definido (CACHE_TTL_DAYS = 7), otimizando o tempo de resposta, reduzindo o consumo de banda e contornando bloqueios por excesso de requisições (rate-limiting).

1.7 Modelagem de Dados e Decisões de Ciclo de Vida
Mutators / Accessors Birecionais: A entidade Agencia encapsula a coerência entre o indicador booleano pagamento_ativo e os estados detalhados de faturamento (status_pagamento).
Exclusão Física (Hard Delete) Justificada: Cadastros com processos de contratação não concluídos utilizam a remoção definitiva (forceDelete) em cadeia transacional. Como o campo email possui índice de unicidade (UNIQUE), a adoção do Soft Delete manteria a restrição ocupada, inviabilizando novas tentativas legítimas de registro com o mesmo endereço de e-mail.

---

2. Mapa Conceitual de Camadas da Aplicação

┌────────────────────────────────────────────────────────┐
│             FRONT-END (Vanilla JS / SPA)               │
│  - Funil Centralizador de Rede (api.js / fetch)        │
│  - Cabeçalhos Injetados: X-Startup-Id, X-Frontend-Url  │
│  - Sessão Volátil (sessionStorage) no Pré-Cadastro     │
└───────────────────────────┬────────────────────────────┘
                            │ HTTP / JSON
┌───────────────────────────▼────────────────────────────┐
│              MIDDLEWARES & SEGURANÇA (Laravel)         │
│  - CheckPagamentoAtivo: Máquina de estados financeira  │
│  - VerificarStartupAtiva: Blindagem Anti-IDOR (Tenant) │
└───────────────────────────┬────────────────────────────┘
                            │
┌───────────────────────────▼────────────────────────────┐
│                    CONTROLADORES & SERVIÇOS            │
│  - AuthController: Login, Sanctum, Password Resets     │
│  - AbacatePayService: Checkout PIX, Webhooks v2        │
│  - CnpjService: Validação Módulo 11, Adapter, Fallback │
│  - StartupController: Regras de Carteira e Negócio     │
└───────────────────────────┬────────────────────────────┘
                            │
┌───────────────────────────▼────────────────────────────┐
│              BANCO DE DADOS & CACHE (MySQL / File)     │
│  - Multi-Tenant por agencia_id (Row-Level Security)    │
│  - Transações Atômicas (DB::beginTransaction / commit) │
│  - Cache de Consultas Externas (TTL 7 dias)           │
└────────────────────────────────────────────────────────┘

---

3. Decisões Arquiteturais Fundamentais (Matriz de ADRs)

A tabela a seguir compila os Registros de Decisões Arquiteturais (ADRs) essenciais que fundamentam a solução:
ADR
Decisão Arquitetural
Problema Resolvido
Justificativa Técnica
ADR 001
Multi-Tenancy em banco compartilhado via agencia_id
Gestão de múltiplas consultorias no mesmo SaaS
Menor custo de manutenção operacional e escalabilidade simplificada.
ADR 005/006
Tokens Sanctum tipados (token_pendencia vs auth_token)
Acesso não autenticado a rotas protegidas antes do pagamento
Segregação estrita de escopos (Abilities), bloqueando a navegação interna sem quitar o plano.
ADR 007
Pré-cadastro em 2 etapas com retenção temporária em sessionStorage
Bloqueio de e-mails em formulários abandonados
O e-mail só é persistido com transação após a confirmação do plano comercial.
ADR 013
Cabeçalho HTTP customizado X-Frontend-Url
Erros 404 em redirecionamentos e e-mails gerados pelo Back-end
A política de segurança do navegador (strict-origin-when-cross-origin) truncava o caminho no Referer padrão.
ADR 015
Contingência Self-Healing na verificação de pagamentos
Perda de notificações assíncronas por queda de webhooks
O Back-end consulta o gateway de forma síncrona na interação do usuário e corrige o estado local no MySQL.
ADR 022
Exclusão física (Hard Delete) de contas pendentes
Conflito na constraint UNIQUE(email) em cancelamentos
O Soft Delete manteria a chave duplicada bloqueando novos cadastros legítimos.
ADR 026/027
Restrição de unicidade composta UNIQUE(agencia_id, cnpj)
Multi-tenancy com concorrência de mercado
Permite que consultorias concorrentes atendam a mesma startup sem colisão no banco.
ADR Pedro 005/006
Validação Módulo 11 + Adapter de Provedores com Cache de 7 dias
Queda de serviços governamentais e custo de rede
Fail-Fast matemático local somado a desacoplamento de provedores e alta resiliência via cache.


---

4. Guia Rápido para a Apresentação na Banca

Segurança e Proteção de Dados: Enfatize o duplo mecanismo de proteção Anti-IDOR (via middleware de contexto e filtros mandatórios no Eloquent), complementado pela persistência segura de credenciais através de algoritmos de hash criptográfico (Bcrypt e SHA-256).
Confiabilidade e Resiliência Operacional: Ressalte a observância das propriedades ACID com suporte a reversão transacional (rollback) em falhas com serviços de terceiros, além do padrão Adapter com Fallback para garantir alta disponibilidade no consumo de APIs públicas.
Escalabilidade e Eficiência de Custos: Defenda o modelo de Multi-Tenancy Lógico (Row-Level Security), que otimiza os custos de infraestrutura em nuvem enquanto mantém performance através do uso estratégico de índices compostos no MySQL.

