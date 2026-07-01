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

    /**
     * Idempotent : ne lève pas si le compte n'existe déjà plus (404 agent = no-op).
     */
    public function deleteLinuxAccount(AgentConnection $connection, string $username, bool $purgeHome): void;

    /**
     * Idempotent : ne lève pas si la base n'existe déjà plus (404 agent = no-op).
     */
    public function deleteDatabase(AgentConnection $connection, string $dbName): void;

    /**
     * Idempotent : ne lève pas si le dépôt n'existe déjà plus (404 agent = no-op).
     */
    public function deleteWebroot(AgentConnection $connection, string $eleveLogin, string $projetSlug): void;
}
