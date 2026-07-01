<?php

namespace App\Tests\Functional;

use App\Entity\Eleve;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie le parcours complet de la Phase 1 (aucun appel à l'agent) :
 * configuration établissement -> gabarit de nommage -> classe -> import CSV
 * d'un lot d'élèves avec doublons -> collisions correctement suffixées.
 */
final class ImportFlowTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    public function testFullImportFlowWithThirtyStudentsAndCollisions(): void
    {
        $client = static::createClient();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        // 1) Configuration de l'établissement.
        $client->request('GET', '/etablissement/configurer');
        $client->submitForm('Enregistrer', [
            'etablissement[nom]' => 'Lycée de Test',
            'etablissement[dbEngine]' => 'mysql',
            'etablissement[webRootBase]' => '/var/www/html',
        ]);
        $this->assertResponseRedirects('/etablissement');

        // 2) Gabarit de nommage (premier gabarit -> activé automatiquement).
        $client->request('GET', '/etablissement/gabarits/nouveau');
        $client->submitForm('Enregistrer', [
            'naming_pattern[label]' => 'prenom.nom',
            'naming_pattern[template]' => '{prenom}.{nom}',
            'naming_pattern[maxLength]' => '32',
        ]);
        $this->assertResponseRedirects('/etablissement');

        // 3) Création d'une classe.
        $client->request('GET', '/classes/nouvelle');
        $client->submitForm('Créer', [
            'classe[nom]' => 'BTS SIO SLAM 2',
            'classe[anneeScolaire]' => '2025-2026',
        ]);
        $this->assertResponseRedirects();
        $classeUrl = $client->getResponse()->headers->get('Location');
        preg_match('#/classes/(\d+)#', $classeUrl, $matches);
        $classeId = (int) $matches[1];

        // 4) Import CSV de 30 élèves, avec deux doublons volontaires (Paul Martin x3).
        $csvPath = tempnam(sys_get_temp_dir(), 'tpmanager_import_').'.csv';
        file_put_contents($csvPath, $this->buildCsvWithThirtyStudents());

        $client->request('GET', "/classes/{$classeId}/import");
        $form = $client->getCrawler()->selectButton("Prévisualiser l'import")->form();
        $form['import_csv[csvFile]']->upload($csvPath);
        $client->submit($form);

        $this->assertResponseIsSuccessful();
        $previewHtml = $client->getResponse()->getContent();
        $this->assertStringContainsString('paul_martin', $previewHtml);
        $this->assertStringContainsString('paul_martin2', $previewHtml);
        $this->assertStringContainsString('paul_martin3', $previewHtml);
        $this->assertStringContainsString('30 élève(s) prêt(s)', $previewHtml);

        // 5) Confirmation de l'import.
        $confirmForm = $client->getCrawler()->selectButton("Confirmer l'import")->form();
        $client->submit($confirmForm);
        $this->assertResponseRedirects("/classes/{$classeId}");

        unlink($csvPath);

        /** @var Eleve[] $eleves */
        $eleves = $this->entityManager->getRepository(Eleve::class)->findAll();
        $this->assertCount(30, $eleves);

        $logins = array_map(static fn (Eleve $e) => $e->getLogin(), $eleves);
        $this->assertContains('paul_martin', $logins);
        $this->assertContains('paul_martin2', $logins);
        $this->assertContains('paul_martin3', $logins);
        $this->assertCount(30, array_unique($logins), 'Tous les logins doivent être uniques.');
    }

    private function buildCsvWithThirtyStudents(): string
    {
        $lines = ['nom;prenom;matricule'];

        // Trois élèves strictement homonymes -> paul_martin, paul_martin2, paul_martin3.
        $lines[] = 'Martin;Paul;';
        $lines[] = 'Martin;Paul;';
        $lines[] = 'Martin;Paul;';

        for ($i = 1; $i <= 27; $i++) {
            $lines[] = sprintf('Nom%d;Prenom%d;M%03d', $i, $i, $i);
        }

        return implode("\n", $lines);
    }
}
