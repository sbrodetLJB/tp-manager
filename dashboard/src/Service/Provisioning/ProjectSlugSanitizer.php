<?php

namespace App\Service\Provisioning;

/**
 * Sanitize le nom d'un projet en segment de chemin pour /var/www/html/<login>/<slug>.
 * Contrairement à LoginSanitizer (identifiants Linux/SQL, pas de tiret), le
 * tiret est autorisé ici — voir agent/src/tpagent/util/sanitize.py::validate_path_segment.
 */
final class ProjectSlugSanitizer
{
    // Même table que LoginSanitizer::TRANSLITERATION_TABLE.
    private const TRANSLITERATION_TABLE = [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
        'ç' => 'c',
        'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'é' => 'e',
        'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'í' => 'i',
        'ñ' => 'n',
        'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'õ' => 'o',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
        'ý' => 'y', 'ÿ' => 'y',
        'œ' => 'oe', 'æ' => 'ae',
        'ß' => 'ss',
    ];

    public function sanitize(string $value, int $maxLength = 100): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $value = strtr($value, self::TRANSLITERATION_TABLE);
        $value = preg_replace('/[^\x20-\x7E]/u', '', $value) ?? '';
        $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? '';
        $value = preg_replace('/^[^a-z0-9]+/', '', $value) ?? '';
        $value = rtrim($value, '-_');

        if (mb_strlen($value) > $maxLength) {
            $value = rtrim(mb_substr($value, 0, $maxLength), '-_');
        }

        return $value;
    }
}
