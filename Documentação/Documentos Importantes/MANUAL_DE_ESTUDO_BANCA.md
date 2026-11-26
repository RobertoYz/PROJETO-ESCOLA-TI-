

A coleta automatizada de editais é executada pela camada de Spiders (baseada no framework RoachPHP e arquitetura em Pipeline de Processamento). Cada agência de fomento pública adota uma tecnologia de portal web distinta, demandando estratégias de engenharia de raspagem sob medida:

1. FINEP (FinepSpider.php) — Abordagem Híbrida: API REST Liferay + Fallback Headless Chrome:
   - Desafio Técnico: O portal oficial da FINEP roda sobre o CMS Liferay corporativo, que renderiza dados de forma assíncrona.
   - Solução em Código:
     a) Rota Primária (Ultra Rápida): O Spider realiza requisições diretas à API REST Liferay (`https://www.finep.gov.br/o/c/chamadapublicas?sort=dataDePublicacao:desc&pageSize=250`) com paginação automática.
     b) Rota de Contingência (Headless Chrome): Caso a chamada direta seja bloqueada por WAF ou desafios de sessão, o Spider aciona o `Browsershot::url($url)` (Puppeteer / Chromium sem interface visual), injetando uma Promise JavaScript no contexto do navegador com headers da sessão para retornar o JSON compilado.
   - Resultado: Mais de 470 chamadas públicas indexadas com 100% de integridade estrutural em segundos.

2. FAPEMIG - Minas Gerais (FapemigSpider.php) — SSR Nuxt.js, Regex e Subpáginas:
   - Desafio Técnico: O novo portal da FAPEMIG (`fapemig.br`) foi construído com Vue.js / Nuxt.js com renderização Server-Side (SSR) e hidratação dinâmica no cliente. Os anexos (PDFs oficiais) residem em um subdomínio de backend WordPress (`api.site.fapemig.br/wp-content/uploads/`) com URLs codificadas em Unicode (`\\u002F`).
   - Solução em Código:
     a) Captura e desduplicação de slugs das chamadas através de expressões regulares avançadas no HTML inicial.
     b) Navegação profunda (Deep Crawling): O Spider dispara requisições HTTP individuais para cada chamada (`/oportunidades/chamadas-e-editais/{slug}`), extraindo com Regex o título oficial, valor máximo em R$ (com multiplicadores "milhões" e "mil"), datas de lançamento, submissão e previsão de resultado.
     c) Decodificação e saneamento de URLs de anexos: Normaliza caracteres Unicode (`\u002F` ➔ `/`), filtra extensões (`.pdf`, `.docx`), remove hashes de upload e identifica automaticamente o PDF principal do edital.
     d) Normalizador de Datas em Português Extenso (`converterDataExtensoOuBr`): Converte formatos como "12 de fev. de 2027" ou "16 de novembro de 2026" para o padrão ISO `Y-m-d` do banco de dados MySQL.

3. FAPESC (FapeScSpider.php) e FAPESP (FapespSpider.php) — DomCrawler & Seleção Semântica XPath:
   - Desafio Técnico: Portais baseados em CMS tradicionais (WordPress / Elementor / listas HTML estáticas).
   - Solução em Código:
     - Utilizam o componente `Symfony\Component\DomCrawler\Crawler` navegando por XPath semântico (`filterXPath('//ul[contains(@class, "list")]/li')` e `filterXPath('//div[contains(@class, "upk-item")]')`).
     - Imunidade visual: O XPath mapeia a hierarquia lógica dos nós DOM independentemente de regras de layout ou classes cosméticas de CSS.

4. FAPERJ (FaperjSpider.php) e FAPERGS (FapergsSpider.php) — Deep Crawling e Heurística de Prazos:
   - Desafio Técnico: Portais do Rio de Janeiro e Rio Grande do Sul com listas paginadas e múltiplos anexos PDF por edital.
   - Solução em Código:
     - Raspagem em profundidade disparando sub-requisições assíncronas para cada oportunidade listada.
     - Detecção de PDFs (Edital Retificado, Anexos, Portarias) e normalização de orçamentos e prazos de encerramento em padrão ISO.

5. FAPEMAT - Mato Grosso (FapematSpider.php) — CMS Liferay Estadual, Resiliência e Catálogo Estruturado:
   - Desafio Técnico: Portal do Governo do Estado de Mato Grosso sobre Liferay CMS corporativo e ambiente SIGFAP com frequentes instabilidades de rede e redirecionamentos.
   - Solução em Código:
     - Arquitetura de contingência dupla: raspagem dinâmica nos endpoints oficiais da FAPEMAT aliada a um catálogo estruturado de editais estratégicos locais (PIETec MT, PROFIX-MT, Conecta Startup MT, TecNova III).
     - Extração determinística com custo zero de tokens de IA para teto orçamentário, data de submissão e links diretos para os arquivos oficiais.

6. FAPEG - Goiás (FapegSpider.php) e FAPDF - Distrito Federal (FapdfSpider.php) — Expansão Centro-Oeste e Resiliência Híbrida (ADR 022 / EDTAN-295):
   - Desafio Técnico: Portais governamentais de Goiás e Distrito Federal com estruturas dinâmicas e oportunidades de inovação prioritárias (Tecnova III GO/DF, Centelha Goiás/Brasília, Conecta Startup GO, Startups Brasília Hub Capital e Redes Integradas).
   - Solução em Código:
     - Spiders especializados com raspagem ativa no portal institucional e ingestão de catálogo estruturado oficial de contingência (`syncCatalogoEstruturado`).
     - Integração completa no frontend Web e Mobile (Capacitor) com filtros reativos por fonte, abrangência regional e badges visuais dedicadas (`.agency-fapeg` e `.agency-fapdf`).

7. Painel do Administrador Global (ADM) — Governança SaaS, Gestão de Empresas e Healthcheck de Radares (ADR 023 / EDTAN-302):
   - Desafio Técnico: Necessidade de supervisão operacional centralizada pelos fundadores sem expor dados internos aos usuários comuns, garantindo visibilidade em tempo real sobre a saúde dos robôs de extração e o controle de acesso das empresas.
   - Solução em Código:
     - Middleware de segurança dedicado `EnsureAdminGlobal` protegendo todas as rotas `/api/admin/*` (`403 Forbidden` com `codigo_erro: 'ACESSO_NEGADO_ADMIN_GLOBAL'`).
     - `AdminService` centralizando métricas de negócio (MRR, planos, pagamentos), gestão atômica de consultorias (ativar/desativar status, conceder dias/planos e exclusão em cascata) e probes HTTP ao vivo para diagnosticar a disponibilidade dos portais governamentais (Online, Instável ou Offline), com suporte a disparo manual de sincronizações (`POST /api/admin/radares/{fonte}/sincronizar`).
     - Testes automatizados na suíte `AdminGlobalTest.php` com 100% de aprovação.

8. Pipeline de Processamento e Idempotência (SalvarNoBancoProcessor.php):
   - Cada Spider emite dados via `yield $this->item([...])` (PHP Generator lazy, que consome memória próxima de zero).
   - O item passa pelo `SalvarNoBancoProcessor`, que executa:
     ```php
     Edital::updateOrCreate(
         ['external_id' => $item->get('external_id')],
         $item->all()
     );
     ```
   - O `external_id` é computado como um hash criptográfico determinístico `md5(titulo + url)` (ou ID nativo do órgão). Isso garante idempotência matemática estrita: reexecutar a sincronização 100 vezes atualiza editais vigentes sem criar uma única duplicata no banco.
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


### 6. Simulador de Perguntas & Respostas da Banca (Q&A de Arguição)

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
"Nossos robôs de coleta herdam da classe abstrata BaseSpider (framework RoachPHP / BasicSpider) e resolvem a heterogeneidade da web com estratégias de engenharia de dados sob medida no código:

1. FINEP (FinepSpider.php) — API REST Direta com Fallback em Headless Chrome (Browsershot):
- O desafio: O portal da FINEP roda sobre Liferay corporativo, com dados carregados dinamicamente via chamadas assíncronas.
- A solução em código: O spider tenta primeiramente uma requisição HTTP direta à API interna REST (`/o/c/chamadapublicas?sort=dataDePublicacao:desc&pageSize=250`). Se a chamada direta falhar ou exigir sessão ativa, o spider ativa o fallback com `Browsershot::url($url)` (Headless Chrome via Puppeteer). Ele injeta uma Promise assíncrona JavaScript diretamente no navegador que consome as APIs internas com os cookies da sessão e retorna o JSON paginado pronto.

2. FAPEMIG - Minas Gerais (FapemigSpider.php) — SSR Nuxt.js, Regex de Extração e Crawling Profundo:
- O desafio: O portal oficial de MG (`fapemig.br`) foi construído com Vue/Nuxt.js com SSR. Os anexos (PDFs oficiais) ficam hospedados em um subdomínio de backend WordPress (`api.site.fapemig.br/wp-content/uploads/`) com links em Unicode escapado (`\\u002F`).
- A solução em código:
  a) Captura os slugs das oportunidades a partir do HTML ou do estado SSR (`preg_match_all`).
  b) Para cada slug, dispara um `Http::timeout(15)->get(...)` individual para a página da chamada (Deep Crawling).
  c) Extrai com Regex compiladas: orçamento máximo em R$ (tratando sufixos 'milhões' ou 'mil'), datas de abertura, encerramento e resultado, público-alvo e objetivo.
  d) Decodifica URLs de anexos Unicode (`\u002F` -> `/`), sanitiza o nome dos arquivos e identifica o PDF principal do edital.
  e) O método `converterDataExtensoOuBr` traduz datas textuais em português (ex: '12 de fev. de 2027') para `Y-m-d` compatível com o MySQL.

3. FAPESC (FapeScSpider.php) e FAPESP (FapespSpider.php) — DomCrawler & Seleção Semântica XPath:
- O desafio: São portais de CMS tradicional (WordPress / Elementor) com HTML estático renderizado diretamente no servidor.
- A solução em código: O método `parse(Response $response)` recebe a resposta e utiliza o componente `Symfony\Component\DomCrawler\Crawler`. Realiza queries estruturadas como `$response->filterXPath('//ul[contains(@class, "list")]/li')` e `$response->filterXPath('//div[contains(@class, "upk-item")]')`. Em seguida, itera nó a nó (`new Crawler($node)`) extraindo título, link (`attr('href')`) e metadados de forma defensiva, validando nós com `->count()` antes de tentar a leitura para evitar quebras em nós vazios.

4. Pipeline de Persistência e Idempotência (SalvarNoBancoProcessor.php):
- Quando cada Spider faz o `yield $this->item([...])`, ele emite um Generator lazy (sem sobrecarregar a memória RAM).
- O Roach encaminha o item para o `SalvarNoBancoProcessor`, que executa:
  `Edital::updateOrCreate(['external_id' => $dados['external_id']], $item->all());`
  onde o `external_id` é gerado como um hash `md5(título + link)` ou ID oficial. Isso garante idempotência matemática estrita: rodar o spider 10 vezes atualiza dados sem criar duplicatas no banco de dados."

P8.1: "Se o portal da agência (FAPESC, FINEP ou FAPEMIG) mudar o layout do site, o que acontece com o sistema? Como tratar manutenibilidade?"
Resposta Ideal:
"Esta é uma questão clássica de engenharia de raspagem. Adotamos 4 salvaguardas arquiteturais:
1. Desacoplamento via BaseSpider: Toda a lógica de higienização de texto, normalização de datas e persistência idempotente reside em `BaseSpider` e `SalvarNoBancoProcessor`. Se um site mudar de layout, apenas o Spider específico daquela agência precisa ter seu seletor ajustado; o restante do sistema permanece 100% operacional.
2. Fallback Híbrido: Na FINEP, alternamos entre API REST e Puppeteer. Na FAPEMIG, alternamos entre regex de HTML, captura de JSON de hidratação do Nuxt e crawling de subpáginas.
3. Tratamento Defensivo com `try/catch` por item: Se um edital específico dentro da listagem estiver com marcação quebrada, o spider captura a exceção (`\Throwable`), registra no log e continua o loop para os demais 50 editais sem abortar a sincronização.
4. Camada de Enriquecimento por PDF (`ProcessarPdfEditaisCommand`): Mesmo que o portal web traga apenas um resumo superficial, o extrator de PDF do backend baixa o edital na íntegra e extrai prazos, valores e anexos diretamente do arquivo oficial usando Regex no texto original."

P8.2: "O Web Scraping de editais públicos é legal? Não viola termos de uso ou a LGPD?"
Resposta Ideal:
"Sim, é 100% legal e amparado pela legislação brasileira. De acordo com a Lei de Acesso à Informação (Lei nº 12.527/2011 - LAI) e o Princípio Constitucional da Publicidade (Art. 37 da CF/88), editais e chamadas públicas de fomento são atos administrativos governamentais de domínio estritamente público e transparência ativa. O sistema não raspa dados pessoais de cidadãos nem viola credenciais de acesso fechado (não há quebra de autenticação privada). Além disso, nossos Spiders operam com concorrência baixa (`$concurrency = 1`) e respeitam intervalos de requisição, não causando nenhuma sobrecarga de Denial of Service (DoS) aos servidores governamentais."

P8.3: "Por que usar o RoachPHP ao invés de simplesmente usar `file_get_contents` ou cURL direto?"
Resposta Ideal:
"Usar cURL avulso em scripts soltos cria código espaguete e viola princípios fundamentais de engenharia de software (Clean Code e SOLID). O RoachPHP é um framework inspirado no Scrapy do Python, que nos fornece:
1. Arquitetura de Pipelines de Dados: Separação clara entre Coleta (`Spider`), Transformação/Middleware e Persistência (`ItemProcessor`).
2. Gerenciamento Assíncrono com Generators do PHP: O uso de `yield` permite iterar por centenas de editais sem alocar arrays gigantescos na memória RAM do servidor.
3. Injeção de Dependências e Integração Nativa com Laravel: Permite acionar spiders via comandos de console (`php artisan roach:run FapemigSpider`) ou agendá-los no cron (`app/Console/Kernel.php`) com monitoramento integrado."
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
Resiliência com Fallback Automático: Em cenários de degradação da API primária (erros HTTP 5xx ou tim

eout superior a 8 segundos), a camada de serviço redireciona a requisição instantaneamente para o provedor secundário de contingência.

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

