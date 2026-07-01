<?php

namespace App\Service\Provisioning;

/**
 * Calcule l'empreinte SHA256 d'une clé publique SSH au même format que
 * `ssh-keygen -lf` (base64 non paddé), pour affichage/traçabilité — la clé
 * publique elle-même n'est jamais persistée, seule son empreinte l'est.
 */
final class SshPublicKeyFingerprintCalculator
{
    public function calculate(string $publicKey): string
    {
        $parts = preg_split('/\s+/', trim($publicKey));

        if (count($parts) < 2) {
            throw new \InvalidArgumentException('Clé publique SSH invalide (format attendu : "type base64 [commentaire]").');
        }

        $keyData = base64_decode($parts[1], true);

        if (false === $keyData) {
            throw new \InvalidArgumentException('Clé publique SSH invalide : partie base64 illisible.');
        }

        return 'SHA256:'.rtrim(base64_encode(hash('sha256', $keyData, true)), '=');
    }
}
