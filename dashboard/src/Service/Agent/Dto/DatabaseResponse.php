<?php

namespace App\Service\Agent\Dto;

final class DatabaseResponse
{
    public function __construct(
        public readonly string $engine,
        public readonly string $dbName,
        public readonly string $dbUser,
        public readonly string $grantsScope,
        public readonly string $status,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self($data['engine'], $data['dbName'], $data['dbUser'], $data['grantsScope'], $data['status']);
    }
}
