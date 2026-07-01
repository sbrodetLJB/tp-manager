<?php

namespace App\Service\Agent\Dto;

final class WebrootRequest
{
    public function __construct(
        public readonly string $requestId,
        public readonly string $eleveLogin,
        public readonly string $projetSlug,
        public readonly string $owner,
        public readonly string $group,
    ) {
    }

    public function toArray(): array
    {
        return [
            'requestId' => $this->requestId,
            'eleveLogin' => $this->eleveLogin,
            'projetSlug' => $this->projetSlug,
            'owner' => $this->owner,
            'group' => $this->group,
        ];
    }
}
