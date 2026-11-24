<?php

namespace App\Services;

use App\Models\DocumentoLegal;
use App\Models\TermoAceite;
use App\Models\User;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class TermoAceiteService
{
    public function publicarNovaVersao(string $tipo, string $versao, string $titulo, array $conteudo, bool $somenteInicial = false): ?DocumentoLegal
    {
        try {
            return DB::transaction(function () use ($tipo, $versao, $titulo, $conteudo, $somenteInicial) {
                $publicacoes = DocumentoLegal::where('tipo', $tipo)->lockForUpdate()->get();
                if ($somenteInicial && $publicacoes->isNotEmpty()) {
                    return null;
                }
                $publicacoes->firstWhere('vigente_tipo', $tipo)?->update(['vigente_tipo' => null]);

                return DocumentoLegal::create([
                    'tipo' => $tipo, 'versao' => $versao, 'titulo' => $titulo, 'conteudo_json' => $conteudo,
                    'vigente_tipo' => $tipo, 'publicado_em' => now('UTC'),
                ]);
            }, 3);
        } catch (UniqueConstraintViolationException $erro) {
            if ($somenteInicial && DocumentoLegal::where('tipo', $tipo)->exists()) {
                return null;
            }
            throw new DomainException('Conflito ao publicar a versão do documento.', 0, $erro);
        }
    }

    public function vigentes(): Collection
    {
        $documentos = DocumentoLegal::whereNotNull('vigente_tipo')->get()->keyBy('tipo');
        if (!$documentos->has(DocumentoLegal::TIPOS)) {
            throw new HttpResponseException(response()->json(['message' => 'Documentos vigentes indisponíveis.'], 503));
        }
        return $documentos;
    }

    public function metadados(Collection $documentos): array
    {
        $termos = $documentos['termos_uso'];
        $politica = $documentos['politica_privacidade'];

        return [
            'documento_termos_id' => $termos->id, 'versao_termos' => $termos->versao, 'hash_termos' => $termos->hash_sha256,
            'documento_privacidade_id' => $politica->id, 'versao_privacidade' => $politica->versao, 'hash_privacidade' => $politica->hash_sha256,
        ];
    }

    public function validar(Request $requisicao): Collection
    {
        if ($requisicao->input('termos_aceitos') !== true) {
            throw new HttpResponseException(response()->json([
                'codigo_erro' => 'TERMOS_NAO_ACEITOS', 'message' => 'Confirme o aceite dos Termos e a ciência da Política.',
            ], 422));
        }
        $documentos = $this->vigentes();
        foreach ($this->metadados($documentos) as $campo => $valor) {
            if (!str_starts_with($campo, 'hash_') && $requisicao->input($campo) !== $valor) {
                throw new HttpResponseException(response()->json([
                    'codigo_erro' => 'VERSAO_TERMOS_DESATUALIZADA', 'message' => 'Confira e aceite as publicações vigentes.',
                ], 422));
            }
        }
        return $documentos;
    }

    public function validarConfiguracao(): void
    {
        $chave = config('termos.hmac_key');
        $id = config('termos.hmac_chave_id');
        if (!is_string($chave) || strlen(trim($chave)) < 32 || !is_string($id) || !preg_match('/^[a-zA-Z0-9._-]{1,32}$/D', $id)
            || (app()->isProduction() && (str_starts_with($chave, 'desenvolvimento-') || $chave === config('app.key')))) {
            throw new LogicException('Configure uma chave HMAC dedicada e seu identificador.');
        }
    }

    public function registrar(User $usuario, Collection $documentos, Request $requisicao): void
    {
        $this->validarConfiguracao();
        TermoAceite::create($this->metadados($documentos) + [
            'user_id' => $usuario->id, 'agencia_id' => $usuario->agencia_id,
            'titular_email_hmac' => hash_hmac('sha256', strtolower(trim($usuario->email)), config('termos.hmac_key')),
            'hmac_chave_id' => config('termos.hmac_chave_id'), 'aceito_em' => now('UTC'),
            'ip_address' => $requisicao->ip(), 'user_agent' => mb_strcut($requisicao->userAgent() ?? '', 0, 2048, 'UTF-8'),
        ]);
    }

    public function iniciarRetencao(array $usuarios): void
    {
        $anos = filter_var(config('termos.retencao_anos_pos_encerramento'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$anos) {
            throw new LogicException('Prazo de retenção inválido.');
        }
        TermoAceite::whereIn('user_id', $usuarios)->whereNull('retencao_ate')
            ->update(['retencao_ate' => now('UTC')->addYears($anos)]);
    }

    public function definirPreservacao(int $aceiteId, bool $preservar): void
    {
        TermoAceite::whereKey($aceiteId)->update(['preservacao_excepcional' => $preservar]);
    }
}
