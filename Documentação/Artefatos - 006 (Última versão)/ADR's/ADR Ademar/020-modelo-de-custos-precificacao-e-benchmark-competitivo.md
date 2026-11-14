# ADR 020: Modelo de Custos Operacionais, Estratégia de Precificação SaaS e Benchmark Competitivo de Mercado

## Status
Aceito

---

## Contexto (Jira EDTAN-250 / EDTAN-209 / Viabilidade Econômica)

O ecossistema **Anjo Nexus** foi concebido para resolver o gargalo de captação de recursos públicos de fomento (subvenção econômica a fundo perdido da FINEP, FAPESC e FAPESP) enfrentado por startups e consultorias de inovação no Brasil.

Historicamente, produtos de software que integram inteligência artificial generativa (LLMs) enfrentam severos riscos de sustentabilidade financeira:
1. **Custos Imprevisíveis de Tokens:** Aplicações ingênuas que submetem centenas de editais públicos (PDFs de 50 a 100 páginas) diretamente a modelos como GPT-4 geram faturas astronômicas em dólares, inviabilizando qualquer modelo SaaS de baixo custo.
2. **Abuso de Consumo por Usuários:** Sem travas rígidas de governança no banco de dados, assinantes de planos de entrada podem gerar centenas de requisições de minutas técnicas, consumindo margens de lucro da plataforma.
3. **Descompasso com a Realidade de Mercado:** No Brasil, consultorias tradicionais cobram mensalidades de R$ 3.000 a R$ 10.000 mais comissões de 10% a 25% sobre o valor captado, afastando startups em estágio de validação. Por outro lado, plataformas de licitação pública (como ConLicitação e Effecti) cobram de R$ 350 a R$ 1.500/mês, mas cobrem apenas pregões de compras de prefeituras (sem qualquer cobertura de editais de inovação e subvenção).

Fazia-se indispensável formalizar uma decisão arquitetural que unisse **engenharia de custos próxima de zero**, **estratégia de precificação recorrente multitenant** e **posicionamento competitivo vantajoso** frente aos substitutos de mercado.

---

## Decisão

Adotamos uma arquitetura integrada de viabilidade econômica e monetização composta por:

### 1. Separação Estrita de Custos de IA (Ingestão Determinística vs Minutas sob Demanda)
- **Custo Zero na Ingestão (RF-01):** A mineração diária e o parsing de editais nos portais da FINEP, FAPESC e FAPESP é **100% determinística**, conduzida via Roach PHP, árvores XPath e regex nativo (`smalot/pdfparser`), com **consumo zero de tokens de IA**.
- **Cálculo Determinístico de Match (RF-47):** A afinidade edital-startup utiliza pontuação multicritério em PHP baseada em CNAE, TRL e palavras-chave sem custo de API externa.
- **Uso Focado de LLMs (RF-33):** A inteligência artificial generativa atua exclusivamente no funil final, durante a redação das 11 seções da minuta técnica do Anjo Inovador, utilizando modelos de alta performance e baixo custo (Google Gemini Flash / Groq) e contingência determinística local em PHP (<10ms).

### 2. Blindagem de Margem por Cotas de Minutas IA no Banco (`MinutaLimitService`)
No banco de dados (`planos.limite_minutas_ia`), cada plano contratado possui uma franquia estrita de minutas com IA por mês:
- **Standard (R$ 49,90):** Franquia de 3 minutas técnicas/mês.
- **Pro (R$ 99,90):** Franquia de 15 minutas técnicas/mês.
- **Enterprise (R$ 299,90):** Franquia de 50 minutas técnicas/mês.

O `MinutaLimitService` intercepta requisições no `MinutaController` antes de acionar qualquer endpoint de IA externo. Caso a agência atinja o teto mensal, a API bloqueia a execução com código HTTP 403 e código semântico `LIMITE_MINUTAS_ATINGIDO`, impedindo qualquer estouro orçamentário.

### 3. Precificação por Métrica de Valor (*Value Metric*)
A precificação SaaS é estruturada no número de startups gerenciadas no portfólio, no número de colaboradores na equipe e na cota de minutas com IA:
- **Standard (R$ 49,90/mês):** 2 Startups, 1 Usuário Consultor, 3 Minutas IA, Radar e Kanban.
- **Pro (R$ 99,90/mês):** 10 Startups, 5 Membros de Equipe, 15 Minutas IA, Match Score, DOCX e Notificações Multi-canal.
- **Enterprise (R$ 299,90/mês):** 50 Startups, 15 Membros de Equipe, 50 Minutas IA, Histórico Auditável e Suporte Prioritário.

### 4. Transações Financeiras via Gateway Pix com Custo Fixo Baixo
Integração com o AbacatePay v2 via Pix nativo com taxa fixa de apenas **R$ 0,80 por transação paga**, com adesão gratuita e custo zero para cobranças pendentes ou canceladas.

---

## Estrutura de Custos Unitários (Unit Economics)

O custo marginal de atendimento de uma agência assinante foi auditado com as seguintes premissas:
- **Custo de Hospedagem (HostGator cPanel):** R$ 39,90/mês fixo.
- **Custo de Domínio:** R$ 3,33/mês fixo.
- **Custo por Minuta Técnica com IA (Gemini Flash):** US$ 0,00124 $\approx$ **R$ 0,00718** (menos de 1 centavo por minuta).
- **Custo de Transação Pix (AbacatePay):** R$ 0,80 por cobrança aprovada.

### Margem Bruta por Plano:
| Plano | Preço Mensal | Custo Pix | Custo Máx. IA | Custo Infra / Cliente (100 clientes) | Custo Unitário Total | Margem Bruta |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| **Standard** | **R$ 49,90** | R$ 0,80 | R$ 0,02 | R$ 0,43 | **R$ 1,25** | **97,5%** |
| **Pro** | **R$ 99,90** | R$ 0,80 | R$ 0,11 | R$ 0,43 | **R$ 1,34** | **98,7%** |
| **Enterprise** | **R$ 299,90** | R$ 0,80 | R$ 0,36 | R$ 0,43 | **R$ 1,59** | **99,5%** |

---

## Benchmark Competitivo de Mercado

| Dimensão de Análise | Anjo Nexus | Plataformas de Licitação (ConLicitação, Effecti) | Consultorias Tradicionais (GAC, BHub, FI Group) | Ferramentas Genéricas (ChatGPT + Trello) |
| :--- | :---: | :---: | :---: | :---: |
| **Foco em Subvenção a Fundo Perdido** | **100% Especializado (FINEP, FAPESC, FAPESP)** | Inexistente (Apenas compras de prefeitura) | Atende (Serviço humano) | Inexistente |
| **Mineração Automatizada de Editais** | **Robôs diários em background** | Sim (Foco em pregões) | Não (Busca manual) | Não (Manual) |
| **Geração de Minutas Técnicas Formais** | **Instantânea (15s) em DOCX** | Não existe | Sim (3 a 5 semanas) | Não estruturada |
| **Precificação Mensal** | **R$ 49,90 a R$ 299,90** | **R$ 350,00 a R$ 1.500,00** | **R$ 3.000 a R$ 10.000** | **~R$ 150/usuário** |
| **Taxa de Sucesso (*Success Fee*)** | **Zero (0%)** | Zero (0%) | **10% a 25% do valor captado** | Zero (0%) |
| **Canal Mobile Nativo com Push** | **Sim (Capacitor 7)** | Não | Não | Apenas app genérico |

---

## Justificativa Acadêmica (TCC / Defesa de Banca)

1. **Demonstração de Viabilidade Econômica Real:** O projeto não é apenas um exercício de código teórico; possui modelo de negócios B2B SaaS validado, métricas unitárias (*Unit Economics*) auditadas e margem bruta superior a 95%, provando viabilidade prática de mercado.
2. **Engenharia de Software Orientada a Custos (*FinOps*):** A separação entre ingestão determinística e geração sob demanda, somada ao controle de cota por middleware no Laravel, demonstra maturidade arquitetural e respeito a restrições orçamentárias de computação em nuvem.
3. **Proposta Única de Valor (UVP) sem Canibalização:** O Anjo Nexus não compete diretamente com os gigantes de compras públicas nem visa substituir as grandes consultorias; pelo contrário, atua no oceano azul de startups de tecnologia e serve como plataforma de produtividade para as próprias consultorias de inovação.

---

## Consequências

### Positivas:
- **Margem de Lucro Excepcional:** Margens brutas superiores a 97% viabilizam investimentos em aquisição de clientes e infraestrutura sem aporte externo de risco.
- **Previsibilidade Orçamentária Total:** O risco de cobranças descontroladas em provedores de IA é anulado pelo `MinutaLimitService` e pelo limite fixo das cotas.
- **Acessibilidade Financeira para Startups:** Mensalidades a partir de R$ 49,90 permitem que qualquer empreendedor em fase de validação tenha acesso a editais públicos de fomento sem pagar taxas de sucesso abusivas.
- **Escala de Clientes:** Atendimento a mais de 100 agências com custos operacionais de servidor inferiores a R$ 50/mês.

### Mitigações:
- **Esgotamento da Cota pelo Usuário:** Caso o consultor utilize toda a cota de minutas antes do fim do mês, a interface do Kanban exibe alerta visual indicando consumo de 100% e permite o upgrade de plano ou contratação de pacote avulso de minutas extras.

