<?php

namespace App\Tests\Functional;

use App\Entity\AgentConnection;
use App\Entity\Classe;
use App\Entity\Eleve;
use App\Entity\Etablissement;
use App\Entity\Projet;
use App\Enum\DbEngine;
use App\Service\Agent\AgentHttpClient;
use App\Service\Security\AgentTokenEncryptor;
use App\Tests\Functional\Fixtures\FakeAgentClient;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie la suppression d'un projet depuis ProjetController::supprimer() :
 * un projet jamais provisionné est simplement retiré de la base, un projet
 * avec des ressources encore assignées est d'abord déprovisionné (via le
 * même orchestrateur idempotent que "Forcer le nettoyage"), et un échec de
 * ce nettoyage préalable annule la suppression plutôt que de laisser des
 * ressources orphelines côté agent.
 */
final class ProjetDeletionTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private FakeAgentClient $fakeAgentClient;

    private function bootWithFakeAgent(): KernelBrowser
    {
        $client = static::createClient();
        // Le client reboote le kernel (donc reconstruit le container, en
        // perdant l'override ci-dessous) avant chaque requête par défaut.
        $client->disableReboot();

        $this->fakeAgentClient = new FakeAgentClient();
        self::getContainer()->set(AgentHttpClient::class, $this->fakeAgentClient);

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        return $client;
    }

    public function testDeletingAPendingProjetWithoutTargetsRemovesItDirectly(): void
    {
        $client = $this->bootWithFakeAgent();
        [$projet, $eleveId] = $this->createPendingProjetNeverAssigned('sophie6', 'blog');

        $this->submitDelete($client, $projet);

        self::assertResponseRedirects("/eleves/{$eleveId}");
        self::assertSame([], $this->fakeAgentClient->calls, "Aucun appel agent n'est attendu pour un projet jamais assigné.");
        self::assertNull($this->entityManager->getRepository(Projet::class)->find($projet->getId()));
    }

    public function testDeletingAProvisionedProjetDeprovisionsFirstThenRemovesIt(): void
    {
        $client = $this->bootWithFakeAgent();
        [$projet, $eleveId] = $this->createProvisionedProjet('dupont2', 'site-vitrine');

        $this->submitDelete($client, $projet);

        self::assertResponseRedirects("/eleves/{$eleveId}");
        $steps = array_map(static fn ($call) => $call['step'], $this->fakeAgentClient->calls);
        self::assertSame(['webroot', 'database', 'linux_account'], $steps, 'Le nettoyage doit se faire avant la suppression de la fiche.');
        self::assertNull($this->entityManager->getRepository(Projet::class)->find($projet->getId()));
    }

    public function testFailedCleanupCancelsTheDeletion(): void
    {
        $client = $this->bootWithFakeAgent();
        $this->fakeAgentClient->failAtStep = 'database';
        [$projet, ] = $this->createProvisionedProjet('martin3', 'app-web');
        $projetId = $projet->getId();

        $this->submitDelete($client, $projet);

        self::assertResponseRedirects("/projets/{$projetId}");
        self::assertNotNull($this->entityManager->getRepository(Projet::class)->find($projetId), 'Le projet doit survivre à un échec du nettoyage préalable.');
    }

    private function submitDelete(KernelBrowser $client, Projet $projet): void
    {
        $client->request('GET', "/projets/{$projet->getId()}");
        $client->submitForm('Supprimer le projet');
    }

    /**
     * @return array{0: Projet, 1: int}
     */
    private function createProvisionedProjet(string $login, string $projetNom): array
    {
        [$projet, $eleveId] = $this->createPendingProjetNeverAssigned($login, $projetNom);
        $projet->assignProvisioningTargets($login, $login.'_'.str_replace('-', '_', $projetNom), $login.'_'.str_replace('-', '_', $projetNom), "/var/www/html/$login/$projetNom");
        $projet->markProvisioned();
        $this->entityManager->flush();

        return [$projet, $eleveId];
    }

    /**
     * @return array{0: Projet, 1: int}
     */
    private function createPendingProjetNeverAssigned(string $login, string $projetNom): array
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

        return [$projet, $eleve->getId()];
    }
}
