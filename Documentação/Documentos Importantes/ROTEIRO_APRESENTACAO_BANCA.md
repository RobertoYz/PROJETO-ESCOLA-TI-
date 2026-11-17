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

## ⏱️ Minuto 2: A Arquitetura em 3 Pilares
> *"Tecnicamente, o sistema foi estruturado sobre três pilares de engenharia de software:*
>
> 1. **Coleta Inteligente com Custo Zero de IA (Web Scraping):**
>    - *Spiders automatizados em RoachPHP minerando mais de 580 editais ativos (FINEP, FAPESC, FAPESP e FAPEMIG).*
>    - *Uso de API REST interna e Puppeteer (Browsershot) para portais assíncronos e seletores XPath para páginas em CMS.*
>    - *Parser local determinístico de PDFs que extrai orçamentos, datas e anexos sem gastar tokens de IA.*
>
> 2. **Gestão Visual e Operacional (Pipeline Kanban & Multi-tenancy):**
>    - *Esteira Kanban em tempo real para gerenciar o ciclo da proposta (Backlog $\rightarrow$ Em Análise $\rightarrow$ Minuta $\rightarrow$ Submetido).*
>    - *Segregação lógica Multi-Tenant por consultoria (`agencia_id` e `startup_id`), garantindo isolamento total dos dados de clientes.*
>    - *Anexos de arquivos e notas operacionais persistidos de forma leve em colunas JSON.*
>
> 3. **Co-Piloto de IA com Fallback em Cascata:**
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
| **Por que não usaram IA para raspar os sites?** | *Custo proibitivo e risco de alucinação em datas e valores legais. Usamos scraping determinístico para a coleta e reservamos os LLMs apenas para a redação da proposta onde agregam valor real.* |
| **Por que Vanilla JS e não React?** | *Arquitetura desacoplada e API-First. O backend Laravel 12 é 100% RESTful e stateless. O Vanilla JS garantiu performance máxima, zero overhead de build e facilidade para empacotar como app mobile nativo via Capacitor.* |
| **E se a API da IA cair ou atingir o limite?** | *Implementamos Fallback em Cascata: se a API primária falhar, aciona a secundária; se não houver internet, nosso motor local gera a proposta com base nas regras de negócio e dados do banco.* |
| **Como garantem auditoria do que a IA gerou?** | *Padrão de Versionamento Imutável. Cada alteração cria uma nova versão vinculada a `parent_minuta_id`, registrando autor, data e modelo utilizado.* |
| **A raspagem de editais públicos é legal?** | *Sim, 100% amparada pela Lei de Acesso à Informação (Lei 12.527/2011) e pelo Princípio da Publicidade da CF/88. Editais são atos públicos e não há captura de dados pessoais.* |

---

## ⏱️ Minuto 5: Conclusão
> *"Em resumo, o Anjo Nexus transforma um processo burocrático e manual de semanas em um fluxo ágil de poucos minutos, com arquitetura robusta, código autoral, alta disponibilidade e pronta para o mercado. Obrigado!"*
