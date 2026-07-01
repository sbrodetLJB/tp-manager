<?php

namespace App\Service\Import;

use App\Entity\NamingPattern;
use App\Service\Naming\CollisionResolver;
use App\Service\Naming\LoginPatternRenderer;
use App\Service\Naming\NamingContext;

/**
 * Applique le gabarit de nommage actif à un lot d'élèves (import CSV ou ajout
 * manuel) en résolvant les collisions à la fois contre les logins déjà en
 * base ET contre ceux déjà attribués plus tôt dans le même lot (deux "Martin"
 * dans le même CSV, par exemple).
 */
final class StudentLoginAssigner
{
    public function __construct(
        private readonly LoginPatternRenderer $renderer,
        private readonly CollisionResolver $collisionResolver,
    ) {
    }

    /**
     * @param CsvStudentRow[] $rows
     * @param callable(string): bool $loginExistsInDatabase
     * @return StudentLoginAssignment[]
     */
    public function assign(array $rows, NamingPattern $pattern, callable $loginExistsInDatabase): array
    {
        $assignedInBatch = [];

        $assignments = [];
        foreach ($rows as $row) {
            $context = new NamingContext($row->nom, $row->prenom, $row->matricule);
            $baseLogin = $this->renderer->render($pattern->getTemplate(), $context, $pattern->getMaxLength());

            $existsCheck = static function (string $candidate) use ($loginExistsInDatabase, &$assignedInBatch): bool {
                return isset($assignedInBatch[$candidate]) || $loginExistsInDatabase($candidate);
            };

            $result = $this->collisionResolver->resolve($baseLogin, $existsCheck, $pattern->getMaxLength());
            $assignedInBatch[$result['login']] = true;

            $assignments[] = new StudentLoginAssignment($row, $result['login'], $result['suffix']);
        }

        return $assignments;
    }
}
