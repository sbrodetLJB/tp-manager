<?php

namespace App\Service\Naming;

/**
 * Ajoute un suffixe numérique (dupont, dupont2, dupont3...) quand un login
 * généré existe déjà. Le suffixe est appliqué après troncature à maxLength,
 * pour ne jamais dépasser la limite (ex: 32 caractères pour un login Linux).
 */
final class CollisionResolver
{
    private const MAX_ATTEMPTS = 1000;

    /**
     * @param callable(string): bool $loginExists
     * @return array{login: string, suffix: int|null}
     */
    public function resolve(string $baseLogin, callable $loginExists, ?int $maxLength = null): array
    {
        $base = null !== $maxLength ? mb_substr($baseLogin, 0, $maxLength) : $baseLogin;

        if (!$loginExists($base)) {
            return ['login' => $base, 'suffix' => null];
        }

        for ($suffix = 2; $suffix < self::MAX_ATTEMPTS; $suffix++) {
            $suffixStr = (string) $suffix;
            $truncatedBase = null !== $maxLength
                ? mb_substr($baseLogin, 0, max(0, $maxLength - mb_strlen($suffixStr)))
                : $baseLogin;
            $candidate = $truncatedBase.$suffixStr;

            if (!$loginExists($candidate)) {
                return ['login' => $candidate, 'suffix' => $suffix];
            }
        }

        throw new \RuntimeException(sprintf('Impossible de générer un login unique pour "%s" après %d tentatives.', $baseLogin, self::MAX_ATTEMPTS));
    }
}
