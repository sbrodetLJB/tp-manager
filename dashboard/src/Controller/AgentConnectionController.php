<?php

namespace App\Controller;

use App\Entity\AgentConnection;
use App\Enum\AgentHealthStatus;
use App\Form\AgentConnectionType;
use App\Repository\EtablissementRepository;
use App\Service\Agent\AgentClientInterface;
use App\Service\Agent\AgentException;
use App\Service\Security\AgentTokenEncryptor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/etablissement/agent')]
class AgentConnectionController extends AbstractController
{
    public function __construct(
        private readonly EtablissementRepository $etablissementRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly AgentTokenEncryptor $tokenEncryptor,
        private readonly AgentClientInterface $agentClient,
    ) {
    }

    #[Route('', name: 'agent_connection_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        $etablissement = $this->etablissementRepository->findSingleton();

        if (null === $etablissement) {
            $this->addFlash('warning', "Configurez d'abord l'établissement avant de connecter l'agent.");

            return $this->redirectToRoute('setup_wizard_etablissement');
        }

        $connection = $etablissement->getAgentConnection() ?? new AgentConnection();

        $form = $this->createForm(AgentConnectionType::class, ['baseUrl' => $connection->getBaseUrl() ?: null]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $connection->setEtablissement($etablissement);
            $connection->setBaseUrl($form->get('baseUrl')->getData() ?? '');

            $token = $form->get('token')->getData();
            if (null !== $token && '' !== $token) {
                $connection->setBearerTokenEncrypted($this->tokenEncryptor->encrypt($token));
            }

            $this->entityManager->persist($connection);
            $this->entityManager->flush();

            $this->verifyConnection($connection, $etablissement);

            return $this->redirectToRoute('etablissement_show');
        }

        return $this->render('etablissement/agent_connection.html.twig', [
            'form' => $form,
            'connection' => $connection,
        ]);
    }

    #[Route('/verifier', name: 'agent_connection_verify', methods: ['POST'])]
    public function verify(Request $request): Response
    {
        $etablissement = $this->etablissementRepository->findSingleton();
        $connection = $etablissement?->getAgentConnection();

        if (null === $connection || !$this->isCsrfTokenValid('agent_connection_verify', $request->request->get('_token'))) {
            throw $this->createNotFoundException();
        }

        $this->verifyConnection($connection, $etablissement);

        return $this->redirectToRoute('etablissement_show');
    }

    private function verifyConnection(AgentConnection $connection, \App\Entity\Etablissement $etablissement): void
    {
        try {
            $config = $this->agentClient->getConfig($connection);
        } catch (AgentException $e) {
            $connection->recordHealthCheck(AgentHealthStatus::Unreachable, null, null);
            $this->entityManager->flush();
            $this->addFlash('danger', "Agent injoignable : {$e->getMessage()}");

            return;
        }

        $agentDbEngine = \App\Enum\DbEngine::from($config->dbEngine);
        $status = $agentDbEngine === $etablissement->getDbEngine()
            ? AgentHealthStatus::Ok
            : AgentHealthStatus::VersionMismatch;

        $connection->recordHealthCheck($status, $config->agentVersion, $agentDbEngine);
        $this->entityManager->flush();

        if (AgentHealthStatus::Ok === $status) {
            $this->addFlash('success', "Agent connecté (version {$config->agentVersion}, moteur {$config->dbEngine}).");
        } else {
            $this->addFlash('warning', "Agent connecté mais moteur BDD différent : établissement=\"{$etablissement->getDbEngine()->value}\", agent=\"{$config->dbEngine}\".");
        }
    }
}
