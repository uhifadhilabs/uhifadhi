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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Unit\Devkit;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Uhifadhi\Bundle\TeamBundle\Devkit\TeamCommandProvider;
use Uhifadhi\Bundle\TeamBundle\Service\UserService;
use Uhifadhi\Contracts\Devkit\CommandDescriptor;
use Uhifadhi\Contracts\Devkit\CommandIo;
use Uhifadhi\Contracts\Devkit\CommandProviderInterface;

/**
 * WHAT THE BUNDLE DECLARES, WITH NOTHING BEHIND IT.
 *
 * The provider is inert. In a production build devkit is not installed, so this
 * object is an ordinary service nobody ever asks anything of — and declaring
 * its commands runs while the container is BUILT, in that same build. So
 * declaring may not touch a database or anything else: the work belongs behind
 * the closure, never in front of it.
 *
 * That is what this suite asserts, by reading the declaration off an instance
 * built WITHOUT its collaborators. A provider that reached for one while naming
 * its commands would fatal here.
 */
#[CoversClass(TeamCommandProvider::class)]
final class TeamCommandProviderTest extends TestCase
{
    public function testItDeclaresTheFirstAdministratorCommand(): void
    {
        $commands = self::undressed()->commands();

        self::assertCount(1, $commands);
        self::assertSame('team:user:create', $commands[0]->name);
        self::assertNotSame('', trim($commands[0]->description));
    }

    public function testEveryDeclarationIsADescriptor(): void
    {
        foreach (self::undressed()->commands() as $command) {
            self::assertInstanceOf(CommandDescriptor::class, $command);
        }
    }

    /** The contract it is collected through, and the only one. */
    public function testItIsACommandProvider(): void
    {
        self::assertInstanceOf(CommandProviderInterface::class, self::undressed());
    }

    /**
     * THE DESCRIPTOR NAMES NO CONSOLE. `symfony/console` is not a requirement of
     * this bundle, and the descriptor is the reason it does not have to be: a
     * name, a help line, and a closure over the argument tail and the streams.
     *
     * The second parameter is where that stays true under pressure. A command
     * has to say what it did, and the alternative to being handed a channel is
     * writing to \STDOUT — which is not console coupling but something worse:
     * output no console can quiet, capture or redirect. So the channel is
     * {@see CommandIo}, a contract of this package, and NOT an OutputInterface —
     * the handler talks through three verbs that import nothing, and devkit
     * wires them to the real console, where symfony/console is allowed.
     */
    public function testTheDeclarationNeedsNoConsole(): void
    {
        $handler = new \ReflectionFunction(self::undressed()->commands()[0]->handler);
        $parameters = $handler->getParameters();

        self::assertCount(2, $parameters, 'The handler takes the argument tail and the io it speaks through.');
        self::assertSame('array', (string) $parameters[0]->getType());
        self::assertSame(CommandIo::class, (string) $parameters[1]->getType());
        self::assertSame('int', (string) $handler->getReturnType());
    }

    /**
     * AND IT REACHES FOR NO FILE DESCRIPTOR OF ITS OWN, which is the other half
     * of the same rule: a handler that was handed a channel and still wrote to
     * \STDOUT — or read \STDIN — would have the leak back, silently.
     */
    public function testItTouchesNoStreamOfItsOwn(): void
    {
        $file = new \ReflectionClass(TeamCommandProvider::class)->getFileName();
        self::assertIsString($file);

        $source = file_get_contents($file);
        self::assertIsString($source);

        self::assertSame(0, preg_match('/\bf(write|gets|open|read|puts)\s*\(/', $source), 'The provider speaks only through the CommandIo it is handed.');
        self::assertSame(0, preg_match('/^[^*]*\\\\STD(OUT|ERR|IN)\b/m', $source), 'No standard stream is reached for outside a docblock.');
    }

    /**
     * IT PERSISTS NOTHING ITSELF. Making an account is one set of rules — the
     * address is the identifier, the credential is hashed, the tier decides who
     * can administer — and a command that reached for an entity manager would
     * hold a second copy of them, drifting from the screens' the first time
     * either changed. So the provider parses a tail and calls the service the
     * screens call; an entity manager or a hasher in this constructor is that
     * drift beginning.
     */
    public function testItWritesThroughTheAccountServiceAndNotThroughStorage(): void
    {
        $parameters = new \ReflectionClass(TeamCommandProvider::class)->getConstructor()?->getParameters() ?? [];
        $types = array_map(static fn (\ReflectionParameter $parameter): string => (string) $parameter->getType(), $parameters);

        self::assertContains(UserService::class, $types);
        self::assertNotContains(EntityManagerInterface::class, $types);
        self::assertNotContains(UserPasswordHasherInterface::class, $types);
    }

    /** Built with no collaborators, exactly as a declaration may not need any. */
    private static function undressed(): TeamCommandProvider
    {
        return new \ReflectionClass(TeamCommandProvider::class)->newInstanceWithoutConstructor();
    }
}
