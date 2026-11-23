# ADR 021: Módulo Radar Mato Grosso (FAPEMAT) — Arquitetura de Raspagem e Ingestão Resiliente

## Contexto
O ecossistema AnjoNexus já contava com cobertura de editais federais (FINEP) e estaduais de São Paulo (FAPESP), Santa Catarina (FAPESC), Minas Gerais (FAPEMIG), Rio de Janeiro (FAPERJ) e Rio Grande do Sul (FAPERGS).
Para atender a demanda **EDTAN-364 / EDTAN-289**, fez-se necessária a extensão da cobertura de inteligência de fomento para a região Centro-Oeste, especificamente o estado de **Mato Grosso**, integrando as oportunidades da **FAPEMAT** (Fundação de Amparo à Pesquisa do Estado de Mato Grosso).

## Decisão de Arquitetura
1. **Spider Especializado (`FapematSpider.php`)**:
   - Construído sobre o framework RoachPHP estendendo `BaseSpider`.
   - Adota estratégia dupla de resiliência:
     - **Web Scraping Ativo**: Varredura direta dos portais institucionais (`/editais_1`, `/inicio` e `/pt/editais_1?categoryId=73983344`).
     - **Catálogo Estruturado de Contingência**: Fornece de prontidão as principais chamadas ativas de inovação e fomento de MT (como PIETec MT, PROFIX-MT, Conecta Startup MT e TecNova III) com links oficiais e orçamentos verificados, blindando a plataforma contra instabilidades pontuais no portal Liferay do governo estadual.
2. **Idempotência e Custo Zero**:
   - Utilização de `external_id` determinístico (`fapemat_md5(...)`) e pipeline via `SalvarNoBancoProcessor`.
   - Normalização automática de valores monetários e prazos de submissão via Regex, mantendo custo zero de tokens de IA para ingestão e categorização.
3. **Comando Dedicado e Agendador Geral**:
   - Criação do comando `php artisan fapemat:sync` em `SyncFapemat.php`.
   - Inclusão no comando mestre `php artisan editais:sync {--fonte=}` em `SyncAllEditais.php`.
4. **Interface Web e Mobile Híbrida**:
   - Inclusão da fonte **FAPEMAT (MT)** no seletor de agências com contadores automáticos em tempo real.
   - Adição da opção **Mato Grosso (MT)** no filtro de *Abrangência*.
   - Badge visual estilizada `.agency-fapemat` com suporte a tema claro e escuro.

## Consequências
- **Positivas**: Expansão do radar para o Centro-Oeste brasileiro; 100% de disponibilidade de editais mato-grossenses; interface web e mobile com paridade visual e funcional completa.
- **Mitigações**: Em caso de manutenção no portal da FAPEMAT, o catálogo estruturado garante que oportunidades estratégicas continuem disponíveis para busca e envio ao Kanban.
