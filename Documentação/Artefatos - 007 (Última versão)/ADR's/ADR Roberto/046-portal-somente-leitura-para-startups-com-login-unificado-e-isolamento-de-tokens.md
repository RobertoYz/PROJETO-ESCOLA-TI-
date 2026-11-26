# ADR 046: Portal somente leitura para startups com login unificado e isolamento de tokens (EDTAN-297)

## Status

Implementado

## Data

25 de novembro de 2026

## Contexto e Problema (Jira EDTAN-297)

A startup precisa saber em que etapa está cada processo conduzido pela agência. Um portal compartilhado de acompanhamento atende a essa necessidade com custo operacional menor que manter uma implantação White Label por cliente. O Kanban histórico identificava a startup por texto livre e admitia quadros sem agência. Nomes repetidos tornam essa associação insuficiente para autorizar a leitura.

## Decisão

Cada startup pode ter um perfil em `startup_portal_acessos`, ligado também à agência. A política de edição da startup autoriza a gestão no rodapé do card. A engrenagem abre um diálogo para ajustar o login, gerar ou redefinir a senha e pausar o acesso.

O login comum encaminha identificadores com @ para `users` e os demais para o perfil dedicado. A API limita tentativas e armazena a senha com hash. O middleware verifica o modelo autenticado e a ability Sanctum `portal-startup`. A startup pode consultar o acompanhamento e encerrar a sessão. Pausa e troca de senha eliminam todos os tokens do perfil na mesma transação. Login e acompanhamento verificam a disponibilidade da startup e a vigência financeira da agência.

A consulta cruza a FK da startup no card, a agência do quadro e o quadro da coluna. A migração preenche o vínculo histórico apenas quando o nome identifica uma única startup na agência do quadro. A descrição pública vem do objetivo do edital. Anotações livres, anexos, metadados e dados de plano ficam fora da projeção.

### Alternativas consideradas e rejeitadas

- Usar um consultor em `users` faria a identidade externa participar do RBAC interno.
- Exigir uma URL de login exclusiva aumentaria a complexidade de distribuição das credenciais.
- Expor o quadro editável ou a resposta bruta do Kanban revelaria campos internos e ampliaria as operações disponíveis ao cliente.

## Riscos residuais

A agência compartilha as credenciais manualmente. A geração criptográfica de senha, a pausa com revogação imediata e o acesso somente leitura limitam o impacto de um compartilhamento indevido. A cópia para a área de transferência ocorre por ação explícita de quem gerencia o acesso.

## Consequências

Web e Capacitor usam a mesma tela de entrada e a mesma projeção. Cards históricos ambíguos ou em quadros sem agência ficam fora do acompanhamento. Renomear uma startup vinculada preserva a associação por ID.
