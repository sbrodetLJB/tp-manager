<?php

namespace App\Service\Agent\Dto;

final class WebrootResponse
{
    public function __construct(
        public readonly string $path,
        public readonly string $owner,
        public readonly string $group,
        public readonly string $mode,
        public readonly string $status,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self($data['path'], $data['owner'], $data['group'], $data['mode'], $data['status']);
    }
}
