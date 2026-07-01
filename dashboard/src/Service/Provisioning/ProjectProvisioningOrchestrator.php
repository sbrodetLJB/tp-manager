<?php

namespace App\Service\Provisioning;

use App\Entity\Projet;
use App\Enum\ProvisioningAction;
use App\Enum\ProvisioningStep;
use App\Enum\SshAuthMethod;
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
 * rollback automatique en cas d'échec partiel — voir ProjectDeprovisioningOrchestrator
 * pour les actions "Retry"/"Force cleanup" (Phase 5).
 */
final class ProjectProvisioningOrchestrator
{
    public function __construct(
        private readonly AgentClientInterface $agentClient,
        private readonly ProjectSlugSanitizer $slugSanitizer,
        private readonly LoginSanitizer $loginSanitizer,
        private readonly CredentialGenerator $credentialGenerator,
        private readonly SshPublicKeyFingerprintCalculator $fingerprintCalculator,
        private readonly ProvisioningEventRecorder $eventRecorder,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function provision(Projet $projet, ?string $sshPublicKey = null): ProvisioningResult
    {
        $eleve = $projet->getEleve();
        $etablissement = $eleve->getClasse()->getEtablissement();
        $connection = $etablissement->getAgentConnection();

        if (null === $connection || null === $connection->getBearerTokenEncrypted()) {
            return ProvisioningResult::failure("Aucune connexion à l'agent configurée pour cet établissement.");
        }

        $usePublicKey = SshAuthMethod::PublicKey === $projet->getSshAuthMethod();
        if ($usePublicKey && (null === $sshPublicKey || '' === trim($sshPublicKey))) {
            return ProvisioningResult::failure("Ce projet est configuré pour une authentification par clé publique : collez la clé de l'élève avant de provisionner.");
        }

        $login = $eleve->getLogin();
        $projetSlug = $this->slugSanitizer->sanitize($projet->getNom());
        $dbName = $this->loginSanitizer->sanitize($login.'_'.$projet->getNom(), 63);
        $homeDir = rtrim($etablissement->getWebRootBase(), '/').'/'.$login;

        $linuxPassword = $usePublicKey ? null : $this->credentialGenerator->generate();
        $dbPassword = $this->credentialGenerator->generate();

        $projet->markInProgress();
        $this->entityManager->flush();

        try {
            $this->eventRecorder->execute($projet, ProvisioningStep::LinuxAccount, ProvisioningAction::Create, fn (string $requestId) => $this->agentClient->createLinuxAccount(
                $connection,
                new LinuxAccountRequest($requestId, $login, $homeDir, $projet->getSshAuthMethod()->value, $linuxPassword, $usePublicKey ? $sshPublicKey : null),
            ));

            $this->eventRecorder->execute($projet, ProvisioningStep::Database, ProvisioningAction::Create, fn (string $requestId) => $this->agentClient->createDatabase(
                $connection,
                new DatabaseRequest($requestId, $dbName, $dbName, $dbPassword),
            ));

            $webrootResponse = $this->eventRecorder->execute($projet, ProvisioningStep::Webroot, ProvisioningAction::Create, fn (string $requestId) => $this->agentClient->createWebroot(
                $connection,
                new WebrootRequest($requestId, $login, $projetSlug, $login, 'www-data'),
            ));
        } catch (AgentException $e) {
            $projet->markFailed($e->getMessage());
            $this->entityManager->flush();

            return ProvisioningResult::failure($e->getMessage());
        }

        if ($usePublicKey) {
            $projet->setSshPublicKeyFingerprint($this->fingerprintCalculator->calculate($sshPublicKey));
        }

        $projet->assignProvisioningTargets($login, $dbName, $dbName, $webrootResponse->path);
        $projet->markProvisioned();
        $this->entityManager->flush();

        return ProvisioningResult::success($linuxPassword, $dbPassword);
    }
}
