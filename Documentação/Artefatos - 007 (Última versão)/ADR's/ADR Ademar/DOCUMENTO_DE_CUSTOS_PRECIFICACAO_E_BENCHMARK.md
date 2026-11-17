# Documento de Viabilidade Financeira, Estrutura de Custos, Precificação SaaS e Benchmark Competitivo

**Projeto:** Anjo Nexus — Plataforma Inteligente de Mineração de Editais de Inovação e Elaboração de Propostas para Startups  
**Versão:** 1.0 (Consolidada para Defesa de Banca e Modelo de Negócio)  
**Data:** Novembro de 2026  
**Autoria:** Equipe de Engenharia e Produto Anjo Nexus  

---

## Sumário Executivo

O presente documento detalha a viabilidade econômica, a engenharia de custos operacionais (OPEX/COGS), a estratégia de monetização e a análise comparativa de mercado do ecossistema **Anjo Nexus**.

Diferente de sistemas de software tradicionais que acumulam despesas elevadas de computação em nuvem e inteligência artificial generativa, o Anjo Nexus foi arquitetado sob o paradigma de **Custo Marginal Próximo de Zero**, combinando:
1. Mineração determinística e autônoma de editais públicos sem consumo de tokens de IA;
2. Modelos LLMs ultrarrápidos e eficientes (Gemini Flash / Groq) utilizados exclusivamente no momento de maior valor agregado (elaboração de minutas e propostas técnicas);
3. Limitação estrita de cotas mensais de IA por assinatura contratada (`MinutaLimitService`);
4. Infraestrutura leve em PHP 8.2 e MySQL com entrega híbrida de frontend e aplicativo mobile nativo (Capacitor 7).

O resultado financeiro é uma **margem bruta superior a 95%** em todos os planos, tornando o negócio altamente rentável, escalável e imune a surpresas orçamentárias de provedores de nuvem.

---

## 1. Estrutura Detalhada de Gastos e Custos Operacionais (OPEX & COGS)

### 1.1. Infraestrutura de Hospedagem, Servidores e Domínio

| Item de Custo | Provedor / Tecnologia | Finalidade Técnica | Custo Mensal Estimado (R$) | Custo Anual Estimado (R$) |
| :--- | :--- | :--- | :---: | :---: |
| **Hospedagem Web / Servidor** | HostGator (Plano Business cPanel) | Execução do Backend Laravel 12, Banco MySQL, Cron Jobs dos Spiders e Frontend Desacoplado | R$ 39,90 | R$ 478,80 |
| **Domínio Próprio** | Registro.br | Endereço institucional oficial (`anjonexus.intalenti.com.br`) | R$ 3,33 | R$ 40,00 |
| **Certificado de Segurança** | Let's Encrypt / AutoSSL cPanel | Criptografia ponta a ponta HTTPS/TLS para web e mobile | R$ 0,00 (Gratuito) | R$ 0,00 |
| **Pipeline de CI/CD** | GitHub Actions (Ubuntu Runners) | Compilação autônoma do APK Android (`build-apk.yml`) e suíte de testes automatizados | R$ 0,00 (2.000 min/mês inclusos no GitHub público) | R$ 0,00 |
| **Armazenamento de Anexos** | Disco SSD Local do Servidor | Armazenamento de editais e arquivos anexos de cards (PDFs até 10MB) | R$ 0,00 (Incluso na hospedagem cPanel até 100GB) | R$ 0,00 |
| **TOTAL INFRAESTRUTURA BASE** | — | — | **R$ 43,23 / mês** | **R$ 518,80 / ano** |

> [!NOTE]
> Com uma infraestrutura de apenas **R$ 43,23 por mês**, o sistema atende com tranquilidade centenas de agências e startups cadastradas simultaneamente, graças ao isolamento dos assets estáticos servidos diretamente pelo Apache sem onerar o interpretador PHP.

---

### 1.2. Custos de Inteligência Artificial Generativa (LLMs)

A estratégia de inteligência artificial do Anjo Nexus foi dividida em duas fases estritas no código-fonte para blindar o fluxo de caixa:

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                        ARQUITETURA DE CONSUMO DE INTELIGÊNCIA ARTIFICIAL               │
├────────────────────────────────────────────────────────────────────────────────────────┤
│ 1. MINERAÇÃO & SCRAPING (FINEP, FAPESC, FAPESP) ───────► CUSTO R$ 0,00 (DETERMINÍSTICO)│
│    - Roach PHP + XPath Semântico + Regex                                               │
│    - Parser nativo de PDF (smalot/pdfparser)                                           │
│                                                                                        │
│ 2. CÁLCULO DE MATCH STARTUP-EDITAL ────────────────────► CUSTO R$ 0,00 (DETERMINÍSTICO)│
│    - Algoritmo multicritério (CNAE, TRL, palavras-chave, 5% contrapartida)             │
│                                                                                        │
│ 3. ELABORAÇÃO DE MINUTAS TÉCNICAS (11 SEÇÕES) ─────────► CUSTO REAL: R$ 0,007 / MINUTA │
│    - Google Gemini Flash (US$ 0,075/1M input, US$ 0,30/1M output)                      │
│    - Fallback Groq (Mixtral) e contingência local determinística em PHP (<10ms)        │
│    - Cota controlada via MinutaLimitService no banco de dados                          │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

#### Cálculo Exato de Custo por Minuta Técnica Gerada:
- **Tamanho médio do Prompt de Entrada:** 2.500 tokens (Diretrizes do edital + dados cadastrais da startup + critérios do Anjo Inovador).
  - Custo de Entrada (Gemini 1.5/2.5 Flash): $\frac{2.500}{1.000.000} \times \text{US\$} 0,075 = \text{US\$} 0,0001875$
- **Tamanho médio da Minuta de Saída:** 3.500 tokens (11 seções completas formatadas com metas, cronograma de 12 meses, TRL, ODS e matriz de riscos).
  - Custo de Saída (Gemini 1.5/2.5 Flash): $\frac{3.500}{1.000.000} \times \text{US\$} 0,30 = \text{US\$} 0,0010500$
- **Custo Total por Minuta em Dólares:** $\text{US\$} 0,0001875 + \text{US\$} 0,0010500 = \text{US\$} 0,0012375$
- **Custo Convertido em Reais (Cotação USD = R$ 5,80):**
  $$\text{Custo Real por Minuta} \approx \mathbf{R\$\ 0,00718} \quad (\text{Menos de um centavo de real por proposta gerada!})$$

#### Custo Mensal Máximo de IA por Assinante (Cenário de Consumo de 100% da Cota):
- **Plano Standard (3 minutas/mês):** $3 \times R\$\ 0,0072 = \mathbf{R\$\ 0,022 / \text{mês}}$
- **Plano Pro (15 minutas/mês):** $15 \times R\$\ 0,0072 = \mathbf{R\$\ 0,108 / \text{mês}}$
- **Plano Enterprise (50 minutas/mês):** $50 \times R\$\ 0,0072 = \mathbf{R\$\ 0,359 / \text{mês}}$

> [!IMPORTANT]
> Mesmo que um cliente Enterprise utilize todos os dias a sua cota integral de 50 propostas no mês, o gasto direto em inteligência artificial é de apenas **R$ 0,36 (trinta e seis centavos)**. O módulo `MinutaLimitService` impede qualquer consumo excedente não tarifado, eliminando completamente o risco de custos inesperados.

---

### 1.3. Custos de Meios de Pagamento e Transações Financeiras (Gateway Pix)

- **Provedor:** AbacatePay v2 (`AbacatePayService.php`).
- **Modelo de Cobrança:** Tarifa fixa de **R$ 0,80 por Pix aprovado**.
- **Adesão e Mensalidade do Gateway:** **R$ 0,00**.
- **Cobrança por Pix Não Pago ou Expirado (60 min):** **R$ 0,00**.
- **Custo Proporcional por Transação:**
  - Plano Standard (R$ 49,90): R$ 0,80 (1,60% da receita do cliente).
  - Plano Pro (R$ 99,90): R$ 0,80 (0,80% da receita do cliente).
  - Plano Enterprise (R$ 299,90): R$ 0,80 (0,27% da receita do cliente).

---

### 1.4. Custos de APIs de Terceiros e Enriquecimento de Dados

- **BrasilAPI e ReceitaWS (Validação e Enriquecimento de CNPJ):** Utilizam endpoints públicos e gratuitos.
- **Mecanismo de Otimização no Código (`CnpjApiTest` / `CnpjCache`):** O sistema salva o retorno do CNPJ no banco de dados com validade de 30 dias. Consultas subsequentes para o mesmo CNPJ não geram nenhuma requisição externa. Custo: **R$ 0,00**.

---

### 1.5. Custo Unitário por Cliente (COGS) e Análise de Margem Bruta

Considerando um cenário conservador onde 100 clientes utilizem ativamente a plataforma (30 Standard, 50 Pro, 20 Enterprise):

| Plano | Preço de Venda (MRR) | Custo Gateway Pix | Custo Máx. IA (100% cota) | Fração de Hospedagem | Custo Unitário Total (COGS) | Lucro Bruto Unitário | Margem Bruta (%) |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **Standard** | **R$ 49,90** | R$ 0,80 | R$ 0,02 | R$ 0,43 | **R$ 1,25** | **R$ 48,65** | **97,5%** |
| **Pro** | **R$ 99,90** | R$ 0,80 | R$ 0,11 | R$ 0,43 | **R$ 1,34** | **R$ 98,56** | **98,7%** |
| **Enterprise** | **R$ 299,90** | R$ 0,80 | R$ 0,36 | R$ 0,43 | **R$ 1,59** | **R$ 298,31** | **99,5%** |

> [!TIP]
> A margem bruta média da operação supera **98%**, o que é um indicador de excelência para negócios digitais no formato B2B SaaS, permitindo reinvestimento maciço em aquisição de clientes (CAC) e marketing digital.

---

## 2. Quanto a Gente Cobra e Forma de Precificação (Pricing Strategy)

### 2.1. Modelo de Negócio: B2B SaaS por Assinatura Mensal

O Anjo Nexus adota o modelo **SaaS (Software as a Service) por Assinatura Recorrente Mensal**, focado em resolver dores agudas de:
1. **Startups em Estágio de Ideação/Validação:** Que não possuem caixa para pagar consultorias tradicionais caras.
2. **Consultorias de Captação e Inovação:** Que precisam de automação para gerenciar dezenas de clientes sem aumentar o quadro de analistas.
3. **Hubs de Inovação, Incubadoras e Aceleradoras:** Que acompanham dezenas de startups residentes e precisam direcioná-las aos editais corretos.

### 2.2. A Métrica de Valor (*Value Metric*)

A precificação não é baseada em "tempo de uso" ou "tamanho de tela", mas sim nas 3 variáveis que determinam o crescimento do cliente:
1. **Startups Gerenciadas no Portfólio:** Capacidade de atendimento multitenant.
2. **Membros de Equipe:** Colaboração em tempo real no mesmo ambiente corporativo.
3. **Minutas Técnicas de Editais com IA Geradas:** Volume de propostas formais submetidas a editais no mês.

---

### 2.3. Matriz Oficial de Planos Comerciais (Implementada no `PlanoSeeder.php`)

```
┌───────────────────────────────┬───────────────────────────────┬───────────────────────────────┐
│        PLANO STANDARD         │           PLANO PRO           │       PLANO ENTERPRISE        │
│          R$ 49,90/mês         │          R$ 99,90/mês         │         R$ 299,90/mês         │
│     (Para Consultor Autônomo) │     (Mais Popular / PMEs)     │    (Aceleradoras e Hubs)      │
├───────────────────────────────┼───────────────────────────────┼───────────────────────────────┤
│ • 2 Startups no Portfólio     │ • 10 Startups no Portfólio    │ • 50 Startups no Portfólio    │
│ • 1 Usuário Consultor         │ • 5 Membros de Equipe         │ • 15 Membros de Equipe        │
│ • 3 Minutas Técnicas com IA   │ • 15 Minutas Técnicas com IA  │ • 50 Minutas Técnicas com IA  │
│ • Radar de Editais em Tempo   │ • Radar de Editais Completo   │ • Radar de Editais Completo   │
│   Real (FINEP, FAPESC, FAPESP)│ • Kanban Avançado             │ • Kanban Avançado             │
│ • Quadro Kanban de Gestão     │ • Exportação DOCX Nativa      │ • Exportação DOCX Nativa      │
│ • Alertas de Prazos Críticos  │ • Alerta de Match Inteligente │ • Alertas de Match e Prazos   │
│ • Acesso Web e Mobile Nativo  │ • Notificações Multi-canal    │ • Auditoria Completa de Versão│
│                               │ • Acesso Web e Mobile Nativo  │ • Suporte Prioritário         │
└───────────────────────────────┴───────────────────────────────┴───────────────────────────────┘
```

#### Detalhamento das Entregas por Plano:

1. **Plano Standard — R$ 49,90 / mês:**
   - **Público-alvo:** Founder técnico de uma startup individual ou consultor de inovação autônomo iniciante.
   - **O que entrega:** Acesso irrestrito a todos os editais minerados no Brasil, quadro kanban pessoal para acompanhar as etapas, alertas automáticos antes do encerramento dos editais e cota de 3 minutas completas por mês (suficiente para aplicar em até 3 editais simultâneos).

2. **Plano Pro — R$ 99,90 / mês (Carro-Chefe / *Sweet Spot*):**
   - **Público-alvo:** Agências de consultoria de fomento, pequenos escritórios de captação e startups com equipe estruturada.
   - **O que entrega:** Gerenciamento centralizado de até 10 startups simultâneas com troca instantânea pelo Seletor Global, 5 colaboradores atuando juntos, 15 minutas geradas com IA por mês, cálculo automático de score de compatibilidade (Match) e download dos arquivos em formato `.docx` pronto para submissão.

3. **Plano Enterprise — R$ 299,90 / mês:**
   - **Público-alvo:** Aceleradoras, Parques Tecnológicos, Núcleos de Inovação Tecnológica (NITs) e grandes agências de assessoria.
   - **O que entrega:** Monitoramento em larga escala de 50 startups residentes, 15 acessos simultâneos para analistas da equipe, 50 minutas técnicas mensais e controle estrito de histórico auditável de propostas.

---

### 2.4. Estratégia de Expansão de Receita (Upselling e Add-ons)

Além da mensalidade fixa, a plataforma conta com fontes complementares de receita:
- **Pacote Adicional de Minutas IA (Cota Extra):** Caso a agência atinja o limite do mês e queira elaborar mais propostas sem subir de plano: **R$ 19,90 por pacote de +10 minutas**.
- **Startup Adicional Avulsa:** **R$ 15,00/mês por startup extra** mantida no portfólio.
- **Taxa de Sucesso Opcional (*Success Fee* em Submissões Assistidas):** Em fases futuras do roadmap, a plataforma pode oferecer serviços de revisão humana com percentual sobre o valor obtido no edital (ex: 2% a 5%, muito inferior aos 20% das consultorias tradicionais).

---

## 3. Comparação de Ferramentas Parecidas e Benchmark de Mercado

Para entender o diferencial competitivo do Anjo Nexus, analisamos as quatro principais categorias de soluções existentes no mercado atual:

```
                                      MATRIZ DE POSICIONAMENTO COMPETITIVO
                                      
             Alto Custo │
                        │           [Consultorias Tradicionais]
                        │           (GAC, FI Group, BHub)
                        │           R$ 5.000/mês + 20% de comissão
                        │           (Serviço manual, demorado e caro)
                        │
                        │   [Portais de Licitação]
                        │   (ConLicitação, Effecti)
                        │   R$ 500 a R$ 1.500/mês
                        │   (Apenas compras públicas e pregões)
                        │
                        │                                          ★ ANJO NEXUS ★
                        │                                          R$ 49,90 a R$ 299,90/mês
                        │                                          (Especializado em Inovação,
                        │                                           Match com IA, Minutas DOCX,
                        │                                           Kanban e App Mobile)
                        │
                        │   [Ferramentas Genéricas]
                        │   (ChatGPT + Trello + Planilhas)
                        │   R$ 150/usuário
                        │   (Manual, desintegrado e propenso a erros)
              Baixo Custo│────────────────────────────────────────────────────────────►
                        Baixa Especialização                      Alta Especialização
                        em Editais de Inovação                    em Editais de Inovação
```

---

### 3.1. Análise Categórica das Soluções Concorrentes

#### Categoria 1: Plataformas de Licitações Públicas Tradicionais
*(Exemplos: ConLicitação, Effecti, SigaPregão, Portal de Compras Públicas)*
- **O que entregam:** Raspagem de Diários Oficiais e do Portal Nacional de Contratações Públicas (PNCP), focadas em pregões eletrônicos e licitações para fornecimento de bens e serviços ao Estado (ex: venda de computadores, merenda escolar, obras de engenharia, limpeza predial).
- **Faixa de Preço:** **R$ 350,00 a R$ 1.500,00 por mês**.
- **Por que NÃO atendem startups de inovação:**
  - Zero cobertura de editais de **subvenção econômica a fundo perdido** (onde o governo dá dinheiro para a empresa desenvolver tecnologia sem pedir devolução).
  - Não integram com agências de fomento científico e tecnológico (FINEP, FAPESC, FAPESP, EMBRAPII, CNPq).
  - Não possuem análise de maturidade tecnológica (**TRL**), nem conhecem modelos de inovação.
  - Não geram minutas técnicas nem planos de trabalho de P&D.

#### Categoria 2: Consultorias Especializadas em Captação de Recursos e Fomento
*(Exemplos: GAC Brasil, BHub Inovação, FI Group, Consultorias Boutiques Regionais)*
- **O que entregam:** Trabalho consultivo 100% humano. Analistas leem os editais, marcam reuniões de alinhamento e escrevem a proposta manualmente.
- **Faixa de Preço:** Retainer mensal fixo de **R$ 3.000,00 a R$ 10.000,00** + **Taxa de Sucesso (*Success Fee*) de 10% a 25%** sobre o valor captado. Em um edital de R$ 500.000 da FINEP, a consultoria embolsa entre R$ 50.000 e R$ 125.000 da startup.
- **Por que deixam uma lacuna no mercado:**
  - Totalmente inacessível para 95% das startups em estágio inicial, que não têm caixa para pagar mensalidades de milhares de reais antes de serem contempladas.
  - Processo lento e artesanal: demoram de 3 a 5 semanas para redigir uma única proposta técnica.
  - **Oportunidade para o Anjo Nexus:** O Anjo Nexus custa a partir de R$ 49,90/mês, gera o primeiro esboço completo da proposta em 15 segundos e democratiza o acesso ao capital público. Ao mesmo tempo, o Anjo Nexus pode ser vendido **para as próprias consultorias**, multiplicando a produtividade de seus analistas por 10x.

#### Categoria 3: Plataformas Internacionais de Bolsas e Financiamento
*(Exemplos: OpenGrants, Instrumentl, GrantWatch, GrantForward)*
- **O que entregam:** Buscadores avançados de financiamento público e privado com filtros temáticos e inteligência artificial.
- **Faixa de Preço:** **US$ 199,00 a US$ 499,00 por mês** (~R$ 1.150,00 a R$ 2.900,00 por mês).
- **Por que não funcionam no Brasil:**
  - Cobertura quase exclusiva do ecossistema norte-americano e europeu (SBIR, STTR, Horizon Europe).
  - Nenhuma aderência aos portais e exigências burocráticas do Brasil (Marco Legal das Startups, Lei da Inovação 10.973/04, contrapartida obrigatória de 5%, regras tributárias brasileiras).
  - Preço em dólar inviável para o mercado nacional.

#### Categoria 4: Ferramentas Genéricas Improvisadas
*(Exemplos: ChatGPT Plus + Trello + Pastas no Google Drive + Planilhas Excel)*
- **O que entregam:** O founder tenta navegar manualmente nos sites da FAPESC e FINEP, baixa PDFs de 80 páginas, cola pedaços no ChatGPT genérico e organiza os prazos no Trello.
- **Faixa de Preço:** **~R$ 150,00 a R$ 250,00 / usuário / mês**.
- **Por que falham:**
  - **Falta de Automação:** Se a pessoa esquecer de entrar no site da FAPESC na quinta-feira, perde o prazo do edital.
  - **Alucinação da IA Genérica:** O ChatGPT genérico não conhece as regras da Lei de Inovação nem a fórmula oficial de contrapartida de 5%, inventando prazos e exigências financeiras erradas.
  - **Desconexão:** O chat não sabe qual edital está vinculado a qual startup, gerando confusão de documentos e perda de anexos cruciais para a habilitação jurídica.

---

### 3.2. Matriz Comparativa de Recursos (*Feature Comparison Matrix*)

| Recurso / Funcionalidade | **ANJO NEXUS** | Plataformas de Licitação | Consultorias Tradicionais | Ferramentas Genéricas (ChatGPT + Trello) |
| :--- | :---: | :---: | :---: | :---: |
| **Foco Exclusivo em Subvenção e Inovação (FINEP, FAPESC, FAPESP)** | ✅ **Sim (100% nativo)** | ❌ Não (Apenas compras de prefeitura) | ✅ Sim (Serviço humano) | ❌ Não |
| **Mineração Automatizada de Editais Públicos (Scraping Autônomo)** | ✅ **Sim (Robôs diários)** | ✅ Sim (Foco em pregões) | ❌ Não (Busca manual) | ❌ Não (Zero automação) |
| **Cálculo de Match de Afinidade Startup-Edital (Score %)** | ✅ **Sim (CNAE, TRL, Nicho)** | ❌ Não | ⚠️ Parcial (Subjetivo) | ❌ Não |
| **Geração de Minuta Técnica Estruturada (11 Seções Oficiais)** | ✅ **Sim (IA em 15s)** | ❌ Não | ✅ Sim (Leva 3 a 4 semanas) | ⚠️ Frágil (Sem contexto legal) |
| **Exportação Direta em Formato Word (.docx)** | ✅ **Sim (Nativo OpenXML)** | ❌ Não | ✅ Sim (Manuscrito) | ❌ Não (Copy/Paste manual) |
| **Gestão Visual Kanban com Fractional Indexing** | ✅ **Sim (Arrastar e soltar)** | ❌ Não | ❌ Não | ⚠️ Sim (Genérico) |
| **Gestão Multitenant B2B (Multi-startups por Agência)** | ✅ **Sim (Até 50 startups)** | ❌ Não | ⚠️ Planilhas internas | ❌ Não |
| **Alertas Multicanal (E-mail + Web Toast + Push Mobile)** | ✅ **Sim (Multi-canal)** | ⚠️ Apenas E-mail | ⚠️ Mensagens WhatsApp | ❌ Não |
| **Aplicativo Mobile Nativo (Android APK com Haptic Touch)** | ✅ **Sim (Capacitor 7)** | ❌ Não | ❌ Não | ⚠️ Apenas app genérico |
| **Preço Mensal Inicial** | **R$ 49,90** | **R$ 350 a R$ 1.500** | **R$ 3.000 + 20% comissão** | **~R$ 150 por usuário** |
| **Cobrança de Taxa de Sucesso sobre o Prêmio Captado** | **R$ 0,00 (0%)** | R$ 0,00 | **10% a 25% do edital** | R$ 0,00 |

---

## 4. Projeção Financeira e Escalabilidade do Negócio

### 4.1. Projeção de Faturamento Recorrente (MRR) e Custos em 12 Meses

Considerando uma curva de tração moderada de aquisição de agências de consultoria e startups:

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│               PROJEÇÃO DE CRESCIMENTO DE ASSINANTES E FATURAMENTO (MRR)                │
├──────────┬─────────────────┬──────────────────┬─────────────────┬───────────┬──────────┤
│ Período  │ Clientes Standard│ Clientes Pro    │ Clientes Enterp.│ Faturamento│ Custo     │
│          │ (R$ 49,90)      │ (R$ 99,90)       │ (R$ 299,90)     │ Mensal    │ Operacional│
├──────────┼─────────────────┼──────────────────┼─────────────────┼───────────┼──────────┤
│ Mês 03   │ 15 clientes     │ 25 clientes      │ 5 clientes      │ R$ 4.745  │ R$ 112   │
│ Mês 06   │ 40 clientes     │ 65 clientes      │ 15 clientes     │ R$ 12.988 │ R$ 225   │
│ Mês 09   │ 80 clientes     │ 130 clientes     │ 35 clientes     │ R$ 27.476 │ R$ 410   │
│ Mês 12   │ 150 clientes    │ 250 clientes     │ 70 clientes     │ R$ 53.453 │ R$ 745   │
└──────────┴─────────────────┴──────────────────┴─────────────────┴───────────┴──────────┘
```

- **Faturamento Anualizado (ARR) no Mês 12:** **R$ 641.436,00**
- **Custo Anual Total de Operação (Cloud + IA + Gateway):** **R$ 8.940,00**
- **Lucro Bruto Operacional:** **R$ 632.496,00 (Margem Bruta de 98,6%)**

---

## 5. Perguntas & Respostas Frequentes para a Banca Avaliadora (Defesa de TCC)

### P1: "Se o modelo de vocês usa Inteligência Artificial Generativa, como a operação não fica no prejuízo com o aumento de usuários?"
> **Resposta:**  
> *"Nossa arquitetura foi blindada em duas frentes fundamentais:  
> 1. **Custo Zero na Ingestão:** Não usamos IA para raspar páginas ou ler PDFs brutos; toda a extração de dados da FINEP e FAPESC é determinística via Roach PHP, XPath e Expressões Regulares de altíssima velocidade.  
> 2. **Cotas Estritas de IA por Assinatura:** Implementamos o `MinutaLimitService` no backend. O plano Standard dá direito a 3 minutas técnicas, o Pro a 15 e o Enterprise a 50. Como cada minuta técnica no modelo Gemini Flash custa cerca de R$ 0,007 (menos de um centavo), o gasto máximo de IA de um cliente Pro que usa 100% da cota é de 11 centavos por mês, contra uma mensalidade de R$ 99,90. Isso garante margem bruta de mais de 98% sob qualquer escala de usuários."*

### P2: "Por que as startups e consultorias pagariam pelo Anjo Nexus em vez de contratar uma consultoria tradicional como a GAC ou BHub?"
> **Resposta:**  
> *"As consultorias tradicionais cobram mensalidades fixas de R$ 3.000 a R$ 10.000, mais uma comissão de 10% a 25% sobre o valor conquistado. Para uma startup em validação, pagar R$ 50 mil de comissão em um edital de R$ 300 mil é financeiramente inviável. O Anjo Nexus entrega por R$ 49,90 ou R$ 99,90 por mês a mesma capacidade de detecção de oportunidades, com minutas técnicas estruturadas em minutos e zero cobrança de taxa de sucesso. Além disso, as próprias consultorias de menor porte são nossas clientes ideais, pois utilizam o Anjo Nexus para gerenciar o portfólio de 10 a 50 startups com apenas 1 analista."*

### P3: "Por que não utilizar simplesmente plataformas consagradas como ConLicitação ou Effecti?"
> **Resposta:**  
> *"Plataformas como ConLicitação e Effecti atuam exclusivamente no mercado de contratações públicas tradicionais (pregões e compras de bens/serviços pelo Estado). Elas não monitoram e não entendem os editais de subvenção econômica e inovação a fundo perdido da FINEP, FAPESC e FAPESP. Elas não possuem matriz de maturidade tecnológica (TRL), não calculam contrapartida da Lei de Inovação e não geram planos de trabalho de P&D. O Anjo Nexus atua no nicho de fomento tecnológico, onde essas ferramentas não operam."*

---

## 6. Conclusão

O ecossistema **Anjo Nexus** prova sua viabilidade não apenas como um projeto acadêmico de alta excelência técnica em engenharia de software, mas como um **produto digital SaaS comercialmente viável, altamente rentável e com barreiras de entrada sólidas**.

A combinação de:
- Mineração autônoma de dados públicos;
- IA generativa de custo insignificante restrita ao momento de maior impacto;
- Formatos de exportação oficial (`.docx` para Anjo Inovador);
- E presença em canais Web e Mobile nativo;

Posiciona a plataforma com uma proposta única de valor no mercado brasileiro de inovação e captação de recursos públicos.

