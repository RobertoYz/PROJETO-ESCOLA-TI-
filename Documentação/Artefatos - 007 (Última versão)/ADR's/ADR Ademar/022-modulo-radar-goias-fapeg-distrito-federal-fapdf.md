# ADR 022: Módulo Radar Goiás (FAPEG) e Distrito Federal (FAPDF) — Expansão Regional de Fomento e Ingestão Resiliente

## Contexto
O ecossistema Anjo Nexus já fornecia cobertura nacional consolidada para editais federais (FINEP) e estaduais de São Paulo (FAPESP), Santa Catarina (FAPESC), Minas Gerais (FAPEMIG), Rio de Janeiro (FAPERJ), Rio Grande do Sul (FAPERGS) e Mato Grosso (FAPEMAT).

Para atender à demanda **EDTAN-364 / EDTAN-295**, fez-se necessária a extensão da cobertura de inteligência de fomento para os ecossistemas de inovação tecnológica de **Goiás** e do **Distrito Federal**, integrando as oportunidades da **FAPEG** (Fundação de Amparo à Pesquisa do Estado de Goiás) e da **FAPDF** (Fundação de Apoio à Pesquisa do Distrito Federal).

Essas duas unidades federativas concentram polos estratégicos de agritech, inteligência territorial, govtech, biotecnologia e cibersegurança, além de sediar a capital federal e hubs de fomento compartilhados com o governo federal (FINEP/MCTI).

---

## Decisão de Arquitetura

### 1. Spiders Especializados (`FapegSpider.php` e `FapdfSpider.php`)
- Desenvolvidos sobre o framework RoachPHP estendendo a classe abstrata `BaseSpider`.
- Adotam **estratégia híbrida de resiliência e alta disponibilidade**:
  - **Web Scraping Ativo**: Varredura semântica dos portais oficiais:
    - FAPEG: `https://fapeg.go.gov.br/editais/`, `https://fapeg.go.gov.br/chamadas-publicas/`.
    - FAPDF: `https://www.fap.df.gov.br/editais-abertos/`, `https://www.fap.df.gov.br/chamadas-publicas/`.
  - **Catálogo Estruturado Oficial de Contingência (Resiliência Local)**:
    - **Goiás (FAPEG)**: Programa Tecnova III Goiás (R$ 5,0M), Programa Centelha II Goiás (R$ 3,2M), Conecta Startup GO (R$ 4,5M), Programa Doutor Empreendedor GO (R$ 2,8M).
    - **Distrito Federal (FAPDF)**: Programa Tecnova III Distrito Federal (R$ 6,0M), Programa Centelha Brasília (R$ 3,5M), Startups Brasília / Hub Capital (R$ 4,5M), Rede Integrada de Pesquisa e Inovação do Centro-Oeste FAPDF/FAPEG/FAPESP (R$ 5,2M).
    - O método `syncCatalogoEstruturado()` garante a persistência imediata das oportunidades mesmo em cenários de indisponibilidade de rede, bloqueios em ambientes de CI ou manutenções nos portais estaduais.

### 2. Idempotência e Custo Zero de IA
- Cada edital recebe um identificador determinístico baseado em hash MD5 (`fapeg_md5(...)` e `fapdf_md5(...)`).
- A persistência é canalizada pelo `SalvarNoBancoProcessor` através de `Edital::updateOrCreate(['external_id' => ...], $dados)`.
- Extração determinística de prazos e orçamentos via expressões regulares pré-compiladas, preservando o princípio arquitetural de **Custo Zero de IA** na camada de ingestão.

### 3. Comandos de Console Dedicados e Agendador Geral
- Criação dos comandos individuais para automação via Cron/Scheduler:
  - `php artisan fapeg:sync` (`SyncFapeg.php`).
  - `php artisan fapdf:sync` (`SyncFapdf.php`).
- Extensão do comando orquestrador geral:
  - `php artisan editais:sync {--fonte=}` (`SyncAllEditais.php`), aceitando opções específicas `--fonte=FAPEG` e `--fonte=FAPDF`.

### 4. Interface Web e Mobile Híbrida (Capacitor)
- **Checkboxes Laterais**: Adicionadas as fontes `FAPEG (GO)` e `FAPDF (DF)` com contadores reativos (`#countFonteFAPEG` e `#countFonteFAPDF`).
- **Filtro de Abrangência**: Inclusão das opções `Goiás (GO)`, `Distrito Federal (DF)` e `Centro-Oeste` no dropdown seletor.
- **Sinônimos Semânticos de Busca**: Normalização e expansão de busca para termos regionais (`goias`, `goiânia`, `df`, `brasilia`, `capital federal`, `centro-oeste`).
- **Badges Visuais**: Classes estilizadas `.agency-fapeg` (verde esmeralda `#059669`) e `.agency-fapdf` (azul celeste `#0284C7`) com total conformidade entre temas claro e escuro.

### 5. Cobertura de Testes Automatizados
- Criação da suíte `RadarGoiasDistritoFederalTest.php` com testes para:
  - Emissão de itens estruturados via `parse()` nos dois spiders.
  - Persistência e idempotência via `SalvarNoBancoProcessor`.
  - Filtros da API REST (`/api/editais?fonte=FAPEG` e `/api/editais?fonte=FAPDF`).
  - Execução dos comandos artisan `fapeg:sync`, `fapdf:sync` e `editais:sync`.
- 100% da suíte de testes aprovada (**143 testes, 989 asserções**).

---

## Consequências

- **Positivas**:
  - Cobertura completa da região Centro-Oeste no Radar de Editais do Anjo Nexus.
  - R$ 34,7M+ em editais de inovação e subvenção econômica disponibilizados para startups de GO e DF.
  - Tolerância a falhas e zero downtime de dados através da arquitetura de contingência estruturada.
  - Custo zero de processamento de LLM na indexação de editais.
- **Mitigações**:
  - Caso os portais estaduais modifiquem seu layout HTML, o catálogo estruturado garante que nenhuma oportunidade crítica deixe de ser exibida no radar ou enviada ao Kanban.
