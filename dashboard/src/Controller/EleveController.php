<?php

namespace App\Controller;

use App\Entity\Classe;
use App\Entity\Eleve;
use App\Form\EleveType;
use App\Repository\EleveRepository;
use App\Service\Import\CsvStudentRow;
use App\Service\Import\StudentLoginAssigner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EleveController extends AbstractController
{
    public function __construct(
        private readonly EleveRepository $eleveRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/classes/{id}/eleves/nouveau', name: 'eleve_new', methods: ['GET', 'POST'])]
    public function nouveau(Classe $classe, Request $request, StudentLoginAssigner $assigner): Response
    {
        $namingPattern = $classe->getEtablissement()->getActiveNamingPattern();

        if (null === $namingPattern) {
            $this->addFlash('warning', "Créez et activez d'abord un gabarit de nommage.");

            return $this->redirectToRoute('naming_pattern_new');
        }

        $eleve = new Eleve();
        $form = $this->createForm(EleveType::class, $eleve);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $assignments = $assigner->assign(
                [new CsvStudentRow($eleve->getNom(), $eleve->getPrenom(), $eleve->getMatricule())],
                $namingPattern,
                fn (string $login) => $this->eleveRepository->loginExists($login),
            );
            $assignment = $assignments[0];

            $eleve->setClasse($classe);
            $eleve->setLogin($assignment->login);
            $eleve->setLoginSuffix($assignment->suffix);

            $this->entityManager->persist($eleve);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Élève "%s %s" ajouté avec le login "%s".', $eleve->getPrenom(), $eleve->getNom(), $eleve->getLogin()));

            return $this->redirectToRoute('classe_show', ['id' => $classe->getId()]);
        }

        return $this->render('eleve/new.html.twig', [
            'form' => $form,
            'classe' => $classe,
            'namingPattern' => $namingPattern,
        ]);
    }

    #[Route('/eleves/{id}', name: 'eleve_show', methods: ['GET'])]
    public function show(Eleve $eleve): Response
    {
        return $this->render('eleve/show.html.twig', ['eleve' => $eleve]);
    }
}
