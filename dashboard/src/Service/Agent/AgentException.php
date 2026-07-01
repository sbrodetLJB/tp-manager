<?php

namespace App\Service\Agent;

final class AgentException extends \RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        private readonly ?int $httpStatusCode = null,
    ) {
        parent::__construct($message);
    }

    public function getHttpStatusCode(): ?int
    {
        return $this->httpStatusCode;
    }
}
