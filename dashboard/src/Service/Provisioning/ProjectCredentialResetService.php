<?php

namespace App\Service\Provisioning;

use App\Entity\Projet;
use App\Enum\ProvisioningAction;
use App\Enum\ProvisioningStatus;
use App\Enum\ProvisioningStep;
use App\Enum\SshAuthMethod;
use App\Service\Agent\AgentClientInterface;
use App\Service\Agent\AgentException;
use App\Service\Agent\Dto\DatabasePasswordResetRequest;
use App\Service\Agent\Dto\LinuxAccountPasswordResetRequest;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Réinitialise les identifiants (compte Linux/SFTP + utilisateur BDD) d'un
 * projet déjà provisionné, sans jamais toucher au home/aux fichiers déposés
 * ni à la base/aux GRANT existants (voir tpagent-create-linux-user.sh et
 * tpagent-mysql-provision.sh, action reset-password). Contrairement au
 * déprovisioning + reprovisioning, cette opération est non destructive :
 * c'est la seule façon sûre de récupérer un projet dont l'élève a perdu son
 * mot de passe.
 */
final class ProjectCredentialResetService
{
    public function __construct(
        private readonly AgentClientInterface $agentClient,
        private readonly CredentialGenerator $credentialGenerator,
        private readonly SshPublicKeyFingerprintCalculator $fingerprintCalculator,
        private readonly ProvisioningEventRecorder $eventRecorder,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function reset(Projet $projet, ?string $sshPublicKey = null): ProvisioningResult
    {
        if (ProvisioningStatus::Provisioned !== $projet->getProvisioningStatus()) {
            return ProvisioningResult::failure('Seul un projet provisionné avec succès peut avoir ses identifiants réinitialisés.');
        }

        $linuxUsername = $projet->getLinuxUsername();
        $dbName = $projet->getDbName();
        $dbUser = $projet->getDbUser();
        if (null === $linuxUsername || null === $dbName || null === $dbUser) {
            return ProvisioningResult::failure("Ce projet n'a pas d'identifiants système assignés.");
        }

        $eleve = $projet->getEleve();
        $etablissement = $eleve->getClasse()->getEtablissement();
        $connection = $etablissement->getAgentConnection();

        if (null === $connection || null === $connection->getBearerTokenEncrypted()) {
            return ProvisioningResult::failure("Aucune connexion à l'agent configurée pour cet établissement.");
        }

        $usePublicKey = SshAuthMethod::PublicKey === $projet->getSshAuthMethod();
        if ($usePublicKey && (null === $sshPublicKey || '' === trim($sshPublicKey))) {
            return ProvisioningResult::failure("Ce projet est configuré pour une authentification par clé publique : collez la nouvelle clé de l'élève avant de réinitialiser.");
        }

        $linuxPassword = $usePublicKey ? null : $this->credentialGenerator->generate();
        $dbPassword = $this->credentialGenerator->generate();

        try {
            $this->eventRecorder->execute($projet, ProvisioningStep::LinuxAccount, ProvisioningAction::Reset, fn (string $requestId) => $this->agentClient->resetLinuxAccountPassword(
                $connection,
                $linuxUsername,
                new LinuxAccountPasswordResetRequest($requestId, $projet->getSshAuthMethod()->value, $linuxPassword, $usePublicKey ? $sshPublicKey : null),
            ));

            $this->eventRecorder->execute($projet, ProvisioningStep::Database, ProvisioningAction::Reset, fn (string $requestId) => $this->agentClient->resetDatabasePassword(
                $connection,
                $dbName,
                new DatabasePasswordResetRequest($requestId, $dbPassword),
            ));
        } catch (AgentException $e) {
            return ProvisioningResult::failure($e->getMessage());
        }

        if ($usePublicKey) {
            $projet->setSshPublicKeyFingerprint($this->fingerprintCalculator->calculate($sshPublicKey));
            $this->entityManager->flush();
        }

        return ProvisioningResult::success($linuxPassword, $dbPassword);
    }
}
