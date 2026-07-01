<?php

namespace App\Tests\Unit;

use App\Service\Naming\LoginSanitizer;
use PHPUnit\Framework\TestCase;

final class LoginSanitizerTest extends TestCase
{
    private LoginSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new LoginSanitizer();
    }

    /**
     * @dataProvider provideAccentsAndCase
     */
    public function testTransliterationAndLowercasing(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->sanitizer->sanitize($input));
    }

    public static function provideAccentsAndCase(): iterable
    {
        yield 'accents français courants' => ['Éléonore Ça-Va', 'eleonore_ca_va'];
        yield 'majuscules simples' => ['DUPONT', 'dupont'];
        yield 'cédille' => ['François', 'francois'];
        yield 'ligature œ' => ['Nœlle', 'noelle'];
        yield 'tréma' => ['Noël', 'noel'];
    }

    public function testReplacesRunsOfInvalidCharactersWithSingleUnderscore(): void
    {
        $this->assertSame('jean_michel', $this->sanitizer->sanitize("Jean--Michel!!"));
        $this->assertSame('d_artagnan', $this->sanitizer->sanitize("D'Artagnan"));
    }

    public function testStripsLeadingDigitsAndUnderscores(): void
    {
        $this->assertSame('dupont', $this->sanitizer->sanitize('42_dupont'));
        $this->assertSame('dupont', $this->sanitizer->sanitize('___dupont'));
    }

    public function testAdversarialInputsDoNotLeakUnsafeCharacters(): void
    {
        $this->assertSame('drop_table_users', $this->sanitizer->sanitize("'; DROP TABLE users; --"));
        $this->assertSame('etc_passwd', $this->sanitizer->sanitize('../../etc/passwd'));
    }

    public function testEmojiOnlyNameSanitizesToEmptyString(): void
    {
        $this->assertSame('', $this->sanitizer->sanitize('🎉🚀'));
    }

    public function testTruncatesToMaxLengthAndTrimsTrailingUnderscore(): void
    {
        $this->assertSame('dupont_jean_p', $this->sanitizer->sanitize('dupont.jean.philippe', 13));
    }

    public function testTruncationDoesNotLeaveTrailingUnderscoreEvenIfCutFallsOnSeparator(): void
    {
        // Coupe exactement sur le séparateur -> "dupont_" doit être retrimé en "dupont".
        $this->assertSame('dupont', $this->sanitizer->sanitize('dupont.jean', 7));
    }
}
