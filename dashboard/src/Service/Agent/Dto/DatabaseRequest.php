<?php

namespace App\Service\Agent\Dto;

final class DatabaseRequest
{
    public function __construct(
        public readonly string $requestId,
        public readonly string $dbName,
        public readonly string $dbUser,
        public readonly string $dbPassword,
        public readonly string $charset = 'utf8mb4',
    ) {
    }

    public function toArray(): array
    {
        return [
            'requestId' => $this->requestId,
            'dbName' => $this->dbName,
            'dbUser' => $this->dbUser,
            'dbPassword' => $this->dbPassword,
            'charset' => $this->charset,
        ];
    }
}
