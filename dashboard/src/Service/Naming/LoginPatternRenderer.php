<?php

namespace App\Service\Naming;

/**
 * Rend un gabarit de nommage (ex: "{prenom}.{nom}") en login sanitizé.
 * Jetons supportés : {prenom} {nom} {initiale_prenom} {initiale_nom} {matricule} {annee}
 */
final class LoginPatternRenderer
{
    public function __construct(private readonly LoginSanitizer $sanitizer)
    {
    }

    public function render(string $template, NamingContext $context, ?int $maxLength = null): string
    {
        $tokens = [
            '{prenom}' => $context->prenom,
            '{nom}' => $context->nom,
            '{initiale_prenom}' => mb_substr($context->prenom, 0, 1),
            '{initiale_nom}' => mb_substr($context->nom, 0, 1),
            '{matricule}' => $context->matricule ?? '',
            '{annee}' => $context->anneeScolaire ?? '',
        ];

        $rendered = strtr($template, $tokens);

        return $this->sanitizer->sanitize($rendered, $maxLength);
    }
}
