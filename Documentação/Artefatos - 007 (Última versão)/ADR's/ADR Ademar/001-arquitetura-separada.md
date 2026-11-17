# ADR 001: Separação de Responsabilidades (Back e Front)

## Status
Aceito

## Contexto
Precisávamos decidir a arquitetura principal do projeto. Uma abordagem comum no ecossistema Laravel seria usar as views Blade para renderizar todo o HTML no servidor e retornar a página completa para o usuário. Além disso, a arquitetura separada via API Stateless era um requisito essencial do projeto.

## Decisão
Decidimos usar uma arquitetura de API Stateless, separando completamente o Front-end (construído com Vanilla JS, HTML e CSS estáticos) do Back-end (desenvolvido no framework Laravel).

## Justificativa
1. **Escalabilidade e Desempenho:** O front-end estático pode ser hospedado de forma barata ou gratuita em CDNs (Content Delivery Networks) como Cloudflare, Vercel ou Amazon S3. Ele carrega quase instantaneamente e consome a API apenas quando necessário.
2. **Desacoplamento:** O Back-end agora é apenas uma API REST. Isso significa que, no futuro, se decidirmos criar um aplicativo móvel (Android/iOS) para o Anjo Nexus, o aplicativo poderá consumir exatamente a mesma API sem nenhuma alteração no servidor.
3. **Foco da Equipe:** Permite que desenvolvedores foquem exclusivamente no Front-end ou no Back-end de forma isolada, sem que os códigos se misturem (separação de responsabilidades).
4. **Por que Laravel no Backend:** O ecossistema Laravel oferece ferramentas nativas extremamente produtivas para a construção da API (como o Eloquent ORM, o sistema de Filas/Jobs que usamos para a IA, e rotas limpas). Isso nos permite focar na complexidade do Web Scraping e Integração com IA em vez de reescrever lógica básica de servidor.
5. **Por que Vanilla JS, HTML e CSS:** Para a Fase 1, o front-end precisava ser o mais simples e independente possível. Adoção de tecnologias puras (Vanilla) remove a necessidade de ferramentas de *build* pesadas (Webpack/Vite), dependências de *node_modules* e reduz a curva de aprendizado. Isso garante um front-end totalmente "burro" (focado apenas em exibir os dados consumidos pela *Fetch API*), aderindo estritamente ao conceito de arquitetura Stateless e Microserviços de forma rápida e leve.

## Consequências
- A autenticação não poderá ser feita por Sessões tradicionais baseadas em cookies (já que é Stateless), sendo necessário adotar tokens (como JWT ou Laravel Sanctum) no futuro.
- É necessário configurar CORS no Laravel para permitir que o Front-end faça requisições à API.
- A manipulação imperativa de DOM no Vanilla JS pode se tornar verbosa conforme a aplicação crescer, o que justificará a migração para frameworks reativos (como React ou Vue) nas próximas fases.
