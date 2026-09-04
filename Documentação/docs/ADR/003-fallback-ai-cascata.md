# ADR 003: Padrão Fallback Cascata (Cascade) para Integração de IAs

## Status
Aceito

## Contexto
Depender de apenas um provedor de Inteligência Artificial para ler editais críticos apresenta um ponto único de falha (Single Point of Failure). Durante o desenvolvimento, observamos que o provedor primário (DeepSeek) rejeitou conexões por falta de créditos (Insufficient Balance). Isso travou o fluxo de processamento de todos os editais na fila, gerando loops de erros.

## Decisão
Implementamos um padrão de arquitetura de **Fallback (Cascata)** no serviço de inteligência artificial (`DeepSeekService`).

## Justificativa
1. **Alta Disponibilidade (High Availability):** O código tenta primeiramente contatar o provedor DeepSeek. Se houver falha (timeout, erro 402, erro 500, etc), o sistema captura a exceção e imediatamente despacha o mesmo prompt para um provedor secundário de backup, neste caso, o Google Gemini (modelo gemini-2.5-flash).
2. **Resiliência Contínua:** Isso garante que o sistema de diagnóstico de editais continue operando sem intervenção humana, mesmo quando um dos provedores está fora do ar ou sem fundos.
3. **Isolamento de Falhas:** O job da fila e o banco de dados não sabem com qual IA estão lidando. Eles apenas esperam o JSON final. A complexidade do Fallback ficou encapsulada unicamente dentro do serviço, respeitando o princípio da Responsabilidade Única (Single Responsibility Principle).

## Consequências
- Maior complexidade no código do serviço (requer gestão de múltiplas chaves de API - `.env`).
- Necessidade de garantir que o modelo secundário consiga interpretar as mesmas instruções (System Prompt) de forma idêntica e retorne um JSON no exato mesmo formato (schema) para evitar bugs de conversão no banco de dados. Como o Gemini se provou capaz de seguir a formatação de JSON perfeitamente, a estratégia foi validada.
