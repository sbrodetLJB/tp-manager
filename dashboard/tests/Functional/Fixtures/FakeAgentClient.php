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

    public function getConfig(AgentConnection $connection): AgentConfig
    {
        return new AgentConfig('0.1.0', 'v1', 'mysql', null, '/var/www/html', null, null);
    }

    public function createLinuxAccount(AgentConnection $connection, LinuxAccountRequest $request): LinuxAccountResponse
    {
        $this->recordAndMaybeFail('linux_account', $request->requestId);

        return new LinuxAccountResponse($request->username, 2001, $request->homeDir, 'created');
    }

    public function createDatabase(AgentConnection $connection, DatabaseRequest $request): DatabaseResponse
    {
        $this->recordAndMaybeFail('database', $request->requestId);

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

    private function recordAndMaybeFail(string $step, string $requestId): void
    {
        $this->calls[] = ['step' => $step, 'requestId' => $requestId];

        if ($this->failAtStep === $step) {
            throw new AgentException('SIMULATED_FAILURE', "Échec simulé à l'étape \"$step\".");
        }
    }
}
