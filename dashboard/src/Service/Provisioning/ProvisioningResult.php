<?php

namespace App\Service\Provisioning;

final class ProvisioningResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?string $linuxPassword,
        public readonly ?string $dbPassword,
        public readonly ?string $errorMessage,
    ) {
    }

    public static function success(string $linuxPassword, string $dbPassword): self
    {
        return new self(true, $linuxPassword, $dbPassword, null);
    }

    public static function failure(string $errorMessage): self
    {
        return new self(false, null, null, $errorMessage);
    }
}
