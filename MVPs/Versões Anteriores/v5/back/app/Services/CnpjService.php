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
    /**
     * Tempo de cache para consultas bem-sucedidas (7 dias).
     */
    protected const CACHE_TTL_DAYS = 7;

    /**
     * Timeout máximo de espera para cada requisição HTTP (segundos).
     */
    protected const TIMEOUT_SECONDS = 8;

    /**
     * Consulta os dados cadastrais do CNPJ utilizando BrasilAPI com fallback para ReceitaWS.
     *
     * @param string $cnpj
     * @param bool $ignorarCache Se true, força a consulta externa sem utilizar o cache.
     * @return array
     * @throws InvalidArgumentException
     * @throws CnpjNotFoundException
     * @throws Exception
     */
    public function consultar(string $cnpj, bool $ignorarCache = false): array
    {
        $digits = CnpjValidationRule::sanitize($cnpj);

        if (!CnpjValidationRule::isValid($digits)) {
            throw new InvalidArgumentException('O CNPJ informado possui formato ou dígitos verificadores inválidos.');
        }

        $cacheKey = "cnpj_data_{$digits}";

        if (!$ignorarCache) {
            try {
                if (Cache::has($cacheKey)) {
                    $dadosEmCache = Cache::get($cacheKey);
                    if (is_array($dadosEmCache)) {
                        $dadosEmCache['origem'] = 'cache';
                        return $dadosEmCache;
                    }
                }
            } catch (\Throwable $e) {
                Log::debug("[CnpjService] Cache indisponível: " . $e->getMessage());
            }
        }

        // 1. Tenta consulta via BrasilAPI (Provedor Primário - Custo Zero)
        try {
            $dados = $this->consultarBrasilApi($digits);
            if ($dados !== null) {
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
            Log::warning("[CnpjService] BrasilAPI falhou para CNPJ {$digits}. Tentando ReceitaWS fallback. Erro: " . $e->getMessage());
        }

        // 2. Fallback: ReceitaWS
        try {
            $dados = $this->consultarReceitaWs($digits);
            if ($dados !== null) {
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

        throw new Exception('Os serviços da Receita Federal estão temporariamente indisponíveis no momento. Por favor, tente novamente ou insira os dados manualmente.');
    }

    /**
     * Consulta via BrasilAPI.
     */
    protected function consultarBrasilApi(string $digits): ?array
    {
        $url = "https://brasilapi.com.br/api/cnpj/v1/{$digits}";

        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->withHeaders(['User-Agent' => 'AnjoNexus-CNPJ/1.0'])
            ->get($url);

        if ($response->status() === 404) {
            throw new CnpjNotFoundException(CnpjValidationRule::format($digits));
        }

        if ($response->successful()) {
            return $this->normalizarBrasilApi($response->json(), $digits);
        }

        return null;
    }

    /**
     * Consulta via ReceitaWS.
     */
    protected function consultarReceitaWs(string $digits): ?array
    {
        $url = "https://receitaws.com.br/v1/cnpj/{$digits}";

        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->withHeaders(['User-Agent' => 'AnjoNexus-CNPJ/1.0'])
            ->get($url);

        if ($response->successful()) {
            $json = $response->json();

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
     * Normaliza a resposta da BrasilAPI para o formato unificado do Anjo Nexus.
     */
    protected function normalizarBrasilApi(array $raw, string $digits): array
    {
        $cnaeDescricao = $raw['cnae_fiscal_descricao'] ?? null;
        $porte = $this->padronizarPorte($raw['porte'] ?? null);

        $regime = null;
        if (isset($raw['opcao_pelo_simples'])) {
            $regime = $raw['opcao_pelo_simples'] ? 'Simples Nacional' : 'Lucro Presumido / Real';
        }

        $telefone = null;
        if (!empty($raw['ddd_telefone_1'])) {
            $telefone = trim($raw['ddd_telefone_1']);
        }

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
            // Campos prontos no formato da tabela startups
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
     * Normaliza a resposta da ReceitaWS para o formato unificado do Anjo Nexus.
     */
    protected function normalizarReceitaWs(array $raw, string $digits): array
    {
        $cnaeDescricao = null;
        $cnaeCodigo = null;
        if (!empty($raw['atividade_principal']) && is_array($raw['atividade_principal'])) {
            $primeira = $raw['atividade_principal'][0] ?? [];
            $cnaeDescricao = $primeira['text'] ?? null;
            $cnaeCodigo = $primeira['code'] ?? null;
        }

        // Converte data DD/MM/AAAA para AAAA-MM-DD
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

        $socios = [];
        if (!empty($raw['qsa']) && is_array($raw['qsa'])) {
            foreach ($raw['qsa'] as $socio) {
                $socios[] = [
                    'nome' => $socio['nome'] ?? '',
                    'qualificacao' => $socio['qual'] ?? ''
                ];
            }
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
            'cnae_codigo' => $cnaeCodigo,
            'cnae_descricao' => $cnaeDescricao,
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
                'telefone' => $raw['telefone'] ?? null
            ],
            'socios' => $socios,
            // Campos prontos no formato da tabela startups
            'startup_payload' => [
                'cnpj' => CnpjValidationRule::format($digits),
                'razao_social' => $raw['nome'] ?? '',
                'nome_fantasia' => !empty($raw['fantasia']) ? $raw['fantasia'] : ($raw['nome'] ?? ''),
                'data_abertura' => $dataAbertura,
                'email' => $raw['email'] ?? null,
                'telefone' => $raw['telefone'] ?? null,
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
     * Padroniza a string de porte da empresa (ME, EPP, Médio, Grande).
     */
    protected function padronizarPorte(?string $porte): string
    {
        if (empty($porte)) {
            return 'Demais';
        }

        $p = mb_strtoupper(trim($porte));

        if (str_contains($p, 'MICRO') || $p === 'ME') {
            return 'Microempresa (ME)';
        }

        if (str_contains($p, 'PEQUENO') || $p === 'EPP') {
            return 'Empresa de Pequeno Porte (EPP)';
        }

        if (str_contains($p, 'MÉDIO') || str_contains($p, 'MEDIO')) {
            return 'Médio Porte';
        }

        if (str_contains($p, 'GRANDE')) {
            return 'Grande Porte';
        }

        return 'Demais';
    }
}
