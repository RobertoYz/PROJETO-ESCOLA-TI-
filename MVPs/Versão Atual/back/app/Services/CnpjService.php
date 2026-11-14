<?php

namespace App\Services;

use App\Adapters\BrasilApiCnpjAdapter;
use App\Rules\CnpjValidationRule;
use App\Exceptions\CnpjNotFoundException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Exception;

class CnpjService
{
    protected const CACHE_TTL_DAYS = 7;
    protected const TIMEOUT_SECONDS = 8;

    protected BrasilApiCnpjAdapter $adapter;

    public function __construct(?BrasilApiCnpjAdapter $adapter = null)
    {
        $this->adapter = $adapter ?? new BrasilApiCnpjAdapter();
    }

    /**
     * Consulta os dados cadastrais do CNPJ utilizando BrasilAPI com fallback para ReceitaWS.
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
            return $this->adapter->deBrasilApi($response->json(), $digits);
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

            return $this->adapter->deReceitaWs($json, $digits);
        }

        if ($response->status() === 404) {
            throw new CnpjNotFoundException(CnpjValidationRule::format($digits));
        }

        return null;
    }
}
