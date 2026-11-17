# 🚀 Roteiro Executivo de Apresentação de TCC
## Projeto: Anjo Nexus — Plataforma Inteligente de Editais e Subvenção

---

## ⏱️ Minuto 1: O Problema e a Oportunidade
> *"Boa noite, banca examinadora. Todos os anos, agências de fomento como FINEP, FAPESC, FAPESP e FAPEMIG disponibilizam centenas de milhões de reais em recursos não-reembolsáveis para inovação. No entanto, a maioria das startups e pequenas empresas perde esses recursos por três motivos principais:*
> 1. *Informação pulverizada em dezenas de portais governamentais com layouts complexos.*
> 2. *Dificuldade em ler editais com mais de 80 páginas em PDF para checar elegibilidade.*
> 3. *Complexidade técnica para redigir um plano de trabalho completo com matriz de risco, TRL e contrapartidas.*
>
> *Para resolver esse gargalo, criamos o **Anjo Nexus**: uma plataforma B2B SaaS que atua como Radar Contínuo e Co-Piloto de Submissão de Editais."*

---

## ⏱️ Minuto 2: A Arquitetura em 4 Pilares Técnicos (com Explicação de Código)
> *"Tecnicamente, o sistema foi estruturado sobre quatro pilares sólidos de engenharia de software:*
>
> 1. **Coleta Inteligente com Custo Zero de IA (Web Scraping com Spiders):**
>    - *Herança de `BaseSpider` (framework RoachPHP / Scrapy do PHP), minerando mais de 580 editais ativos (FINEP, FAPESC, FAPESP e FAPEMIG).*
>    - **FINEP (`FinepSpider.php`):** *Faz requisições diretas à API REST Liferay (`/o/c/chamadapublicas`) com paginação; possui fallback com `Browsershot::url($url)` (Puppeteer / Headless Chrome) injetando script JS autenticado no navegador quando há bloqueio de sessão.*
>    - **FAPEMIG (`FapemigSpider.php`):** *Lida com SSR Nuxt.js. Captura slugs de chamadas por Regex, faz Deep Crawling nas subpáginas e decodifica URLs de anexos em Unicode (`\\u002F` ➔ `/`). O método `converterDataExtensoOuBr` converte datas textuais (ex: '12 de fev. de 2027') para o padrão ISO `Y-m-d` do MySQL.*
>    - **FAPESC e FAPESP (`FapeScSpider.php` / `FapespSpider.php`):** *Utilizam `Symfony\Component\DomCrawler\Crawler` com XPath semântico (`filterXPath('//ul[contains(@class, "list")]/li')`). O XPath mapeia a hierarquia dos nós do DOM, sendo imune a mudanças de layout ou CSS.*
>    - **Pipeline e Idempotência (`SalvarNoBancoProcessor.php`):** *Usa PHP Generators (`yield $this->item(...)`) para consumo mínimo de memória RAM. O processador executa `Edital::updateOrCreate(['external_id' => $hash], $dados)`, onde o hash `md5(titulo + url)` garante que rodar o spider 100 vezes atualiza os registros sem duplicatas no banco.*
>
> 2. **Canal Mobile Nativo com Capacitor (`Capacitor 7 + Android Studio/Gradle`):**
>    - *Como funciona o Capacitor:* *O Capacitor atua como uma ponte (bridge nativa) entre o código Web desacoplado (HTML5/CSS3/Vanilla JS) e os runtimes nativos (Android WebView / Java / Kotlin).*
>    - *Configuração (`capacitor.config.json`):* *Mapeia o diretório web `webDir: "www"`, apontando para a pasta sincronizada do front.*
>    - *Sincronização (`npx @capacitor/cli sync android`):* *Copia os assets web para `android/app/src/main/assets/public` e atualiza plugins nativos.*
>    - *Pipeline de CI/CD (GitHub Actions `.github/workflows/build-apk.yml`):* *Compila automaticamente em container Ubuntu (Node.js 20 + Java JDK 21 Zulu + Android SDK 35) via `./gradlew assembleDebug`, gerando o binário `anjonexus.apk` sem exigir que os desenvolvedores tenham máquinas pesadas com Android Studio instalado.*
>    - *Ergonomia Mobile (`mobile-adaptive.css`):* *Uso de variáveis CSS para entalhe de câmera e barra de status (`--safe-area-top: env(safe-area-inset-top)`), cards touch com feedback tátil e gaveta lateral de menu com backdrop.*
>
> 3. **Gestão Visual e Operacional (Pipeline Kanban & Multi-tenancy):**
>    - *Esteira Kanban em tempo real para gerenciar o ciclo da proposta (Backlog $\rightarrow$ Em Análise $\rightarrow$ Minuta $\rightarrow$ Submetido).*
>    - *Segregação lógica Multi-Tenant por consultoria (`agencia_id` e `startup_id`), garantindo isolamento total dos dados de clientes via middleware e escopos do Eloquent.*
>    - *Anexos de arquivos (PDF, DOCX) e notas operacionais persistidos de forma leve em colunas JSON (`metadata` em `kanban_cards`).*
>
> 4. **Co-Piloto de IA com Fallback em Cascata:**
>    - *Algoritmo de Match Inteligente (0% a 100%) cruzando nicho, porte e contrapartida financeira.*
>    - *Geração automatizada de Minutas Técnicas estruturadas nas 11 seções oficiais exigidas pelos órgãos de fomento.*
>    - *Resiliência com Fallback em 3 níveis: Google Gemini $\rightarrow$ DeepSeek $\rightarrow$ Motor Determinístico Local, garantindo que o sistema nunca falhe."*

---

## ⏱️ Minuto 3: Demonstração Prática (O Fluxo na Tela)
> *"Na prática, o fluxo do usuário na plataforma ocorre em 4 passos simples:*
> 1. **Radar de Editais:** *O consultor filtra as oportunidades por órgão (como o novo radar de Minas Gerais / FAPEMIG) e visualiza o score de compatibilidade da startup.*
> 2. **Enviar para o Kanban:** *Com um clique, a oportunidade vira um card na esteira de trabalho.*
> 3. **Gerar Minuta:** *O sistema pré-seleciona a empresa e o edital, chamando o orquestrador de IA que redige a proposta em 11 seções com cronograma de 12 meses e matriz de risco.*
> 4. **Exportação Imediata:** *O consultor exporta o documento em `.docx` nativo (OpenXML puro) ou `.pdf`, pronto para submissão oficial."*

---

## ⏱️ Minuto 4: Decisões Técnicas Chave para Justificar à Banca

| Pergunta Típica da Banca | Resposta Direta e Firme |
| :--- | :--- |
| **Por que usar o framework RoachPHP nos Spiders e não cURL avulso?** | *O RoachPHP implementa a arquitetura de Pipelines de Dados (como o Scrapy em Python). Ele separa coleta (`Spider`), transformação/filtro e persistência (`SalvarNoBancoProcessor`), usando `yield` (Generators assíncronos) que não estouram a memória RAM ao raspar centenas de editais.* |
| **Como o Capacitor compila o APK a partir do código Web?** | *O Capacitor não converte HTML em código nativo, ele empacota a aplicação Web em uma WebView de alta performance gerenciada por uma casca nativa em Java/Kotlin (Android). Nossa esteira do GitHub Actions roda `npx @capacitor/cli sync` para sincronizar os arquivos para `android/app/src/main/assets/public` e dispara `./gradlew assembleDebug`, gerando o `.apk` em nuvem.* |
| **Por que não usaram IA para raspar os sites?** | *Custo proibitivo e risco de alucinação em datas e valores legais. Usamos scraping determinístico para a coleta e reservamos os LLMs apenas para a redação da proposta onde agregam valor real.* |
| **Por que Vanilla JS e não React?** | *Arquitetura desacoplada e API-First. O backend Laravel 12 é 100% RESTful e stateless. O Vanilla JS garantiu performance máxima, zero overhead de build e facilidade para empacotar como app mobile nativo via Capacitor.* |
| **E se a API da IA cair ou atingir o limite?** | *Implementamos Fallback em Cascata: se a API primária falhar, aciona a secundária; se não houver internet, nosso motor local gera a proposta com base nas regras de negócio e dados do banco.* |
| **Como garantem auditoria do que a IA gerou?** | *Padrão de Versionamento Imutável. Cada alteração cria uma nova versão vinculada a `parent_minuta_id`, registrando autor, data e modelo utilizado.* |
| **A raspagem de editais públicos é legal?** | *Sim, 100% amparada pela Lei de Acesso à Informação (Lei 12.527/2011) e pelo Princípio da Publicidade da CF/88. Editais são atos públicos e não há captura de dados pessoais.* |

---

## ⏱️ Minuto 5: Conclusão
> *"Em resumo, o Anjo Nexus transforma um processo burocrático e manual de semanas em um fluxo ágil de poucos minutos, com arquitetura robusta, código autoral, alta disponibilidade e pronta para o mercado. Obrigado!"*
