<?php

namespace App\Entity;

use App\Enum\AgentHealthStatus;
use App\Enum\DbEngine;
use App\Repository\AgentConnectionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Séparée d'Etablissement pour que la rotation du token n'interfère pas avec
 * l'audit trail de la configuration établissement. Champs présents mais non
 * utilisés avant la Phase 2 (aucun appel réseau vers l'agent en Phase 1).
 */
#[ORM\Entity(repositoryClass: AgentConnectionRepository::class)]
class AgentConnection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'agentConnection', targetEntity: Etablissement::class)]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private ?Etablissement $etablissement = null;

    #[ORM\Column(length: 255)]
    private string $baseUrl = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bearerTokenEncrypted = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastHealthCheckAt = null;

    #[ORM\Column(length: 20, enumType: AgentHealthStatus::class)]
    private AgentHealthStatus $lastHealthCheckStatus = AgentHealthStatus::Unknown;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $agentVersion = null;

    #[ORM\Column(length: 20, enumType: DbEngine::class, nullable: true)]
    private ?DbEngine $agentDbEngine = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEtablissement(): ?Etablissement
    {
        return $this->etablissement;
    }

    public function setEtablissement(?Etablissement $etablissement): static
    {
        $this->etablissement = $etablissement;

        return $this;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function setBaseUrl(string $baseUrl): static
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }

    public function getBearerTokenEncrypted(): ?string
    {
        return $this->bearerTokenEncrypted;
    }

    public function setBearerTokenEncrypted(?string $bearerTokenEncrypted): static
    {
        $this->bearerTokenEncrypted = $bearerTokenEncrypted;

        return $this;
    }

    public function getLastHealthCheckAt(): ?\DateTimeImmutable
    {
        return $this->lastHealthCheckAt;
    }

    public function getLastHealthCheckStatus(): AgentHealthStatus
    {
        return $this->lastHealthCheckStatus;
    }

    public function recordHealthCheck(AgentHealthStatus $status, ?string $agentVersion, ?DbEngine $agentDbEngine): static
    {
        $this->lastHealthCheckAt = new \DateTimeImmutable();
        $this->lastHealthCheckStatus = $status;
        $this->agentVersion = $agentVersion;
        $this->agentDbEngine = $agentDbEngine;

        return $this;
    }

    public function getAgentVersion(): ?string
    {
        return $this->agentVersion;
    }

    public function getAgentDbEngine(): ?DbEngine
    {
        return $this->agentDbEngine;
    }
}
