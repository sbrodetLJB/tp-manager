<?php

namespace App\Service\Security;

/**
 * Chiffre/déchiffre le bearer token agent au repos (AgentConnection::bearerTokenEncrypted).
 * Clé dérivée d'APP_SECRET — simplification documentée pour la V1 (voir
 * docs/security.md) : une rotation d'APP_SECRET invalide les tokens stockés,
 * qu'il faut alors régénérer via le wizard.
 */
final class AgentTokenEncryptor
{
    private const CIPHER = 'aes-256-gcm';

    private readonly string $key;

    public function __construct(string $appSecret)
    {
        $this->key = hash('sha256', $appSecret, true);
    }

    public function encrypt(string $plaintext): string
    {
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = random_bytes($ivLength);
        $tag = '';

        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        return base64_encode($iv.$tag.$ciphertext);
    }

    public function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = substr($raw, 0, $ivLength);
        $tag = substr($raw, $ivLength, 16);
        $ciphertext = substr($raw, $ivLength + 16);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        if (false === $plaintext) {
            throw new \RuntimeException("Impossible de déchiffrer le jeton agent (la clé APP_SECRET a-t-elle changé ?).");
        }

        return $plaintext;
    }
}
