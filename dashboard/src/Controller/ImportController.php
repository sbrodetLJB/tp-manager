<?php

namespace App\Controller;

use App\Entity\Classe;
use App\Entity\Eleve;
use App\Form\ImportCsvType;
use App\Repository\EleveRepository;
use App\Service\Import\CsvStudentParser;
use App\Service\Import\CsvStudentRow;
use App\Service\Import\InvalidCsvException;
use App\Service\Import\StudentLoginAssigner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/classes/{id}/import')]
class ImportController extends AbstractController
{
    public function __construct(
        private readonly EleveRepository $eleveRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    private function sessionKey(Classe $classe): string
    {
        return sprintf('import_preview_classe_%d', $classe->getId());
    }

    #[Route('', name: 'import_new', methods: ['GET', 'POST'])]
    public function nouveau(Classe $classe, Request $request, CsvStudentParser $parser, StudentLoginAssigner $assigner): Response
    {
        $namingPattern = $classe->getEtablissement()->getActiveNamingPattern();

        if (null === $namingPattern) {
            $this->addFlash('warning', "Créez et activez d'abord un gabarit de nommage.");

            return $this->redirectToRoute('naming_pattern_new');
        }

        $form = $this->createForm(ImportCsvType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $csvFile */
            $csvFile = $form->get('csvFile')->getData();

            try {
                $rows = $parser->parse(file_get_contents($csvFile->getPathname()));
            } catch (InvalidCsvException $e) {
                $this->addFlash('danger', $e->getMessage());

                return $this->redirectToRoute('import_new', ['id' => $classe->getId()]);
            }

            $assignments = $assigner->assign(
                $rows,
                $namingPattern,
                fn (string $login) => $this->eleveRepository->loginExists($login),
            );

            $request->getSession()->set($this->sessionKey($classe), array_map(
                static fn (CsvStudentRow $row) => ['nom' => $row->nom, 'prenom' => $row->prenom, 'matricule' => $row->matricule],
                $rows,
            ));

            return $this->render('import/apercu.html.twig', [
                'classe' => $classe,
                'assignments' => $assignments,
                'namingPattern' => $namingPattern,
            ]);
        }

        return $this->render('import/new.html.twig', [
            'form' => $form,
            'classe' => $classe,
            'namingPattern' => $namingPattern,
        ]);
    }

    #[Route('/confirmer', name: 'import_confirm', methods: ['POST'])]
    public function confirmer(Classe $classe, Request $request, StudentLoginAssigner $assigner): Response
    {
        if (!$this->isCsrfTokenValid('import_confirm_'.$classe->getId(), $request->request->get('_token'))) {
            throw new BadRequestHttpException('Jeton CSRF invalide.');
        }

        $namingPattern = $classe->getEtablissement()->getActiveNamingPattern();
        $stored = $request->getSession()->get($this->sessionKey($classe));

        if (null === $namingPattern || null === $stored) {
            $this->addFlash('danger', "Aucun aperçu d'import en attente pour cette classe.");

            return $this->redirectToRoute('import_new', ['id' => $classe->getId()]);
        }

        $rows = array_map(
            static fn (array $r) => new CsvStudentRow($r['nom'], $r['prenom'], $r['matricule']),
            $stored,
        );

        // Recalculé au moment de la confirmation (et non réutilisé depuis l'aperçu)
        // pour rester correct si l'état de la base a changé entre les deux étapes.
        $assignments = $assigner->assign(
            $rows,
            $namingPattern,
            fn (string $login) => $this->eleveRepository->loginExists($login),
        );

        foreach ($assignments as $assignment) {
            $eleve = new Eleve();
            $eleve->setClasse($classe);
            $eleve->setNom($assignment->row->nom);
            $eleve->setPrenom($assignment->row->prenom);
            $eleve->setMatricule($assignment->row->matricule);
            $eleve->setLogin($assignment->login);
            $eleve->setLoginSuffix($assignment->suffix);

            $this->entityManager->persist($eleve);
        }

        $this->entityManager->flush();
        $request->getSession()->remove($this->sessionKey($classe));

        $this->addFlash('success', sprintf('%d élève(s) importé(s) dans "%s".', count($assignments), $classe->getNom()));

        return $this->redirectToRoute('classe_show', ['id' => $classe->getId()]);
    }
}
