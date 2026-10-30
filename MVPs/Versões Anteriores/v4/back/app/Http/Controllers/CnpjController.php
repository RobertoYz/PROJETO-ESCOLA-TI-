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
     * Consulta dados cadastrais por CNPJ via parâmetro de URL (GET /api/cnpj/{cnpj}).
     */
    public function consultar(Request $request, string $cnpj): JsonResponse
    {
        return $this->processarConsulta($cnpj, $request->boolean('refresh'));
    }

    /**
     * Consulta dados cadastrais por CNPJ via corpo da requisição (POST /api/cnpj/consultar).
     */
    public function consultarPost(Request $request): JsonResponse
    {
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
            ], 422);
        }

        return $this->processarConsulta($request->input('cnpj'), $request->boolean('refresh'));
    }

    /**
     * Núcleo de processamento e tratamento de exceções.
     */
    protected function processarConsulta(string $cnpj, bool $refresh = false): JsonResponse
    {
        // Validação inicial matemática
        if (!CnpjValidationRule::isValid($cnpj)) {
            return response()->json([
                'status' => 'erro',
                'mensagem' => 'O CNPJ informado possui dígitos verificadores ou formato inválido.'
            ], 422);
        }

        try {
            $dados = $this->cnpjService->consultar($cnpj, $refresh);

            return response()->json([
                'status' => 'sucesso',
                'mensagem' => 'Dados da empresa obtidos com sucesso na Receita Federal.',
                'dados' => $dados
            ], 200);

        } catch (CnpjNotFoundException $e) {
            return response()->json([
                'status' => 'erro',
                'mensagem' => $e->getMessage()
            ], 404);

        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status' => 'erro',
                'mensagem' => $e->getMessage()
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'erro',
                'mensagem' => $e->getMessage()
            ], 503);
        }
    }
}
