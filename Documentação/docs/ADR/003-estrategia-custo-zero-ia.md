# ADR 003: Estratégia de Custo Zero e Resiliência em IA (Free Tiers e Fallback)

## Status
Aceito

## Contexto
Em estágios iniciais (MVP) de uma startup, o custo operacional (Cloud e APIs de IA) pode ser um gargalo crítico. O processamento semântico de editais longos através de modelos de inteligência artificial (LLMs) como GPT-4, Claude ou similares, consome uma grande quantidade de *tokens*, o que resultaria em contas astronômicas rapidamente.
Foi cogitado o uso de *proxies reversos* (scraping em chats gratuitos), mas eles são notórios por serem instáveis, lentos e frequentemente bloqueados por CAPTCHAs.

## Decisão
Decidimos adotar uma **Arquitetura de Contingência e Rotação de Chaves** utilizando exclusivamente os *Free Tiers* (Níveis Gratuitos) de APIs oficiais, orquestrados pelo nosso sistema de Filas (Jobs).

As seguintes regras foram implementadas na camada de Serviço (`DeepSeekService`):
1. **Modelos de Custo Zero (Groq e Gemini):** A aplicação prioriza a API da Groq (modelo `mixtral-8x7b-32768`) pela sua velocidade extrema e franquia gratuita generosa.
2. **Fallback em Cascata:** Se a Groq falhar ou atingir o limite, o código automaticamente tenta o DeepSeek. Se este falhar, cai para o Google Gemini (modelo `gemini-3.6-flash`), que possui uma franquia oficial gratuita de 15 requisições por minuto.
3. **Rotação de Chaves de API:** O sistema suporta múltiplas chaves separadas por vírgula no `.env`. A cada requisição, ele escolhe uma aleatoriamente (`getRandomKey()`), balanceando a carga e multiplicando os limites da franquia gratuita.
4. **Auto-Regulação por Fila (Rate Limiting Handling):** Se todas as chaves e modelos falharem por *Rate Limit* (limite de uso no minuto), o `AnalyzeEditalWithIA` não quebra nem perde o edital. Ele aciona um `$this->release(60)`, devolvendo o edital para o final da fila e aguardando 1 minuto para que as franquias das APIs "esfriem" e se renovem.

## Justificativa
- **Custo Operacional Zero:** Mantemos o processamento de ponta em Inteligência Artificial sem onerar o fluxo de caixa inicial do projeto.
- **Estabilidade:** Usamos endpoints oficiais (REST APIs) em vez de proxies não oficiais, garantindo respostas rápidas em JSON e zero bloqueio por IP.
- **Escalabilidade Auto-gerenciada:** Mesmo se 5.000 editais entrarem na plataforma no mesmo dia, o backend absorve o impacto jogando-os na fila. O sistema processará tudo devagar, respeitando o limite diário/minuto das APIs gratuitas, sem interrupção humana.

## Consequências
- A análise de grandes lotes de editais pode demorar mais horas para finalizar completamente, visto que a fila fará pausas automáticas (throttling) quando atingir os limites das contas gratuitas.
- Requer a manutenção e monitoramento periódico das chaves `.env` para garantir que as contas gratuitas não foram banidas ou revogadas pelas provedoras (Google/Groq).
