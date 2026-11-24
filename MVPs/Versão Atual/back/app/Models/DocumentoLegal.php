<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class DocumentoLegal extends Model
{
    public const UPDATED_AT = null;
    public const TIPOS = ['termos_uso', 'politica_privacidade'];

    protected $table = 'documentos_legais';
    protected $fillable = ['tipo', 'versao', 'titulo', 'conteudo_json', 'vigente_tipo', 'publicado_em'];
    protected $casts = ['conteudo_json' => 'array', 'publicado_em' => 'datetime'];

    public static function calcularHash(string $tipo, string $versao, string $titulo, array $conteudo): string
    {
        $normalizar = static function (array $valor) use (&$normalizar): array {
            if (!array_is_list($valor)) {
                ksort($valor, SORT_STRING);
            }
            foreach ($valor as &$item) {
                if (is_array($item)) {
                    $item = $normalizar($item);
                }
            }
            return $valor;
        };

        return hash('sha256', json_encode([$tipo, $versao, $titulo, $normalizar($conteudo)],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    protected static function booted(): void
    {
        static::saving(function (self $documento) {
            if (!in_array($documento->tipo, self::TIPOS, true)
                || ($documento->vigente_tipo !== null && $documento->vigente_tipo !== $documento->tipo)) {
                throw new LogicException('Tipo de publicação inválido.');
            }
        });
        static::creating(function (self $documento) {
            $documento->hash_sha256 = self::calcularHash($documento->tipo, $documento->versao, $documento->titulo, $documento->conteudo_json);
        });
        static::updating(function (self $documento) {
            if (array_diff(array_keys($documento->getDirty()), ['vigente_tipo'])) {
                throw new LogicException('O conteúdo publicado é imutável.');
            }
        });
        static::deleting(fn () => throw new LogicException('Publicações devem ser preservadas.'));
    }
}
