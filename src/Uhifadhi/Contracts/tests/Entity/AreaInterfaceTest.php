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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Contracts\Entity\AreaInterface;

/**
 * The area contract asks three questions — which area is this (the id), what is
 * it called (the name), and how is it addressed across a boundary (the uuid
 * string). Two things are worth a test: that the published surface is exactly
 * those and no wider, and that answering them costs an implementer nothing but
 * the answers.
 */
final class AreaInterfaceTest extends TestCase
{
    /**
     * THE SURFACE, TYPED OUT BY HAND — the same discipline {@see UserInterfaceTest}
     * uses: a list derived from the interface would agree with whatever the
     * interface happens to say; written out separately, the two disagree loudly
     * the day somebody widens a contract other people implement.
     *
     * @return list<array{string, string}>
     */
    public static function surface(): array
    {
        return [
            ['getId', '?int'],
            ['getName', '?string'],
            ['getUuidString', '?string'],
        ];
    }

    public function testTheContractPublishesExactlyTheMeasuredSurface(): void
    {
        $reflection = new \ReflectionClass(AreaInterface::class);

        self::assertTrue($reflection->isInterface(), 'The area contract is an interface, not a class.');

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
    public function testEveryQuestionAsksForNothingAndReturnsAValue(string $method, string $returnType): void
    {
        $reflection = new \ReflectionMethod(AreaInterface::class, $method);

        self::assertSame([], $reflection->getParameters(), \sprintf('%s() is a question, so it takes no arguments.', $method));
        self::assertSame($returnType, (string) $reflection->getReturnType());
    }

    /**
     * MAPPING-FREE, AND THE TEST SAYS SO. The whole point of this package is that
     * depending on it costs nothing, so the area contract may not reach for
     * Doctrine, for symfony/uid, or for anything else.
     */
    public function testTheContractImportsNothing(): void
    {
        $file = (new \ReflectionClass(AreaInterface::class))->getFileName();
        self::assertIsString($file);

        $source = file_get_contents($file);
        self::assertIsString($source);

        self::assertSame(0, preg_match('/^use /m', $source), 'The area contract imports nothing: no ORM, no uid, no framework.');
    }

    /**
     * An implementer supplies the answers and inherits no machinery — the whole
     * contract is satisfiable by a class with three getters and no parent, and a
     * consumer holding it only as an {@see AreaInterface} can ask all three.
     */
    public function testAnImplementationNeedsNothingButItsAnswers(): void
    {
        $area = new class implements AreaInterface {
            public ?int $id = 42;
            public ?string $name = 'Ngorongoro';
            public ?string $uuid = '018f1c2d-0000-7000-8000-000000000000';

            public function getId(): ?int
            {
                return $this->id;
            }

            public function getName(): ?string
            {
                return $this->name;
            }

            public function getUuidString(): ?string
            {
                return $this->uuid;
            }
        };

        // A consumer that knows the value only as the published contract.
        $contract = $area;
        self::assertInstanceOf(AreaInterface::class, $contract);
        self::assertSame(42, $contract->getId());
        self::assertSame('Ngorongoro', $contract->getName());
        self::assertSame('018f1c2d-0000-7000-8000-000000000000', $contract->getUuidString());

        // An area that has never been stored is an answer the contract allows.
        $area->id = null;
        $area->name = null;
        $area->uuid = null;
        self::assertNull($contract->getId());
        self::assertNull($contract->getName());
        self::assertNull($contract->getUuidString());
    }
}
