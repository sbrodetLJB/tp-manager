<?php

namespace App\Service\Provisioning;

use App\Entity\Projet;
use App\Entity\ProvisioningEvent;
use App\Enum\ProvisioningAction;
use App\Enum\ProvisioningEventStatus;
use App\Enum\ProvisioningStep;
use App\Service\Agent\AgentClientInterface;
use App\Service\Agent\AgentException;
use App\Service\Agent\Dto\DatabaseRequest;
use App\Service\Agent\Dto\LinuxAccountRequest;
use App\Service\Agent\Dto\WebrootRequest;
use App\Service\Naming\LoginSanitizer;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Exécute le provisioning d'un projet en 3 étapes fixes (compte Linux -> BDD ->
 * webroot), avec journal d'audit (ProvisioningEvent) à chaque étape. Pas de
 * rollback automatique en cas d'échec partiel en Phase 2 — voir Phase 5 pour
 * les actions "Retry"/"Force cleanup".
 */
final class ProjectProvisioningOrchestrator
{
    public function __construct(
        private readonly AgentClientInterface $agentClient,
        private readonly ProjectSlugSanitizer $slugSanitizer,
        private readonly LoginSanitizer $loginSanitizer,
        private readonly CredentialGenerator $credentialGenerator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function provision(Projet $projet): ProvisioningResult
    {
        $eleve = $projet->getEleve();
        $etablissement = $eleve->getClasse()->getEtablissement();
        $connection = $etablissement->getAgentConnection();

        if (null === $connection || null === $connection->getBearerTokenEncrypted()) {
            return ProvisioningResult::failure("Aucune connexion à l'agent configurée pour cet établissement.");
        }

        $login = $eleve->getLogin();
        $projetSlug = $this->slugSanitizer->sanitize($projet->getNom());
        $dbName = $this->loginSanitizer->sanitize($login.'_'.$projet->getNom(), 63);
        $homeDir = rtrim($etablissement->getWebRootBase(), '/').'/'.$login;

        $linuxPassword = $this->credentialGenerator->generate();
        $dbPassword = $this->credentialGenerator->generate();

        $projet->markInProgress();
        $this->entityManager->flush();

        try {
            $this->executeStep($projet, ProvisioningStep::LinuxAccount, fn (string $requestId) => $this->agentClient->createLinuxAccount(
                $connection,
                new LinuxAccountRequest($requestId, $login, $homeDir, $projet->getSshAuthMethod()->value, $linuxPassword),
            ));

            $this->executeStep($projet, ProvisioningStep::Database, fn (string $requestId) => $this->agentClient->createDatabase(
                $connection,
                new DatabaseRequest($requestId, $dbName, $dbName, $dbPassword),
            ));

            $webrootResponse = $this->executeStep($projet, ProvisioningStep::Webroot, fn (string $requestId) => $this->agentClient->createWebroot(
                $connection,
                new WebrootRequest($requestId, $login, $projetSlug, $login, 'www-data'),
            ));
        } catch (AgentException $e) {
            $projet->markFailed($e->getMessage());
            $this->entityManager->flush();

            return ProvisioningResult::failure($e->getMessage());
        }

        $projet->assignProvisioningTargets($login, $dbName, $dbName, $webrootResponse->path);
        $projet->markProvisioned();
        $this->entityManager->flush();

        return ProvisioningResult::success($linuxPassword, $dbPassword);
    }

    private function executeStep(Projet $projet, ProvisioningStep $step, callable $agentCall): mixed
    {
        $requestId = $this->generateRequestId();
        $this->recordEvent($projet, $step, ProvisioningEventStatus::Started, $requestId);

        try {
            $result = $agentCall($requestId);
        } catch (AgentException $e) {
            $this->recordEvent($projet, $step, ProvisioningEventStatus::Failed, $requestId, $e->getMessage());
            throw $e;
        }

        $this->recordEvent($projet, $step, ProvisioningEventStatus::Succeeded, $requestId);

        return $result;
    }

    private function recordEvent(Projet $projet, ProvisioningStep $step, ProvisioningEventStatus $status, ?string $requestId, ?string $detail = null): void
    {
        $event = new ProvisioningEvent($projet, $step, ProvisioningAction::Create, $status);
        $event->setAgentRequestId($requestId);
        $event->setDetail($detail);

        $this->entityManager->persist($event);
        $this->entityManager->flush();
    }

    private function generateRequestId(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
