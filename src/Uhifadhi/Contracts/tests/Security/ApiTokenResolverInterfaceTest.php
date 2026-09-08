<?php

declare(strict_types=1);

/*
 * This file is part of the Uhifadhi core.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Uhifadhi\Contracts\Tests\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Contracts\Security\ApiTokenResolverInterface;

/**
 * THE CUT BETWEEN AUTHENTICATING AND KNOWING WHO.
 *
 * Two questions, and they are the whole contract: turn a presented string into
 * a person, and record that it was seen. Everything a credential store actually
 * does — how the string is hashed, when it expires, which device it was minted
 * for, how it is withdrawn — is behind it, because whoever authenticates a
 * request has no business knowing any of that.
 *
 * The surface is typed out by hand rather than derived, so widening it is a
 * disagreement somebody has to argue for.
 */
final class ApiTokenResolverInterfaceTest extends TestCase
{
    /**
     * @return list<array{string, string}>
     */
    public static function surface(): array
    {
        return [
            ['find', '?Uhifadhi\Contracts\Entity\UserInterface'],
            ['touch', 'void'],
        ];
    }

    public function testTheContractPublishesExactlyTheTwoQuestions(): void
    {
        $reflection = new \ReflectionClass(ApiTokenResolverInterface::class);

        self::assertTrue($reflection->isInterface(), 'The resolver contract is an interface, not a class.');

        $declared = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods(),
        );
        sort($declared);

        $expected = array_column(self::surface(), 0);
        sort($expected);

        self::assertSame($expected, $declared);
    }

    /**
     * @param non-empty-string $method
     */
    #[DataProvider('surface')]
    public function testEachQuestionAnswersTheDeclaredType(string $method, string $type): void
    {
        $return = new \ReflectionMethod(ApiTokenResolverInterface::class, $method)->getReturnType();

        self::assertInstanceOf(\ReflectionNamedType::class, $return);
        self::assertSame($type, ($return->allowsNull() && 'void' !== $return->getName() ? '?' : '').$return->getName());
    }

    /**
     * BOTH TAKE THE PRESENTED STRING, and that is deliberate. Recording a use
     * against a token OBJECT would put the credential store's own row in the
     * contract, which is exactly the knowledge the cut exists to withhold.
     *
     * @param non-empty-string $method
     * @param non-empty-string $type   the declared return, carried by the same provider
     */
    #[DataProvider('surface')]
    public function testEachQuestionIsAskedWithThePresentedStringAlone(string $method, string $type): void
    {
        self::assertNotSame('', $type);

        $parameters = new \ReflectionMethod(ApiTokenResolverInterface::class, $method)->getParameters();

        self::assertCount(1, $parameters);
        self::assertSame('presented', $parameters[0]->getName());

        $parameterType = $parameters[0]->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $parameterType);
        self::assertSame('string', $parameterType->getName());
    }
}
