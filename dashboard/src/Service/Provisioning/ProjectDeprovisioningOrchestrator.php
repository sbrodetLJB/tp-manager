<?php

namespace App\Service\Provisioning;

use App\Entity\Projet;
use App\Enum\ProvisioningAction;
use App\Enum\ProvisioningStep;
use App\Service\Agent\AgentClientInterface;
use App\Service\Agent\AgentException;
use App\Service\Naming\LoginSanitizer;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Déprovisioning en ordre inverse de la création (webroot -> BDD -> compte
 * Linux) : on défait d'abord ce qui dépend des autres. Chaque appel agent
 * DELETE est idempotent (voir AgentHttpClient::requestIgnoringNotFound), donc
 * cette même méthode sert à la fois pour un déprovisioning normal et pour
 * "Forcer le nettoyage" (ProjetController) — recalcule les identifiants
 * attendus plutôt que de se fier uniquement aux champs stockés, pour
 * fonctionner même sur un projet jamais entièrement provisionné.
 */
final class ProjectDeprovisioningOrchestrator
{
    public function __construct(
        private readonly AgentClientInterface $agentClient,
        private readonly ProjectSlugSanitizer $slugSanitizer,
        private readonly LoginSanitizer $loginSanitizer,
        private readonly ProvisioningEventRecorder $eventRecorder,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function deprovision(Projet $projet): DeprovisioningResult
    {
        $eleve = $projet->getEleve();
        $etablissement = $eleve->getClasse()->getEtablissement();
        $connection = $etablissement->getAgentConnection();

        if (null === $connection || null === $connection->getBearerTokenEncrypted()) {
            return DeprovisioningResult::failure("Aucune connexion à l'agent configurée pour cet établissement.");
        }

        $login = $projet->getLinuxUsername() ?? $eleve->getLogin();
        $dbName = $projet->getDbName() ?? $this->loginSanitizer->sanitize($login.'_'.$projet->getNom(), 63);
        $projetSlug = $this->slugSanitizer->sanitize($projet->getNom());

        $projet->markDeprovisioning();
        $this->entityManager->flush();

        try {
            $this->eventRecorder->execute(
                $projet,
                ProvisioningStep::Webroot,
                ProvisioningAction::Delete,
                function () use ($connection, $login, $projetSlug) {
                    $this->agentClient->deleteWebroot($connection, $login, $projetSlug);
                },
            );

            $this->eventRecorder->execute(
                $projet,
                ProvisioningStep::Database,
                ProvisioningAction::Delete,
                function () use ($connection, $dbName) {
                    $this->agentClient->deleteDatabase($connection, $dbName);
                },
            );

            $this->eventRecorder->execute(
                $projet,
                ProvisioningStep::LinuxAccount,
                ProvisioningAction::Delete,
                function () use ($connection, $login) {
                    $this->agentClient->deleteLinuxAccount($connection, $login, true);
                },
            );
        } catch (AgentException $e) {
            $projet->markFailed("Déprovisioning échoué : {$e->getMessage()}");
            $this->entityManager->flush();

            return DeprovisioningResult::failure($e->getMessage());
        }

        $projet->markDeprovisioned();
        $this->entityManager->flush();

        return DeprovisioningResult::success();
    }
}
