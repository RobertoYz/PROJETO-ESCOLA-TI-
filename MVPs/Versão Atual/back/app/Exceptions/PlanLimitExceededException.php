<?php

namespace App\Exceptions;

use RuntimeException;

class PlanLimitExceededException extends RuntimeException
{
    protected string $errorCode;
    protected int $atual;
    protected ?int $limite;
    protected string $planoAtual;
    protected ?int $restantes;

    public function __construct(
        string $message,
        string $errorCode = 'LIMITE_STARTUPS_ATINGIDO',
        int $atual = 0,
        ?int $limite = 0,
        string $planoAtual = 'Standard',
        ?int $restantes = null
    ) {
        parent::__construct($message, 403);
        $this->errorCode = $errorCode;
        $this->atual = $atual;
        $this->limite = $limite;
        $this->planoAtual = $planoAtual;
        $this->restantes = $restantes ?? ($limite !== null ? max(0, $limite - $atual) : null);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getAtual(): int
    {
        return $this->atual;
    }

    public function getLimite(): ?int
    {
        return $this->limite;
    }

    public function getPlanoAtual(): string
    {
        return $this->planoAtual;
    }

    public function getRestantes(): ?int
    {
        return $this->restantes;
    }
}
