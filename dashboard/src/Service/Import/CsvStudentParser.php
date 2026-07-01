<?php

namespace App\Service\Import;

/**
 * Parse un CSV de liste d'élèves. Colonnes attendues (insensibles à la casse,
 * ordre libre) : nom, prenom, matricule (optionnelle). Le délimiteur (`,` ou
 * `;`, ce dernier étant l'export usuel d'Excel en France) est détecté sur la
 * ligne d'en-tête.
 */
final class CsvStudentParser
{
    private const REQUIRED_COLUMNS = ['nom', 'prenom'];

    /**
     * @return CsvStudentRow[]
     */
    public function parse(string $csvContent): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csvContent));
        $lines = array_values(array_filter($lines, static fn (string $line) => '' !== trim($line)));

        if ([] === $lines) {
            throw new InvalidCsvException('Le fichier CSV est vide.');
        }

        $delimiter = substr_count($lines[0], ';') >= substr_count($lines[0], ',') ? ';' : ',';

        $header = array_map(
            static fn (string $column) => mb_strtolower(trim($column)),
            str_getcsv($lines[0], $delimiter),
        );

        foreach (self::REQUIRED_COLUMNS as $required) {
            if (!in_array($required, $header, true)) {
                throw new InvalidCsvException(sprintf('Colonne obligatoire manquante dans l\'en-tête du CSV : "%s".', $required));
            }
        }

        $rows = [];
        foreach (array_slice($lines, 1) as $lineNumber => $line) {
            $values = str_getcsv($line, $delimiter);
            $columns = array_combine($header, array_pad($values, count($header), null));

            $nom = trim((string) ($columns['nom'] ?? ''));
            $prenom = trim((string) ($columns['prenom'] ?? ''));

            if ('' === $nom || '' === $prenom) {
                throw new InvalidCsvException(sprintf('Ligne %d invalide : nom et prénom sont obligatoires.', $lineNumber + 2));
            }

            $matricule = trim((string) ($columns['matricule'] ?? ''));

            $rows[] = new CsvStudentRow($nom, $prenom, '' !== $matricule ? $matricule : null);
        }

        return $rows;
    }
}
