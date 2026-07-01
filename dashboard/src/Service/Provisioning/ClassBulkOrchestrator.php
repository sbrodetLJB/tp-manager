<?php

namespace App\Service\Provisioning;

use App\Entity\Classe;
use App\Entity\Projet;
use App\Enum\ProvisioningStatus;
use App\Enum\SshAuthMethod;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Actions de masse par classe : une file d'appels individuels aux
 * orchestrateurs existants (pas de nouvel endpoint agent dédié au batch — le
 * batching reste entièrement côté dashboard, voir docs/architecture.md).
 * Synchrone : pour 30 élèves cela prend plusieurs secondes, acceptable en V1
 * sans file d'attente/websocket ; le résultat par ligne n'apparaît qu'une
 * fois le lot terminé.
 */
final class ClassBulkOrchestrator
{
    private const PROVISIONABLE = [ProvisioningStatus::Pending, ProvisioningStatus::Failed, ProvisioningStatus::Deprovisioned];
    private const DEPROVISIONABLE = [ProvisioningStatus::Provisioned, ProvisioningStatus::Failed, ProvisioningStatus::Deprovisioning];

    public function __construct(
        private readonly ProjectProvisioningOrchestrator $provisioningOrchestrator,
        private readonly ProjectDeprovisioningOrchestrator $deprovisioningOrchestrator,
        private readonly CredentialRevealTokenManager $credentialRevealTokenManager,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Crée un projet du même nom pour chaque élève de la classe qui n'en a
     * pas déjà un — les élèves déjà pourvus sont ignorés silencieusement
     * (le projet existant n'est jamais modifié), avec le détail par élève
     * renvoyé pour affichage.
     *
     * @return array<int, array{eleve: object, created: bool}>
     */
    public function createProjectForAll(Classe $classe, string $nom, SshAuthMethod $sshAuthMethod): array
    {
        $etablissement = $classe->getEtablissement();
        $rows = [];

        foreach ($classe->getEleves() as $eleve) {
            $dejaExistant = false;
            foreach ($eleve->getProjets() as $projetExistant) {
                if ($projetExistant->getNom() === $nom) {
                    $dejaExistant = true;
                    break;
                }
            }

            if ($dejaExistant) {
                $rows[] = ['eleve' => $eleve, 'created' => false];
                continue;
            }

            $projet = new Projet($etablissement->getDbEngine());
            $projet->setEleve($eleve)->setNom($nom)->setSshAuthMethod($sshAuthMethod);
            $this->entityManager->persist($projet);

            $rows[] = ['eleve' => $eleve, 'created' => true];
        }

        $this->entityManager->flush();

        return $rows;
    }

    /**
     * @return array<int, array{eleve: object, projet: object, success: bool, skipped: bool, message: ?string, revealToken: ?string}>
     */
    public function provisionAll(Classe $classe): array
    {
        $rows = [];

        foreach ($classe->getEleves() as $eleve) {
            foreach ($eleve->getProjets() as $projet) {
                if (!in_array($projet->getProvisioningStatus(), self::PROVISIONABLE, true)) {
                    continue;
                }

                if (SshAuthMethod::PublicKey === $projet->getSshAuthMethod()) {
                    $rows[] = [
                        'eleve' => $eleve,
                        'projet' => $projet,
                        'success' => false,
                        'skipped' => true,
                        'message' => "Nécessite une clé publique : à provisionner individuellement depuis la page du projet.",
                        'revealToken' => null,
                    ];
                    continue;
                }

                $result = $this->provisioningOrchestrator->provision($projet);
                $revealToken = null;

                if ($result->success) {
                    $reveal = $this->credentialRevealTokenManager->create($projet, [
                        'linuxPassword' => $result->linuxPassword,
                        'dbPassword' => $result->dbPassword,
                    ]);
                    $revealToken = $reveal->getRevealToken();
                }

                $rows[] = [
                    'eleve' => $eleve,
                    'projet' => $projet,
                    'success' => $result->success,
                    'skipped' => false,
                    'message' => $result->errorMessage,
                    'revealToken' => $revealToken,
                ];
            }
        }

        return $rows;
    }

    /**
     * @return array<int, array{eleve: object, projet: object, success: bool, skipped: bool, message: ?string}>
     */
    public function deprovisionAll(Classe $classe): array
    {
        $rows = [];

        foreach ($classe->getEleves() as $eleve) {
            foreach ($eleve->getProjets() as $projet) {
                if (!in_array($projet->getProvisioningStatus(), self::DEPROVISIONABLE, true)) {
                    continue;
                }
                if (ProvisioningStatus::Failed === $projet->getProvisioningStatus() && !$projet->hasProvisioningTargetsAssigned()) {
                    continue; // jamais rien eu à nettoyer
                }

                $result = $this->deprovisioningOrchestrator->deprovision($projet);

                $rows[] = [
                    'eleve' => $eleve,
                    'projet' => $projet,
                    'success' => $result->success,
                    'skipped' => false,
                    'message' => $result->errorMessage,
                ];
            }
        }

        return $rows;
    }
}
