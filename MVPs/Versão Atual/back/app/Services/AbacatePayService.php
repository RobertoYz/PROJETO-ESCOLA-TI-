<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;

class AbacatePayService
{
    protected $apiKey;
    protected $baseUrl;

    public function __construct()
    {
        //Chave da API do AbacatePay é pra colocar no ".env"
        $this->apiKey = env('ABACATEPAY_API_KEY'); 
        
        //URL da API do AbacatePay (na doc diz que tanto no modo de produção/teste é o mesma URL)
        $this->baseUrl = 'https://api.abacatepay.com/v2'; 
    }

    public function gerarCobranca($agencia, $usuario, $plano)
    {
        // Se a chave não existir no ambiente de desenvolvimento, redireciona para o login de forma relativa
        if (empty($this->apiKey)) {
            return 'login.html?agencia=' . $agencia->id;
        }

        $idProdutoAbacatePay = $plano->id_plataforma_pagamento;

        $payload = [
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
            'externalId' => 'agencia_' . $agencia->id,
            'returnUrl' => 'http://127.0.0.1:5500/Vers%C3%A3o%20Atual/front/paginas/cadastro.html',
            'completionUrl' => 'http://127.0.0.1:5500/Vers%C3%A3o%20Atual/front/paginas/login.html',
            'methods' => ['PIX']
        ];

        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/checkouts/create", $payload);

        if ($response->failed()) {
            throw new Exception('Falha ao comunicar com o gateway de pagamento AbacatePay.');
        }

        $dadosRetorno = $response->json();
        
        return $dadosRetorno['data']['url'] ?? 'https://link-ficticio-abacatepay.com/pay/123';
    }
}