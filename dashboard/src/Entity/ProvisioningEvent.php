<?php

namespace App\Entity;

use App\Enum\ProvisioningAction;
use App\Enum\ProvisioningEventStatus;
use App\Enum\ProvisioningStep;
use App\Repository\ProvisioningEventRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Journal d'audit append-only : corrèle un échec visible côté enseignant
 * avec les logs de l'agent via agentRequestId (voir contracts/openapi.yaml).
 */
#[ORM\Entity(repositoryClass: ProvisioningEventRepository::class)]
class ProvisioningEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'provisioningEvents', targetEntity: Projet::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Projet $projet = null;

    #[ORM\Column(length: 20, enumType: ProvisioningStep::class)]
    private ProvisioningStep $step;

    #[ORM\Column(length: 20, enumType: ProvisioningAction::class)]
    private ProvisioningAction $action;

    #[ORM\Column(length: 20, enumType: ProvisioningEventStatus::class)]
    private ProvisioningEventStatus $status;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $agentRequestId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $detail = null;

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    public function __construct(Projet $projet, ProvisioningStep $step, ProvisioningAction $action, ProvisioningEventStatus $status)
    {
        $this->projet = $projet;
        $this->step = $step;
        $this->action = $action;
        $this->status = $status;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProjet(): ?Projet
    {
        return $this->projet;
    }

    public function getStep(): ProvisioningStep
    {
        return $this->step;
    }

    public function getAction(): ProvisioningAction
    {
        return $this->action;
    }

    public function getStatus(): ProvisioningEventStatus
    {
        return $this->status;
    }

    public function getAgentRequestId(): ?string
    {
        return $this->agentRequestId;
    }

    public function setAgentRequestId(?string $agentRequestId): static
    {
        $this->agentRequestId = $agentRequestId;

        return $this;
    }

    public function getDetail(): ?string
    {
        return $this->detail;
    }

    public function setDetail(?string $detail): static
    {
        $this->detail = $detail;

        return $this;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
