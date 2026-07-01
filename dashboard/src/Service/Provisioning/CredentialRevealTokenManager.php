<?php

namespace App\Service\Provisioning;

use App\Entity\CredentialReveal;
use App\Entity\Projet;
use App\Service\Security\AgentTokenEncryptor;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Génère et consomme les liens "affichage unique" des secrets de provisioning
 * (mots de passe SSH/BDD). Réutilise AgentTokenEncryptor (chiffrement
 * générique dérivé d'APP_SECRET) plutôt que de dupliquer une primitive crypto.
 */
final class CredentialRevealTokenManager
{
    private const TTL_HOURS = 24;

    public function __construct(
        private readonly AgentTokenEncryptor $encryptor,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, string|null> $secrets
     */
    public function create(Projet $projet, array $secrets): CredentialReveal
    {
        $token = bin2hex(random_bytes(32));
        $ciphertext = $this->encryptor->encrypt(json_encode($secrets, JSON_THROW_ON_ERROR));
        $expiresAt = (new \DateTimeImmutable())->modify('+'.self::TTL_HOURS.' hours');

        $reveal = new CredentialReveal($projet, $token, $ciphertext, $expiresAt);
        $this->entityManager->persist($reveal);
        $this->entityManager->flush();

        return $reveal;
    }

    /**
     * @return array<string, string|null>|null null si déjà consulté (ou expiré et purgé)
     */
    public function reveal(CredentialReveal $credentialReveal): ?array
    {
        if ($credentialReveal->isViewed() || null === $credentialReveal->getSecretCiphertext()) {
            return null;
        }

        $secrets = json_decode(
            $this->encryptor->decrypt($credentialReveal->getSecretCiphertext()),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $credentialReveal->markViewedAndWipe();
        $this->entityManager->flush();

        return $secrets;
    }
}
