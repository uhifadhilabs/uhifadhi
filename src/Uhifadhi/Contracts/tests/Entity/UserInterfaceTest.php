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

namespace Uhifadhi\Contracts\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Contracts\Entity\UserInterface;

/**
 * The user contract is a list of questions and nothing else. Two things are
 * worth a test: that the published surface is exactly the surface modules were
 * measured to need — no wider — and that answering it costs an implementer
 * nothing but the answers.
 */
final class UserInterfaceTest extends TestCase
{
    /**
     * THE SURFACE, TYPED OUT BY HAND. The same discipline the shell's
     * LayoutContract uses: a list derived from the interface would agree with
     * whatever the interface happens to say. Written out separately, the two
     * disagree loudly the day somebody widens the contract without arguing for
     * it — which for a contract other people implement is a breaking change.
     *
     * @return list<array{string, string}>
     */
    public static function surface(): array
    {
        return [
            ['getId', '?int'],
            ['getUuidString', '?string'],
            ['getEmail', '?string'],
            ['getFirstName', '?string'],
            ['getLastName', '?string'],
            ['getFullName', 'string'],
            ['getRangerCode', '?string'],
        ];
    }

    public function testTheContractPublishesExactlyTheMeasuredSurface(): void
    {
        $reflection = new \ReflectionClass(UserInterface::class);

        self::assertTrue($reflection->isInterface(), 'The user contract is an interface, not a class.');

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
    #[\PHPUnit\Framework\Attributes\DataProvider('surface')]
    public function testEveryQuestionAsksForNothingAndReturnsAValue(string $method, string $returnType): void
    {
        $reflection = new \ReflectionMethod(UserInterface::class, $method);

        self::assertSame([], $reflection->getParameters(), \sprintf('%s() is a question, so it takes no arguments.', $method));
        self::assertSame($returnType, (string) $reflection->getReturnType());
    }

    /**
     * MAPPING-FREE, AND THE TEST SAYS SO. The whole point of this package is
     * that depending on it costs nothing, so the user contract may not reach
     * for Doctrine, for symfony/uid, or for anything else. That is why the
     * public address is asked for as a string rather than as a Uuid object.
     */
    public function testTheContractImportsNothing(): void
    {
        $file = (new \ReflectionClass(UserInterface::class))->getFileName();
        self::assertIsString($file);

        $source = file_get_contents($file);
        self::assertIsString($source);

        self::assertSame(0, preg_match('/^use /m', $source), 'The user contract imports nothing: no ORM, no uid, no framework.');
    }

    /**
     * An implementer supplies answers and inherits no machinery — the whole
     * contract is satisfiable by a class with seven getters and no parent.
     */
    public function testAnImplementationNeedsNothingButAnswers(): void
    {
        $user = new class implements UserInterface {
            public ?int $id = 7;
            public ?string $uuid = '0198f5b1-0000-7000-8000-000000000001';
            public ?string $email = 'asha@example.org';
            public ?string $firstName = 'Asha';
            public ?string $lastName = 'Mollel';
            public ?string $rangerCode = 'sl-0142';

            public function getId(): ?int
            {
                return $this->id;
            }

            public function getUuidString(): ?string
            {
                return $this->uuid;
            }

            public function getEmail(): ?string
            {
                return $this->email;
            }

            public function getFirstName(): ?string
            {
                return $this->firstName;
            }

            public function getLastName(): ?string
            {
                return $this->lastName;
            }

            public function getFullName(): string
            {
                return trim(($this->firstName ?? '').' '.($this->lastName ?? ''));
            }

            public function getRangerCode(): ?string
            {
                return $this->rangerCode;
            }
        };

        self::assertSame(7, $user->getId());
        self::assertSame('0198f5b1-0000-7000-8000-000000000001', $user->getUuidString());
        self::assertSame('asha@example.org', $user->getEmail());
        self::assertSame('Asha Mollel', $user->getFullName());
        self::assertSame('sl-0142', $user->getRangerCode());

        // An account that has never been stored, and an office worker who was
        // never issued a service number: both are answers the contract allows.
        $user->id = null;
        $user->uuid = null;
        $user->rangerCode = null;
        $user->lastName = null;

        self::assertNull($user->getId());
        self::assertNull($user->getUuidString());
        self::assertNull($user->getRangerCode());
        self::assertSame('Asha', $user->getFullName());
    }
}
