<?php

namespace App\Entity;

use App\Enum\DbEngine;
use App\Repository\EtablissementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Singleton en V1 : un seul établissement, une seule VM de TP cible.
 */
#[ORM\Entity(repositoryClass: EtablissementRepository::class)]
class Etablissement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $nom = '';

    #[ORM\Column(length: 20, enumType: DbEngine::class)]
    private DbEngine $dbEngine = DbEngine::Mysql;

    #[ORM\Column(length: 255)]
    private string $webRootBase = '/var/www/html';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToOne(mappedBy: 'etablissement', targetEntity: AgentConnection::class, cascade: ['persist', 'remove'])]
    private ?AgentConnection $agentConnection = null;

    /** @var Collection<int, NamingPattern> */
    #[ORM\OneToMany(mappedBy: 'etablissement', targetEntity: NamingPattern::class, orphanRemoval: true)]
    private Collection $namingPatterns;

    /** @var Collection<int, Classe> */
    #[ORM\OneToMany(mappedBy: 'etablissement', targetEntity: Classe::class, orphanRemoval: true)]
    private Collection $classes;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->namingPatterns = new ArrayCollection();
        $this->classes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function setDbEngine(DbEngine $dbEngine): static
    {
        $this->dbEngine = $dbEngine;

        return $this;
    }

    public function getWebRootBase(): string
    {
        return $this->webRootBase;
    }

    public function setWebRootBase(string $webRootBase): static
    {
        $this->webRootBase = $webRootBase;

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

    public function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getAgentConnection(): ?AgentConnection
    {
        return $this->agentConnection;
    }

    /**
     * @return Collection<int, NamingPattern>
     */
    public function getNamingPatterns(): Collection
    {
        return $this->namingPatterns;
    }

    public function getActiveNamingPattern(): ?NamingPattern
    {
        foreach ($this->namingPatterns as $pattern) {
            if ($pattern->isActive()) {
                return $pattern;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, Classe>
     */
    public function getClasses(): Collection
    {
        return $this->classes;
    }
}
