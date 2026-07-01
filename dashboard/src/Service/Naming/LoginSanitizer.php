<?php

namespace App\Service\Naming;

/**
 * Frontière de confiance côté UX uniquement : l'agent (agent/src/tpagent/util/sanitize.py)
 * revalide indépendamment tout identifiant avant de l'utiliser dans une commande
 * système ou une requête SQL — voir docs/security.md.
 */
final class LoginSanitizer
{
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

    public function sanitize(string $value, ?int $maxLength = null): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $value = strtr($value, self::TRANSLITERATION_TABLE);
        // Tout caractère non-ASCII restant (autres accents, emoji, idéogrammes...) est écarté.
        $value = preg_replace('/[^\x20-\x7E]/u', '', $value) ?? '';
        // Whitelist stricte : toute suite de caractères hors [a-z0-9_] devient un underscore unique.
        $value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?? '';
        // Un login ne doit pas commencer par un chiffre ou un underscore (contrainte Linux).
        $value = preg_replace('/^[0-9_]+/', '', $value) ?? '';
        $value = rtrim($value, '_');

        if (null !== $maxLength && mb_strlen($value) > $maxLength) {
            $value = rtrim(mb_substr($value, 0, $maxLength), '_');
        }

        return $value;
    }
}
