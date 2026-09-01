# Matriz GUT - Priorização de Sprints (Anjo Nexus)

A **Matriz GUT** (Gravidade, Urgência e Tendência) foi elaborada para ditar o ritmo de código da equipe. Ela justifica matematicamente por que não podemos começar programando a IA (que é o grande atrativo de vendas) antes de estabilizar o motor de raspagem e o faturamento.

**Critérios de Pontuação (1 a 5):**
* **Gravidade (G):** Impacto no negócio/TCC se não for entregue.
* **Urgência (U):** Pressão de tempo para entregar.
* **Tendência (T):** O potencial do problema crescer se for ignorado.

## Priorização do Backlog (Roadmap)

| Épico / Módulo | G | U | T | Nota (G×U×T) | Justificativa de Negócio e Arquitetura |
| :--- | :---: | :---: | :---: | :---: | :--- |
| **1. Autenticação e Multi-Tenant** | 5 | 5 | 5 | **125** | Sem Auth, o conceito de B2B2B não existe. A consultoria precisa do seu "workspace" isolado para gerenciar suas startups. |
| **2. Assinaturas (Mercado Pago)** | 5 | 5 | 4 | **100** | Prova para a banca que é um SaaS real e validado financeiramente. O Webhook assíncrono é fundação estrutural. |
| **3. Crawler Nacional (RoachPHP)** | 5 | 4 | 4 | **80** | O motor de dados. Sem extrair os prazos e requisitos das FAPs dos 27 estados, o sistema não tem matéria-prima para trabalhar. |
| **4. Motor de Match** | 4 | 3 | 3 | **36** | Funcionalidade *core* para a automação da consultoria, mas dependente absoluto dos dados raspados no Módulo 3. |
| **5. Kanban e IA (Gemini)** | 3 | 2 | 2 | **12** | É o "Matador de Concorrência" (Escreve a minuta sozinho). Tem nota GUT baixa pois é o ápice da pirâmide: só funciona se a base (Auth, Scraper e Match) estiver sólida. |

---

### Insights de Gestão (Scrum)
A ordem da GUT reflete a maturidade do software. O time (Ademar e Pedro) focará as Sprints iniciais em "trabalho invisível" (Módulos 1, 2 e 3). O encanto visual e a "Mágica da IA" que brilhará na apresentação do TCC (Módulo 5) será desenvolvida apenas nas Sprints finais, mitigando o risco de ter uma IA que fala bonito, mas sem banco de dados estruturado por trás.
