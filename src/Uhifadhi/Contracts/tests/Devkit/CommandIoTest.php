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

namespace Uhifadhi\Contracts\Tests\Devkit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Contracts\Devkit\CommandIo;

/**
 * The three streams a command handler may talk through, and the reason there
 * are exactly three of them.
 *
 * A handler is the process contract — tail in, exit code out — and without a
 * channel its only way to say what it did is \STDOUT directly, which escapes
 * whatever the console was wired to: it ignores `--quiet`, cannot be captured,
 * and appears uninvited in a test run. This contract closes that hole while
 * importing nothing, and what this suite guards is that it stays that small:
 * three verbs, no verbosity, no formatter, no OutputInterface.
 */
final class CommandIoTest extends TestCase
{
    /**
     * THE SURFACE, TYPED OUT BY HAND. Anything that widens it — a verbosity, a
     * section, a `write` that does not end a line — is the console being
     * reimplemented here, and this list disagrees before the release does.
     *
     * @return list<array{string, string}>
     */
    public static function surface(): array
    {
        return [
            ['error', 'void'],
            ['readLine', '?string'],
            ['write', 'void'],
        ];
    }

    public function testTheContractPublishesExactlyTheMeasuredSurface(): void
    {
        $reflection = new \ReflectionClass(CommandIo::class);

        self::assertTrue($reflection->isInterface(), 'The io contract is an interface, not a class.');

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
    public function testEveryVerbHasTheMeasuredShape(string $method, string $returnType): void
    {
        $reflection = new \ReflectionMethod(CommandIo::class, $method);

        self::assertSame($returnType, (string) $reflection->getReturnType());
        self::assertSame(
            'readLine' === $method ? 0 : 1,
            $reflection->getNumberOfParameters(),
            'Writing takes the one line to write; reading takes nothing.',
        );
    }

    /**
     * FRAMEWORK-FREE, AND THE TEST SAYS SO. The whole point of describing the
     * streams here rather than passing an OutputInterface is that an
     * always-installed module can name this in production, where devkit and its
     * console are absent. One import would undo that.
     */
    public function testTheContractImportsNothing(): void
    {
        $file = (new \ReflectionClass(CommandIo::class))->getFileName();
        self::assertIsString($file);

        $source = file_get_contents($file);
        self::assertIsString($source);

        self::assertSame(0, preg_match('/^use /m', $source), 'The io contract imports nothing: no console, no framework.');
    }

    /**
     * The two streams are separate ones. What a command produced is pipeable;
     * why it refused must survive that pipe without corrupting it — so an
     * implementation that sent both to one place would be wrong, and the
     * contract keeps them apart by having two verbs rather than a flag.
     */
    public function testTheStreamsAreKeptApartAndInputIsConsumedALineAtATime(): void
    {
        $io = new class implements CommandIo {
            /** @var list<string> */
            public array $out = [];

            /** @var list<string> */
            public array $err = [];

            /** @var list<string> */
            public array $in = ['first', 'second'];

            public function write(string $line): void
            {
                $this->out[] = $line;
            }

            public function error(string $line): void
            {
                $this->err[] = $line;
            }

            public function readLine(): ?string
            {
                return array_shift($this->in);
            }
        };

        $io->write('made the thing');
        $io->error('could not make the other thing');

        self::assertSame(['made the thing'], $io->out);
        self::assertSame(['could not make the other thing'], $io->err);

        self::assertSame('first', $io->readLine());
        self::assertSame('second', $io->readLine());
        self::assertNull($io->readLine(), 'Null is the end of input — nothing more to read, unlike an empty line.');
    }
}
