# ADR 032: Isolamento de Provedores Externos via Design Pattern Adapter e Padronização de Contratos JSON da API REST

## Status
Implementado

## Data
Novembro de 2026

## Contexto e Problema (Jira EDTAN-280)
O ecossistema **Anjo Nexus** comunica-se com múltiplos serviços externos para viabilizar seus fluxos operacionais centrais: o gateway **AbacatePay v2** para liquidação de cobranças PIX e as APIs públicas da **BrasilAPI** e **ReceitaWS** para validação e enriquecimento de dados cadastrais de CNPJ de startups.

Originalmente, esses serviços externos eram consumidos diretamente por meio de arrays heterogêneos dentro dos respectivos Services e Controllers. Essa abordagem acarretava três problemas graves de arquitetura:
1. **Vazamento de Vocabulário Externo (*Domain Contamination*):** Termos em inglês proprietários das APIs terceiras (`data.id`, `billing_id`, `url`, `status: PAID`, `company_size`, etc.) começaram a vazar para colunas do banco de dados, regras nos Models Eloquent e nos retornos JSON da API interna.
2. **Inconsistência de Contratos HTTP (`'mensagem'` vs `'message'`):** Diferentes Controllers e Middlewares da aplicação alternavam arbitrariamente entre as chaves `'mensagem'` (português) e `'message'` (inglês/padrão Laravel) nos payloads de resposta de erro e sucesso, forçando a camada de Front-end (`api.js`) a manter validações condicionais frágeis do tipo `dados.mensagem || dados.message || 'Erro inesperado'`.
3. **Mascaração de Dados no Cadastro Inicial:** No fluxo de registro de consultores, existia um fallback paliativo que gerava o nome fantasia como `"Consultoria de " . $name` caso omitido, mascarando a ausência do dado cadastral mandatório da agência e impedindo a personalização corporativa.

### Alternativas Consideradas e Rejeitadas
- **Tratamento inline nos Controllers e Services:** Rejeitado por violar o Princípio da Responsabilidade Única (SRP) e o Princípio Aberto-Fechado (OCP). Qualquer mudança no schema de resposta da BrasilAPI ou da AbacatePay forçaria a alteração de múltiplos arquivos de lógica de negócio.
- **Mapeamento via Accessors/Mutators nos Models Eloquent:** Rejeitado porque os Models devem representar estritamente a persistência relacional do domínio, e não atuar como conversores de payload de provedores externos temporários.

---

## Decisão Arquitetural
Implementou-se uma camada formal de isolamento baseada no padrão estrutural **Adapter (GoF)** localizada no namespace `App\Adapters\`, complementada pela unificação estrita dos contratos de resposta HTTP:

1. **Camada Anticorrupção (`App\Adapters\AbacatePayAdapter`):**
   - Atua como uma *Anti-Corruption Layer (ACL)* entre o gateway de pagamentos e o domínio do sistema;
   - Métodos estruturados:
     - `paraPayloadCriacao(Agencia $agencia, Plano $plano, string $urlRetorno): array`: Converte as entidades internas no payload exigido pela API v2 do AbacatePay;
     - `deRespostaCriacao(array $respostaExterna): array` e `normalizarRetornoCobranca(...)`: Normaliza respostas de emissão de cobrança;
     - `deWebhookPayload(array $payload): array` e `traduzirStatus(string $statusExterno): string`: Mapeia eventos assíncronos (`checkout.completed`, `billing.paid`, `checkout.lost`) diretamente para os enums de domínio em português (`'ativo'`, `'pendente'`, `'suspenso'`, `'cancelado'`).
2. **Adaptador Resiliente de CNPJ (`App\Adapters\BrasilApiCnpjAdapter`):**
   - Unifica as divergências contratuais entre os provedores de consulta de pessoas jurídicas através dos métodos `deBrasilApi(array $dados)` e `deReceitaWs(array $dados)`;
   - Centraliza a função `padronizarPorte(string $porteBruto): string` (convertendo variações como `ME`, `EPP`, `Demais`, `01`, `03` para o vocabulário padronizado do Anjo Nexus);
   - Gera um DTO padronizado através de `paraRespostaApi(array $dadosNormalizados): array`, entregando dados saneados ao `CnpjService` e `CnpjController`.
3. **Padronização Global de Contratos HTTP (`message`):**
   - Unificação de 100% das respostas JSON de Controllers e Middlewares para utilizar exclusivamente a chave `'message'`, alinhando as respostas de negócio às exceções de validação nativas do framework (`ValidationException`);
   - Eliminação de tratamentos bivalentes no cliente HTTP JavaScript (`api.js`), consolidando o consumo de mensagens de feedback.
4. **Registro Explícito Obrigatório:**
   - Remoção do fallback `"Consultoria de " . $name` no `AuthController` e `RegistroService`;
   - O campo `nome_agencia` passou a ser obrigatório desde o formulário de cadastro no Front-end (`cadastro.html`), assegurando a consistência cadastral desde a porta de entrada.

---

## Justificativa
1. **Baixo Acoplamento e Alta Coesão:** A aplicação passa a interagir exclusivamente com seu próprio modelo de domínio. Caso o gateway AbacatePay seja substituído no futuro por outro provedor (ou caso novas APIs fiscais sejam adicionadas), apenas a classe Adapter correspondente precisará ser criada ou adaptada.
2. **Segurança de Contratos no Front-end:** A padronização da chave `'message'` remove redundâncias de código defensivo em todos os scripts do Front-end (`auth.js`, `startups.js`, `kanban.js`, etc.), tornando a exibição de toasts e modais de erro previsível e determinística.
3. **Legibilidade e Manutenibilidade:** O vocabulário do domínio permanece coeso e integralmente em português no banco de dados e nos Models Eloquent, respeitando o padrão arquitetural do projeto.

---

## Consequências

### Positivas
- Isolamento completo das dependências externas contra quebras de contrato de APIs de terceiros;
- Código de negócio 100% limpo, sem conhecimento de formatos específicos de payloads externos;
- Simplificação e robustez na manipulação de respostas de erro e sucesso no Front-end (`api.js`).

### Negativas / Trade-offs
- Introdução de uma camada adicional de classes e indireção (`App\Adapters`) no ciclo de processamento da aplicação.

---

## Rastreabilidade
- **Demanda Jira:** `EDTAN-280` (Refatoração de Contratos, Adapters e Padronização de APIs)
- **Requisitos Funcionais e Não-Funcionais:** `RF-01` (Cadastro de Consultoria), `RF-40` (Consulta CNPJ), `RNF-02` (Tolerância a Falhas e Resiliência), `RNF-04` (Manutenibilidade de Software)
- **Artefatos Vinculados:** `App\Adapters\AbacatePayAdapter.php`, `App\Adapters\BrasilApiCnpjAdapter.php`, `App\Services\CnpjService.php`, `public/js/api.js`
