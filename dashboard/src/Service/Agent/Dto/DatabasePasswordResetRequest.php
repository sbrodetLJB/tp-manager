<?php

namespace App\Service\Agent\Dto;

final class DatabasePasswordResetRequest
{
    public function __construct(
        public readonly string $requestId,
        public readonly string $dbPassword,
    ) {
    }

    public function toArray(): array
    {
        return [
            'requestId' => $this->requestId,
            'dbPassword' => $this->dbPassword,
        ];
    }
}
