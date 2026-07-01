<?php

namespace App\Service\Agent\Dto;

final class LinuxAccountPasswordResetRequest
{
    public function __construct(
        public readonly string $requestId,
        public readonly string $authMethod,
        public readonly ?string $password = null,
        public readonly ?string $publicKey = null,
    ) {
    }

    public function toArray(): array
    {
        return array_filter([
            'requestId' => $this->requestId,
            'authMethod' => $this->authMethod,
            'password' => $this->password,
            'publicKey' => $this->publicKey,
        ], static fn ($value) => null !== $value);
    }
}
