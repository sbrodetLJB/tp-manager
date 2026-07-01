<?php

namespace App\Controller;

use App\Entity\AgentConnection;
use App\Entity\Etablissement;
use App\Enum\AgentHealthStatus;
use App\Enum\DbEngine;
use App\Form\AgentConnectionType;
use App\Form\EtablissementType;
use App\Repository\EtablissementRepository;
use App\Service\Agent\AgentClientInterface;
use App\Service\Agent\AgentException;
use App\Service\Security\AgentTokenEncryptor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Assistant de configuration initiale (2 étapes) : établissement puis agent,
 * avec vérification live de l'agent avant toute sauvegarde. Contrairement à
 * EtablissementController/AgentConnectionController (édition indépendante,
 * utilisable à tout moment), cet assistant ne persiste RIEN tant que l'agent
 * n'a pas été vérifié avec succès — voir tests/Functional/SetupWizardTest.php.
 */
#[Route('/configuration')]
class SetupWizardController extends AbstractController
{
    private const SESSION_KEY = 'setup_wizard_etablissement';

    public function __construct(
        private readonly EtablissementRepository $etablissementRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly AgentTokenEncryptor $tokenEncryptor,
        private readonly AgentClientInterface $agentClient,
    ) {
    }

    #[Route('/etablissement', name: 'setup_wizard_etablissement', methods: ['GET', 'POST'])]
    public function etablissement(Request $request): Response
    {
        if (null !== $this->etablissementRepository->findSingleton()) {
            return $this->redirectToRoute('etablissement_show');
        }

        $etablissement = new Etablissement();
        $form = $this->createForm(EtablissementType::class, $etablissement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $request->getSession()->set(self::SESSION_KEY, [
                'nom' => $etablissement->getNom(),
                'dbEngine' => $etablissement->getDbEngine()->value,
                'webRootBase' => $etablissement->getWebRootBase(),
            ]);

            return $this->redirectToRoute('setup_wizard_agent');
        }

        return $this->render('setup_wizard/etablissement.html.twig', ['form' => $form]);
    }

    #[Route('/agent', name: 'setup_wizard_agent', methods: ['GET', 'POST'])]
    public function agent(Request $request): Response
    {
        if (null !== $this->etablissementRepository->findSingleton()) {
            return $this->redirectToRoute('etablissement_show');
        }

        $etablissementData = $request->getSession()->get(self::SESSION_KEY);
        if (null === $etablissementData) {
            $this->addFlash('warning', "Renseignez d'abord les informations de l'établissement.");

            return $this->redirectToRoute('setup_wizard_etablissement');
        }

        $form = $this->createForm(AgentConnectionType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $baseUrl = $form->get('baseUrl')->getData() ?? '';
            $token = trim((string) $form->get('token')->getData());

            if ('' === $token) {
                $form->get('token')->addError(new FormError('Le jeton est obligatoire pour terminer la configuration.'));
            } else {
                $etablissementCandidat = new Etablissement();
                $etablissementCandidat
                    ->setNom($etablissementData['nom'])
                    ->setDbEngine(DbEngine::from($etablissementData['dbEngine']))
                    ->setWebRootBase($etablissementData['webRootBase']);

                $connectionCandidate = new AgentConnection();
                $connectionCandidate->setBaseUrl($baseUrl);
                $connectionCandidate->setBearerTokenEncrypted($this->tokenEncryptor->encrypt($token));

                try {
                    $config = $this->agentClient->getConfig($connectionCandidate);
                } catch (AgentException $e) {
                    $this->addFlash('danger', "Agent injoignable, rien n'a été enregistré : {$e->getMessage()}");

                    return $this->render('setup_wizard/agent.html.twig', ['form' => $form, 'etablissementNom' => $etablissementData['nom']]);
                }

                $agentDbEngine = DbEngine::from($config->dbEngine);
                if ($agentDbEngine !== $etablissementCandidat->getDbEngine()) {
                    $this->addFlash('danger', sprintf(
                        "Moteur BDD différent entre l'établissement (\"%s\") et l'agent (\"%s\") — rien n'a été enregistré. Corrigez l'un des deux avant de continuer.",
                        $etablissementCandidat->getDbEngine()->value,
                        $config->dbEngine,
                    ));

                    return $this->render('setup_wizard/agent.html.twig', ['form' => $form, 'etablissementNom' => $etablissementData['nom']]);
                }

                // Tout est vérifié : établissement + agent sont enregistrés ensemble.
                $connectionCandidate->setEtablissement($etablissementCandidat);
                $connectionCandidate->recordHealthCheck(AgentHealthStatus::Ok, $config->agentVersion, $agentDbEngine);

                $this->entityManager->persist($etablissementCandidat);
                $this->entityManager->persist($connectionCandidate);
                $this->entityManager->flush();

                $request->getSession()->remove(self::SESSION_KEY);

                $this->addFlash('success', "Établissement configuré, agent vérifié (version {$config->agentVersion}) : prêt à provisionner.");

                return $this->redirectToRoute('etablissement_show');
            }
        }

        return $this->render('setup_wizard/agent.html.twig', ['form' => $form, 'etablissementNom' => $etablissementData['nom']]);
    }
}
