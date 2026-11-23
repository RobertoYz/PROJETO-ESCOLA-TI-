<?php

namespace App\Exceptions;

use Exception;

class CnpjNotFoundException extends Exception
{
    public function __construct(string $cnpj, int $code = 404, ?Exception $previous = null)
    {
        parent::__construct("CNPJ {$cnpj} não foi encontrado na base da Receita Federal.", $code, $previous);
    }
}
