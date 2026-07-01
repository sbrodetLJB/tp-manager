<?php

namespace App\Controller;

use App\Entity\CredentialReveal;
use App\Service\Provisioning\CredentialRevealTokenManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CredentialController extends AbstractController
{
    #[Route('/projets/{id}/credentials/{revealToken}', name: 'credential_reveal', methods: ['GET'])]
    public function reveal(
        int $id,
        #[MapEntity(mapping: ['revealToken' => 'revealToken'])] CredentialReveal $credentialReveal,
        CredentialRevealTokenManager $tokenManager,
    ): Response {
        // L'id du projet dans l'URL n'est utilisé que pour la lisibilité du
        // lien ; le jeton (globalement unique) est la seule preuve d'accès —
        // on vérifie ici qu'ils désignent bien le même projet, par défense en
        // profondeur (détecte un lien mal formé plutôt qu'un vrai problème
        // de sécurité, le jeton étant déjà suffisant).
        if ($credentialReveal->getProjet()->getId() !== $id) {
            throw $this->createNotFoundException();
        }

        $secrets = $tokenManager->reveal($credentialReveal);

        if (null === $secrets) {
            return $this->render('credential/already_viewed.html.twig', [
                'credentialReveal' => $credentialReveal,
            ]);
        }

        return $this->render('credential/reveal.html.twig', [
            'projet' => $credentialReveal->getProjet(),
            'secrets' => $secrets,
        ]);
    }
}
