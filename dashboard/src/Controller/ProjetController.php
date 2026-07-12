<?php

namespace App\Controller;

use App\Entity\Eleve;
use App\Entity\Projet;
use App\Enum\ProvisioningStatus;
use App\Form\ProjetType;
use App\Service\Provisioning\CredentialRevealTokenManager;
use App\Service\Provisioning\ProjectCredentialResetService;
use App\Service\Provisioning\ProjectDeprovisioningOrchestrator;
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
    public function provisionner(
        Projet $projet,
        Request $request,
        ProjectProvisioningOrchestrator $orchestrator,
        CredentialRevealTokenManager $credentialRevealTokenManager,
    ): Response {
        if (!$this->isCsrfTokenValid('projet_provisionner_'.$projet->getId(), $request->request->get('_token'))) {
            throw new BadRequestHttpException('Jeton CSRF invalide.');
        }

        $publicKey = $request->request->get('publicKey');
        $result = $orchestrator->provision($projet, is_string($publicKey) ? $publicKey : null);

        if (!$result->success) {
            $this->addFlash('danger', "Provisioning échoué : {$result->errorMessage}");

            return $this->redirectToRoute('projet_show', ['id' => $projet->getId()]);
        }

        $reveal = $credentialRevealTokenManager->create($projet, [
            'linuxPassword' => $result->linuxPassword,
            'dbPassword' => $result->dbPassword,
        ]);

        return $this->redirectToRoute('credential_reveal', [
            'id' => $projet->getId(),
            'revealToken' => $reveal->getRevealToken(),
        ]);
    }

    #[Route('/projets/{id}/reinitialiser-identifiants', name: 'projet_reinitialiser_identifiants', methods: ['POST'])]
    public function reinitialiserIdentifiants(
        Projet $projet,
        Request $request,
        ProjectCredentialResetService $resetService,
        CredentialRevealTokenManager $credentialRevealTokenManager,
    ): Response {
        if (!$this->isCsrfTokenValid('projet_reinitialiser_identifiants_'.$projet->getId(), $request->request->get('_token'))) {
            throw new BadRequestHttpException('Jeton CSRF invalide.');
        }

        $publicKey = $request->request->get('publicKey');
        $result = $resetService->reset($projet, is_string($publicKey) ? $publicKey : null);

        if (!$result->success) {
            $this->addFlash('danger', "Réinitialisation des identifiants échouée : {$result->errorMessage}");

            return $this->redirectToRoute('projet_show', ['id' => $projet->getId()]);
        }

        $reveal = $credentialRevealTokenManager->create($projet, [
            'linuxPassword' => $result->linuxPassword,
            'dbPassword' => $result->dbPassword,
        ]);

        return $this->redirectToRoute('credential_reveal', [
            'id' => $projet->getId(),
            'revealToken' => $reveal->getRevealToken(),
        ]);
    }

    /**
     * Sert à la fois pour un déprovisioning normal (statut "provisioned") et
     * pour "Forcer le nettoyage" (statut "failed" avec cibles déjà assignées) :
     * les suppressions agent sont idempotentes, donc rejouer cette action ne
     * risque jamais de casser un état déjà propre.
     */
    #[Route('/projets/{id}/deprovisionner', name: 'projet_deprovisionner', methods: ['POST'])]
    public function deprovisionner(Projet $projet, Request $request, ProjectDeprovisioningOrchestrator $orchestrator): Response
    {
        if (!$this->isCsrfTokenValid('projet_deprovisionner_'.$projet->getId(), $request->request->get('_token'))) {
            throw new BadRequestHttpException('Jeton CSRF invalide.');
        }

        $result = $orchestrator->deprovision($projet);

        if (!$result->success) {
            $this->addFlash('danger', "Déprovisioning échoué : {$result->errorMessage}");
        } else {
            $this->addFlash('success', sprintf('Projet "%s" déprovisionné.', $projet->getNom()));
        }

        return $this->redirectToRoute('projet_show', ['id' => $projet->getId()]);
    }

    /**
     * Supprime définitivement la fiche projet. Si un compte/une base/un
     * dépôt web lui sont encore assignés, on les nettoie d'abord via le même
     * orchestrateur idempotent que "Forcer le nettoyage" - impossible de se
     * retrouver avec des ressources orphelines sur le serveur agent.
     */
    #[Route('/projets/{id}/supprimer', name: 'projet_supprimer', methods: ['POST'])]
    public function supprimer(Projet $projet, Request $request, ProjectDeprovisioningOrchestrator $orchestrator): Response
    {
        if (!$this->isCsrfTokenValid('projet_supprimer_'.$projet->getId(), $request->request->get('_token'))) {
            throw new BadRequestHttpException('Jeton CSRF invalide.');
        }

        if (ProvisioningStatus::InProgress === $projet->getProvisioningStatus()) {
            $this->addFlash('danger', 'Suppression impossible : une opération de provisioning est en cours pour ce projet.');

            return $this->redirectToRoute('projet_show', ['id' => $projet->getId()]);
        }

        if ($projet->hasProvisioningTargetsAssigned()) {
            $result = $orchestrator->deprovision($projet);

            if (!$result->success) {
                $this->addFlash('danger', "Suppression annulée : le nettoyage préalable des ressources a échoué ({$result->errorMessage}).");

                return $this->redirectToRoute('projet_show', ['id' => $projet->getId()]);
            }
        }

        $eleveId = $projet->getEleve()->getId();
        $nom = $projet->getNom();

        $this->entityManager->remove($projet);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Projet "%s" supprimé.', $nom));

        return $this->redirectToRoute('eleve_show', ['id' => $eleveId]);
    }
}
