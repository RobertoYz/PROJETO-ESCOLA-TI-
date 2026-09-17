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
        //ID do produto Cadastrado no AbacatePay
        $idProdutoAbacatePay = 'prod_sWfDg0eZXTLbtZEHYpqnMLKP';

        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/checkouts/create", [
                'items' => [
                    [
                        'id' => $idProdutoAbacatePay,
                        'quantity' => 1
                    ]
                ],
                'externalId' => 'agencia_' . $agencia->id,
                'returnUrl' => 'http://localhost:3000/voltar',
                'completionUrl' => 'http://localhost:3000/sucesso',
                'methods' => ['PIX'] //, 'CARD'
            ]);

        if ($response->failed()) {
            throw new Exception('Falha ao comunicar com o gateway de pagamento AbacatePay.');
        }

        $dadosRetorno = $response->json();
        
        return $dadosRetorno['data']['url'] ?? 'https://link-ficticio-abacatepay.com/pay/123';
    }
}