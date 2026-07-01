<?php

namespace App\Entity;

use App\Repository\EleveRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Le login est unique établissement-wide (et non par classe) car il est mappé
 * à un compte Linux sur une seule VM de TP partagée.
 */
#[ORM\Entity(repositoryClass: EleveRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_eleve_login', columns: ['login'])]
class Eleve
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'eleves', targetEntity: Classe::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Classe $classe = null;

    #[ORM\Column(length: 255)]
    private string $nom = '';

    #[ORM\Column(length: 255)]
    private string $prenom = '';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $matricule = null;

    #[ORM\Column(length: 32)]
    private string $login = '';

    #[ORM\Column(nullable: true)]
    private ?int $loginSuffix = null;

    #[ORM\Column]
    private \DateTimeImmutable $importedAt;

    #[ORM\Column]
    private bool $active = true;

    /** @var Collection<int, Projet> */
    #[ORM\OneToMany(mappedBy: 'eleve', targetEntity: Projet::class, orphanRemoval: true)]
    private Collection $projets;

    public function __construct()
    {
        $this->importedAt = new \DateTimeImmutable();
        $this->projets = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClasse(): ?Classe
    {
        return $this->classe;
    }

    public function setClasse(?Classe $classe): static
    {
        $this->classe = $classe;

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

    public function getPrenom(): string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getMatricule(): ?string
    {
        return $this->matricule;
    }

    public function setMatricule(?string $matricule): static
    {
        $this->matricule = $matricule;

        return $this;
    }

    public function getLogin(): string
    {
        return $this->login;
    }

    public function setLogin(string $login): static
    {
        $this->login = $login;

        return $this;
    }

    public function getLoginSuffix(): ?int
    {
        return $this->loginSuffix;
    }

    public function setLoginSuffix(?int $loginSuffix): static
    {
        $this->loginSuffix = $loginSuffix;

        return $this;
    }

    public function getImportedAt(): \DateTimeImmutable
    {
        return $this->importedAt;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @return Collection<int, Projet>
     */
    public function getProjets(): Collection
    {
        return $this->projets;
    }
}
