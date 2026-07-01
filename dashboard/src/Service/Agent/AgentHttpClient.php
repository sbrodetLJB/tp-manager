<?php

namespace App\Service\Agent;

use App\Entity\AgentConnection;
use App\Service\Agent\Dto\AgentConfig;
use App\Service\Agent\Dto\DatabasePasswordResetRequest;
use App\Service\Agent\Dto\DatabaseRequest;
use App\Service\Agent\Dto\DatabaseResponse;
use App\Service\Agent\Dto\LinuxAccountPasswordResetRequest;
use App\Service\Agent\Dto\LinuxAccountRequest;
use App\Service\Agent\Dto\LinuxAccountResponse;
use App\Service\Agent\Dto\WebrootRequest;
use App\Service\Agent\Dto\WebrootResponse;
use App\Service\Security\AgentTokenEncryptor;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AgentHttpClient implements AgentClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AgentTokenEncryptor $tokenEncryptor,
    ) {
    }

    public function getConfig(AgentConnection $connection): AgentConfig
    {
        return AgentConfig::fromArray($this->request($connection, 'GET', '/v1/config'));
    }

    public function createLinuxAccount(AgentConnection $connection, LinuxAccountRequest $request): LinuxAccountResponse
    {
        return LinuxAccountResponse::fromArray(
            $this->request($connection, 'POST', '/v1/linux-accounts', $request->toArray())
        );
    }

    public function createDatabase(AgentConnection $connection, DatabaseRequest $request): DatabaseResponse
    {
        return DatabaseResponse::fromArray(
            $this->request($connection, 'POST', '/v1/databases', $request->toArray())
        );
    }

    public function createWebroot(AgentConnection $connection, WebrootRequest $request): WebrootResponse
    {
        return WebrootResponse::fromArray(
            $this->request($connection, 'POST', '/v1/webroots', $request->toArray())
        );
    }

    public function resetLinuxAccountPassword(AgentConnection $connection, string $username, LinuxAccountPasswordResetRequest $request): void
    {
        $this->request($connection, 'POST', '/v1/linux-accounts/'.rawurlencode($username).'/reset-password', $request->toArray());
    }

    public function resetDatabasePassword(AgentConnection $connection, string $dbName, DatabasePasswordResetRequest $request): void
    {
        $this->request($connection, 'POST', '/v1/databases/'.rawurlencode($dbName).'/reset-password', $request->toArray());
    }

    public function deleteLinuxAccount(AgentConnection $connection, string $username, bool $purgeHome): void
    {
        $path = '/v1/linux-accounts/'.rawurlencode($username).'?purgeHome='.($purgeHome ? 'true' : 'false');
        $this->requestIgnoringNotFound($connection, 'DELETE', $path, 'USER_NOT_FOUND');
    }

    public function deleteDatabase(AgentConnection $connection, string $dbName): void
    {
        $this->requestIgnoringNotFound($connection, 'DELETE', '/v1/databases/'.rawurlencode($dbName), 'DB_NOT_FOUND');
    }

    public function deleteWebroot(AgentConnection $connection, string $eleveLogin, string $projetSlug): void
    {
        $this->requestIgnoringNotFound(
            $connection,
            'DELETE',
            '/v1/webroots',
            'WEBROOT_NOT_FOUND',
            ['eleveLogin' => $eleveLogin, 'projetSlug' => $projetSlug],
        );
    }

    /**
     * Une suppression d'une ressource déjà absente n'est pas une erreur du
     * point de vue de l'appelant : c'est l'essence même de l'idempotence
     * d'une opération DELETE (voir ProjectDeprovisioningOrchestrator).
     */
    private function requestIgnoringNotFound(AgentConnection $connection, string $method, string $path, string $notFoundErrorCode, ?array $jsonBody = null): void
    {
        try {
            $this->request($connection, $method, $path, $jsonBody);
        } catch (AgentException $e) {
            if ($notFoundErrorCode !== $e->errorCode) {
                throw $e;
            }
        }
    }

    private function request(AgentConnection $connection, string $method, string $path, ?array $jsonBody = null): array
    {
        $url = rtrim($connection->getBaseUrl(), '/').$path;
        $token = $this->tokenEncryptor->decrypt($connection->getBearerTokenEncrypted());

        $options = [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'timeout' => 10,
        ];
        if (null !== $jsonBody) {
            $options['json'] = $jsonBody;
        }

        try {
            $response = $this->httpClient->request($method, $url, $options);
            $statusCode = $response->getStatusCode();
            $body = $response->toArray(false);
        } catch (TransportException $e) {
            throw new AgentException('AGENT_UNREACHABLE', "Impossible de joindre l'agent à \"$url\" : ".$e->getMessage());
        } catch (ExceptionInterface $e) {
            throw new AgentException('AGENT_TRANSPORT_ERROR', $e->getMessage());
        }

        if ($statusCode >= 400) {
            throw new AgentException(
                $body['errorCode'] ?? 'UNKNOWN_ERROR',
                $body['message'] ?? "Erreur agent inconnue (HTTP $statusCode).",
                $statusCode,
            );
        }

        return $body;
    }
}
