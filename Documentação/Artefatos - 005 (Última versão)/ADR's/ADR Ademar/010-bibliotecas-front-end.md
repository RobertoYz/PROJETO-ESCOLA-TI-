# ADR 010: Escolha do Vanilla JS no Front-end (Fase 1)

## Status
Aceito

## Contexto
Durante o desenvolvimento do Anjo Nexus (Fase 1 do TCC), o projeto exigia a construção de uma interface para listar editais, demonstrar diagnósticos de Inteligência Artificial e atualizar o status visual das minutas através de integrações via APIs RESTful com o back-end em Laravel.

## Decisão
Decidimos **não** utilizar frameworks modernos e reativos (como React, Vue.js, Angular, Next.js, etc) nesta primeira fase.
A construção do front-end foi baseada inteiramente em **Vanilla JS (JavaScript Puro)**, HTML5 Semântico e Vanilla CSS com escopo definido, isolando toda a lógica do front na pasta `front/` (Totalmente desacoplado do Laravel e do Blade). O consumo de dados é feito com a Fetch API nativa.

## Justificativa
A Fase 1 visa primordialmente a validação arquitetural e a validação do pipeline de dados (Web Scraping + AI Router). Inserir um framework complexo neste momento introduziria uma curva de aprendizado desnecessária, dependência excessiva de pacotes (Node modules no front), etapas demoradas de build/compilação e sobrecarga arquitetônica. O Vanilla JS atende perfeitamente ao requisito de "Consumir um JSON via API e injetar no DOM", mantendo o projeto leve e fiel aos fundamentos de Engenharia de Software focada em APIs e microserviços.

## Consequências
- **Positivas:** 
  - Zero tempo de "build".
  - Curva de aprendizado mínima para apresentação bancária do TCC.
  - Front-end extremamente leve, podendo ser rodado apenas abrindo o arquivo `index.html` em qualquer navegador ou usando um *Live Server* rudimentar.
  - Aderência estrita à regra de arquitetura "Stateless e Desacoplada" imposta no Documento de Handover.
- **Negativas:** 
  - Manipulação de DOM imperativa é mais verbosa. A medida que a complexidade do painel crescer na Fase 2 ou 3 (com autenticação complexa, websockets e estado local), o código JS pode tornar-se desorganizado ("spaghetti code"), necessitando futura migração para um React ou Vue.js.
