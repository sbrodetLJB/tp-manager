<?php

namespace App\Tests\Functional;

use App\Entity\AgentConnection;
use App\Entity\Etablissement;
use App\Service\Agent\AgentHttpClient;
use App\Tests\Functional\Fixtures\FakeAgentClient;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie que l'assistant de configuration initiale (2 étapes) ne persiste
 * RIEN si la vérification de l'agent échoue, et enregistre établissement +
 * agent ensemble seulement en cas de succès — voir SetupWizardController.
 */
final class SetupWizardTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    private function bootWithFakeAgent(FakeAgentClient $fake): KernelBrowser
    {
        $client = static::createClient();
        // Le client reboote le kernel (donc reconstruit le container, en
        // perdant l'override ci-dessous) avant chaque requête par défaut.
        $client->disableReboot();
        self::getContainer()->set(AgentHttpClient::class, $fake);

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        return $client;
    }

    private function submitStepOne(KernelBrowser $client): void
    {
        $client->request('GET', '/configuration/etablissement');
        $client->submitForm('Continuer →', [
            'etablissement[nom]' => 'Lycée de Test',
            'etablissement[dbEngine]' => 'mysql',
            'etablissement[webRootBase]' => '/var/www/html',
        ]);
        $this->assertResponseRedirects('/configuration/agent');
        $client->followRedirect();
    }

    public function testUnreachableAgentPersistsNothing(): void
    {
        $fake = new FakeAgentClient();
        $fake->failGetConfig = true;
        $client = $this->bootWithFakeAgent($fake);

        $this->submitStepOne($client);
        $client->submitForm('Vérifier et terminer', [
            'agent_connection[baseUrl]' => 'https://fake-vm.local:8000',
            'agent_connection[token]' => 'un-jeton-quelconque',
        ]);

        $this->assertResponseIsSuccessful(); // ré-affiche l'étape 2 avec une erreur
        self::assertCount(0, $this->entityManager->getRepository(Etablissement::class)->findAll());
        self::assertCount(0, $this->entityManager->getRepository(AgentConnection::class)->findAll());
    }

    public function testDbEngineMismatchPersistsNothing(): void
    {
        $fake = new FakeAgentClient();
        $fake->configDbEngine = 'postgresql'; // établissement configuré en mysql à l'étape 1
        $client = $this->bootWithFakeAgent($fake);

        $this->submitStepOne($client);
        $client->submitForm('Vérifier et terminer', [
            'agent_connection[baseUrl]' => 'https://fake-vm.local:8000',
            'agent_connection[token]' => 'un-jeton-quelconque',
        ]);

        $this->assertResponseIsSuccessful();
        self::assertCount(0, $this->entityManager->getRepository(Etablissement::class)->findAll());
        self::assertCount(0, $this->entityManager->getRepository(AgentConnection::class)->findAll());
    }

    public function testSuccessfulVerificationPersistsEtablissementAndAgentTogether(): void
    {
        $fake = new FakeAgentClient();
        $client = $this->bootWithFakeAgent($fake);

        $this->submitStepOne($client);
        $client->submitForm('Vérifier et terminer', [
            'agent_connection[baseUrl]' => 'https://fake-vm.local:8000',
            'agent_connection[token]' => 'un-bon-jeton',
        ]);

        $this->assertResponseRedirects('/etablissement');

        $etablissements = $this->entityManager->getRepository(Etablissement::class)->findAll();
        self::assertCount(1, $etablissements);

        $connections = $this->entityManager->getRepository(AgentConnection::class)->findAll();
        self::assertCount(1, $connections);
        self::assertSame($etablissements[0]->getId(), $connections[0]->getEtablissement()->getId());
    }
}
