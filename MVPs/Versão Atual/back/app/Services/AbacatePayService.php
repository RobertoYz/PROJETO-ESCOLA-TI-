<?php

namespace App\Services;

use App\Adapters\AbacatePayAdapter;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Exception;

class AbacatePayService
{
    protected $chaveApi;
    protected $urlBase;
    protected AbacatePayAdapter $adapter;

    public function __construct(?AbacatePayAdapter $adapter = null)
    {
        $this->chaveApi = env('ABACATEPAY_API_KEY');
        $this->urlBase = 'https://api.abacatepay.com/v2';
        $this->adapter = $adapter ?? new AbacatePayAdapter();
    }

    /**
     * Gera cobrança real no AbacatePay e retorna os campos padronizados do domínio (`id_transacao`, `url_checkout`).
     */
    public function gerarCobranca($agencia, $usuario, $plano): array
    {
        if (empty($this->chaveApi)) {
            throw new Exception('Chave ABACATEPAY_API_KEY não configurada no arquivo .env.');
        }

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

        $dadosEnvio = $this->adapter->paraPayloadCriacao($agencia, $usuario, $plano, $urlLogin);

        $resposta = Http::withToken($this->chaveApi)
            ->post("{$this->urlBase}/checkouts/create", $dadosEnvio);

        if ($resposta->failed()) {
            $detalheErro = $resposta->json('error.message') ?? $resposta->json('message') ?? $resposta->body();
            throw new Exception('Falha ao comunicar com o gateway de pagamento AbacatePay: ' . $detalheErro);
        }

        $dadosAdaptados = $this->adapter->deRespostaCriacao($resposta->json() ?? []);
        $urlCheckout = $dadosAdaptados['url_checkout'];

        if (empty($urlCheckout) || !str_starts_with($urlCheckout, 'http') || !str_contains($urlCheckout, 'abacatepay.com')) {
            throw new Exception('Gateway AbacatePay não retornou uma URL de checkout válida.');
        }

        return $dadosAdaptados;
    }

    /**
     * Consulta o status da cobrança no AbacatePay.
     */
    public function consultarStatusCobranca($idTransacao)
    {
        if (empty($this->chaveApi) || empty($idTransacao)) {
            return null;
        }

        try {
            $resposta = Http::withToken($this->chaveApi)
                ->get("{$this->urlBase}/checkouts/get", ['id' => $idTransacao]);

            if ($resposta->successful()) {
                return $resposta->json('data.status');
            }
        } catch (\Exception $excecao) {
            return null;
        }

        return null;
    }

    /**
     * Verifica se o pagamento/cobrança de uma agência está expirado.
     */
    public function estaExpirado($agencia): bool
    {
        if (empty($agencia->url_checkout)) {
            return true;
        }

        if ($agencia->pagamento_expira_em && Carbon::parse($agencia->pagamento_expira_em)->isPast()) {
            return true;
        }

        if (!empty($agencia->id_transacao)) {
            $status = $this->consultarStatusCobranca($agencia->id_transacao);
            if ($status && in_array($this->adapter->traduzirStatus($status), ['suspenso', 'cancelado'], true)) {
                return true;
            }
        }

        return false;
    }
}
