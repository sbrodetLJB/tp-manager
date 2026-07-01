<?php

namespace App\Controller;

use App\Entity\Eleve;
use App\Entity\Projet;
use App\Form\ProjetType;
use App\Service\Provisioning\ProjectProvisioningOrchestrator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProjetController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('/eleves/{id}/projets/nouveau', name: 'projet_new', methods: ['GET', 'POST'])]
    public function nouveau(Eleve $eleve, Request $request): Response
    {
        $etablissement = $eleve->getClasse()->getEtablissement();
        $projet = new Projet($etablissement->getDbEngine());
        $projet->setEleve($eleve);

        $form = $this->createForm(ProjetType::class, $projet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($projet);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Projet "%s" créé pour %s %s (statut : en attente de provisioning).', $projet->getNom(), $eleve->getPrenom(), $eleve->getNom()));

            return $this->redirectToRoute('eleve_show', ['id' => $eleve->getId()]);
        }

        return $this->render('projet/new.html.twig', [
            'form' => $form,
            'eleve' => $eleve,
        ]);
    }

    #[Route('/projets/{id}', name: 'projet_show', methods: ['GET'])]
    public function show(Projet $projet): Response
    {
        return $this->render('projet/show.html.twig', ['projet' => $projet]);
    }

    #[Route('/projets/{id}/provisionner', name: 'projet_provisionner', methods: ['POST'])]
    public function provisionner(Projet $projet, Request $request, ProjectProvisioningOrchestrator $orchestrator): Response
    {
        if (!$this->isCsrfTokenValid('projet_provisionner_'.$projet->getId(), $request->request->get('_token'))) {
            throw new BadRequestHttpException('Jeton CSRF invalide.');
        }

        $result = $orchestrator->provision($projet);

        if (!$result->success) {
            $this->addFlash('danger', "Provisioning échoué : {$result->errorMessage}");

            return $this->redirectToRoute('projet_show', ['id' => $projet->getId()]);
        }

        return $this->render('projet/provisioning_result.html.twig', [
            'projet' => $projet,
            'result' => $result,
        ]);
    }
}
