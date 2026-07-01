<?php

namespace App\Tests\Functional;

use App\Entity\Classe;
use App\Entity\Eleve;
use App\Entity\Etablissement;
use App\Entity\Projet;
use App\Enum\DbEngine;
use App\Repository\CredentialRevealRepository;
use App\Service\Provisioning\CredentialRevealTokenManager;
use App\Service\Security\AgentTokenEncryptor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CredentialRevealTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CredentialRevealTokenManager $tokenManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $this->tokenManager = new CredentialRevealTokenManager(new AgentTokenEncryptor('test-secret'), $this->entityManager);
    }

    public function testSecretCanBeRevealedExactlyOnce(): void
    {
        $projet = $this->createProjet();

        $reveal = $this->tokenManager->create($projet, ['linuxPassword' => 'AAAA1111', 'dbPassword' => 'BBBB2222']);

        $firstRead = $this->tokenManager->reveal($reveal);
        self::assertSame(['linuxPassword' => 'AAAA1111', 'dbPassword' => 'BBBB2222'], $firstRead);
        self::assertTrue($reveal->isViewed());
        self::assertNull($reveal->getSecretCiphertext(), 'Le ciphertext doit être effacé après consultation.');

        $secondRead = $this->tokenManager->reveal($reveal);
        self::assertNull($secondRead, 'Une deuxième consultation ne doit rien renvoyer.');
    }

    public function testPurgeCommandRemovesOnlyExpiredAndUnviewedReveals(): void
    {
        $projet = $this->createProjet();

        $expiredUnviewed = $this->tokenManager->create($projet, ['linuxPassword' => 'X', 'dbPassword' => 'Y']);
        $this->forceExpiry($expiredUnviewed, new \DateTimeImmutable('-1 hour'));

        $stillValid = $this->tokenManager->create($projet, ['linuxPassword' => 'X2', 'dbPassword' => 'Y2']);

        $expiredButViewed = $this->tokenManager->create($projet, ['linuxPassword' => 'X3', 'dbPassword' => 'Y3']);
        $this->tokenManager->reveal($expiredButViewed);
        $this->forceExpiry($expiredButViewed, new \DateTimeImmutable('-1 hour'));

        // Capturés avant suppression : Doctrine remet l'id à null en mémoire
        // sur une entité supprimée après un flush() réussi.
        $expiredUnviewedId = $expiredUnviewed->getId();
        $stillValidId = $stillValid->getId();
        $expiredButViewedId = $expiredButViewed->getId();

        $repository = self::getContainer()->get(CredentialRevealRepository::class);
        $expired = $repository->findExpiredAndUnviewed();

        self::assertCount(1, $expired);
        self::assertSame($expiredUnviewedId, $expired[0]->getId());

        foreach ($expired as $reveal) {
            $this->entityManager->remove($reveal);
        }
        $this->entityManager->flush();

        self::assertNull($repository->find($expiredUnviewedId));
        self::assertNotNull($repository->find($stillValidId));
        self::assertNotNull($repository->find($expiredButViewedId));
    }

    private function forceExpiry(\App\Entity\CredentialReveal $reveal, \DateTimeImmutable $expiresAt): void
    {
        $reflection = new \ReflectionProperty($reveal, 'expiresAt');
        $reflection->setValue($reveal, $expiresAt);
        $this->entityManager->flush();
    }

    private function createProjet(): Projet
    {
        $etablissement = new Etablissement();
        $etablissement->setNom('Lycée de Test')->setDbEngine(DbEngine::Mysql)->setWebRootBase('/var/www/html');
        $this->entityManager->persist($etablissement);

        $classe = new Classe();
        $classe->setEtablissement($etablissement)->setNom('BTS SIO SLAM 2')->setAnneeScolaire('2025-2026');
        $this->entityManager->persist($classe);

        $eleve = new Eleve();
        $eleve->setClasse($classe)->setNom('Dupont')->setPrenom('Jean')->setLogin('dupont2');
        $this->entityManager->persist($eleve);

        $projet = new Projet(DbEngine::Mysql);
        $projet->setEleve($eleve)->setNom('site-vitrine');
        $this->entityManager->persist($projet);

        $this->entityManager->flush();

        return $projet;
    }
}
