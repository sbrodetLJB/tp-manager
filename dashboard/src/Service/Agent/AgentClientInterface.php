<?php

namespace App\Service\Agent;

use App\Entity\AgentConnection;
use App\Service\Agent\Dto\AgentConfig;
use App\Service\Agent\Dto\DatabaseRequest;
use App\Service\Agent\Dto\DatabaseResponse;
use App\Service\Agent\Dto\LinuxAccountRequest;
use App\Service\Agent\Dto\LinuxAccountResponse;
use App\Service\Agent\Dto\WebrootRequest;
use App\Service\Agent\Dto\WebrootResponse;

/**
 * Contrat implémenté par AgentHttpClient (réel, contracts/openapi.yaml) et par
 * les doubles de test utilisés dans les tests fonctionnels de l'orchestrateur.
 */
interface AgentClientInterface
{
    public function getConfig(AgentConnection $connection): AgentConfig;

    public function createLinuxAccount(AgentConnection $connection, LinuxAccountRequest $request): LinuxAccountResponse;

    public function createDatabase(AgentConnection $connection, DatabaseRequest $request): DatabaseResponse;

    public function createWebroot(AgentConnection $connection, WebrootRequest $request): WebrootResponse;
}
