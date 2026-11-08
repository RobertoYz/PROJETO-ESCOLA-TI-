# 📚 Manuais de Estudo de Código e Engenharia de Software - Anjo Nexus

Bem-vindo à central de **Manuais de Estudo Técnico** do ecossistema **Anjo Nexus**.

Estes documentos foram elaborados com **linguagem didática, acessível e comentários linha por linha**, permitindo que estagiários, desenvolvedores juniores e membros da banca examinadora compreendam em detalhes a lógica, a arquitetura, a matemática e os padrões de projeto adotados no código real da plataforma.

---

## 🗂️ Manuais Disponíveis

| Manual | Escopo & Tópicos Abordados | ADRs Relacionadas | Tecnologias Chave |
| :--- | :--- | :--- | :--- |
| 📘 [**Manual de Estudo: Pipeline Kanban B2B**](./MANUAL_ESTUDO_KANBAN.md) | - Modelagem relacional normalizada (`Board ➔ Column ➔ Card`)<br>- Algoritmo de Fractional Indexing ($O(1)$)<br>- Eventos de Domínio (`CardMovedToProposal`)<br>- Drag & Drop nativo em Vanilla JS (HTML5 API)<br>- Padrão *Optimistic UI* com *Rollback* automático | ADRs 001, 002, 003 e 004 do Pedro | Laravel 11, PHP 8.2, MySQL InnoDB, Vanilla JS, HTML5 Drag & Drop |
| 📘 [**Manual de Estudo: API de CNPJ e Onboarding**](./MANUAL_ESTUDO_API_CNPJ.md) | - Validação Matemática por Módulo 11 (sem gastar rede)<br>- Cache distribuído de 7 dias com resposta em <5ms<br>- Padrão Fallback em Cascata (BrasilAPI ➔ ReceitaWS)<br>- Normalizador semântico (*Adapter Pattern*) CNAE ➔ Nicho<br>- Enriquecimento cadastral reativo no Onboarding/Login | ADRs 005, 006 e 007 do Pedro | Laravel 11, HTTP Client, Cache API, BrasilAPI, ReceitaWS |

---

## 🧭 Como Estudar por estes Manuais

1. **Visão de Negócio:** Comece entendendo *qual dor do cliente* o código resolve.
2. **Caminho da Requisição:** Siga o diagrama que mostra a jornada da informação (do clique no navegador até a gravação no MySQL).
3. **Código Comentado Linha por Linha:** Leia o código recriado com os comentários explicativos sobre cada método, cálculo e variável.
4. **FAQ de Estagiário:** Revise as perguntas e respostas no final de cada manual para fixar os conceitos para a banca avaliadora.
