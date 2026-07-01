<?php

namespace App\Tests\Unit;

use App\Service\Provisioning\SshPublicKeyFingerprintCalculator;
use PHPUnit\Framework\TestCase;

final class SshPublicKeyFingerprintCalculatorTest extends TestCase
{
    private SshPublicKeyFingerprintCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new SshPublicKeyFingerprintCalculator();
    }

    public function testCalculatesAStableSha256FingerprintFormat(): void
    {
        $key = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIBXTGO1n9VfMbDx0GkgHSeqQzHLKzGRLvfhTdMga3rHV eleve@laptop';

        $fingerprint = $this->calculator->calculate($key);

        $this->assertMatchesRegularExpression('/^SHA256:[A-Za-z0-9+\/]{40,50}$/', $fingerprint);
        $this->assertSame($fingerprint, $this->calculator->calculate($key), 'Le calcul doit être déterministe.');
    }

    public function testDifferentKeysProduceDifferentFingerprints(): void
    {
        $keyA = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIBXTGO1n9VfMbDx0GkgHSeqQzHLKzGRLvfhTdMga3rHV';
        $keyB = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIEXTGO1n9VfMbDx0GkgHSeqQzHLKzGRLvfhTdMga3rHV';

        $this->assertNotSame($this->calculator->calculate($keyA), $this->calculator->calculate($keyB));
    }

    public function testIgnoresTrailingCommentAndWhitespace(): void
    {
        $withoutComment = 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABgQC7';
        $withComment = "  ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABgQC7   eleve@example.com\n";

        $this->assertSame(
            $this->calculator->calculate($withoutComment),
            $this->calculator->calculate($withComment),
        );
    }

    public function testRejectsKeyWithoutBase64Part(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calculator->calculate('not-a-valid-key');
    }

    public function testRejectsInvalidBase64Content(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calculator->calculate('ssh-rsa ***not-base64***');
    }
}
