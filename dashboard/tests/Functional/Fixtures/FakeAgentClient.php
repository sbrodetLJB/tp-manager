<?php

namespace App\Tests\Functional\Fixtures;

use App\Entity\AgentConnection;
use App\Service\Agent\AgentClientInterface;
use App\Service\Agent\AgentException;
use App\Service\Agent\Dto\AgentConfig;
use App\Service\Agent\Dto\DatabaseRequest;
use App\Service\Agent\Dto\DatabaseResponse;
use App\Service\Agent\Dto\LinuxAccountRequest;
use App\Service\Agent\Dto\LinuxAccountResponse;
use App\Service\Agent\Dto\WebrootRequest;
use App\Service\Agent\Dto\WebrootResponse;

/**
 * Double de test pour AgentClientInterface : simule les réponses de l'agent
 * sans réseau ni système réel, et permet de simuler l'échec d'une étape
 * donnée pour tester le comportement de ProjectProvisioningOrchestrator.
 */
final class FakeAgentClient implements AgentClientInterface
{
    /** @var array<int, array{step: string, requestId: string}> */
    public array $calls = [];

    public ?string $failAtStep = null;

    public bool $failGetConfig = false;

    public string $configDbEngine = 'mysql';

    /**
     * Simule une panne temporaire (ex: mysqld tué en plein lot) qui n'affecte
     * que certains identifiants précis, plutôt que TOUTES les requêtes d'une
     * étape (voir failAtStep) — utile pour les tests d'actions de masse.
     *
     * @var array<int, string>
     */
    public array $failForDbNames = [];

    public function getConfig(AgentConnection $connection): AgentConfig
    {
        if ($this->failGetConfig) {
            throw new AgentException('AGENT_UNREACHABLE', "Impossible de joindre l'agent (simulé).");
        }

        return new AgentConfig('0.1.0', 'v1', $this->configDbEngine, null, '/var/www/html', null, null);
    }

    public function createLinuxAccount(AgentConnection $connection, LinuxAccountRequest $request): LinuxAccountResponse
    {
        $this->recordAndMaybeFail('linux_account', $request->requestId);

        return new LinuxAccountResponse($request->username, 2001, $request->homeDir, 'created');
    }

    public function createDatabase(AgentConnection $connection, DatabaseRequest $request): DatabaseResponse
    {
        $this->recordAndMaybeFail('database', $request->requestId);

        if (in_array($request->dbName, $this->failForDbNames, true)) {
            throw new AgentException('SIMULATED_FAILURE', "Échec simulé pour la base \"{$request->dbName}\".");
        }

        return new DatabaseResponse('mysql', $request->dbName, $request->dbUser, 'database-only', 'created');
    }

    public function createWebroot(AgentConnection $connection, WebrootRequest $request): WebrootResponse
    {
        $this->recordAndMaybeFail('webroot', $request->requestId);

        return new WebrootResponse(
            "/var/www/html/{$request->eleveLogin}/{$request->projetSlug}",
            $request->owner,
            $request->group,
            '2750',
            'created',
        );
    }

    public function deleteLinuxAccount(AgentConnection $connection, string $username, bool $purgeHome): void
    {
        $this->recordAndMaybeFail('linux_account', 'delete-'.$username);
    }

    public function deleteDatabase(AgentConnection $connection, string $dbName): void
    {
        $this->recordAndMaybeFail('database', 'delete-'.$dbName);
    }

    public function deleteWebroot(AgentConnection $connection, string $eleveLogin, string $projetSlug): void
    {
        $this->recordAndMaybeFail('webroot', 'delete-'.$eleveLogin.'-'.$projetSlug);
    }

    private function recordAndMaybeFail(string $step, string $requestId): void
    {
        $this->calls[] = ['step' => $step, 'requestId' => $requestId];

        if ($this->failAtStep === $step) {
            throw new AgentException('SIMULATED_FAILURE', "Échec simulé à l'étape \"$step\".");
        }
    }
}
