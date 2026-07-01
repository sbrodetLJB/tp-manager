<?php

namespace App\Tests\Unit;

use App\Service\Naming\LoginPatternRenderer;
use App\Service\Naming\LoginSanitizer;
use App\Service\Naming\NamingContext;
use PHPUnit\Framework\TestCase;

final class LoginPatternRendererTest extends TestCase
{
    private LoginPatternRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new LoginPatternRenderer(new LoginSanitizer());
    }

    public function testPrenomDotNomTemplate(): void
    {
        $context = new NamingContext(nom: 'Dupont', prenom: 'Jean');

        // Le point n'est pas dans la whitelist [a-z0-9_] (cohérent avec les
        // contraintes de nommage des comptes Linux) : il devient un underscore.
        $this->assertSame('jean_dupont', $this->renderer->render('{prenom}.{nom}', $context));
    }

    public function testInitialePrenomNomTemplate(): void
    {
        $context = new NamingContext(nom: 'Dupont', prenom: 'Jean');

        $this->assertSame('jdupont', $this->renderer->render('{initiale_prenom}{nom}', $context));
    }

    public function testMatriculeTemplate(): void
    {
        $context = new NamingContext(nom: 'Dupont', prenom: 'Jean', matricule: 'A12345');

        $this->assertSame('a12345', $this->renderer->render('{matricule}', $context));
    }

    public function testAnneeToken(): void
    {
        $context = new NamingContext(nom: 'Dupont', prenom: 'Jean', anneeScolaire: '2025');

        $this->assertSame('dupont2025', $this->renderer->render('{nom}{annee}', $context));
    }

    public function testAccentedNamesAreSanitizedAfterRendering(): void
    {
        $context = new NamingContext(nom: 'Éléonore', prenom: 'Amélie');

        $this->assertSame('amelie_eleonore', $this->renderer->render('{prenom}.{nom}', $context));
    }

    public function testMaxLengthIsAppliedToTheFinalRenderedLogin(): void
    {
        $context = new NamingContext(nom: 'Vandenberghe', prenom: 'Maximilien');

        $this->assertSame('maximilien_vand', $this->renderer->render('{prenom}_{nom}', $context, 15));
    }
}
