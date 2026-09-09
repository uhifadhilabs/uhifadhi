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

namespace Uhifadhi\Bundle\TeamBundle\Devkit;

use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Exception\EmailAlreadyUsedException;
use Uhifadhi\Bundle\TeamBundle\Exception\PasswordTooShortException;
use Uhifadhi\Bundle\TeamBundle\Service\UserService;
use Uhifadhi\Contracts\Devkit\CommandDescriptor;
use Uhifadhi\Contracts\Devkit\CommandIo;
use Uhifadhi\Contracts\Devkit\CommandProviderInterface;

/**
 * THE FIRST ADMINISTRATOR — the one account an installation cannot make through
 * a screen, because every screen is behind the sign-in it does not yet have.
 *
 * The core ships no console commands. What it ships instead is this: an INERT
 * provider, an ordinary tagged service that names a command and hands over a
 * closure. devkit — dev-only, installed through `require-dev` — collects every
 * such service and turns each descriptor into a real console command, so
 * `team:user:create` exists on a developer's machine and in CI and nowhere
 * else. In a production build devkit is absent, nothing collects this, and it
 * is data waiting for a tool that is not there.
 *
 * NOTHING HERE NEEDS symfony/console, and that is the point of the descriptor.
 * A name, a help line, and a closure taking the argument tail and the streams to
 * speak through, returning an exit code — the process contract rather than the
 * console one. So this bundle does not carry a console runtime to offer a
 * command, and still says what it did somewhere a console can hear: everything
 * below talks through {@see CommandIo} and touches no file descriptor of its
 * own.
 *
 * THE HANDLER PARSES ITS OWN TAIL, because the contract deliberately models no
 * options and no arguments: doing so would mean reimplementing an input
 * definition in a package whose whole claim is that depending on it costs
 * nothing.
 *
 *     team:user:create <email> <first name> <last name> [--tier=…] [--password=…]
 *
 * THE TIER DEFAULTS TO SUPER ADMIN, because the account this exists to make is
 * the one an installation is bootstrapped with — and a first administrator who
 * could not administer would leave an installation with nobody who can.
 *
 * IT WRITES NOTHING ITSELF. Making an account is one set of rules, held by
 * {@see UserService} and called by every screen that adds somebody; a command
 * with its own hasher and its own entity manager would be a second copy of them,
 * drifting from the first the moment either changed. This parses a tail and
 * calls that service.
 *
 * THE PASSWORD IS READ FROM STANDARD INPUT WHEN IT IS NOT GIVEN, so it need
 * never appear in a shell history or a process list:
 *
 *     printf '%s' "$PASSPHRASE" | bin/console team:user:create ada@example.test Ada Mwangi
 *
 * @see CommandProviderInterface
 */
final readonly class TeamCommandProvider implements CommandProviderInterface
{
    /** What a person may type after `--tier=`, and the tier each names. */
    private const array TIERS = [
        'super-admin' => TeamRoleEnum::SuperAdmin,
        'admin' => TeamRoleEnum::Admin,
        'staff' => TeamRoleEnum::Staff,
    ];

    public function __construct(
        private UserService $accounts,
    ) {
    }

    public function commands(): array
    {
        return [
            new CommandDescriptor(
                'team:user:create',
                'Create an account and set its password — the administrator an installation is bootstrapped with. Usage: <email> <first name> <last name> [--tier=super-admin|admin|staff] [--password=…]; the password is read from standard input when the option is absent.',
                fn (array $arguments, CommandIo $io): int => $this->createUser($arguments, $io),
            ),
        ];
    }

    /** @param list<string> $arguments */
    private function createUser(array $arguments, CommandIo $io): int
    {
        [$options, $positional] = self::split($arguments);

        if (3 !== \count($positional)) {
            return self::refuse($io, 'Give an email address, a first name and a last name: team:user:create <email> <first name> <last name> [--tier=super-admin|admin|staff] [--password=…].');
        }

        [$email, $firstName, $lastName] = $positional;

        $tierToken = $options['tier'] ?? 'super-admin';
        $tier = self::TIERS[$tierToken] ?? null;
        if (null === $tier) {
            return self::refuse($io, \sprintf('Unknown tier "%s". Use one of: %s.', $tierToken, implode(', ', array_keys(self::TIERS))));
        }

        $password = $options['password'] ?? self::readPassword($io);
        if ('' === trim($password)) {
            return self::refuse($io, 'A password is required. Pass --password=… or write it on standard input.');
        }

        // VERIFIED AND ACTIVE, unlike an invited account: this is the
        // credential somebody signs in with in the installation's first minute,
        // not an invitation waiting to be accepted. That is what create() means,
        // so the tier is the only thing this has to say.
        try {
            $user = $this->accounts->create($email, $firstName, $lastName, $password, $tier);
        } catch (EmailAlreadyUsedException $taken) {
            return self::refuse($io, \sprintf('An account already answers to "%s". Accounts are never duplicated; reset that one\'s password instead.', $taken->email));
        } catch (PasswordTooShortException $short) {
            return self::refuse($io, $short->getMessage());
        }

        $io->write(\sprintf('Created %s <%s> as %s.', $user->getFullName(), $user->getUserIdentifier(), $tier->label()));

        return 0;
    }

    /**
     * The tail, as the options a person wrote and the words they wrote between
     * them. `--name=value` only: a two-token form would need to know which
     * options take a value, which is the input definition this contract refuses
     * to grow.
     *
     * @param list<string> $arguments
     *
     * @return array{array<string, string>, list<string>}
     */
    private static function split(array $arguments): array
    {
        $options = [];
        $positional = [];

        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--') && str_contains($argument, '=')) {
                [$name, $value] = explode('=', substr($argument, 2), 2);
                $options[$name] = $value;

                continue;
            }

            $positional[] = $argument;
        }

        return [$options, $positional];
    }

    /**
     * One line from standard input, so a passphrase need never appear in a
     * shell history or a process list. It comes through the channel rather than
     * off \STDIN directly: the handler is given a process to run inside, and a
     * command reading the file descriptor itself would be reading past whatever
     * the console was actually wired to. An input that has ended offers nothing,
     * which is an empty password and is refused above.
     */
    private static function readPassword(CommandIo $io): string
    {
        return $io->readLine() ?? '';
    }

    /**
     * A refusal on the ERROR stream, because it says why nothing happened
     * rather than what did — so it survives a pipeline that is collecting this
     * command's output, and does not corrupt it.
     */
    private static function refuse(CommandIo $io, string $why): int
    {
        $io->error($why);

        return 1;
    }
}
