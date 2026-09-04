# ADR 001: Separação de Responsabilidades (Back e Front)

## Status
Aceito

## Contexto
Precisávamos decidir a arquitetura principal do projeto. Uma abordagem comum no ecossistema Laravel seria usar as views Blade para renderizar todo o HTML no servidor e retornar a página completa para o usuário. 

## Decisão
Decidimos usar uma arquitetura de API Stateless, separando completamente o Front-end (Vanilla JS, HTML e CSS estáticos) do Back-end (Laravel).

## Justificativa
1. **Escalabilidade e Desempenho:** O front-end estático pode ser hospedado de forma barata ou gratuita em CDNs (Content Delivery Networks) como Cloudflare, Vercel ou Amazon S3. Ele carrega quase instantaneamente e consome a API apenas quando necessário.
2. **Desacoplamento:** O Back-end agora é apenas uma API REST. Isso significa que, no futuro, se decidirmos criar um aplicativo móvel (Android/iOS) para o Anjo Nexus, o aplicativo poderá consumir exatamente a mesma API sem nenhuma alteração no servidor.
3. **Foco da Equipe:** Permite que desenvolvedores foquem exclusivamente no Front-end ou no Back-end de forma isolada, sem que os códigos se misturem (separação de responsabilidades).

## Consequências
- A autenticação não poderá ser feita por Sessões tradicionais baseadas em cookies (já que é Stateless), sendo necessário adotar tokens (como JWT ou Laravel Sanctum) no futuro.
- É necessário configurar CORS no Laravel para permitir que o Front-end faça requisições à API.
