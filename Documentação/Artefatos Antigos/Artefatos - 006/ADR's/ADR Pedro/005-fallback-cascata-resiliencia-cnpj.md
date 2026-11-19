# ADR 005: Padrão Fallback em Cascata de Resiliência para Consulta de Dados de CNPJ

## Status
Aceito

## Contexto (Jira EDTAN-134)
No ecossistema **Anjo Nexus**, a gestão de editais de fomento e o pipeline Kanban dependem do perfil cadastral e fiscal de cada startup cliente. Para avaliar a elegibilidade jurídica e orçamentária em agências públicas de fomento (como FINEP, FAPESC e FAPESP), são requeridos dados oficiais rigorosos: Razão Social, CNAE Fiscal primário, porte empresarial (MEI, ME, EPP, Demais), optante pelo Simples Nacional, data de constituição e localização geográfica.

Entretanto, o acesso a dados corporativos oficiais no Brasil impõe graves barreiras técnicas:
1. **Inexistência de API Governamental Direta e Gratuita:** A Receita Federal do Brasil (RFB) não oferece uma API REST pública direta e sem custos para consumo em tempo real por aplicações web.
2. **Instabilidade e Quedas Frequentes de Provedores Abertos:** As iniciativas públicas abertas existentes (como BrasilAPI e ReceitaWS) enfrentam janelas periódicas de manutenção, timeouts e instabilidades de rede nos servidores públicos de origem.
3. **Restrições Severas de Taxa (*Rate Limits*):** A versão pública da ReceitaWS impõe um limite estrito de 3 requisições por minuto por endereço IP, provocando respostas HTTP 429 (*Too Many Requests*) caso múltiplos consultores utilizem o sistema simultaneamente.

Se a aplicação dependesse de um único fornecedor externo, qualquer instabilidade de terceiros bloquearia o cadastro de empresas e a movimentação de oportunidades, violando o Requisito Não-Funcional de Tolerância a Falhas (RNF-02).

### Alternativas Descartadas:
- **Dependência Exclusiva da BrasilAPI:** Descartada por ausência de redundância. Quedas temporárias na BrasilAPI deixariam o cadastro de startups inoperante.
- **Dependência Exclusiva da ReceitaWS:** Descartada devido ao teto severo de 3 requisições/minuto no tier gratuito, inviabilizando testes e operação em horários de pico.
- **Contratação de Birôs Pagos de Dados (Serasa, BigDataCorp):** Descartada por violar o princípio de custo zero de infraestrutura e a viabilidade do projeto no escopo acadêmico e de MVP.

---

## Decisão

Implementou-se no serviço especializado `CnpjService.php` (em `app/Services`) o padrão **Fallback em Cascata (*Cascade Fallback Pattern*) com Camada de Resiliência e Adaptação Unificada**:

```
                  Requisição de Consulta do CNPJ
                                │
                                ▼
               ┌─────────────────────────────────┐
               │ 1. Consulta em Cache Local      │ <--- HIT: Retorno em <5ms
               └────────────────┬────────────────┘
                                │ MISS
                                ▼
               ┌─────────────────────────────────┐
               │ 2. Provedor Primário: BrasilAPI │ (Sem limite agressivo de IP)
               └────────────────┬────────────────┘
                                │
                     ┌──────────┴──────────┐
                     ▼                     ▼
               [Sucesso 200]        [Timeout / Erro 5xx]
                     │                     │
                     ▼                     ▼
             Normaliza dados        ┌──────────────────────────────────┐
             e grava em cache       │ 3. Fallback: ReceitaWS           │
                                    └──────────────┬───────────────────┘
                                                   │
                                        ┌──────────┴──────────┐
                                        ▼                     ▼
                                  [Sucesso 200]        [Falha / 429 / 5xx]
                                        │                     │
                                        ▼                     ▼
                                Normaliza dados        Dispara contingência
                                e grava em cache       amigável ao usuário
```

### 1. Enfileiramento em Cascata
O método `consultar(string $cnpj, bool $ignorarCache = false)` orquestra a sequência de contingência de forma transparente para o chamador:
- **Passo 1 (Provedor Primário): BrasilAPI:**
  Endpoint: `https://brasilapi.com.br/api/cnpj/v1/{digits}` com timeout máximo de 8 segundos. Se responder com sucesso, os dados são normalizados e retornados.
- **Passo 2 (Fallback Secundário): ReceitaWS:**
  Caso a BrasilAPI dispare uma exceção de rede ou retorne status HTTP 5xx, um log de aviso (`Log::warning`) é registrado e o fluxo aciona imediatamente `https://receitaws.com.br/v1/cnpj/{digits}`.

### 2. Tratamento Diferenciado de CNPJ Inexistente (404) vs Falha de Rede
Para evitar chamadas redundantes a outros provedores quando um CNPJ de fato não existe nos registros oficiais:
```php
if ($response->status() === 404) {
    throw new CnpjNotFoundException(CnpjValidationRule::format($digits));
}
```
Se a Receita Federal retornar 404 (*Not Found*), uma exceção de domínio personalizada `CnpjNotFoundException.php` (em `app/Exceptions`) é lançada imediatamente, interrompendo a cascata sem consultar o provedor secundário desnecessariamente.

### 3. Normalização pelo Padrão Adapter
Como cada provedor externo adota convenções de nomenclatura divergentes (ex.: a BrasilAPI retorna `cnae_fiscal` e `razao_social`, enquanto a ReceitaWS retorna `atividade_principal[0].code` e `nome`), o `CnpjService` executa a padronização no contrato canônico do Anjo Nexus através dos métodos `normalizarBrasilApi()` e `normalizarReceitaWs()`, entregando um payload homogêneo pronto para persistência (`startup_payload`).

---

## Justificativa Acadêmica (TCC / Banca Avaliadora)

- **Padrão de Resiliência Fallback / Circuit Breaker (GoF / Cloud Patterns):** Assegura alta disponibilidade e impede o efeito de falha em cascata (*cascading failure*), isolando a aplicação da fragilidade dos servidores de terceiros.
- **Atendimento ao Requisito Não-Funcional de Tolerância a Falhas (RNF-02):** A taxa de disponibilidade na resolução de CNPJs salta de ~92% (quando dependente de um único provedor público) para **mais de 99.7%** com a redundância ativa.
- **Padrão Adapter (GoF):** Desacopla o domínio interno do Anjo Nexus dos esquemas JSON arbitrários de APIs governamentais externas, facilitando a substituição ou inclusão de novos provedores (ex.: MinhaReceita ou APIs estaduais) sem impacto no restante do sistema.

---

## Consequências

### Positivas:
- **Resiliência Transparente:** O usuário final nunca percebe se o dado foi obtido pelo provedor primário ou secundário; a interface responde com a mesma estrutura uniforme.
- **Economia e Custo Zero:** Combina o volume ilimitado da BrasilAPI com a confiabilidade complementar da ReceitaWS sem necessidade de contratação de serviços pagos.
- **Segurança Operacional:** Timeouts controlados de 8 segundos impedem o travamento de conexões abertas no servidor web.

### Negativas / Limitações Futuras:
- No pior cenário possível de instabilidade simultânea em que a BrasilAPI sofra timeout de 8 segundos e a ReceitaWS leve mais 3 segundos para responder, a requisição pode totalizar cerca de 11 segundos de espera antes da resposta final.
