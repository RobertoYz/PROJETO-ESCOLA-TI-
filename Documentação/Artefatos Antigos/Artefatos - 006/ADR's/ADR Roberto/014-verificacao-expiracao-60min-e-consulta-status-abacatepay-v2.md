# ADR 014: Algoritmo Híbrido de Expiração (`estaExpirado`) e Consulta Direta de Status (`consultarStatusCobranca` em `/v2/checkouts/get`)

## Status
Aceito

## Contexto
No módulo de gestão de cobranças pendentes (`EDTAN-208`), o Back-end precisava determinar com precisão se uma cobrança PIX emitida anteriormente para uma `Agencia` ainda estava apta a ser paga ou se já havia expirado/sido cancelada. Nas versões iniciais, o código tentava consultar um endpoint legado da v1 (`/billing/list`) incompatível com os checkouts da API v2 ou confiava apenas no relógio local sem verificar se a cobrança já havia mudado de status no servidor do AbacatePay.

## Decisão
Decidimos implementar em `app/Services/AbacatePayService.php` dois métodos complementares:
1. `consultarStatusCobranca($idCobranca)`: consulta em tempo real o endpoint `GET https://api.abacatepay.com/v2/checkouts/get?id={idCobranca}` e retorna `$resposta->json('data.status')`;
2. `estaExpirado($agencia)`: executa um algoritmo híbrido que combina a validação local do timestamp `pagamento_expira_em` (60 minutos via `Carbon`) com a verificação remota de status via `consultarStatusCobranca()`.

## Justificativa
1. **Por que validar primeiro `empty($urlCheckout)` e `Carbon::parse($agencia->pagamento_expira_em)->isPast()` em `estaExpirado($agencia)`:**
   - Se a agência não possui `checkout_url`/`url_checkout` gravada ou se o timestamp `pagamento_expira_em` (definido como `now()->addMinutes(60)` na emissão) já ficou no passado (`isPast() === true`), o método retorna `true` imediatamente em memória, poupando uma chamada de rede externa desnecessária para a API do AbacatePay.
2. **Por que consultar `consultarStatusCobranca($idCobranca)` quando o prazo local de 60 minutos ainda não expirou:** Mesmo dentro da janela de 1 hora, a cobrança pode ter sido expirada, cancelada ou reembolsada no gateway. O método lê `$idCobranca = $agencia->billing_id ?? $agencia->id_cobranca ?? $agencia->id_plataforma_pagamento`, chama `GET /v2/checkouts/get` passando `['id' => $idCobranca]` e verifica `if ($status && in_array(strtoupper($status), ['EXPIRED', 'CANCELLED', 'REFUNDED']))`, retornando `true` caso o gateway indique invalidação da cobrança.
3. **Por que encapsular `consultarStatusCobranca` em um bloco `try / catch (\Exception $excecao)` retornando `null` em caso de falha de rede:** Garante tolerância a falhas: se a API de consulta do AbacatePay oscilar momentaneamente, o sistema não lança erro `500` e decide a validade com base no timestamp local `pagamento_expira_em`.

## Consequências
- Verificação exata da validade de links PIX combinando velocidade local (`Carbon`) e consistência remota (`/v2/checkouts/get`).

