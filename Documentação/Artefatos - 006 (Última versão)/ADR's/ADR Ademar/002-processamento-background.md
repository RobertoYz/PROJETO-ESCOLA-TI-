# ADR 002: Processamento Assíncrono para IA e Scrape (Background Jobs)

## Status
Aceito

## Contexto
O processo de analisar um edital envolve:
1. Buscar o texto do edital na página web de origem (Web Scraping / Deep Scrape).
2. Enviar esse texto gigante para uma LLM (DeepSeek ou Gemini) processar, ler e extrair os dados.

Fazer esse fluxo de forma síncrona (no momento em que o usuário clica ou durante o ciclo normal de uma requisição HTTP) leva muito tempo. APIs de IA podem levar de 5 a 20 segundos para responder, e fazer o download de páginas pesadas também custa tempo. Se processássemos 10 editais de uma vez na mesma requisição, o servidor daria Timeout (erro 504) e o sistema travaria.

## Decisão
Decidimos processar todas as integrações externas pesadas utilizando as Filas (Queues) do Laravel, rodando Workers em background. 

## Justificativa
1. **Experiência do Usuário (UX):** O processo de descoberta de editais se torna imediato. A página pode listar o edital imediatamente com o status "Analisando...", enquanto o trabalho pesado é feito por trás das cortinas sem travar a navegação do usuário.
2. **Resiliência:** Se a API da IA falhar momentaneamente (ex: Rate Limit ou indisponibilidade), a Fila do Laravel tentará rodar a tarefa de novo (retry) automaticamente alguns minutos depois, garantindo que nenhum edital seja perdido.
3. **Proteção do Servidor:** Os Workers em background consomem memória de forma controlada, impedindo picos de consumo que derrubariam o servidor principal onde a API está rodando.

## Consequências
- O ambiente de produção precisará rodar não apenas o servidor Web (Nginx/Apache), mas também processos Daemon supervisores (como o Supervisor ou PM2) para manter o comando `php artisan queue:work` rodando 24/7.
