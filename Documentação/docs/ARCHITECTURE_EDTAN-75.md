# Arquitetura e Implementação - Integração Front/Back (EDTAN-75)

## 1. O Desafio: Acabar com a "Página Estática"

A tarefa EDTAN-75 tinha um objetivo claro: fazer com que a nossa interface visual (o Front-end construído em HTML/CSS) parasse de mostrar dados de teste (Mock) e passasse a puxar os Editais reais, com os diagnósticos da Inteligência Artificial, diretamente do nosso Banco de Dados (MySQL) gerido pelo Laravel.

## 2. A Escolha da Arquitetura: Decoplamento Total (Stateless)

Poderíamos ter usado as *Views Blade* do Laravel (onde o servidor PHP "pinta" o HTML e entrega pronto). Mas optamos por uma abordagem muito mais moderna e escalável (conforme descrito no `ADR-001`): **API RESTful Stateless**.

Isso significa que:
1. O nosso **Back-end (Laravel)** trabalha apenas como um "garçom de dados". Ele não sabe nada sobre cores, botões ou telas. Ele só vai no banco, pega os editais e entrega um pacote de texto cru (no formato JSON).
2. O nosso **Front-end (Vanilla JavaScript)** é quem recebe esse pacote e cuida de toda a mágica visual, populando a tela dinamicamente.

## 3. Como a Mágica Acontece no Código

### Passo 1: O Endpoint no Back-end (`EditalController.php`)
No Laravel, criamos uma rota de API (`/api/editais`) e um Controlador. Esse controlador vai no Banco de Dados, busca os editais já raspados (e analisados pela IA) e os prepara para viagem.
Um ponto de destaque técnico que implementamos foi a **Formatação de Datas Direto no Back-end**: 
```php
// Transformamos a data "Y-m-d H:i:s" do banco para o padrão brasileiro "d/m/Y às H:i"
'data_abertura' => Carbon::parse($edital->data_abertura)->format('d/m/Y \à\s H:i'),
```

### Passo 2: O Consumo no Front-end (`app.js`)
No Javascript, criamos uma função assíncrona (`async/await`) para bater na porta do garçom (API) e pedir os dados:
```javascript
const response = await fetch('http://localhost:8000/api/editais');
const data = await response.json();
```
O uso do `fetch` nativo com `async/await` foi escolhido para manter o projeto leve, sem dependências de bibliotecas externas (como Axios ou jQuery), aderindo à premissa de Vanilla JS.

### Passo 3: Injeção Dinâmica (DOM Manipulation)
Com os dados em mãos, o Javascript cria os cards dos editais dinamicamente. Ele injeta o HTML (`innerHTML`) para cada edital recebido.

Se o edital ainda não tiver passado pela Fila da Inteligência Artificial, o Front-end trata isso graciosamente:
```javascript
// Exemplo de como o front lida com dados em processamento
const trlExibido = edital.ai_analyzed ? edital.ai_trl : "A definir";
const nichoExibido = edital.ai_analyzed ? edital.ai_nicho : "Analisando...";
```

## 4. Conclusão da Integração

A tarefa EDTAN-75 conectou perfeitamente os dois mundos (Robô/IA e Interface Visual). 
O resultado é um painel de controle extremamente rápido (pois a IA rodou previamente em background) e que consome pouquíssima banda da internet (trafegando apenas strings JSON em vez de páginas HTML completas).
