# Arquitetura e Implementação - Radar FINEP (EDTAN-72)


## 1. O Problema: O Segurança da FINEP (Liferay)

Imagine que o portal da FINEP (`https://www.finep.gov.br/oportunidades`) é uma sala  exclusiva. Essa sala é organizada por um sistema chamado **Liferay**.

A lista real dos editais não está escrita no HTML da página inicial (na porta da sala); ela fica guardada numa salinha VIP chamada "API" (no endereço interno `/o/c/chamadapublicas`). 

Se o nosso código PHP tentar bater na porta dessa sala VIP direto e pedir os editais (fazendo uma requisição normal), o segurança do Liferay barra a gente com um "Erro 403 (Proibido)". Por quê? Porque não temos a "pulseirinha" da sala (os cookies e tokens de sessão que só são gerados quando você entra na sala pela porta da frente pelo navegador).

## 2. A Solução: Nosso Agente Disfarçado (Browsershot)

Como não podemos pedir os dados direto do PHP, nós usamos uma ferramenta incrível chamada **Spatie Browsershot** junto com o **Puppeteer**. 
Pense no Puppeteer como um "robô fantasma": ele é um Google Chrome de verdade, mas que roda escondido (Headless), sem abrir a janela na tela do seu computador.

### Como a mágica acontece no código:

Lá no arquivo `FinepSpider.php`, o nosso método `parse()` faz o seguinte passo a passo:

1. **Entrando pela porta da frente:** Nós mandamos o Chrome fantasma acessar a URL principal da FINEP. Ao fazer isso, o site nos dá a pulseirinha VIP (carrega os cookies de sessão corretamente).
2. **O trabalho sujo (Injeção de JavaScript):** Como o Chrome invisível já está com a pulseirinha, nós escrevemos um pequeno script em JavaScript dentro do PHP e "injetamos" ele direto no navegador.
3. **Pegando os dados:** Esse script JavaScript executa um comando chamado `fetch()`. Ele vai até a salinha VIP da API da FINEP e consegue entrar, pois o navegador já tem a permissão! O JavaScript varre as páginas da API, coleta todos os editais, junta tudo num arquivo JSON (uma lista gigante de texto estruturado) e devolve para o nosso PHP.

Isso é genial porque o PHP só fica de braços cruzados esperando o Chrome trazer a bandeja cheia de dados já perfeitamente formatados. E se a FINEP mudar o visual (CSS) do site amanhã? O robô não quebra, pois ele lê dados puros da API!

## 3. A Linha de Montagem (Pipeline e Banco de Dados)

Quando o Chrome devolve o JSON gigante para o PHP, o método `parse()` entra na segunda fase: a **linha de montagem**.

Em vez de pegar todos os editais de uma vez e tentar forçar goela abaixo no banco de dados, usamos um comando especial do PHP chamado `yield`. 

```php
// Pedaço do nosso código no FinepSpider.php
yield $this->item([
    'external_id' => $externalId, // O código único daquele edital
    'title'       => $titulo,     // O nome do edital
    // ...
]);
```

**O que o `yield` faz?** 
Ele funciona exatamente como uma esteira de fábrica. Ele pega UM único edital da lista gigante, entrega para a próxima etapa da esteira (que chamamos de *ItemProcessor*, a classe `SalvarEditalProcessor`) e pausa. O Processador salva esse único edital no banco. Depois, a esteira anda e o `yield` entrega o próximo edital. Isso é fantástico porque economiza a memória do servidor!

### O nosso Banco de Dados
Na tabela `editais`, nós salvamos as coisas que vêm certinhas da FINEP (como o `title`, `objetivo` e `publico`). 
Porém, algumas coisas super importantes como `min_budget` (orçamento) ou `deadline` (prazo final) nem sempre vêm explícitas da API deles. 
O que fazemos? Salvamos como nulo (`NULL`) por enquanto. No futuro, um outro módulo nosso (com Inteligência Artificial) vai ler o PDF oficial do edital em anexo para preencher magicamente essas lacunas!

## 4. O que eu preciso ter para rodar isso?

Se você acabou de clonar o projeto e quer testar o robô na sua máquina, além do PHP (Composer), você **precisa**:

1. **Ter o Node.js**: Instalado no seu computador (é ele que controla o Puppeteer).
2. **Instalar o Puppeteer**: Na pasta raiz do back-end, abra o terminal e rode `npm install puppeteer`. Isso instala a ponte essencial que deixa o nosso PHP comandar o Chrome.

## 5. Dicionário do Código (Para não esquecer amanhã)

Como o código do robô envolve algumas técnicas avançadas, aqui vai uma "cola" rápida para você bater o olho e lembrar o que cada peça faz:

### Entendendo o `FinepSpider.php`
- **`use LimpaTextoTrait;`**: Importa uma "ferramenta extra" que criamos. Com isso, podemos usar o comando `$this->limpaTexto(...)` em qualquer lugar do robô para lavar a sujeira (como tags HTML perdidas e espaços duplos) que vêm nos textos da FINEP.
- **`public array $startUrls = [...]`**: É a porta da frente! É o primeiro link que o robô vai abrir para conseguir entrar na FINEP e pegar a tal da "pulseirinha VIP" (cookies de sessão).
- **`public int $concurrency = 1;`**: Avisa o robô para trabalhar de 1 em 1. Como abrir o Chrome invisível exige muita memória RAM do computador, se colocássemos "5", seu PC iria sofrer tentando abrir 5 navegadores ao mesmo tempo.
- **`public array $itemProcessors = [...]`**: A lista de funcionários da nossa esteira de fábrica. Se a gente descomentar o `SalvarNoBancoProcessor` aí dentro, cada vez que o robô achar um edital, ele manda pra esse processador que, por sua vez, vai cadastrar no MySQL.
- **`$jsonString = Browsershot::url(...)->evaluate($script)`**: O coração da operação. Chama o Chrome fantasma, diz pra ele esperar 5 segundinhos (`delay(5000)`) pra página carregar bem e, em seguida, injeta o nosso script de busca (`$script`) dentro do Chrome. A resposta do site volta guardada nessa variável.
- **`json_decode($jsonString, true)`**: O JavaScript lá do navegador manda a resposta em formato de "Texto Puro" (String). Esse comando do PHP transforma esse texto numa lista oficial (Array) para podermos ler no `foreach`.
- **`yield $this->item([...])`**: Ao invés de usar `return`, usamos o `yield`. Ele empacota o edital mapeado e empurra ele pra esteira. Depois que a esteira processa (ex: salva no banco), o robô volta pro loop para pegar o próximo edital.

### Entendendo a `LimpaTextoTrait.php`
- No PHP, uma **Trait** é como um "pacote de superpoderes" que você pode dar para qualquer classe. Nossa `LimpaTextoTrait` guarda códigos que tiram acentos estranhos, limpam marcações de HTML e ajeitam textos feios. Como colocamos isso dentro de uma Trait, amanhã, se formos criar um robô para raspar o site do BNDES, não precisamos reescrever as regras de limpeza; é só colocar um `use LimpaTextoTrait;` lá e mágica está pronta!

### O Script JavaScript (A Alma do Robô)

Como o Liferay é blindado contra o PHP puro, precisamos escrever JavaScript dentro do nosso PHP para rodar lá no navegador invisível. Aqui está a tradução detalhada de cada pedaço desse script:

```javascript
new Promise(async (resolve, reject) => {
```
**O que faz:** O `Promise` (Promessa) e o `async` são a forma do JavaScript avisar o navegador: *"Calma, eu vou demorar um pouco para baixar as coisas da internet. Fique esperando eu terminar antes de desligar a página."* O `resolve` é o comando que usaremos no final para dizer: *"Terminei, aqui estão os dados!"*.

```javascript
const PAGE_SIZE = 250;
const API_BASE  = '/o/c/chamadapublicas';
const SORT      = 'sort=dataDePublicacao:desc';
```
**O que faz:** Preparamos os ingredientes da busca. O `PAGE_SIZE = 250` exige que a API mande logo de cara 250 editais de uma vez só (assim o robô não perde tempo pedindo de 10 em 10, que é o padrão deles). O `API_BASE` é o endereço da "salinha VIP". O `SORT` manda trazer sempre os editais mais frescos primeiro.

```javascript
const primeiraResp = await fetch(
    API_BASE + '?' + SORT + '&search=&page=1&pageSize=' + PAGE_SIZE,
    { headers: { 'Accept': 'application/json' } }
);
```
**O que faz:** A mágica do `fetch()`. É ele quem bate na porta da salinha VIP e pede a **Página 1**. O `await` na frente obriga o script a pausar e não fazer mais nada até o servidor da FINEP responder.

```javascript
const primeiroJson = await primeiraResp.json();
const lastPage     = primeiroJson.lastPage || 1;
let   todosItens   = primeiroJson.items || [];
```
**O que faz:** Transforma a resposta que chegou em JSON. A grande sacada aqui é o `lastPage`. Ao ler a página 1, nós descobrimos automaticamente **quantas páginas existem no total** na base de dados da FINEP. E já guardamos os editais dessa página 1 na nossa sacola gigante chamada `todosItens`.

```javascript
for (let pagina = 2; pagina <= lastPage; pagina++) {
    const resp = await fetch( ... );
    const dados = await resp.json();
    todosItens = todosItens.concat(dados.items || []);
}
```
**O que faz:** O "efeito Pac-Man". Se a FINEP disse lá em cima que tem 3 páginas no total, nós criamos um `for` (loop) que vai da página 2 até a última página. Ele repete o `fetch` para baixar a página 2, depois a 3... e usa o `concat` para ir "grudando" os editais novos dentro da nossa sacola gigante `todosItens`.

```javascript
resolve(JSON.stringify(todosItens));
```
**O que faz:** Missão cumprida! O script converte nossa sacola gigante com absolutamente todos os editais do site em um Texto Simples (usando o `JSON.stringify`) e chama o `resolve(...)`. É esse `resolve` que dispara a resposta e joga o JSON no colo do PHP, prontinho para entrar na nossa esteira do Banco de Dados!
