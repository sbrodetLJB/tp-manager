<?php

namespace App\Entity;

use App\Repository\CredentialRevealRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Distribution "affichage unique" d'un secret généré au provisioning (mots
 * de passe SSH/BDD) : ce n'est PAS un magasin de secrets, juste un relais.
 * Après la première consultation, secretCiphertext est effacé — seule la
 * trace ("consulté le ...") est conservée pour l'audit.
 */
#[ORM\Entity(repositoryClass: CredentialRevealRepository::class)]
class CredentialReveal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Projet::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Projet $projet = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $revealToken;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $secretCiphertext;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $viewedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Projet $projet, string $revealToken, string $secretCiphertext, \DateTimeImmutable $expiresAt)
    {
        $this->projet = $projet;
        $this->revealToken = $revealToken;
        $this->secretCiphertext = $secretCiphertext;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProjet(): ?Projet
    {
        return $this->projet;
    }

    public function getRevealToken(): string
    {
        return $this->revealToken;
    }

    public function getSecretCiphertext(): ?string
    {
        return $this->secretCiphertext;
    }

    public function isViewed(): bool
    {
        return null !== $this->viewedAt;
    }

    public function getViewedAt(): ?\DateTimeImmutable
    {
        return $this->viewedAt;
    }

    /**
     * Marque le secret comme consulté et l'efface — irréversible.
     */
    public function markViewedAndWipe(): void
    {
        $this->viewedAt = new \DateTimeImmutable();
        $this->secretCiphertext = null;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
