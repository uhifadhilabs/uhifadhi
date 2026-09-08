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

use PHPUnit\Framework\TestCase;
use Uhifadhi\Contracts\Devkit\CommandDescriptor;

/**
 * A descriptor is the console-free unit a module hands to devkit: a name, a help
 * line, and the closure that does the work. Like {@see \Uhifadhi\Contracts\ModulePermission},
 * every field is required — a maintenance command with no name cannot be
 * registered and one with no help line is a blank row in `list` — so a
 * descriptor that has not thought about them does not compile.
 */
final class CommandDescriptorTest extends TestCase
{
    public function testItCarriesTheNameHelpAndHandler(): void
    {
        $descriptor = new CommandDescriptor(
            'patrol:demo:reset',
            'Wipe and reseed the patrol demo content.',
            static fn (array $arguments): int => 0,
        );

        self::assertSame('patrol:demo:reset', $descriptor->name);
        self::assertSame('Wipe and reseed the patrol demo content.', $descriptor->description);

        $handler = $descriptor->handler;
        self::assertSame(0, $handler([]));
    }

    /**
     * The handler is a closure over the token tail returning an exit code — the
     * Unix process contract, and nothing from symfony/console. devkit passes the
     * arguments a person typed and uses the returned int as the command's exit
     * status.
     */
    public function testTheHandlerReceivesTheArgumentTailAndReturnsAnExitCode(): void
    {
        $descriptor = new CommandDescriptor(
            'demo:seed',
            'Seed demo content, optionally scaled by a --count argument.',
            static fn (array $arguments): int => \count($arguments),
        );

        $handler = $descriptor->handler;
        self::assertSame(0, $handler([]));
        self::assertSame(2, $handler(['--count=10', '--fresh']));
    }

    public function testEveryFieldIsRequired(): void
    {
        $reflection = new \ReflectionClass(CommandDescriptor::class);
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);
        self::assertSame(3, $constructor->getNumberOfParameters());
        self::assertSame(3, $constructor->getNumberOfRequiredParameters(), 'No field of a command descriptor is optional.');
    }

    public function testAnEmptyNameIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CommandDescriptor('   ', 'A help line.', static fn (array $arguments): int => 0);
    }

    public function testAnEmptyDescriptionIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CommandDescriptor('demo:reset', '   ', static fn (array $arguments): int => 0);
    }
}
