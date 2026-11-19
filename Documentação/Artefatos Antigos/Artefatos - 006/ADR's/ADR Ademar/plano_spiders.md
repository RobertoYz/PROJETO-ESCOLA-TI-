# Plano de Refatoração Arquitetural dos Spiders (Versão Atual)

A refatoração tem como objetivo remover a "fragilidade" das buscas por texto (regex soltos e laços gigantes) e adotar as melhores práticas de Arquitetura de Software orientada a XML (como sugerido pelo professor) e Padrões de Projeto (Design Patterns).

## 1. O Problema Atual
Se você olhar o `FapespSpider.php` hoje, verá que dentro do `foreach` nós quebramos todo o HTML em linhas com `explode("\n", $textoComQuebras)` e depois fazemos um `foreach` secundário fazendo `str_contains` para tentar "adivinhar" onde está o código da chamada e a data limite. Isso é:
- Muito lento.
- Extremamente frágil (se a FAPESP mudar um espaço ou pular linha, a lógica quebra).
- Difícil de testar.

## 2. A Nova Estrutura Proposta

Vamos dividir a responsabilidade em **três pilares** para deixar o código profissional:

### Pilar A: Padrão XML / XPath
No lugar de ler texto puro, passaremos a interagir diretamente com os "nós" da árvore (XML Nodes) usando a linguagem `XPath`.
- **Como fica:** Ao invés de *procurar texto por linha*, nós dizemos ao framework: *"Vá na `<div class="content">`, encontre a `<ul>`, entre em cada `<li>` e pegue o texto dentro do 2º `<strong>`"*.

### Pilar B: Criação de um `BaseSpider` (Herança)
Hoje cada Spider (FINEP, FAPESP, FAPESC) implementa toda a lógica do zero e duplica funções como conversão de datas (ex: *13 de outubro* -> `2026-10-13`).
- **O que faremos:** Criaremos uma classe `App\Spiders\BaseSpider`. Todos os spiders herdarão dela. Essa classe base terá funções genéricas prontas:
  - `converterDataPorExtenso()`
  - `limparNodeXPath()`
  - `gerarHashUnico()`

### Pilar C: Validação via DTO (Opcional, mas Recomendado)
Em vez de enviar os dados direto pro `SalvarNoBancoProcessor` através de um *Array solto*, passaremos um objeto estruturado. Isso garante que o Spider nunca envie um dado no formato errado pro banco.

---

## 3. Rascunho Prático do Código Novo

Veja a diferença visual de como o código ficará mais limpo.

**Classe Base (Nova):**
```php
namespace App\Spiders;
use RoachPHP\Spider\BasicSpider;

abstract class BaseSpider extends BasicSpider {
    // Todos os spiders terão o SalvarNoBancoProcessor por padrão!
    public array $itemProcessors = [\App\Spiders\Processors\SalvarNoBancoProcessor::class];

    // Helper unificado para arrumar datas do BR para o formato MySQL
    protected function parseDateBr(string $dateText): ?string { ... }
}
```

**FapespSpider.php (Refatorado com XPath):**
```php
namespace App\Spiders;
use Symfony\Component\DomCrawler\Crawler;

class FapespSpider extends BaseSpider
{
    public array $startUrls = ['https://fapesp.br/2185/...'];

    public function parse(Response $response): \Generator
    {
        // 1. Usa XPATH para pegar cirurgicamente a lista exata!
        $nodes = $response->crawler()->filterXPath('//ul[@class="list"]/li');

        foreach ($nodes as $node) {
            $crawler = new Crawler($node);
            
            // 2. Extrai direto do galho da árvore XML sem fazer explode de linhas
            $link = $crawler->filterXPath('//p/a')->attr('href');
            $titulo = $crawler->filterXPath('//p/a/strong')->text();

            // 3. Usa os helpers da Classe Base que criamos
            yield $this->item([
                'external_id' => $this->extrairIdFapesp($link),
                'title'       => $titulo,
                'deadline'    => $this->parseDateBr($crawler->text()),
                'fonte'       => 'FAPESP'
            ]);
        }
    }
}
```

## Próximos Passos
Se aprovar esse rascunho, a ordem de execução seria:
1. Criar o `BaseSpider.php` na pasta `Versão Atual`.
2. Refatorar o `FapespSpider.php` e testar com XPath.
3. Se der certo, aplicamos o mesmo padrão no `FinepSpider.php`.
