<?php

namespace App\Tests\Contract;

use App\Entity\AgentConnection;
use App\Service\Agent\AgentHttpClient;
use App\Service\Agent\Dto\DatabasePasswordResetRequest;
use App\Service\Agent\Dto\DatabaseRequest;
use App\Service\Agent\Dto\LinuxAccountPasswordResetRequest;
use App\Service\Agent\Dto\LinuxAccountRequest;
use App\Service\Agent\Dto\WebrootRequest;
use App\Service\Security\AgentTokenEncryptor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Yaml\Yaml;

/**
 * Vérifie que les requêtes envoyées par AgentHttpClient (côté PHP) contiennent
 * bien tous les champs requis par contracts/openapi.yaml (côté source de
 * vérité), et que les réponses simulées sont correctement désérialisées en DTO.
 */
final class AgentHttpClientContractTest extends TestCase
{
    private const CONTRACT_PATH = __DIR__.'/../../../contracts/openapi.yaml';

    public function testCreateLinuxAccountSendsAllRequiredContractFields(): void
    {
        $captured = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured) {
            $captured = $options;
            self::assertSame('https://tp-vm.local:8000/v1/linux-accounts', $url);

            return new MockResponse(json_encode([
                'username' => 'dupont2', 'uid' => 2001, 'homeDir' => '/var/www/html/dupont2', 'status' => 'created',
            ]), ['http_code' => 201]);
        });

        $client = new AgentHttpClient($httpClient, new AgentTokenEncryptor('test-secret'));
        $response = $client->createLinuxAccount(
            $this->makeConnection(),
            new LinuxAccountRequest('req-1', 'dupont2', '/var/www/html/dupont2', 'password', 'secret'),
        );

        $this->assertHeadersContainBearerToken($captured);
        $this->assertRequestBodyHasRequiredFields($captured, 'LinuxAccountCreateRequest');
        $this->assertResponseHasRequiredFields($response, 'LinuxAccountResponse');
    }

    public function testCreateDatabaseSendsAllRequiredContractFields(): void
    {
        $captured = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured) {
            $captured = $options;
            self::assertSame('https://tp-vm.local:8000/v1/databases', $url);

            return new MockResponse(json_encode([
                'engine' => 'mysql', 'dbName' => 'dupont2_sitevitrine', 'dbUser' => 'dupont2_sitevitrine',
                'grantsScope' => 'database-only', 'status' => 'created',
            ]), ['http_code' => 201]);
        });

        $client = new AgentHttpClient($httpClient, new AgentTokenEncryptor('test-secret'));
        $response = $client->createDatabase(
            $this->makeConnection(),
            new DatabaseRequest('req-2', 'dupont2_sitevitrine', 'dupont2_sitevitrine', 'secret'),
        );

        $this->assertRequestBodyHasRequiredFields($captured, 'DatabaseCreateRequest');
        $this->assertResponseHasRequiredFields($response, 'DatabaseResponse');
    }

    public function testCreateWebrootSendsAllRequiredContractFields(): void
    {
        $captured = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured) {
            $captured = $options;
            self::assertSame('https://tp-vm.local:8000/v1/webroots', $url);

            return new MockResponse(json_encode([
                'path' => '/var/www/html/dupont2/site-vitrine', 'owner' => 'dupont2', 'group' => 'www-data',
                'mode' => '2750', 'status' => 'created',
            ]), ['http_code' => 201]);
        });

        $client = new AgentHttpClient($httpClient, new AgentTokenEncryptor('test-secret'));
        $response = $client->createWebroot(
            $this->makeConnection(),
            new WebrootRequest('req-3', 'dupont2', 'site-vitrine', 'dupont2', 'www-data'),
        );

        $this->assertRequestBodyHasRequiredFields($captured, 'WebrootCreateRequest');
        $this->assertResponseHasRequiredFields($response, 'WebrootResponse');
    }

    public function testResetLinuxAccountPasswordSendsAllRequiredContractFields(): void
    {
        $captured = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured) {
            $captured = $options;
            self::assertSame('POST', $method);
            self::assertSame('https://tp-vm.local:8000/v1/linux-accounts/dupont2/reset-password', $url);

            return new MockResponse(json_encode(['username' => 'dupont2', 'status' => 'reset']), ['http_code' => 200]);
        });

        $client = new AgentHttpClient($httpClient, new AgentTokenEncryptor('test-secret'));
        $client->resetLinuxAccountPassword(
            $this->makeConnection(),
            'dupont2',
            new LinuxAccountPasswordResetRequest('req-5', 'password', 'new-secret'),
        );

        $this->assertRequestBodyHasRequiredFields($captured, 'LinuxAccountPasswordResetRequest');
    }

    public function testResetLinuxAccountPasswordNotFoundIsMappedToAgentException(): void
    {
        $httpClient = new MockHttpClient(fn () => new MockResponse(
            json_encode(['errorCode' => 'USER_NOT_FOUND', 'message' => 'Utilisateur introuvable.']),
            ['http_code' => 404],
        ));

        $client = new AgentHttpClient($httpClient, new AgentTokenEncryptor('test-secret'));

        try {
            $client->resetLinuxAccountPassword($this->makeConnection(), 'dupont2', new LinuxAccountPasswordResetRequest('req-6', 'password', 'new-secret'));
            self::fail('Une AgentException était attendue.');
        } catch (\App\Service\Agent\AgentException $e) {
            self::assertSame('USER_NOT_FOUND', $e->errorCode);
            self::assertSame(404, $e->getHttpStatusCode());
        }
    }

    public function testResetDatabasePasswordSendsAllRequiredContractFields(): void
    {
        $captured = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured) {
            $captured = $options;
            self::assertSame('POST', $method);
            self::assertSame('https://tp-vm.local:8000/v1/databases/dupont2_sitevitrine/reset-password', $url);

            return new MockResponse(json_encode([
                'dbName' => 'dupont2_sitevitrine', 'dbUser' => 'dupont2_sitevitrine', 'status' => 'reset',
            ]), ['http_code' => 200]);
        });

        $client = new AgentHttpClient($httpClient, new AgentTokenEncryptor('test-secret'));
        $client->resetDatabasePassword(
            $this->makeConnection(),
            'dupont2_sitevitrine',
            new DatabasePasswordResetRequest('req-7', 'new-secret'),
        );

        $this->assertRequestBodyHasRequiredFields($captured, 'DatabasePasswordResetRequest');
    }

    public function testResetDatabasePasswordNotFoundIsMappedToAgentException(): void
    {
        $httpClient = new MockHttpClient(fn () => new MockResponse(
            json_encode(['errorCode' => 'DB_NOT_FOUND', 'message' => 'Base introuvable.']),
            ['http_code' => 404],
        ));

        $client = new AgentHttpClient($httpClient, new AgentTokenEncryptor('test-secret'));

        try {
            $client->resetDatabasePassword($this->makeConnection(), 'dupont2_sitevitrine', new DatabasePasswordResetRequest('req-8', 'new-secret'));
            self::fail('Une AgentException était attendue.');
        } catch (\App\Service\Agent\AgentException $e) {
            self::assertSame('DB_NOT_FOUND', $e->errorCode);
            self::assertSame(404, $e->getHttpStatusCode());
        }
    }

    public function testErrorResponseIsMappedToAgentException(): void
    {
        $httpClient = new MockHttpClient(fn () => new MockResponse(
            json_encode(['errorCode' => 'INVALID_IDENTIFIER', 'message' => 'Identifiant invalide.']),
            ['http_code' => 422],
        ));

        $client = new AgentHttpClient($httpClient, new AgentTokenEncryptor('test-secret'));

        try {
            $client->createWebroot($this->makeConnection(), new WebrootRequest('req-4', 'x', 'y', 'x', 'www-data'));
            self::fail('Une AgentException était attendue.');
        } catch (\App\Service\Agent\AgentException $e) {
            self::assertSame('INVALID_IDENTIFIER', $e->errorCode);
            self::assertSame(422, $e->getHttpStatusCode());
        }
    }

    private function makeConnection(): AgentConnection
    {
        $encryptor = new AgentTokenEncryptor('test-secret');
        $connection = new AgentConnection();
        $connection->setBaseUrl('https://tp-vm.local:8000');
        $connection->setBearerTokenEncrypted($encryptor->encrypt('test-token'));

        return $connection;
    }

    private function assertHeadersContainBearerToken(array $options): void
    {
        $headers = $options['headers'] ?? [];
        $flat = is_array($headers)
            ? implode(' ', array_map(static fn ($h) => is_array($h) ? implode(' ', $h) : (string) $h, $headers))
            : (string) $headers;

        self::assertStringContainsString('test-token', $flat, "L'en-tête Authorization Bearer n'a pas été envoyé.");
    }

    private function assertRequestBodyHasRequiredFields(array $options, string $schemaName): void
    {
        $body = json_decode((string) ($options['body'] ?? '{}'), true);

        foreach ($this->requiredFields($schemaName) as $field) {
            self::assertArrayHasKey($field, $body, "Champ requis \"$field\" absent de la requête envoyée (schéma $schemaName).");
        }
    }

    private function assertResponseHasRequiredFields(object $response, string $schemaName): void
    {
        foreach ($this->requiredFields($schemaName) as $field) {
            self::assertTrue(property_exists($response, $field), "Champ requis \"$field\" absent du DTO de réponse (schéma $schemaName).");
        }
    }

    private function requiredFields(string $schemaName): array
    {
        $spec = Yaml::parseFile(self::CONTRACT_PATH);

        return $spec['components']['schemas'][$schemaName]['required'] ?? [];
    }
}
