<?php

namespace App\Entity;

use App\Repository\NamingPatternRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NamingPatternRepository::class)]
class NamingPattern
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'namingPatterns', targetEntity: Etablissement::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Etablissement $etablissement = null;

    #[ORM\Column(length: 100)]
    private string $label = '';

    /**
     * Jetons supportés : {prenom} {nom} {initiale_prenom} {initiale_nom} {matricule} {annee}
     */
    #[ORM\Column(length: 100)]
    private string $template = '{prenom}.{nom}';

    #[ORM\Column]
    private bool $isActive = false;

    /**
     * Plafond de longueur avant résolution de collision (ex: 32 pour un login Linux).
     */
    #[ORM\Column(nullable: true)]
    private ?int $maxLength = 32;

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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function setTemplate(string $template): static
    {
        $this->template = $template;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    public function setMaxLength(?int $maxLength): static
    {
        $this->maxLength = $maxLength;

        return $this;
    }
}
