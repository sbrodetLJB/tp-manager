<?php

namespace App\Controller;

use App\Entity\Etablissement;
use App\Entity\NamingPattern;
use App\Form\EtablissementType;
use App\Form\NamingPatternType;
use App\Repository\EtablissementRepository;
use App\Service\Import\CsvStudentRow;
use App\Service\Import\StudentLoginAssigner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/etablissement')]
class EtablissementController extends AbstractController
{
    public function __construct(
        private readonly EtablissementRepository $etablissementRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'etablissement_show', methods: ['GET'])]
    public function show(): Response
    {
        $etablissement = $this->etablissementRepository->findSingleton();

        if (null === $etablissement) {
            return $this->redirectToRoute('etablissement_configurer');
        }

        return $this->render('etablissement/show.html.twig', [
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/configurer', name: 'etablissement_configurer', methods: ['GET', 'POST'])]
    public function configurer(Request $request): Response
    {
        $etablissement = $this->etablissementRepository->findSingleton() ?? new Etablissement();

        $form = $this->createForm(EtablissementType::class, $etablissement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $etablissement->touch();
            $this->entityManager->persist($etablissement);
            $this->entityManager->flush();

            $this->addFlash('success', "Configuration de l'établissement enregistrée.");

            return $this->redirectToRoute('etablissement_show');
        }

        return $this->render('etablissement/configurer.html.twig', [
            'form' => $form,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/gabarits/nouveau', name: 'naming_pattern_new', methods: ['GET', 'POST'])]
    public function nouveauGabarit(Request $request, StudentLoginAssigner $assigner): Response
    {
        $etablissement = $this->etablissementRepository->findSingleton();

        if (null === $etablissement) {
            $this->addFlash('warning', "Configurez d'abord l'établissement avant de créer un gabarit de nommage.");

            return $this->redirectToRoute('etablissement_configurer');
        }

        $namingPattern = new NamingPattern();
        $namingPattern->setEtablissement($etablissement);

        $form = $this->createForm(NamingPatternType::class, $namingPattern);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ([] === $etablissement->getNamingPatterns()->toArray()) {
                $namingPattern->setActive(true);
            }

            $this->entityManager->persist($namingPattern);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Gabarit "%s" créé.', $namingPattern->getLabel()));

            return $this->redirectToRoute('etablissement_show');
        }

        $preview = $this->buildPreview($assigner, $namingPattern->getTemplate(), $namingPattern->getMaxLength());

        return $this->render('etablissement/naming_pattern_new.html.twig', [
            'form' => $form,
            'preview' => $preview,
        ]);
    }

    #[Route('/gabarits/{id}/activer', name: 'naming_pattern_activate', methods: ['POST'])]
    public function activerGabarit(NamingPattern $namingPattern, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('naming_pattern_activate_'.$namingPattern->getId(), $request->request->get('_token'))) {
            throw new BadRequestHttpException('Jeton CSRF invalide.');
        }

        foreach ($namingPattern->getEtablissement()->getNamingPatterns() as $pattern) {
            $pattern->setActive($pattern === $namingPattern);
        }

        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Gabarit "%s" activé.', $namingPattern->getLabel()));

        return $this->redirectToRoute('etablissement_show');
    }

    /**
     * @return array<int, array{nom: string, prenom: string, login: string, suffix: int|null}>
     */
    private function buildPreview(StudentLoginAssigner $assigner, string $template, ?int $maxLength): array
    {
        if ('' === trim($template)) {
            return [];
        }

        $samplePattern = new NamingPattern();
        $samplePattern->setTemplate($template)->setMaxLength($maxLength);

        $sampleRows = [
            new CsvStudentRow('Dupont', 'Jean'),
            new CsvStudentRow('Martin', 'Paul'),
            new CsvStudentRow('Martin', 'Paul'),
            new CsvStudentRow('Lefèvre', 'Amélie'),
        ];

        $assignments = $assigner->assign($sampleRows, $samplePattern, static fn () => false);

        return array_map(static fn ($a) => [
            'nom' => $a->row->nom,
            'prenom' => $a->row->prenom,
            'login' => $a->login,
            'suffix' => $a->suffix,
        ], $assignments);
    }
}
