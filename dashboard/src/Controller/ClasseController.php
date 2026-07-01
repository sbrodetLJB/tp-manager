<?php

namespace App\Controller;

use App\Entity\Classe;
use App\Entity\Projet;
use App\Form\ClasseType;
use App\Form\ProjetType;
use App\Repository\ClasseRepository;
use App\Repository\CredentialRevealRepository;
use App\Repository\EtablissementRepository;
use App\Service\Provisioning\ClassBulkOrchestrator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/classes')]
class ClasseController extends AbstractController
{
    public function __construct(
        private readonly ClasseRepository $classeRepository,
        private readonly EtablissementRepository $etablissementRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'classe_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('classe/index.html.twig', [
            'classes' => $this->classeRepository->findAll(),
        ]);
    }

    #[Route('/nouvelle', name: 'classe_new', methods: ['GET', 'POST'])]
    public function nouvelle(Request $request): Response
    {
        $etablissement = $this->etablissementRepository->findSingleton();

        if (null === $etablissement) {
            $this->addFlash('warning', "Configurez d'abord l'établissement avant de créer une classe.");

            return $this->redirectToRoute('setup_wizard_etablissement');
        }

        $classe = new Classe();
        $classe->setEtablissement($etablissement);

        $form = $this->createForm(ClasseType::class, $classe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($classe);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Classe "%s" créée.', $classe->getNom()));

            return $this->redirectToRoute('classe_show', ['id' => $classe->getId()]);
        }

        return $this->render('classe/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'classe_show', methods: ['GET'])]
    public function show(Classe $classe): Response
    {
        return $this->render('classe/show.html.twig', ['classe' => $classe]);
    }

    /**
     * Fiche imprimable (Ctrl+P -> "Enregistrer en PDF" côté navigateur) listant,
     * pour chaque projet provisionné, le lien de récupération des identifiants
     * s'il n'a pas encore été consulté.
     */
    #[Route('/{id}/credentials', name: 'classe_credentials', methods: ['GET'])]
    public function ficheCredentials(Classe $classe, CredentialRevealRepository $credentialRevealRepository): Response
    {
        $lignes = [];
        foreach ($classe->getEleves() as $eleve) {
            foreach ($eleve->getProjets() as $projet) {
                $lignes[] = [
                    'eleve' => $eleve,
                    'projet' => $projet,
                    'credentialReveal' => $credentialRevealRepository->findLatestForProjet($projet),
                ];
            }
        }

        return $this->render('classe/fiche_credentials.html.twig', [
            'classe' => $classe,
            'lignes' => $lignes,
        ]);
    }

    /**
     * Crée un même projet (nom + méthode SSH) pour tous les élèves de la
     * classe qui n'en ont pas déjà un du même nom — étape préalable au
     * provisioning de masse (qui ne fait que provisionner des projets déjà
     * existants, jamais n'en crée).
     */
    #[Route('/{id}/projets/nouveau-pour-tous', name: 'classe_projet_new_bulk', methods: ['GET', 'POST'])]
    public function nouveauProjetPourTous(Classe $classe, Request $request, ClassBulkOrchestrator $bulkOrchestrator): Response
    {
        $etablissement = $classe->getEtablissement();
        $projetTemplate = new Projet($etablissement->getDbEngine());

        $form = $this->createForm(ProjetType::class, $projetTemplate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $resultats = $bulkOrchestrator->createProjectForAll($classe, $projetTemplate->getNom(), $projetTemplate->getSshAuthMethod());

            $crees = count(array_filter($resultats, static fn (array $r) => $r['created']));
            $ignores = count($resultats) - $crees;
            $this->addFlash('success', sprintf(
                '%d projet(s) "%s" créé(s)%s.',
                $crees,
                $projetTemplate->getNom(),
                $ignores > 0 ? sprintf(' (%d élève(s) en avaient déjà un du même nom, ignoré(s))', $ignores) : '',
            ));

            return $this->render('classe/projet_bulk_result.html.twig', [
                'classe' => $classe,
                'nom' => $projetTemplate->getNom(),
                'resultats' => $resultats,
            ]);
        }

        return $this->render('classe/projet_new_bulk.html.twig', [
            'form' => $form,
            'classe' => $classe,
        ]);
    }

    #[Route('/{id}/provisionner-tout', name: 'classe_provisionner_tout', methods: ['POST'])]
    public function provisionnerTout(Classe $classe, Request $request, ClassBulkOrchestrator $bulkOrchestrator): Response
    {
        if (!$this->isCsrfTokenValid('classe_provisionner_tout_'.$classe->getId(), $request->request->get('_token'))) {
            throw new BadRequestHttpException('Jeton CSRF invalide.');
        }

        $lignes = $bulkOrchestrator->provisionAll($classe);

        return $this->render('classe/bulk_result.html.twig', [
            'classe' => $classe,
            'lignes' => $lignes,
            'action' => 'provisioning',
        ]);
    }

    #[Route('/{id}/deprovisionner-tout', name: 'classe_deprovisionner_tout', methods: ['POST'])]
    public function deprovisionnerTout(Classe $classe, Request $request, ClassBulkOrchestrator $bulkOrchestrator): Response
    {
        if (!$this->isCsrfTokenValid('classe_deprovisionner_tout_'.$classe->getId(), $request->request->get('_token'))) {
            throw new BadRequestHttpException('Jeton CSRF invalide.');
        }

        $lignes = $bulkOrchestrator->deprovisionAll($classe);

        return $this->render('classe/bulk_result.html.twig', [
            'classe' => $classe,
            'lignes' => $lignes,
            'action' => 'deprovisioning',
        ]);
    }
}
