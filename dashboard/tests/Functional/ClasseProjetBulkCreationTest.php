<?php

namespace App\Tests\Functional;

use App\Entity\Classe;
use App\Entity\Eleve;
use App\Entity\Etablissement;
use App\Entity\Projet;
use App\Enum\DbEngine;
use App\Enum\SshAuthMethod;
use App\Service\Provisioning\ClassBulkOrchestrator;
use App\Service\Provisioning\CredentialRevealTokenManager;
use App\Service\Provisioning\ProjectDeprovisioningOrchestrator;
use App\Service\Provisioning\ProjectProvisioningOrchestrator;
use App\Service\Provisioning\ProjectSlugSanitizer;
use App\Service\Provisioning\ProvisioningEventRecorder;
use App\Service\Provisioning\SshPublicKeyFingerprintCalculator;
use App\Service\Naming\LoginSanitizer;
use App\Service\Provisioning\CredentialGenerator;
use App\Service\Security\AgentTokenEncryptor;
use App\Tests\Functional\Fixtures\FakeAgentClient;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Répond au trou d'UX signalé : il n'existait aucun moyen de créer un projet
 * pour toute une classe en une fois (seulement élève par élève).
 * ClassBulkOrchestrator::createProjectForAll doit créer le projet pour les
 * élèves qui n'en ont pas déjà un du même nom, et ignorer silencieusement
 * (sans y toucher) ceux qui en ont déjà un.
 */
final class ClasseProjetBulkCreationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ClassBulkOrchestrator $bulkOrchestrator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $fakeAgentClient = new FakeAgentClient();
        $eventRecorder = new ProvisioningEventRecorder($this->entityManager);
        $tokenManager = new CredentialRevealTokenManager(new AgentTokenEncryptor('test-secret'), $this->entityManager);

        $this->bulkOrchestrator = new ClassBulkOrchestrator(
            new ProjectProvisioningOrchestrator(
                $fakeAgentClient,
                new ProjectSlugSanitizer(),
                new LoginSanitizer(),
                new CredentialGenerator(),
                new SshPublicKeyFingerprintCalculator(),
                $eventRecorder,
                $this->entityManager,
            ),
            new ProjectDeprovisioningOrchestrator(
                $fakeAgentClient,
                new ProjectSlugSanitizer(),
                new LoginSanitizer(),
                $eventRecorder,
                $this->entityManager,
            ),
            $tokenManager,
            $this->entityManager,
        );
    }

    public function testCreatesProjectOnlyForStudentsWithoutOneOfTheSameName(): void
    {
        $classe = $this->createClasseWithThreeStudents();
        $classe = $this->reloadClasse($classe);

        // Sophie a déjà un projet "site-vitrine" avant l'action de masse.
        $sophie = null;
        foreach ($classe->getEleves() as $eleve) {
            if ('sophie1' === $eleve->getLogin()) {
                $sophie = $eleve;
            }
        }
        $existant = new Projet(DbEngine::Mysql);
        $existant->setEleve($sophie)->setNom('site-vitrine');
        $this->entityManager->persist($existant);
        $this->entityManager->flush();

        $classe = $this->reloadClasse($classe);

        $resultats = $this->bulkOrchestrator->createProjectForAll($classe, 'site-vitrine', SshAuthMethod::Password);

        self::assertCount(3, $resultats);

        $parLogin = [];
        foreach ($resultats as $r) {
            $parLogin[$r['eleve']->getLogin()] = $r['created'];
        }

        self::assertTrue($parLogin['jean2']);
        self::assertTrue($parLogin['lucas3']);
        self::assertFalse($parLogin['sophie1'], "Sophie avait déjà un projet du même nom, il ne doit pas être dupliqué.");

        // Le projet existant de Sophie n'a pas été touché (toujours un seul).
        $classe = $this->reloadClasse($classe);
        foreach ($classe->getEleves() as $eleve) {
            if ('sophie1' === $eleve->getLogin()) {
                self::assertCount(1, $eleve->getProjets());
            } else {
                self::assertCount(1, $eleve->getProjets());
            }
        }
    }

    private function createClasseWithThreeStudents(): Classe
    {
        $etablissement = new Etablissement();
        $etablissement->setNom('Lycée de Test')->setDbEngine(DbEngine::Mysql)->setWebRootBase('/var/www/html');
        $this->entityManager->persist($etablissement);

        $classe = new Classe();
        $classe->setEtablissement($etablissement)->setNom('BTS SIO SLAM 2')->setAnneeScolaire('2025-2026');
        $this->entityManager->persist($classe);

        foreach (['jean2', 'sophie1', 'lucas3'] as $index => $login) {
            $eleve = new Eleve();
            $eleve->setClasse($classe)->setNom('Nom'.$index)->setPrenom('Prenom'.$index)->setLogin($login);
            $this->entityManager->persist($eleve);
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
