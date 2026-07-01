<?php

namespace App\Tests\Functional;

use App\Entity\AgentConnection;
use App\Entity\Classe;
use App\Entity\Eleve;
use App\Entity\Etablissement;
use App\Entity\Projet;
use App\Enum\DbEngine;
use App\Enum\ProvisioningEventStatus;
use App\Enum\ProvisioningStatus;
use App\Enum\SshAuthMethod;
use App\Service\Naming\LoginSanitizer;
use App\Service\Provisioning\CredentialGenerator;
use App\Service\Provisioning\ProjectProvisioningOrchestrator;
use App\Service\Provisioning\ProjectSlugSanitizer;
use App\Service\Provisioning\ProvisioningEventRecorder;
use App\Service\Provisioning\SshPublicKeyFingerprintCalculator;
use App\Service\Security\AgentTokenEncryptor;
use App\Tests\Functional\Fixtures\FakeAgentClient;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProvisioningOrchestratorTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private FakeAgentClient $fakeAgentClient;
    private ProjectProvisioningOrchestrator $orchestrator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $this->fakeAgentClient = new FakeAgentClient();
        $this->orchestrator = new ProjectProvisioningOrchestrator(
            $this->fakeAgentClient,
            new ProjectSlugSanitizer(),
            new LoginSanitizer(),
            new CredentialGenerator(),
            new SshPublicKeyFingerprintCalculator(),
            new ProvisioningEventRecorder($this->entityManager),
            $this->entityManager,
        );
    }

    public function testSuccessfulProvisioningMarksProjectProvisionedAndLogsEvents(): void
    {
        $projet = $this->createProjetPourNouvelEleve('dupont2', 'site-vitrine');
        $projet = $this->reloadFromDatabase($projet);

        $result = $this->orchestrator->provision($projet);

        self::assertTrue($result->success);
        self::assertNotNull($result->linuxPassword);
        self::assertNotNull($result->dbPassword);

        self::assertSame(ProvisioningStatus::Provisioned, $projet->getProvisioningStatus());
        self::assertSame('dupont2', $projet->getLinuxUsername());
        self::assertSame('dupont2_site_vitrine', $projet->getDbName());
        self::assertSame('/var/www/html/dupont2/site-vitrine', $projet->getWebPath());

        $events = $projet->getProvisioningEvents();
        self::assertCount(6, $events); // 3 étapes x (started + succeeded)
        self::assertCount(3, $this->fakeAgentClient->calls);
    }

    public function testFailureAtDatabaseStepMarksProjectFailedAndStopsBeforeWebroot(): void
    {
        $this->fakeAgentClient->failAtStep = 'database';
        $projet = $this->createProjetPourNouvelEleve('martin3', 'app-web');
        $projet = $this->reloadFromDatabase($projet);

        $result = $this->orchestrator->provision($projet);

        self::assertFalse($result->success);
        self::assertSame(ProvisioningStatus::Failed, $projet->getProvisioningStatus());
        self::assertStringContainsString('database', $projet->getProvisioningError());

        $steps = array_map(static fn ($call) => $call['step'], $this->fakeAgentClient->calls);
        self::assertSame(['linux_account', 'database'], $steps, 'Le webroot ne doit jamais être appelé après un échec BDD.');

        $statuses = array_map(static fn ($event) => $event->getStatus(), $projet->getProvisioningEvents()->toArray());
        self::assertContains(ProvisioningEventStatus::Succeeded, $statuses);
        self::assertContains(ProvisioningEventStatus::Failed, $statuses);
    }

    public function testPublicKeyProvisioningComputesFingerprintAndReturnsNoLinuxPassword(): void
    {
        $projet = $this->createProjetPourNouvelEleve('amelie4', 'app-mobile', SshAuthMethod::PublicKey);
        $projet = $this->reloadFromDatabase($projet);

        $publicKey = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIBXTGO1n9VfMbDx0GkgHSeqQzHLKzGRLvfhTdMga3rHV eleve@laptop';
        $result = $this->orchestrator->provision($projet, $publicKey);

        self::assertTrue($result->success);
        self::assertNull($result->linuxPassword);
        self::assertNotNull($result->dbPassword);
        self::assertStringStartsWith('SHA256:', $projet->getSshPublicKeyFingerprint());
    }

    public function testPublicKeyProvisioningFailsFastWithoutCallingAgentWhenKeyMissing(): void
    {
        $projet = $this->createProjetPourNouvelEleve('amelie5', 'app-mobile', SshAuthMethod::PublicKey);
        $projet = $this->reloadFromDatabase($projet);

        $result = $this->orchestrator->provision($projet, null);

        self::assertFalse($result->success);
        self::assertCount(0, $this->fakeAgentClient->calls, "L'agent ne doit pas être appelé si la clé publique manque.");
    }

    private function createProjetPourNouvelEleve(string $login, string $projetNom, SshAuthMethod $sshAuthMethod = SshAuthMethod::Password): Projet
    {
        $etablissement = new Etablissement();
        $etablissement->setNom('Lycée de Test')->setDbEngine(DbEngine::Mysql)->setWebRootBase('/var/www/html');
        $this->entityManager->persist($etablissement);

        $connection = new AgentConnection();
        $connection->setEtablissement($etablissement);
        $connection->setBaseUrl('https://fake-vm.local:8000');
        $connection->setBearerTokenEncrypted((new AgentTokenEncryptor('test-secret'))->encrypt('test-token'));
        $this->entityManager->persist($connection);

        $classe = new Classe();
        $classe->setEtablissement($etablissement)->setNom('BTS SIO SLAM 2')->setAnneeScolaire('2025-2026');
        $this->entityManager->persist($classe);

        $eleve = new Eleve();
        $eleve->setClasse($classe)->setNom('Dupont')->setPrenom('Jean')->setLogin($login);
        $this->entityManager->persist($eleve);

        $projet = new Projet(DbEngine::Mysql);
        $projet->setEleve($eleve)->setNom($projetNom)->setSshAuthMethod($sshAuthMethod);
        $this->entityManager->persist($projet);

        $this->entityManager->flush();

        return $projet;
    }

    /**
     * Après un flush initial, les associations inverses (ex: Etablissement <-
     * AgentConnection) ne sont pas synchronisées en mémoire côté PHP — seule
     * la relecture depuis la base (après clear()) donne un graphe cohérent,
     * comme ce serait le cas sur une vraie requête HTTP suivante.
     */
    private function reloadFromDatabase(Projet $projet): Projet
    {
        $id = $projet->getId();
        $this->entityManager->clear();

        return $this->entityManager->getRepository(Projet::class)->find($id);
    }
}
