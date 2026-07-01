<?php

namespace App\Service\Provisioning;

use App\Entity\Projet;
use App\Entity\ProvisioningEvent;
use App\Enum\ProvisioningAction;
use App\Enum\ProvisioningEventStatus;
use App\Enum\ProvisioningStep;
use App\Service\Agent\AgentException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Exécute un appel agent en l'entourant d'un journal d'audit
 * (ProvisioningEvent started/succeeded/failed) — partagé par l'orchestrateur
 * de provisioning et celui de déprovisioning pour éviter la duplication.
 */
final class ProvisioningEventRecorder
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function execute(Projet $projet, ProvisioningStep $step, ProvisioningAction $action, callable $agentCall): mixed
    {
        $requestId = $this->generateRequestId();
        $this->record($projet, $step, $action, ProvisioningEventStatus::Started, $requestId);

        try {
            $result = $agentCall($requestId);
        } catch (AgentException $e) {
            $this->record($projet, $step, $action, ProvisioningEventStatus::Failed, $requestId, $e->getMessage());
            throw $e;
        }

        $this->record($projet, $step, $action, ProvisioningEventStatus::Succeeded, $requestId);

        return $result;
    }

    private function record(Projet $projet, ProvisioningStep $step, ProvisioningAction $action, ProvisioningEventStatus $status, ?string $requestId, ?string $detail = null): void
    {
        $event = new ProvisioningEvent($projet, $step, $action, $status);
        $event->setAgentRequestId($requestId);
        $event->setDetail($detail);

        $this->entityManager->persist($event);
        $this->entityManager->flush();
    }

    public function generateRequestId(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
