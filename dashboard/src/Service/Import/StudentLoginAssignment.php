<?php

namespace App\Service\Import;

final class StudentLoginAssignment
{
    public function __construct(
        public readonly CsvStudentRow $row,
        public readonly string $login,
        public readonly ?int $suffix,
    ) {
    }
}
