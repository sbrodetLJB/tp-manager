<?php

namespace App\Tests\Functional;

use App\Entity\AgentConnection;
use App\Entity\Classe;
use App\Entity\Eleve;
use App\Entity\Etablissement;
use App\Entity\Projet;
use App\Enum\DbEngine;
use App\Enum\ProvisioningStatus;
use App\Service\Naming\LoginSanitizer;
use App\Service\Provisioning\ProjectDeprovisioningOrchestrator;
use App\Service\Provisioning\ProjectSlugSanitizer;
use App\Service\Provisioning\ProvisioningEventRecorder;
use App\Service\Security\AgentTokenEncryptor;
use App\Tests\Functional\Fixtures\FakeAgentClient;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DeprovisioningOrchestratorTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private FakeAgentClient $fakeAgentClient;
    private ProjectDeprovisioningOrchestrator $orchestrator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $this->fakeAgentClient = new FakeAgentClient();
        $this->orchestrator = new ProjectDeprovisioningOrchestrator(
            $this->fakeAgentClient,
            new ProjectSlugSanitizer(),
            new LoginSanitizer(),
            new ProvisioningEventRecorder($this->entityManager),
            $this->entityManager,
        );
    }

    public function testSuccessfulDeprovisioningClearsTargetsAndMarksDeprovisioned(): void
    {
        $projet = $this->createProvisionedProjet('dupont2', 'site-vitrine');
        $projet = $this->reloadFromDatabase($projet);

        $result = $this->orchestrator->deprovision($projet);

        self::assertTrue($result->success);
        self::assertSame(ProvisioningStatus::Deprovisioned, $projet->getProvisioningStatus());
        self::assertNull($projet->getLinuxUsername());
        self::assertNull($projet->getDbName());
        self::assertNull($projet->getWebPath());
        self::assertNotNull($projet->getDeprovisionedAt());

        $steps = array_map(static fn ($call) => $call['step'], $this->fakeAgentClient->calls);
        self::assertSame(['webroot', 'database', 'linux_account'], $steps, 'Ordre inverse de la création attendu.');
    }

    public function testFailureAtDatabaseStepStopsBeforeLinuxAccountDeletion(): void
    {
        $this->fakeAgentClient->failAtStep = 'database';
        $projet = $this->createProvisionedProjet('martin3', 'app-web');
        $projet = $this->reloadFromDatabase($projet);

        $result = $this->orchestrator->deprovision($projet);

        self::assertFalse($result->success);
        self::assertSame(ProvisioningStatus::Failed, $projet->getProvisioningStatus());
        // Les cibles restent assignées : le nettoyage n'est pas allé jusqu'au bout.
        self::assertNotNull($projet->getLinuxUsername());

        $steps = array_map(static fn ($call) => $call['step'], $this->fakeAgentClient->calls);
        self::assertSame(['webroot', 'database'], $steps);
    }

    public function testForceCleanupOnNeverFullyProvisionedProjectRecomputesTargetsFromEleveLogin(): void
    {
        $projet = $this->createPendingProjetNeverAssigned('sophie6', 'blog');
        $projet = $this->reloadFromDatabase($projet);

        $result = $this->orchestrator->deprovision($projet);

        self::assertTrue($result->success);
        $steps = array_map(static fn ($call) => $call['step'], $this->fakeAgentClient->calls);
        self::assertSame(['webroot', 'database', 'linux_account'], $steps);
    }

    private function createProvisionedProjet(string $login, string $projetNom): Projet
    {
        $projet = $this->createPendingProjetNeverAssigned($login, $projetNom);
        $projet->assignProvisioningTargets($login, $login.'_'.str_replace('-', '_', $projetNom), $login.'_'.str_replace('-', '_', $projetNom), "/var/www/html/$login/$projetNom");
        $projet->markProvisioned();
        $this->entityManager->flush();

        return $projet;
    }

    private function createPendingProjetNeverAssigned(string $login, string $projetNom): Projet
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
        $projet->setEleve($eleve)->setNom($projetNom);
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
