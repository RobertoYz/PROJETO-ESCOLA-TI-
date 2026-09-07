# ADR 007: Roteamento de IA em Cascata e Múltiplas Chaves de API (EDTAN-82)

## Status
Aceito

## Contexto
O processo assíncrono de análise de editais (via fila/Worker) frequentemente lidava com interrupções por falta de saldo nas APIs pagas (como DeepSeek) ou estouro de cota limite (Rate Limit) nos níveis gratuitos (como Gemini), paralisando o pipeline e quebrando o front-end ao tentar ler respostas vazias ou nulas.

## Decisão
1. **Model Routing (Roteamento de Modelos em Cascata):** O `DeepSeekService` foi transformado em um roteador de IA. Ele tenta utilizar primeiramente a Groq (Llama 3 70B), por ser ultra-rápida, gratuita e ilimitada baseada em LPU. Caso falhe, ele engatilha o DeepSeek e, sucessivamente, a API do Google Gemini.
2. **Mock Seguro de Emergência:** Caso todas as APIs de inferência estejam inoperantes, o serviço não lança uma exceção destrutiva. Ele retorna um JSON "Mockado" com *match* e avisos simulados, mantendo a integridade da UI na visualização dos editais.
3. **Múltiplas Chaves de API (Round Robin):** Foi adicionado um algoritmo que lê múltiplas chaves (separadas por vírgula) no arquivo `.env`. Através do método `getRandomKey()` (`array_rand`), o sistema rotaciona a chave de API (Conta) utilizada a cada novo edital processado. Isso distribui a carga da fila, burlando efetivamente limites impostos por cotas gratuitas singulares em processos de scraping massivo.

## Consequências
- **Positivas:** 
  - Maior resiliência e estabilidade do fluxo assíncrono.
  - Mitigação de custos de IA, priorizando modelos abertos no *Free Tier* (Groq).
  - Frontend blindado contra quedas dos provedores LLM.
- **Negativas:** 
  - O código do backend assume uma complexidade um pouco maior e acopla provedores diferentes (Groq, DeepSeek, Gemini).
  - É necessário manter o arquivo `.env` atualizado e gerenciar manualmente a criação de contas/chaves caso a demanda ultrapasse a soma das cotas de todas as contas combinadas.
