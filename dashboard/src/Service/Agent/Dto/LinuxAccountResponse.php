<?php

namespace App\Service\Agent\Dto;

final class LinuxAccountResponse
{
    public function __construct(
        public readonly string $username,
        public readonly int $uid,
        public readonly string $homeDir,
        public readonly string $status,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self($data['username'], $data['uid'], $data['homeDir'], $data['status']);
    }
}
