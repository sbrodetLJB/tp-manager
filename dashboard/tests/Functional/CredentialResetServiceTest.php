<?php

namespace App\Tests\Functional;

use App\Entity\AgentConnection;
use App\Entity\Classe;
use App\Entity\Eleve;
use App\Entity\Etablissement;
use App\Entity\Projet;
use App\Enum\DbEngine;
use App\Enum\ProvisioningStatus;
use App\Enum\SshAuthMethod;
use App\Service\Naming\LoginSanitizer;
use App\Service\Provisioning\CredentialGenerator;
use App\Service\Provisioning\ProjectCredentialResetService;
use App\Service\Provisioning\ProjectProvisioningOrchestrator;
use App\Service\Provisioning\ProjectSlugSanitizer;
use App\Service\Provisioning\ProvisioningEventRecorder;
use App\Service\Provisioning\SshPublicKeyFingerprintCalculator;
use App\Service\Security\AgentTokenEncryptor;
use App\Tests\Functional\Fixtures\FakeAgentClient;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Vérifie que la réinitialisation d'identifiants ne modifie jamais
 * linuxUsername/dbName/webPath (contrairement au déprovisioning) et refuse
 * de s'exécuter sur un projet qui n'est pas provisionné.
 */
final class CredentialResetServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private FakeAgentClient $fakeAgentClient;
    private ProjectProvisioningOrchestrator $orchestrator;
    private ProjectCredentialResetService $resetService;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $this->fakeAgentClient = new FakeAgentClient();
        $eventRecorder = new ProvisioningEventRecorder($this->entityManager);

        $this->orchestrator = new ProjectProvisioningOrchestrator(
            $this->fakeAgentClient,
            new ProjectSlugSanitizer(),
            new LoginSanitizer(),
            new CredentialGenerator(),
            new SshPublicKeyFingerprintCalculator(),
            $eventRecorder,
            $this->entityManager,
        );

        $this->resetService = new ProjectCredentialResetService(
            $this->fakeAgentClient,
            new CredentialGenerator(),
            new SshPublicKeyFingerprintCalculator(),
            $eventRecorder,
            $this->entityManager,
        );
    }

    public function testResetGeneratesNewPasswordsWithoutChangingAssignedTargets(): void
    {
        $projet = $this->createProvisionedProjet('dupont2', 'site-vitrine');
        $linuxUsernameAvant = $projet->getLinuxUsername();
        $dbNameAvant = $projet->getDbName();
        $webPathAvant = $projet->getWebPath();

        $this->fakeAgentClient->calls = [];
        $result = $this->resetService->reset($projet);

        self::assertTrue($result->success);
        self::assertNotNull($result->linuxPassword);
        self::assertNotNull($result->dbPassword);

        self::assertSame(ProvisioningStatus::Provisioned, $projet->getProvisioningStatus());
        self::assertSame($linuxUsernameAvant, $projet->getLinuxUsername());
        self::assertSame($dbNameAvant, $projet->getDbName());
        self::assertSame($webPathAvant, $projet->getWebPath());

        self::assertCount(1, $this->fakeAgentClient->resetLinuxAccountCalls);
        self::assertCount(1, $this->fakeAgentClient->resetDatabaseCalls);
        self::assertSame($linuxUsernameAvant, $this->fakeAgentClient->resetLinuxAccountCalls[0]['username']);
        self::assertSame($dbNameAvant, $this->fakeAgentClient->resetDatabaseCalls[0]['dbName']);
    }

    public function testResetRefusesToRunOnAProjectThatIsNotProvisioned(): void
    {
        $projet = $this->createProjetPourNouvelEleve('martin3', 'app-web');
        $projet = $this->reloadFromDatabase($projet);

        $result = $this->resetService->reset($projet);

        self::assertFalse($result->success);
        self::assertCount(0, $this->fakeAgentClient->resetLinuxAccountCalls);
        self::assertCount(0, $this->fakeAgentClient->resetDatabaseCalls);
    }

    public function testResetSurfacesNotFoundAsFailureInsteadOfThrowing(): void
    {
        $projet = $this->createProvisionedProjet('amelie4', 'app-mobile');

        $this->fakeAgentClient->failResetWithNotFound = true;
        $result = $this->resetService->reset($projet);

        self::assertFalse($result->success);
        self::assertStringContainsString('introuvable', $result->errorMessage);
    }

    public function testPublicKeyResetFailsFastWithoutCallingAgentWhenKeyMissing(): void
    {
        $projet = $this->createProvisionedProjet('amelie5', 'app-mobile', SshAuthMethod::PublicKey);

        $result = $this->resetService->reset($projet, null);

        self::assertFalse($result->success);
        self::assertCount(0, $this->fakeAgentClient->resetLinuxAccountCalls);
    }

    public function testPublicKeyResetRecomputesFingerprintAndReturnsNoLinuxPassword(): void
    {
        $projet = $this->createProvisionedProjet('amelie6', 'app-mobile', SshAuthMethod::PublicKey);
        $ancienneEmpreinte = $projet->getSshPublicKeyFingerprint();

        $nouvelleCle = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIKZftw+8ZmVoHVJVRbVjSNsQ9v3v/aTfIudydeVdd93z eleve@nouveau-pc';
        $result = $this->resetService->reset($projet, $nouvelleCle);

        self::assertTrue($result->success);
        self::assertNull($result->linuxPassword);
        self::assertNotNull($result->dbPassword);
        self::assertNotSame($ancienneEmpreinte, $projet->getSshPublicKeyFingerprint());
    }

    private function createProvisionedProjet(string $login, string $projetNom, SshAuthMethod $sshAuthMethod = SshAuthMethod::Password): Projet
    {
        $projet = $this->createProjetPourNouvelEleve($login, $projetNom, $sshAuthMethod);
        $projet = $this->reloadFromDatabase($projet);

        $publicKey = SshAuthMethod::PublicKey === $sshAuthMethod
            ? 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIBXTGO1n9VfMbDx0GkgHSeqQzHLKzGRLvfhTdMga3rHV eleve@laptop'
            : null;

        $result = $this->orchestrator->provision($projet, $publicKey);
        self::assertTrue($result->success, "Préparation du test : le provisioning initial a échoué ({$result->errorMessage}).");

        return $projet;
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

    private function reloadFromDatabase(Projet $projet): Projet
    {
        $id = $projet->getId();
        $this->entityManager->clear();

        return $this->entityManager->getRepository(Projet::class)->find($id);
    }
}
