# ADR 013: Integração Funcional e Gestão de Anexos no Pipeline Kanban B2B

## Status
Aceito

## Contexto (Jira EDTAN-183 / EDTAN-175)
No ciclo operacional de assessoria e submissão de editais de inovação, o usuário necessita gerenciar cada oportunidade através do funil Kanban, mantendo anexos (planilhas orçamentárias, declarações, CNDs, cartas de anuência de ICTs) e notas de submissão associados ao card do edital.

Surgiram as seguintes necessidades arquiteturais:
1. **Armazenamento de Anexos do Card:** Como vincular múltiplos arquivos (PDF, DOCX, XLSX, PNG) a um card Kanban de forma desacoplada e sem exigir migrações rígidas de banco a cada novo atributo?
2. **Isolamento de Acesso e Download:** Como disponibilizar os arquivos para download/visualização direta sem expor caminhos internos do sistema operacional ou gerar erros de `403 Forbidden`?
3. **Persistência de Dados Customizados:** Como permitir que o usuário adicione o "Valor Estimado do Projeto (R$)" e "Notas Internas de Submissão" específicas daquele ticket?
4. **Ergonomia do Frontend:** Como organizar a navegação para que o usuário transite entre o resumo do edital, a elaboração da proposta técnica e a gestão de anexos de forma fluida e sem cortes de interface?

---

## Decisão

### 1. Metadados Flexíveis em JSON no Modelo `KanbanCard`
Em vez de criar uma tabela relacional intermediária com colunas fixas para cada tipo de metadado, decidimos utilizar a coluna `metadata` (JSON) na tabela `kanban_cards`.
A estrutura do campo `metadata` armazena:
```json
{
  "valor_estimado": "350000.00",
  "notas_internas": "CND federal e estadual validadas...",
  "anexos": [
    {
      "id": "673f8a91",
      "nome": "Plano_Trabalho_FAPESC.docx",
      "caminho": "/storage/kanban_anexos/...",
      "tamanho": 1048576,
      "enviado_em": "2026-10-21T21:20:00Z"
    }
  ]
}
```
Isso confere flexibilidade evolutiva sem downtime ou migrações que quebrem compatibilidade.

### 2. Upload Público e Link Simbólico do Storage Laravel
Para o upload de arquivos de até 10MB, adotamos o disco público do Laravel:
- **Armazenamento:** `$file->store('kanban_anexos', 'public');`
- **Exposição de Acesso:** O comando `php artisan storage:link` mapeia `storage/app/public` diretamente para `public/storage`.
- **Rota de API:** `POST /api/kanban/cards/{id}/dados-anexos` gerenciada pelo método `updateCard` em `KanbanController`.

### 3. Barra de Abas Estática e Tratamento de Transbordamento no Frontend
Para resolver os problemas de corte e responsividade no modal:
- A barra de navegação (`.minuta-tabs-container`) foi desacoplada do container rolável do corpo do modal (`.modal-minuta-body`), tornando-se estática e imune a cortes verticais de scroll.
- O campo seletor de cards do edital (`#selectCardAnexo`) teve sua largura contida via CSS (`text-overflow: ellipsis; overflow: hidden; white-space: nowrap; width: 100%`) e tratamento no JavaScript truncando títulos com mais de 65 caracteres, com o título original preservado no atributo `title` como tooltip.

---

## Justificativa Acadêmica (TCC)
- **Schema-less com Integridade:** O uso de JSON para metadados de workflow é um padrão consagrado na indústria (PostgreSQL JSONB / MySQL JSON document store) para sistemas de ticketing dinâmico.
- **Padrão Controller-Service:** O controlador `KanbanController` valida uploads via `$request->validate()` e manipula os metadados com atomicidade.
- **Resiliência e Usabilidade:** A blindagem com `try/catch` no clique dos cards e o desacoplamento de eventos de arrastar (*drag-and-drop*) garantem que falhas na API ou no mouse não travem o ciclo de interface do usuário.

---

## Consequências

### Positivas:
- Suporte a múltiplos anexos de até 10MB por card com download instantâneo.
- Anotações de valores estimados e notas de acompanhamento salvas em tempo real.
- Layout limpo, responsivo e sem cortes visuais no modal de propostas.
- Isolamento multi-tenancy mantido: cards e anexos pertencem à startup ativa.

### Negativas / Limitações Futuras:
- Em um ambiente com múltiplos servidores de aplicação (cluster/load balancing), o armazenamento local em disco deverá ser substituído por um bucket S3 compatível (AWS S3, MinIO ou Cloudflare R2).
