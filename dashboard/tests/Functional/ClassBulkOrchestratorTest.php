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
use App\Service\Provisioning\ClassBulkOrchestrator;
use App\Service\Provisioning\CredentialGenerator;
use App\Service\Provisioning\CredentialRevealTokenManager;
use App\Service\Provisioning\ProjectDeprovisioningOrchestrator;
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
 * Scénario du plan Phase 5 : import d'une classe, provisioning de masse avec
 * un échec injecté sur un projet précis (mysqld tué en plein lot), puis
 * déprovisioning de masse.
 */
final class ClassBulkOrchestratorTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private FakeAgentClient $fakeAgentClient;
    private ClassBulkOrchestrator $bulkOrchestrator;

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
        $tokenManager = new CredentialRevealTokenManager(new AgentTokenEncryptor('test-secret'), $this->entityManager);

        $provisioningOrchestrator = new ProjectProvisioningOrchestrator(
            $this->fakeAgentClient,
            new ProjectSlugSanitizer(),
            new LoginSanitizer(),
            new CredentialGenerator(),
            new SshPublicKeyFingerprintCalculator(),
            $eventRecorder,
            $this->entityManager,
        );
        $deprovisioningOrchestrator = new ProjectDeprovisioningOrchestrator(
            $this->fakeAgentClient,
            new ProjectSlugSanitizer(),
            new LoginSanitizer(),
            $eventRecorder,
            $this->entityManager,
        );

        $this->bulkOrchestrator = new ClassBulkOrchestrator($provisioningOrchestrator, $deprovisioningOrchestrator, $tokenManager, $this->entityManager);
    }

    public function testBulkProvisioningSurfacesPartialFailurePerRowAndSkipsPublicKeyProjects(): void
    {
        $classe = $this->createClasseWithFiveStudents();
        $classe = $this->reloadClasse($classe);

        // "martin3" échoue (mysqld tué en plein lot), les autres réussissent,
        // et "amelie4" (clé publique) est ignoré automatiquement.
        $this->fakeAgentClient->failForDbNames = ['martin3_site'];

        $rows = $this->bulkOrchestrator->provisionAll($classe);

        self::assertCount(5, $rows);

        $byLogin = [];
        foreach ($rows as $row) {
            $byLogin[$row['eleve']->getLogin()] = $row;
        }

        self::assertTrue($byLogin['dupont1']['success']);
        self::assertNotNull($byLogin['dupont1']['revealToken']);

        self::assertFalse($byLogin['martin3']['success']);
        self::assertFalse($byLogin['martin3']['skipped']);
        self::assertStringContainsString('martin3_site', $byLogin['martin3']['message']);

        self::assertTrue($byLogin['amelie4']['skipped']);
        self::assertFalse($byLogin['amelie4']['success']);

        self::assertTrue($byLogin['sophie5']['success']);

        // "Retry" : la panne est résolue, on ne rejoue que sur la classe entière
        // (les projets déjà réussis sont désormais "provisioned", donc ignorés).
        $this->fakeAgentClient->failForDbNames = [];
        $classe = $this->reloadClasse($classe);
        $retryRows = $this->bulkOrchestrator->provisionAll($classe);

        $retryByLogin = [];
        foreach ($retryRows as $row) {
            $retryByLogin[$row['eleve']->getLogin()] = $row;
        }
        self::assertArrayNotHasKey('dupont1', $retryByLogin, 'Déjà provisionné, ne doit pas être rejoué.');
        self::assertTrue($retryByLogin['martin3']['success'], 'Doit réussir une fois la panne résolue.');
    }

    public function testBulkDeprovisioningCleansUpAllProvisionedProjects(): void
    {
        $classe = $this->createClasseWithFiveStudents();
        $classe = $this->reloadClasse($classe);

        $this->bulkOrchestrator->provisionAll($classe);

        $classe = $this->reloadClasse($classe);
        $rows = $this->bulkOrchestrator->deprovisionAll($classe);

        foreach ($rows as $row) {
            self::assertTrue($row['success'], "Le déprovisioning de {$row['eleve']->getLogin()} doit réussir.");
        }

        $classe = $this->reloadClasse($classe);
        foreach ($classe->getEleves() as $eleve) {
            foreach ($eleve->getProjets() as $projet) {
                if (SshAuthMethod::PublicKey === $projet->getSshAuthMethod()) {
                    continue; // jamais provisionné dans ce test, rien à déprovisionner
                }
                self::assertSame(ProvisioningStatus::Deprovisioned, $projet->getProvisioningStatus());
                self::assertNull($projet->getLinuxUsername());
            }
        }
    }

    private function createClasseWithFiveStudents(): Classe
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

        $logins = ['dupont1', 'martin3', 'amelie4', 'sophie5', 'paul6'];
        foreach ($logins as $index => $login) {
            $eleve = new Eleve();
            $eleve->setClasse($classe)->setNom('Nom'.$index)->setPrenom('Prenom'.$index)->setLogin($login);
            $this->entityManager->persist($eleve);

            $projet = new Projet(DbEngine::Mysql);
            $projet->setEleve($eleve)->setNom('site');
            if ('amelie4' === $login) {
                $projet->setSshAuthMethod(SshAuthMethod::PublicKey);
            }
            $this->entityManager->persist($projet);
        }

        $this->entityManager->flush();

        return $classe;
    }

    private function reloadClasse(Classe $classe): Classe
    {
        $id = $classe->getId();
        $this->entityManager->clear();

        return $this->entityManager->getRepository(Classe::class)->find($id);
    }
}
