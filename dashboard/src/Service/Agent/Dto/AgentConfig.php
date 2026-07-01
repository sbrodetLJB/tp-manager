<?php

namespace App\Service\Agent\Dto;

final class AgentConfig
{
    public function __construct(
        public readonly string $agentVersion,
        public readonly string $contractVersion,
        public readonly string $dbEngine,
        public readonly ?string $dbEngineVersion,
        public readonly string $webRootBase,
        public readonly ?string $sftpChrootStrategy,
        public readonly ?string $hostname,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['agentVersion'],
            $data['contractVersion'],
            $data['dbEngine'],
            $data['dbEngineVersion'] ?? null,
            $data['webRootBase'],
            $data['sftpChrootStrategy'] ?? null,
            $data['hostname'] ?? null,
        );
    }
}
