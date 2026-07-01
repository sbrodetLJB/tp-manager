<?php

namespace App\Controller;

use App\Entity\Classe;
use App\Form\ClasseType;
use App\Repository\ClasseRepository;
use App\Repository\CredentialRevealRepository;
use App\Repository\EtablissementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
}
