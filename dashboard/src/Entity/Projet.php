<?php

namespace App\Entity;

use App\Enum\DbEngine;
use App\Enum\ProvisioningStatus;
use App\Enum\SshAuthMethod;
use App\Repository\ProjetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjetRepository::class)]
class Projet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'projets', targetEntity: Eleve::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Eleve $eleve = null;

    #[ORM\Column(length: 100)]
    private string $nom = '';

    /**
     * Copie figée du moteur BDD de l'établissement au moment de la création,
     * pour que les projets historiques restent compréhensibles même si le
     * réglage global de l'établissement change plus tard.
     */
    #[ORM\Column(length: 20, enumType: DbEngine::class)]
    private DbEngine $dbEngine;

    #[ORM\Column(length: 63, nullable: true)]
    private ?string $dbName = null;

    #[ORM\Column(length: 63, nullable: true)]
    private ?string $dbUser = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $webPath = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $linuxUsername = null;

    #[ORM\Column(length: 20, enumType: SshAuthMethod::class)]
    private SshAuthMethod $sshAuthMethod = SshAuthMethod::Password;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sshPublicKeyFingerprint = null;

    #[ORM\Column(length: 20, enumType: ProvisioningStatus::class)]
    private ProvisioningStatus $provisioningStatus = ProvisioningStatus::Pending;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $provisioningError = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $provisionedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deprovisionedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, ProvisioningEvent> */
    #[ORM\OneToMany(mappedBy: 'projet', targetEntity: ProvisioningEvent::class, orphanRemoval: true)]
    private Collection $provisioningEvents;

    public function __construct(DbEngine $dbEngine)
    {
        $this->dbEngine = $dbEngine;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->provisioningEvents = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEleve(): ?Eleve
    {
        return $this->eleve;
    }

    public function setEleve(?Eleve $eleve): static
    {
        $this->eleve = $eleve;

        return $this;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getDbEngine(): DbEngine
    {
        return $this->dbEngine;
    }

    public function getDbName(): ?string
    {
        return $this->dbName;
    }

    public function getDbUser(): ?string
    {
        return $this->dbUser;
    }

    public function getWebPath(): ?string
    {
        return $this->webPath;
    }

    public function getLinuxUsername(): ?string
    {
        return $this->linuxUsername;
    }

    public function getSshAuthMethod(): SshAuthMethod
    {
        return $this->sshAuthMethod;
    }

    public function setSshAuthMethod(SshAuthMethod $sshAuthMethod): static
    {
        $this->sshAuthMethod = $sshAuthMethod;

        return $this;
    }

    public function getSshPublicKeyFingerprint(): ?string
    {
        return $this->sshPublicKeyFingerprint;
    }

    public function setSshPublicKeyFingerprint(?string $fingerprint): static
    {
        $this->sshPublicKeyFingerprint = $fingerprint;

        return $this;
    }

    public function getProvisioningStatus(): ProvisioningStatus
    {
        return $this->provisioningStatus;
    }

    public function getProvisioningError(): ?string
    {
        return $this->provisioningError;
    }

    /**
     * Assigne les identifiants système déterminés par l'orchestrateur de
     * provisioning (Phase 2+) avant l'appel à l'agent.
     */
    public function assignProvisioningTargets(string $linuxUsername, string $dbName, string $dbUser, string $webPath): static
    {
        $this->linuxUsername = $linuxUsername;
        $this->dbName = $dbName;
        $this->dbUser = $dbUser;
        $this->webPath = $webPath;
        $this->touch();

        return $this;
    }

    public function markInProgress(): static
    {
        $this->provisioningStatus = ProvisioningStatus::InProgress;
        $this->provisioningError = null;
        $this->touch();

        return $this;
    }

    public function markProvisioned(): static
    {
        $this->provisioningStatus = ProvisioningStatus::Provisioned;
        $this->provisioningError = null;
        $this->provisionedAt = new \DateTimeImmutable();
        $this->touch();

        return $this;
    }

    public function markFailed(string $error): static
    {
        $this->provisioningStatus = ProvisioningStatus::Failed;
        $this->provisioningError = $error;
        $this->touch();

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @return Collection<int, ProvisioningEvent>
     */
    public function getProvisioningEvents(): Collection
    {
        return $this->provisioningEvents;
    }
}
