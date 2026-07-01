<?php

namespace App\Service\Import;

final class CsvStudentRow
{
    public function __construct(
        public readonly string $nom,
        public readonly string $prenom,
        public readonly ?string $matricule = null,
    ) {
    }
}
