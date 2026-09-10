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
 * IT ASKS FOR WHAT IT WAS NOT TOLD. The person running this is at the console
 * of an installation with no account in it, and the tail above is four things
 * long. So anything missing from it is asked for, in the order it is written:
 * the address, the two names, the tier, and last the passphrase.
 *
 *     bin/console team:user:create
 *     Email address: ada@example.test
 *     First name: Ada
 *     Last name: Mwangi
 *     Tier — super-admin, admin, staff [super-admin]:
 *     Passphrase (not shown):
 *     Created Ada Mwangi <ada@example.test> as Super Admin.
 *
 * A TAIL THAT NAMED EVERYTHING IS ASKED NOTHING, and that rule is not a
 * convenience — it is the only one available. Whether somebody is sitting at a
 * terminal is a question about a tty, and the descriptor contract deliberately
 * models nothing of the sort; the tail is the whole of what this can see. A
 * tail carrying all three names was written by something rather than typed by
 * somebody, and a question put to a script is answered by whatever the pipe
 * held next — which is how asking a scripted run for a tier would quietly turn
 * a piped passphrase into a rejected tier. So the scripted form is left exactly
 * as it was:
 *
 *     printf '%s' "$PASSPHRASE" | bin/console team:user:create ada@example.test Ada Mwangi
 *
 * The passphrase is the one thing read the same way in both: `--password=` when
 * it was given, and {@see CommandIo::readSecret()} otherwise. That verb reads a
 * pipe's line plainly, because there is no echo to suppress where there is no
 * terminal, and hides the typing where there is one — so the two routes need no
 * two paths here.
 *
 * @see CommandProviderInterface
 */
final readonly class TeamCommandProvider implements CommandProviderInterface
{
    /** What a person may type after `--tier=` or at the tier prompt, and the tier each names. */
    private const array TIERS = [
        'super-admin' => TeamRoleEnum::SuperAdmin,
        'admin' => TeamRoleEnum::Admin,
        'staff' => TeamRoleEnum::Staff,
    ];

    /** The three positional names, in the order they are written and asked for. */
    private const array NAMES = ['Email address', 'First name', 'Last name'];

    public function __construct(
        private UserService $accounts,
    ) {
    }

    public function commands(): array
    {
        return [
            new CommandDescriptor(
                'team:user:create',
                'Create an account and set its password — the administrator an installation is bootstrapped with. Usage: [<email> <first name> <last name>] [--tier=super-admin|admin|staff] [--password=…]; anything not given is asked for, and the passphrase is never echoed. A tail naming all three is asked nothing, so the password may be piped in.',
                fn (array $arguments, CommandIo $io): int => $this->createUser($arguments, $io),
            ),
        ];
    }

    /** @param list<string> $arguments */
    private function createUser(array $arguments, CommandIo $io): int
    {
        [$options, $positional] = self::split($arguments);

        // FIRST, BECAUSE IT IS THE ACCURATE ANSWER. A token that looks like an
        // option but carries no value is not a name, and letting it fall
        // through to the count below tells a person who typed `--password x`
        // that they left a name out when they had given all three.
        $bare = self::bareOptions($arguments);
        if ([] !== $bare) {
            return self::refuse($io, \sprintf('Options take the form --tier=admin or --password=…, with the value joined on by "="; got %s.', implode(', ', $bare)));
        }

        if (\count($positional) > \count(self::NAMES)) {
            return self::refuse($io, 'Give an email address, a first name and a last name: team:user:create <email> <first name> <last name> [--tier=super-admin|admin|staff] [--password=…].');
        }

        // A TAIL SHORT OF ITS NAMES WAS TYPED BY SOMEBODY, so the rest is asked
        // for; a tail carrying all three was written by something, and is asked
        // nothing at all.
        $scripted = \count($positional) === \count(self::NAMES);

        $names = [];
        foreach (self::NAMES as $index => $label) {
            $given = $positional[$index] ?? self::ask($io, $label.':');
            if (null === $given || '' === trim($given)) {
                return self::refuse($io, \sprintf('%s: nothing was given, and nothing more can be read. Type it at the prompt, or write it on the command line: team:user:create <email> <first name> <last name>.', $label));
            }

            $names[] = trim($given);
        }

        [$email, $firstName, $lastName] = $names;

        if (isset($options['tier'])) {
            // A TAIL IS REFUSED RATHER THAN ASKED AGAIN. It was written before
            // the command ran, so it can be written again with the word
            // corrected; a person mid-prompt has no such second chance.
            $tier = self::TIERS[$options['tier']] ?? null;
            if (null === $tier) {
                return self::refuse($io, \sprintf('Unknown tier "%s". Use one of: %s.', $options['tier'], implode(', ', array_keys(self::TIERS))));
            }
        } elseif ($scripted) {
            $tier = TeamRoleEnum::SuperAdmin;
        } else {
            $tier = self::askTier($io);
            if (null === $tier) {
                return self::refuse($io, \sprintf('Tier: nothing was given, and nothing more can be read. Type one of: %s, or pass --tier=….', implode(', ', array_keys(self::TIERS))));
            }
        }

        $password = $options['password'] ?? self::readPassword($io, $scripted);
        if ('' === trim($password)) {
            return self::refuse($io, 'A password is required. Type it at the prompt, pass --password=…, or write it on standard input.');
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
     * The option-looking tokens that carry no value, in the order they were
     * written.
     *
     * WHAT IS ACCEPTED IS UNCHANGED by this — `--name=value` and nothing else,
     * because a two-token form would need to know which options take a value,
     * which is the input definition this contract refuses to grow. This only
     * makes the refusal say so. A short `-v` is left alone: nothing ever
     * promised short options, and a lone dash is likelier to be somebody's odd
     * name than a flag.
     *
     * @param list<string> $arguments
     *
     * @return list<string>
     */
    private static function bareOptions(array $arguments): array
    {
        $bare = [];

        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--') && !str_contains($argument, '=')) {
                $bare[] = $argument;
            }
        }

        return $bare;
    }

    /**
     * A question and the line it is answered with, or null once the input has
     * ended and asking again would be pointless.
     *
     * THE QUESTION GOES ON THE ERROR STREAM, for the same reason a refusal
     * does: it is not what the command produced. A person whose output is being
     * piped somewhere still reads it, and it never lands in that pipe.
     */
    private static function ask(CommandIo $io, string $question): ?string
    {
        $io->error($question);

        return $io->readLine();
    }

    /**
     * The tier a person types, asked until it is one — or null once the input
     * has ended.
     *
     * AN EMPTY ANSWER IS THE DEFAULT, and the default is super admin: the
     * account this command exists to make is the one an installation is
     * bootstrapped with, and a first administrator who could not administer
     * would leave nobody who can. A WORD THAT IS NOT A TIER IS ASKED AGAIN
     * rather than refused, because the alternative is telling somebody who
     * typed one letter wrong to start the whole command over.
     */
    private static function askTier(CommandIo $io): ?TeamRoleEnum
    {
        $tiers = implode(', ', array_keys(self::TIERS));

        while (true) {
            $answer = self::ask($io, \sprintf('Tier — %s [super-admin]:', $tiers));
            if (null === $answer) {
                return null;
            }

            $answer = trim($answer);
            if ('' === $answer) {
                return TeamRoleEnum::SuperAdmin;
            }

            $tier = self::TIERS[$answer] ?? null;
            if (null !== $tier) {
                return $tier;
            }

            $io->error(\sprintf('Unknown tier "%s". Use one of: %s.', $answer, $tiers));
        }
    }

    /**
     * The passphrase, through the verb that does not put it on the screen — so
     * it need never appear in a shell history, a process list or a terminal's
     * scrollback. It comes through the channel rather than off \STDIN directly:
     * the handler is given a process to run inside, and a command reading the
     * file descriptor itself would be reading past whatever the console was
     * actually wired to.
     *
     * A SCRIPTED RUN IS NOT PROMPTED, only read: there is nobody to read the
     * question, and the line the pipe holds is the answer either way, since
     * readSecret() has no echo to suppress where there is no terminal. An input
     * that has ended offers nothing, which is an empty password and is refused
     * above.
     */
    private static function readPassword(CommandIo $io, bool $scripted): string
    {
        if (!$scripted) {
            $io->error('Passphrase (not shown):');
        }

        return $io->readSecret() ?? '';
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
