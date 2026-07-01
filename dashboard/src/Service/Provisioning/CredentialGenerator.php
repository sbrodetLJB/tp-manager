<?php

namespace App\Service\Provisioning;

/**
 * Génère des secrets initiaux (compte SSH, utilisateur BDD). Alphabet restreint
 * à [A-Za-z0-9] par construction : évite tout besoin d'échapper le mot de passe
 * côté scripts shell/SQL (voir agent/scripts/tpagent-mysql-provision.sh).
 *
 * Le mécanisme de distribution "show-once" (CredentialReveal) arrive en
 * Phase 3 ; en Phase 2 le secret n'est affiché qu'une fois sur la page de
 * résultat du provisioning et n'est jamais persisté en clair.
 */
final class CredentialGenerator
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';

    public function generate(int $length = 24): string
    {
        $alphabetLength = strlen(self::ALPHABET);
        $secret = '';

        for ($i = 0; $i < $length; $i++) {
            $secret .= self::ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return $secret;
    }
}
