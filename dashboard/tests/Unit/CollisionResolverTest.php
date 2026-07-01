<?php

namespace App\Tests\Unit;

use App\Service\Naming\CollisionResolver;
use PHPUnit\Framework\TestCase;

final class CollisionResolverTest extends TestCase
{
    private CollisionResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new CollisionResolver();
    }

    public function testReturnsBaseLoginUnchangedWhenNoCollision(): void
    {
        $result = $this->resolver->resolve('dupont', fn () => false);

        $this->assertSame(['login' => 'dupont', 'suffix' => null], $result);
    }

    public function testAppendsSuffix2OnFirstCollision(): void
    {
        $existing = ['dupont'];
        $result = $this->resolver->resolve('dupont', fn (string $login) => in_array($login, $existing, true));

        $this->assertSame(['login' => 'dupont2', 'suffix' => 2], $result);
    }

    public function testIncrementsSuffixUntilUnique(): void
    {
        $existing = ['martin', 'martin2', 'martin3'];
        $result = $this->resolver->resolve('martin', fn (string $login) => in_array($login, $existing, true));

        $this->assertSame(['login' => 'martin4', 'suffix' => 4], $result);
    }

    public function testTruncatesBaseBeforeAppendingSuffixToRespectMaxLength(): void
    {
        // base de 10 caractères, maxLength=10 -> le suffixe doit "manger" sur la base tronquée.
        $existing = ['abcdefghij'];
        $result = $this->resolver->resolve('abcdefghij', fn (string $login) => in_array($login, $existing, true), 10);

        $this->assertSame(['login' => 'abcdefghi2', 'suffix' => 2], $result);
        $this->assertSame(10, mb_strlen($result['login']));
    }

    public function testThrowsAfterExhaustingAttempts(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->resolver->resolve('dupont', fn () => true);
    }
}
