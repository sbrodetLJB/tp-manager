<?php

namespace App\Service\Provisioning;

final class DeprovisioningResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?string $errorMessage,
    ) {
    }

    public static function success(): self
    {
        return new self(true, null);
    }

    public static function failure(string $errorMessage): self
    {
        return new self(false, $errorMessage);
    }
}
