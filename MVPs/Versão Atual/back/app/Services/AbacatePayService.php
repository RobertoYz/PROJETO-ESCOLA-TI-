<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Exception;

class AbacatePayService
{
    protected $chaveApi;
    protected $urlBase;

    public function __construct()
    {
        // Chave da API do AbacatePay obtida do arquivo .env
        $this->chaveApi = env('ABACATEPAY_API_KEY'); 
        
        // URL base da API do AbacatePay (v2)
        $this->urlBase = 'https://api.abacatepay.com/v2'; 
    }

    /**
     * Gera cobrança real no AbacatePay sem bypass/mock
     * Retorna array com url e id de cobrança
     */
    public function gerarCobranca($agencia, $usuario, $plano)
    {
        if (empty($this->chaveApi)) {
            throw new Exception('Chave ABACATEPAY_API_KEY não configurada no arquivo .env.');
        }

        // Determina a URL base do front-end para retorno e conclusão
        $urlBaseFront = env('FRONT_URL');
        if (!$urlBaseFront) {
            $origemRequisicao = request()->header('X-Frontend-Url') 
                ?? request()->header('X-Referer') 
                ?? request()->header('referer');
            if ($origemRequisicao) {
                $urlAnalisada = parse_url($origemRequisicao);
                $host = ($urlAnalisada['scheme'] ?? 'http') . '://' . ($urlAnalisada['host'] ?? 'localhost') . (isset($urlAnalisada['port']) ? ':' . $urlAnalisada['port'] : '');
                $caminho = $urlAnalisada['path'] ?? '';
                if (str_contains($caminho, '/paginas/')) {
                    $prefixo = substr($caminho, 0, strpos($caminho, '/paginas/'));
                    $urlBaseFront = rtrim($host . $prefixo, '/');
                } else {
                    $urlBaseFront = $host;
                }
            } else {
                $urlBaseFront = request()->getSchemeAndHttpHost();
            }
        }

        $urlLogin = rtrim($urlBaseFront, '/') . '/paginas/login.html';

        $idProdutoAbacatePay = $plano->id_plataforma_pagamento ?? 'prod_sWfDg0eZXTLbtZEHYpqnMLKP';

        // Carga de dados exigida pela API do AbacatePay v2
        $dadosEnvio = [
            'frequency' => 'ONE_TIME',
            'items' => [
                [
                    'id' => $idProdutoAbacatePay,
                    'quantity' => 1
                ]
            ],
            'customer' => [
                'name' => $usuario->name,
                'email' => $usuario->email
            ],
            'externalId' => 'agencia_' . $agencia->id . '_' . uniqid(),
            'returnUrl' => $urlLogin,
            'completionUrl' => $urlLogin,
            'methods' => ['PIX']
        ];

        $resposta = Http::withToken($this->chaveApi)
            ->post("{$this->urlBase}/checkouts/create", $dadosEnvio);

        if ($resposta->failed()) {
            $detalheErro = $resposta->json('error.message') ?? $resposta->json('message') ?? $resposta->body();
            throw new Exception('Falha ao comunicar com o gateway de pagamento AbacatePay: ' . $detalheErro);
        }

        $dadosRetorno = $resposta->json();
        $urlCheckout = $dadosRetorno['data']['url'] ?? null;
        $idCobranca = $dadosRetorno['data']['id'] ?? null;

        if (empty($urlCheckout) || !str_starts_with($urlCheckout, 'http') || !str_contains($urlCheckout, 'abacatepay.com')) {
            throw new Exception('Gateway AbacatePay não retornou uma URL de checkout válida.');
        }

        return [
            'url' => $urlCheckout,
            'id' => $idCobranca,
            'checkout_url' => $urlCheckout,
            'url_checkout' => $urlCheckout,
            'billing_id' => $idCobranca,
            'id_cobranca' => $idCobranca,
        ];
    }

    /**
     * Consulta o status da cobrança no AbacatePay
     */
    public function consultarStatusCobranca($idCobranca)
    {
        if (empty($this->chaveApi) || empty($idCobranca)) {
            return null;
        }

        try {
            $resposta = Http::withToken($this->chaveApi)
                ->get("{$this->urlBase}/checkouts/get", ['id' => $idCobranca]);

            if ($resposta->successful()) {
                return $resposta->json('data.status');
            }
        } catch (\Exception $excecao) {
            return null;
        }

        return null;
    }

    /**
     * Verifica se o pagamento/cobrança de uma agência está expirado
     */
    public function estaExpirado($agencia)
    {
        $urlCheckout = $agencia->checkout_url ?? $agencia->url_checkout;
        if (empty($urlCheckout)) {
            return true;
        }

        // Validação via timestamp (expira após 60 minutos da geração)
        if ($agencia->pagamento_expira_em && Carbon::parse($agencia->pagamento_expira_em)->isPast()) {
            return true;
        }

        // Validação via status retornado pelo gateway
        $idCobranca = $agencia->billing_id ?? $agencia->id_cobranca ?? $agencia->id_plataforma_pagamento;
        if (!empty($idCobranca)) {
            $status = $this->consultarStatusCobranca($idCobranca);
            if ($status && in_array(strtoupper($status), ['EXPIRED', 'CANCELLED', 'REFUNDED'])) {
                return true;
            }
        }

        return false;
    }
}