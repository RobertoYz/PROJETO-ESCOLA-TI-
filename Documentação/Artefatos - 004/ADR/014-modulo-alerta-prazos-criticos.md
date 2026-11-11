# ADR 014: Módulo de Alertas de Prazos Críticos no Pipeline e Radar (EDTAN-171)

## Status
Aceito

## Contexto (Jira EDTAN-171 / Épico EDTAN-281)
O acompanhamento de submissão a editais de subvenção econômica e fomento exige rigor no cumprimento de prazos. Prazos perdidos geram desclassificação imediata de propostas de startups clientes. 

Na arquitetura anterior, o campo `deadline` era exibido apenas como string estática nas tabelas e cards, sem avaliação proativa de urgência, sem contagem regressiva em tempo real e sem mecanismos agregados de alerta.

Surgiram as seguintes necessidades:
1. **Classificação e Criticidade de Prazos:** Como categorizar os editais e cartões em janelas críticas objetivas (ex: <= 24h/4h urgente, <= 72h alerta, <= 7 dias moderado, estável ou expirado)?
2. **Visibilidade Global no Frontend:** Como notificar o consultor sobre prazos que estão se esgotando sem que ele precise abrir card por card do Kanban?
3. **Multi-tenancy Lógico:** Como filtrar os alertas críticos considerando a startup ativa selecionada pelo usuário?
4. **Baixa Sobrecarga de Rede:** Evitar WebSockets desnecessários ou polling agressivo que sobrecarregue o servidor.

---

## Decisão

### 1. Serviço Especializado de Prazos (`PrazoAlertaService`)
Criamos um serviço no backend (`app/Services/PrazoAlertaService.php`) com responsabilidade única (SRP):
- Avalia o `deadline` em relação ao timestamp corrente (`Carbon::now()`).
- Computa `horas_restantes`, `dias_restantes` e o estado de urgência:
  - `critico_24h`: Janela de emergência (inclui alerta de <= 4h).
  - `alerta_72h`: Janela de atenção primária (3 dias).
  - `moderado_7dias`: Janela de preparação (7 dias).
  - `estavel`: Prazo superior a 7 dias.
  - `expirado`: Prazo vencido.
  - `sem_prazo`: Editais de fluxo contínuo.

### 2. Endpoints REST Dedicados em `routes/api.php`
- `GET /api/prazos/alertas`: Retorna a lista detalhada e ordenada de oportunidades com prazos críticos, enriquecida com a startup associada, fonte e coluna do Kanban.
- `GET /api/prazos/resumo`: Retorna contadores agregados para renderização de badges numéricos na barra de navegação.

### 3. Central de Alertas e Sino Reativo no Frontend (`centralPrazos.js` e `sidebarGlobal.js`)
- Adicionamos um sino de notificações interativo no topo da barra lateral global (`sidebarGlobal.js`).
- O sino exibe um badge numérico pulsante em vermelho sempre que houver prazos nas janelas `<= 24h` ou `<= 72h`.
- Ao clicar no sino, abre-se a **Central de Alertas de Prazos Críticos**, apresentando contadores rápidos, detalhes de cada oportunidade e link direto para o card correspondente no Kanban.
- Cada card no Kanban recebe automaticamente a tag visual estilizada (`badge-prazo-tag`) indicando o tempo restante.

---

## Consequências

### Positivas
- **Prevenção de Perda de Prazos:** Consultores identificam imediatamente oportunidades que demandam ação urgente nas próximas 24h a 72h.
- **Isolamento de Responsabilidades:** O cálculo de datas e criticidade fica concentrado no `PrazoAlertaService`, reaproveitável tanto pelo `KanbanController` quanto pela API de alertas.
- **Experiência Visual Clara:** O design system conta agora com badges semânticos e animações sutis (`alerta-pulsante`) para destacar urgências sem poluir visualmente a interface.

### Negativas / Mitigações
- Para editais sem horário específico informado no edital original (apenas data), o Carbon assume `00:00:00` do dia. *Mitigação:* O formato de exibição explicita a data limite para confirmação do usuário.

