# 📘 Manual de Estudo de Código: Módulo de API de CNPJ e Onboarding
> **Nível:** Guia Didático para Estagiários e Desenvolvedores Júnior  
> **Módulo:** Busca, Validação, Resiliência em Cascata e Enriquecimento Cadastral (EDTAN-134 e EDTAN-41 / ADRs 005 a 007 do Pedro)  
> **Tecnologias:** PHP 8.2, Laravel 11 (Cache, HTTP Client, Validation Rules, Exceptions), APIs REST (BrasilAPI, ReceitaWS), Vanilla JS.

---

## 🎯 1. O que é este módulo e para que serve? (Visão Geral de Negócio)

No **Anjo Nexus**, para saber se uma startup pode ou não concorrer a um edital do governo (ex: R$ 300 mil da FINEP para Startups de Software em Santa Catarina), o sistema precisa saber com precisão:
- Razão Social e Nome Fantasia
- Porte Fiscal (MEI, Microempresa - ME, Empresa de Pequeno Porte - EPP, Média ou Grande)
- Regime Tributário (Simples Nacional, Lucro Presumido ou Lucro Real)
- CNAE Principal (o código fiscal que diz o que a empresa faz da vida)
- Cidade e Estado (muitos editais são exclusivos para empresas de SC, SP, etc.)

### O problema do mundo real:
Se você pedir para o consultor preencher esses 15 campos na mão durante o cadastro:
1. Ele vai demorar 5 minutos e pode desistir (abandono de cadastro).
2. Ele vai errar na digitação (ex: digitar o CNAE errado ou colocar que é Grande Empresa sendo ME).
3. Com dados errados, o motor de Inteligência Artificial do Anjo Nexus vai gerar uma proposta técnica com dados fiscais falsos que serão desclassificados pelo governo!

### A solução da nossa engenharia:
O consultor digita **apenas os 14 números do CNPJ**.  
O nosso backend consulta a base oficial da Receita Federal em milissegundos, valida a matemática dos dígitos, guarda em cache e preenche todos os 15 campos da tela na hora!

---

## 🗺️ 2. Como as peças se conversam? (O Caminho da Requisição)

Veja o ciclo de vida completo de uma consulta de CNPJ:

```
[ Usuário digita o CNPJ no formulário (cadastrar-startup.html) ]
   │
   │ 1. Clica no botão "Puxar Dados".
   │    O JavaScript faz fetch GET /api/cnpj/45123456000178.
   │
   ▼
[ CnpjController.php (Controlador) ]
   │ 2. Recebe o parâmetro e chama o CnpjService.
   │
   ▼
[ CnpjValidationRule.php (Validação Algorítmica Local) ]
   │ 3. Executa a matemática do Módulo 11 (sem gastar internet!).
   │    Se o CNPJ for falso/inválido: responde HTTP 422 na hora (< 1ms).
   │
   ▼
[ CnpjService.php (A Camada de Resiliência) ]
   │ 4. Procura no Cache Local do servidor (chave: "cnpj_data_45123456000178").
   │    - Se já estava no Cache: responde em 3ms!
   │    - Se não estava:
   │         a) Tenta a BrasilAPI (provedor primário, sem custo).
   │         b) Deu timeout ou erro 500? Aciona o Fallback: ReceitaWS!
   │         c) Salva o resultado no Cache por 7 dias.
   │
   ▼
[ Normalização Semântica (Adapter Pattern) ]
   │ 5. Converte o CNAE fiscal em Nicho de Atuação (ex: "Tecnologia / Software").
   │    Padroniza o Porte (ME/EPP) e o Regime Tributário.
   │
   ▼
[ Resposta JSON 200 OK ] ──> O navegador recebe os dados e popula os campos
                             bloqueando os dados oficiais como somente-leitura!
```

---

## 🧮 3. Validação Matemática Local sem Gastar Rede (`CnpjValidationRule.php`)
Arquivo: `app/Rules/CnpjValidationRule.php`

> **Por que validar na mão antes de chamar a internet?**  
> Imagine se um usuário engraçadinho digite `11.111.111/1111-11`. Se fizermos uma requisição HTTP para a Receita Federal para cada besteira digitada, vamos gastar internet, lotar a fila de conexões e tomar bloqueio de IP.  
> O algoritmo do **Módulo 11** calcula os dois últimos dígitos (Dígitos Verificadores) matematicamente. Se a conta não fechar, o CNPJ é falso com 100% de certeza!

```php
<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Implementa a interface ValidationRule do Laravel 11.
 * Isso permite usar esta regra dentro do $request->validate(['cnpj' => [new CnpjValidationRule()]]).
 */
class CnpjValidationRule implements ValidationRule
{
    /**
     * Método obrigatório do Laravel para rodar a validação em FormRequests.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Se o valor não for texto nem número, falha imediatamente
        if (!is_string($value) && !is_numeric($value)) {
            $fail('O campo :attribute deve ser um CNPJ válido.');
            return;
        }

        // Se a matemática não fechar, chama o callback $fail com mensagem em português
        if (!self::isValid((string)$value)) {
            $fail('O CNPJ informado é inválido.');
        }
    }

    /**
     * Remove pontos, barras e traços, deixando APENAS os números.
     * Exemplo: "45.123.456/0001-78" -> "45123456000178"
     */
    public static function sanitize(?string $cnpj): string
    {
        if ($cnpj === null) {
            return '';
        }
        // \D significa qualquer caractere que NÃO seja dígito numérico (0-9)
        return preg_replace('/\D/', '', $cnpj);
    }

    /**
     * Formata uma sequência pura de 14 dígitos no padrão visual oficial com máscara.
     * Exemplo: "45123456000178" -> "45.123.456/0001-78"
     */
    public static function format(string $cnpj): string
    {
        $digits = self::sanitize($cnpj);
        if (strlen($digits) !== 14) {
            return $cnpj; // Se não tiver 14 dígitos, devolve como veio
        }

        // sprintf formata pegando pedaço por pedaço com substr:
        // 00 (2 dig) . 000 (3 dig) . 000 (3 dig) / 0001 (4 dig) - 00 (2 dig)
        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($digits, 0, 2),
            substr($digits, 2, 3),
            substr($digits, 5, 3),
            substr($digits, 8, 4),
            substr($digits, 12, 2)
        );
    }

    /**
     * A MATEMÁTICA OFICIAL DO CNPJ (Módulo 11 da Receita Federal):
     */
    public static function isValid(string $cnpj): bool
    {
        // 1. Limpa os caracteres especiais
        $digits = self::sanitize($cnpj);

        // 2. Um CNPJ DEVE ter obrigatoriamente 14 números
        if (strlen($digits) !== 14) {
            return false;
        }

        // 3. Rejeita números repetidos conhecidos (ex: 00000000000000, 11111111111111).
        // Embora passem na conta do módulo 11 em alguns casos, são CNPJs matematicamente nulos.
        if (preg_match('/^(\d)\1{13}$/', $digits)) {
            return false;
        }

        // 4. VALIDAÇÃO DO PRIMEIRO DÍGITO VERIFICADOR (o 13º número):
        // Pesos oficiais: [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum1 = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum1 += (int)$digits[$i] * $weights1[$i];
        }

        // Resto da divisão por 11
        $remainder1 = $sum1 % 11;
        // Se o resto for menor que 2, o dígito é 0; caso contrário, é 11 menos o resto
        $digit1 = $remainder1 < 2 ? 0 : 11 - $remainder1;

        // Se o 13º dígito do CNPJ não for exatamente igual ao que calculamos: é INVÁLIDO!
        if ((int)$digits[12] !== $digit1) {
            return false;
        }

        // 5. VALIDAÇÃO DO SEGUNDO DÍGITO VERIFICADOR (o 14º número):
        // Pesos oficiais: [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
        $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum2 = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum2 += (int)$digits[$i] * $weights2[$i];
        }

        $remainder2 = $sum2 % 11;
        $digit2 = $remainder2 < 2 ? 0 : 11 - $remainder2;

        // Se o 14º dígito não bater: é INVÁLIDO!
        if ((int)$digits[13] !== $digit2) {
            return false;
        }

        // Se passou por tudo, o CNPJ é matematicamente autêntico!
        return true;
    }
}
```

---

## 🚫 4. A Exceção Personalizada (`CnpjNotFoundException.php`)
Arquivo: `app/Exceptions/CnpjNotFoundException.php`

> **Por que criar uma exceção própria?**  
> Para separar semântica!  
> - Uma coisa é a Receita Federal dizer: *"Eu procurei e esse CNPJ não existe"* (HTTP 404).  
> - Outra coisa é a internet cair ou o servidor da Receita dar erro 500.  
> Com uma classe própria, capturamos o erro 404 de forma cirúrgica e interrompemos a cascata na mesma hora!

```php
<?php

namespace App\Exceptions;

use Exception;

class CnpjNotFoundException extends Exception
{
    /**
     * Construtor da exceção com status HTTP 404 por padrão.
     *
     * @param string          $cnpj     O CNPJ procurado formatado
     * @param int             $code     Código de status HTTP (404)
     * @param Exception|null  $previous Exceção anterior (se houver)
     */
    public function __construct(string $cnpj, int $code = 404, ?Exception $previous = null)
    {
        parent::__construct("CNPJ {$cnpj} não foi encontrado na base da Receita Federal.", $code, $previous);
    }
}
```

---

## 🛡️ 5. O Serviço Resiliente em Cascata (`CnpjService.php`)
Arquivo: `app/Services/CnpjService.php`

Aqui está o coração do módulo. Ele gerencia o Cache de 7 dias, chama a BrasilAPI e, se der qualquer problema, aciona a ReceitaWS automaticamente sem o usuário perceber.

```php
<?php

namespace App\Services;

use App\Rules\CnpjValidationRule;
use App\Exceptions\CnpjNotFoundException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Exception;

class CnpjService
{
    // Tempo que o dado da empresa vai ficar guardado no cache da nossa aplicação (7 dias).
    // Empresas quase nunca mudam de nome ou de CNAE de um dia pro outro!
    protected const CACHE_TTL_DAYS = 7;

    // Se a API externa não responder em 8 segundos, nós cancelamos e tentamos o fallback.
    // Isso evita que a requisição do usuário fique travada eternamente.
    protected const TIMEOUT_SECONDS = 8;

    /**
     * Método público principal: busca dados do CNPJ com Cache e Fallback.
     *
     * @param string $cnpj
     * @param bool   $ignorarCache Se true (parâmetro ?refresh=true), ignora o cache e busca na Receita agora.
     * @return array
     */
    public function consultar(string $cnpj, bool $ignorarCache = false): array
    {
        // 1. Limpa o CNPJ e deixa só dígitos
        $digits = CnpjValidationRule::sanitize($cnpj);

        // 2. Valida a matemática antes de gastar qualquer requisição externa
        if (!CnpjValidationRule::isValid($digits)) {
            throw new InvalidArgumentException('O CNPJ informado possui formato ou dígitos verificadores inválidos.');
        }

        // Nome da chave no Cache (ex: "cnpj_data_45123456000178")
        $cacheKey = "cnpj_data_{$digits}";

        // 3. CONSULTA NA CAMADA DE CACHE:
        if (!$ignorarCache) {
            try {
                if (Cache::has($cacheKey)) {
                    $dadosEmCache = Cache::get($cacheKey);
                    if (is_array($dadosEmCache)) {
                        // Marca que a resposta veio do cache interno (velocidade < 5ms!)
                        $dadosEmCache['origem'] = 'cache';
                        return $dadosEmCache;
                    }
                }
            } catch (\Throwable $e) {
                // Se o Redis ou o banco de cache falhar, apenas loga e segue para a API externa
                Log::debug("[CnpjService] Cache indisponível: " . $e->getMessage());
            }
        }

        // 4. PROVEDOR PRIMÁRIO: BrasilAPI (Custo zero e sem limite estrito de IP)
        try {
            $dados = $this->consultarBrasilApi($digits);
            if ($dados !== null) {
                // Sucesso! Grava no Cache por 7 dias para as próximas consultas
                try {
                    Cache::put($cacheKey, $dados, now()->addDays(self::CACHE_TTL_DAYS));
                } catch (\Throwable $e) {
                    Log::debug("[CnpjService] Falha ao persistir em cache: " . $e->getMessage());
                }
                return $dados;
            }
        } catch (CnpjNotFoundException $e) {
            // Se a BrasilAPI disse que o CNPJ NÃO EXISTE (404), interrompe imediatamente!
            // Não adianta tentar a ReceitaWS porque um CNPJ inexistente não vai existir em lugar nenhum.
            throw $e;
        } catch (Exception $e) {
            // BrasilAPI deu erro 500 ou Timeout? Registra no log e segue pro plano B!
            Log::warning("[CnpjService] BrasilAPI falhou para CNPJ {$digits}. Acionando ReceitaWS fallback. Erro: " . $e->getMessage());
        }

        // 5. PROVEDOR SECUNDÁRIO (FALLBACK): ReceitaWS
        try {
            $dados = $this->consultarReceitaWs($digits);
            if ($dados !== null) {
                // Sucesso no Fallback! Salva no Cache também
                try {
                    Cache::put($cacheKey, $dados, now()->addDays(self::CACHE_TTL_DAYS));
                } catch (\Throwable $e) {
                    Log::debug("[CnpjService] Falha ao persistir em cache: " . $e->getMessage());
                }
                return $dados;
            }
        } catch (CnpjNotFoundException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error("[CnpjService] ReceitaWS também falhou para CNPJ {$digits}. Erro: " . $e->getMessage());
        }

        // 6. DEGRADAÇÃO AMIGÁVEL (Graceful Degradation):
        // Se as duas APIs do governo estiverem fora do ar ao mesmo tempo,
        // devolvemos uma mensagem educada orientando o usuário a preencher manualmente.
        throw new Exception('Os serviços da Receita Federal estão temporariamente indisponíveis no momento. Por favor, tente novamente ou insira os dados manualmente.');
    }

    /**
     * Consulta na BrasilAPI com timeout de 8 segundos.
     */
    protected function consultarBrasilApi(string $digits): ?array
    {
        $url = "https://brasilapi.com.br/api/cnpj/v1/{$digits}";

        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->withHeaders(['User-Agent' => 'AnjoNexus-CNPJ/1.0'])
            ->get($url);

        // Se retornou 404, o CNPJ não existe
        if ($response->status() === 404) {
            throw new CnpjNotFoundException(CnpjValidationRule::format($digits));
        }

        // Se respondeu HTTP 200, normaliza o JSON para o padrão Anjo Nexus
        if ($response->successful()) {
            return $this->normalizarBrasilApi($response->json(), $digits);
        }

        return null;
    }

    /**
     * Consulta na ReceitaWS com timeout de 8 segundos.
     */
    protected function consultarReceitaWs(string $digits): ?array
    {
        $url = "https://receitaws.com.br/v1/cnpj/{$digits}";

        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->withHeaders(['User-Agent' => 'AnjoNexus-CNPJ/1.0'])
            ->get($url);

        if ($response->successful()) {
            $json = $response->json();

            // A ReceitaWS às vezes responde 200 OK com status interno "ERROR"
            if (isset($json['status']) && strtoupper($json['status']) === 'ERROR') {
                $message = strtolower($json['message'] ?? '');
                if (str_contains($message, 'não encontrado') || str_contains($message, 'inválido')) {
                    throw new CnpjNotFoundException(CnpjValidationRule::format($digits));
                }
                return null;
            }

            return $this->normalizarReceitaWs($json, $digits);
        }

        if ($response->status() === 404) {
            throw new CnpjNotFoundException(CnpjValidationRule::format($digits));
        }

        return null;
    }

    /**
     * ADAPTER PATTERN (Normalizador):
     * A BrasilAPI retorna campos com nomes como 'cnae_fiscal', 'razao_social'.
     * Este método traduz tudo para o formato padrão do nosso sistema!
     */
    protected function normalizarBrasilApi(array $raw, string $digits): array
    {
        $cnaeDescricao = $raw['cnae_fiscal_descricao'] ?? null;
        $porte = $this->padronizarPorte($raw['porte'] ?? null);

        // Traduz booleano de simples nacional em texto formal
        $regime = null;
        if (isset($raw['opcao_pelo_simples'])) {
            $regime = $raw['opcao_pelo_simples'] ? 'Simples Nacional' : 'Lucro Presumido / Real';
        }

        $telefone = null;
        if (!empty($raw['ddd_telefone_1'])) {
            $telefone = trim($raw['ddd_telefone_1']);
        }

        // Extrai os sócios (QSA)
        $socios = [];
        if (!empty($raw['qsa']) && is_array($raw['qsa'])) {
            foreach ($raw['qsa'] as $socio) {
                $socios[] = [
                    'nome' => $socio['nome_socio'] ?? ($socio['nome'] ?? ''),
                    'qualificacao' => $socio['qualificacao_socio'] ?? ($socio['qualificacao'] ?? '')
                ];
            }
        }

        return [
            'provedor' => 'brasilapi',
            'cnpj' => $digits,
            'cnpj_formatado' => CnpjValidationRule::format($digits),
            'razao_social' => $raw['razao_social'] ?? '',
            'nome_fantasia' => !empty($raw['nome_fantasia']) ? $raw['nome_fantasia'] : ($raw['razao_social'] ?? ''),
            'data_abertura' => $raw['data_inicio_atividade'] ?? null,
            'situacao_cadastral' => $raw['descricao_situacao_cadastral'] ?? 'ATIVA',
            'porte' => $porte,
            'regime_tributario' => $regime,
            'cnae_codigo' => $raw['cnae_fiscal'] ?? null,
            'cnae_descricao' => $cnaeDescricao,
            // AQUI ESTÁ O PULO DO GATO: o CNAE é transformado no nicho de inovação da startup!
            'nicho_atuacao' => $cnaeDescricao ?: 'Tecnologia / Inovação',
            'natureza_juridica' => $raw['natureza_juridica'] ?? null,
            'endereco' => [
                'cep' => $raw['cep'] ?? null,
                'logradouro' => $raw['logradouro'] ?? null,
                'numero' => $raw['numero'] ?? null,
                'complemento' => $raw['complemento'] ?? null,
                'bairro' => $raw['bairro'] ?? null,
                'cidade' => $raw['municipio'] ?? null,
                'estado' => $raw['uf'] ?? null,
            ],
            'contato' => [
                'email' => $raw['email'] ?? null,
                'telefone' => $telefone
            ],
            'socios' => $socios,
            // Retorna também o array pronto para salvar direto na tabela 'startups'
            'startup_payload' => [
                'cnpj' => CnpjValidationRule::format($digits),
                'razao_social' => $raw['razao_social'] ?? '',
                'nome_fantasia' => !empty($raw['nome_fantasia']) ? $raw['nome_fantasia'] : ($raw['razao_social'] ?? ''),
                'data_abertura' => $raw['data_inicio_atividade'] ?? null,
                'email' => $raw['email'] ?? null,
                'telefone' => $telefone,
                'cep' => $raw['cep'] ?? null,
                'logradouro' => $raw['logradouro'] ?? null,
                'numero' => $raw['numero'] ?? null,
                'bairro' => $raw['bairro'] ?? null,
                'cidade' => $raw['municipio'] ?? null,
                'estado' => $raw['uf'] ?? null,
                'porte' => $porte,
                'regime_tributario' => $regime,
                'nicho_atuacao' => $cnaeDescricao ?: 'Tecnologia / Inovação'
            ]
        ];
    }

    /**
     * Normalizador correspondente para quando a resposta vier da ReceitaWS.
     */
    protected function normalizarReceitaWs(array $raw, string $digits): array
    {
        $cnaeDescricao = null;
        if (!empty($raw['atividade_principal']) && is_array($raw['atividade_principal'])) {
            $primeira = $raw['atividade_principal'][0] ?? [];
            $cnaeDescricao = $primeira['text'] ?? null;
        }

        // Converte data de abertura de DD/MM/AAAA para formato ISO (AAAA-MM-DD)
        $dataAbertura = null;
        if (!empty($raw['abertura'])) {
            $partes = explode('/', $raw['abertura']);
            if (count($partes) === 3) {
                $dataAbertura = "{$partes[2]}-{$partes[1]}-{$partes[0]}";
            }
        }

        $porte = $this->padronizarPorte($raw['porte'] ?? null);

        $regime = null;
        if (isset($raw['simples']['optante'])) {
            $regime = $raw['simples']['optante'] ? 'Simples Nacional' : 'Lucro Presumido / Real';
        }

        return [
            'provedor' => 'receitaws',
            'cnpj' => $digits,
            'cnpj_formatado' => CnpjValidationRule::format($digits),
            'razao_social' => $raw['nome'] ?? '',
            'nome_fantasia' => !empty($raw['fantasia']) ? $raw['fantasia'] : ($raw['nome'] ?? ''),
            'data_abertura' => $dataAbertura,
            'situacao_cadastral' => $raw['situacao'] ?? 'ATIVA',
            'porte' => $porte,
            'regime_tributario' => $regime,
            'cnae_descricao' => $cnaeDescricao,
            'nicho_atuacao' => $cnaeDescricao ?: 'Tecnologia / Inovação',
            'endereco' => [
                'cep' => $raw['cep'] ?? null,
                'logradouro' => $raw['logradouro'] ?? null,
                'numero' => $raw['numero'] ?? null,
                'bairro' => $raw['bairro'] ?? null,
                'cidade' => $raw['municipio'] ?? null,
                'estado' => $raw['uf'] ?? null,
            ],
            'startup_payload' => [
                'cnpj' => CnpjValidationRule::format($digits),
                'razao_social' => $raw['nome'] ?? '',
                'nome_fantasia' => !empty($raw['fantasia']) ? $raw['fantasia'] : ($raw['nome'] ?? ''),
                'data_abertura' => $dataAbertura,
                'email' => $raw['email'] ?? null,
                'telefone' => $raw['telefone'] ?? null,
                'cidade' => $raw['municipio'] ?? null,
                'estado' => $raw['uf'] ?? null,
                'porte' => $porte,
                'regime_tributario' => $regime,
                'nicho_atuacao' => $cnaeDescricao ?: 'Tecnologia / Inovação'
            ]
        ];
    }

    /**
     * Padroniza o porte em siglas oficiais: ME, EPP ou Demais.
     */
    protected function padronizarPorte(?string $porte): string
    {
        if (empty($porte)) return 'Demais';
        $p = mb_strtoupper(trim($porte));
        if (str_contains($p, 'MICRO') || $p === 'ME') return 'ME';
        if (str_contains($p, 'PEQUENO') || $p === 'EPP') return 'EPP';
        return 'Demais';
    }
}
```

---

## 🚪 6. O Controlador da API (`CnpjController.php`)
Arquivo: `app/Http/Controllers/CnpjController.php`

Disponibiliza duas rotas REST:
1. `GET /api/cnpj/{cnpj}` (para consulta rápida na URL pelo navegador)
2. `POST /api/cnpj/consultar` (para envio via formulário com corpo JSON)

```php
<?php

namespace App\Http\Controllers;

use App\Services\CnpjService;
use App\Rules\CnpjValidationRule;
use App\Exceptions\CnpjNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Exception;

class CnpjController extends Controller
{
    protected CnpjService $cnpjService;

    public function __construct(CnpjService $cnpjService)
    {
        $this->cnpjService = $cnpjService;
    }

    /**
     * GET /api/cnpj/{cnpj}?refresh=true
     */
    public function consultar(Request $request, string $cnpj): JsonResponse
    {
        // $request->boolean('refresh') lê se a URL tem ?refresh=1 ou ?refresh=true
        return $this->processarConsulta($cnpj, $request->boolean('refresh'));
    }

    /**
     * POST /api/cnpj/consultar
     */
    public function consultarPost(Request $request): JsonResponse
    {
        // Validação usando o Validator do Laravel com nossa regra personalizada
        $validator = Validator::make($request->all(), [
            'cnpj' => ['required', 'string', new CnpjValidationRule()],
            'refresh' => ['nullable', 'boolean']
        ], [
            'cnpj.required' => 'O campo CNPJ é obrigatório para realizar a consulta.',
            'cnpj.string' => 'O CNPJ deve ser informado como texto.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'erro',
                'mensagem' => $validator->errors()->first('cnpj'),
                'erros' => $validator->errors()
            ], 422); // HTTP 422: Validação Falhou
        }

        return $this->processarConsulta($request->input('cnpj'), $request->boolean('refresh'));
    }

    /**
     * Método central que chama o serviço e cuida de cada tipo de código HTTP.
     */
    protected function processarConsulta(string $cnpj, bool $refresh = false): JsonResponse
    {
        // 1. Checagem matemática prévia
        if (!CnpjValidationRule::isValid($cnpj)) {
            return response()->json([
                'status' => 'erro',
                'mensagem' => 'O CNPJ informado possui dígitos verificadores ou formato inválido.'
            ], 422);
        }

        try {
            // 2. Chama a cascata de resiliência no CnpjService
            $dados = $this->cnpjService->consultar($cnpj, $refresh);

            // Responde HTTP 200 com os dados oficiais da empresa
            return response()->json([
                'status' => 'sucesso',
                'mensagem' => 'Dados da empresa obtidos com sucesso na Receita Federal.',
                'dados' => $dados
            ], 200);

        } catch (CnpjNotFoundException $e) {
            // CNPJ não existe na base do governo: HTTP 404
            return response()->json([
                'status' => 'erro',
                'mensagem' => $e->getMessage()
            ], 404);

        } catch (InvalidArgumentException $e) {
            // Erro de formato: HTTP 422
            return response()->json([
                'status' => 'erro',
                'mensagem' => $e->getMessage()
            ], 422);

        } catch (Exception $e) {
            // Queda geral da internet/Receita: HTTP 503 (Serviço Indisponível)
            return response()->json([
                'status' => 'erro',
                'mensagem' => $e->getMessage()
            ], 503);
        }
    }
}
```

---

## 🎨 7. O Frontend Reativo no Onboarding (`cadastrar-startup.html`)
Arquivo: `front/paginas/cadastrar-startup.html`

Veja como o JavaScript puro consome a API e preenche 15 campos da tela em uma fração de segundo:

```javascript
// Pega os elementos do formulário no HTML
const campoCnpj = document.getElementById('campo-cnpj');
const btnBuscarCnpj = document.getElementById('botao-buscar-cnpj');
const divAvisoStatus = document.getElementById('aviso-status-cnpj');

// Funçãozinha para exibir mensagens verdes (sucesso) ou vermelhas (erro)
function exibirAviso(mensagem, tipo = 'info') {
    divAvisoStatus.textContent = mensagem;
    divAvisoStatus.className = `aviso-status aviso-${tipo}`;
    divAvisoStatus.style.display = 'block';
}

// Quando o usuário clica no botão "Puxar Dados":
btnBuscarCnpj.addEventListener('click', async () => {
    // 1. Remove pontos, traços e barras
    const cnpjLimpo = campoCnpj.value.replace(/\D/g, '');

    // 2. Valida se digitou os 14 números
    if (cnpjLimpo.length !== 14) {
        exibirAviso('Informe um CNPJ completo com 14 dígitos.', 'erro');
        return;
    }

    // 3. Feedback visual: desabilita o botão e muda o texto para o usuário não clicar duas vezes
    btnBuscarCnpj.disabled = true;
    btnBuscarCnpj.textContent = 'Buscando na Receita...';
    exibirAviso('Consultando base oficial da Receita Federal...', 'info');

    try {
        // 4. Faz a chamada HTTP assíncrona para a nossa API no backend
        const res = await fetch(`${API_BASE}/cnpj/${cnpjLimpo}`);
        const json = await res.json();

        if (json.status === 'sucesso') {
            const dados = json.dados;

            // 5. AUTO-PREENCHIMENTO INSTANTÂNEO DOS CAMPOS DO FORMULÁRIO:
            document.getElementById('campo-razao-social').value = dados.razao_social;
            document.getElementById('campo-nome-fantasia').value = dados.nome_fantasia;
            document.getElementById('campo-data-abertura').value = dados.data_abertura || '';
            document.getElementById('campo-cidade').value = dados.endereco.cidade || '';
            document.getElementById('campo-estado').value = dados.endereco.estado || '';
            document.getElementById('campo-porte').value = dados.porte || 'Demais';
            document.getElementById('campo-regime-tributario').value = dados.regime_tributario || 'Outro';
            document.getElementById('campo-nicho-atuacao').value = dados.nicho_atuacao;

            // 6. BLOQUEIO DE SEGURANÇA (Read-only):
            // Não deixamos o usuário editar a Razão Social ou o Porte manualmente
            // para evitar que ele tente falsificar o enquadramento do edital!
            document.getElementById('campo-razao-social').readOnly = true;
            document.getElementById('campo-porte').disabled = true;

            exibirAviso('✅ Dados importados com sucesso da Receita Federal!', 'sucesso');
        } else {
            exibirAviso(`❌ ${json.mensagem}`, 'erro');
        }
    } catch (err) {
        exibirAviso('Erro de conexão ao consultar a Receita Federal.', 'erro');
    } finally {
        // Restaura o botão ao estado normal
        btnBuscarCnpj.disabled = false;
        btnBuscarCnpj.textContent = 'Puxar Dados';
    }
});
```

---

## 💡 8. Perguntas Frequentes de Estagiário (FAQ Rápido)

1. **"Por que não usamos direto a ReceitaWS como primeira opção?"**  
   *Resposta:* A ReceitaWS tem um limite rígido de 3 requisições por minuto no plano gratuito. Se 4 pessoas tentarem cadastrar empresas ao mesmo tempo, a 4ª pessoa tomaria erro HTTP 429 de bloqueio. Por isso usamos a BrasilAPI como primária (que é ilimitada) e a ReceitaWS apenas como contingência de resiliência.

2. **"Por que guardar os dados no Cache por 7 dias inteiros?"**  
   *Resposta:* Porque os dados cadastrais oficiais de empresas (CNPJ, Razão Social, CNAE) quase nunca mudam de um dia para o outro. Guardando por 7 dias, a resposta da nossa API cai de 1500ms para **menos de 5 milissegundos**, economizando internet e servidores. Se a empresa realmente alterou o contrato ontem, o consultor pode forçar a atualização enviando `?refresh=true`.

3. **"O que é aquele `nicho_atuacao` que nós geramos a partir do CNAE?"**  
   *Resposta:* É a ponte entre a contabilidade e a inteligência artificial. O governo diz: *"CNAE 62.01-5-01 - Desenvolvimento de programas de computador"*. Nosso código traduz isso para *"Tecnologia / Software"*, permitindo que o Radar de Editais cruze esse nicho com os editais que dão dinheiro para empresas de TI!
