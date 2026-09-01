# Matriz RACI - Papéis e Responsabilidades (Projeto Anjo Nexus)

Esta matriz delineia as responsabilidades para a execução do plano de Sprints do TCC. O foco operacional da equipe foi alinhado para construir não apenas um software, mas um **Operating System (OS) para Consultorias de Inovação** (B2B2B).

**Legenda dos Papéis:**
* **R (Responsible):** Quem executa a tarefa ("mão na massa").
* **A (Accountable):** O "Dono" da tarefa. Quem aprova e garante que o entregável atende à regra de negócio (No nosso cenário, o PO detém forte peso aqui).
* **C (Consulted):** Especialista consultado durante a execução.
* **I (Informed):** Mantido informado do progresso.

## Perfil da Equipe (Squad)
Para ganhar escala e atender à complexidade de integrar Web Scraping, IA e Kanban, os focos foram assim divididos:
* **Ademar:** PO (Product Owner) e Fullstack (Foco Backend). Como idealizador da regra de negócio (o pivot para B2B2B), é o *Accountable* por garantir que o motor de Scraping (RoachPHP) e a IA (Gemini) realmente resolvam a dor do consultor.
* **Pedro:** Desenvolvedor Frontend. Responsável por traduzir a complexidade do backend em uma interface fluida (Vanilla JS) para o usuário final (o consultor).
* **Roberto:** Gestor do Projeto (Scrum Master), Qualidade e Documentação. Garante que as Sprints do Jira andem no prazo apertado do TCC.

## Matriz de Distribuição por Módulos/Épicos

| Épico / Módulo (Tarefas) | Ademar (PO/Back) | Pedro (Front) | Roberto (Gestor/Docs) | Orientador |
| :--- | :---: | :---: | :---: | :---: |
| **Módulo 1: Autenticação (Base SaaS)** | R / A | R | C | I |
| **Módulo 2: Assinatura/Webhook (B2B)** | R / A | R | C | I |
| **Módulo 3: Crawler 27 Estados (RoachPHP)** | R / A | C | I | I |
| **Módulo 4: Match Engine (Tags vs Startups)** | R / A | R | C | I |
| **Módulo 5: Kanban & Minuta IA (Gemini)** | R / A | R | I | I |
| **Gestão do Jira e Monitoramento de Sprints** | A | I | R | I |
| **Documentação Acadêmica (UML, Pitch)** | C | I | R / A | I |
| **Testes E2E e Validação de UX** | C | R / A | R | I |

---

### Dinâmica de Execução
A matriz revela que o **Ademar** puxa a responsabilidade da engenharia pesada (`R/A` nos módulos 1 a 5), mas ele depende diretamente do **Pedro** para que essa engenharia seja utilizável. O **Roberto** atua como o escudo metodológico, blindando os desenvolvedores de burocracias acadêmicas e garantindo a rastreabilidade do projeto no Jira.
