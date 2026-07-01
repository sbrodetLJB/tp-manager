<?php

namespace App\Service\Naming;

/**
 * Données d'un élève nécessaires au rendu d'un gabarit de nommage, découplées
 * de l'entité Eleve pour garder LoginPatternRenderer testable sans Doctrine.
 */
final class NamingContext
{
    public function __construct(
        public readonly string $nom,
        public readonly string $prenom,
        public readonly ?string $matricule = null,
        public readonly ?string $anneeScolaire = null,
    ) {
    }
}
