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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Integration\Devkit;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Uhifadhi\Bundle\TeamBundle\Devkit\TeamCommandProvider;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Repository\UserRepository;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\DevkitCommandCollector;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\RecordingCommandIo;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\IntegrationTestCase;

/**
 * THE FIRST ADMINISTRATOR — the one account an installation cannot make through
 * a screen, because every screen is behind the sign-in it does not yet have.
 *
 * The core ships no console command; devkit owns every command the platform
 * has. So this bundle ships an INERT provider, devkit collects it in a dev
 * install and turns the descriptor into a real console command, and the
 * collector standing in here does the same much: read the descriptor off the
 * tagged service, call its handler with the argument tail, take the returned
 * int as the exit status.
 *
 * WHAT IS ASSERTED IS THE ROW, not the message. The account has to be one the
 * firewall would actually accept, which means a hash the framework's own
 * verifier agrees with — so the password is checked back through
 * `security.user_password_hasher`, the service a firewall uses.
 */
#[CoversClass(TeamCommandProvider::class)]
final class FirstAdministratorTest extends IntegrationTestCase
{
    public function testTheBundleOffersTheCommandThroughTheDevkitContracts(): void
    {
        self::assertContains('team:user:create', $this->collector()->names());
    }

    public function testItCreatesAnAccountWhosePasswordVerifies(): void
    {
        $exit = $this->collector()->run('team:user:create', [
            'ada@example.test', 'Ada', 'Mwangi', '--password=a-long-enough-passphrase',
        ]);

        self::assertSame(0, $exit);

        $user = $this->users()->findOneByEmail('ada@example.test');
        self::assertNotNull($user);
        self::assertSame('Ada Mwangi', $user->getFullName());

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get('test_public.hasher');
        self::assertTrue($hasher->isPasswordValid($user, 'a-long-enough-passphrase'));
        self::assertNotSame('a-long-enough-passphrase', $user->getPassword());
    }

    /**
     * SUPER ADMIN BY DEFAULT, because the account this command exists to make
     * is the one an installation is bootstrapped with — and a first
     * administrator who could not administer would leave nobody who can.
     */
    public function testTheAccountIsASuperAdminUnlessAnotherTierIsAsked(): void
    {
        $this->collector()->run('team:user:create', ['ada@example.test', 'Ada', 'Mwangi', '--password=a-long-enough-passphrase']);

        self::assertSame(TeamRoleEnum::SuperAdmin, $this->users()->findOneByEmail('ada@example.test')?->getTeamRole());
    }

    public function testAnotherTierCanBeAsked(): void
    {
        $exit = $this->collector()->run('team:user:create', [
            'bea@example.test', 'Bea', 'Kimaro', '--tier=staff', '--password=a-long-enough-passphrase',
        ]);

        self::assertSame(0, $exit);
        self::assertSame(TeamRoleEnum::Staff, $this->users()->findOneByEmail('bea@example.test')?->getTeamRole());
    }

    /**
     * THE ACCOUNT IS READY TO SIGN IN. An invited account is unverified and
     * carries no usable credential on purpose; this one is the opposite — it is
     * the credential somebody signs in with in the installation's first minute.
     */
    public function testTheAccountIsVerifiedAndActive(): void
    {
        $this->collector()->run('team:user:create', ['ada@example.test', 'Ada', 'Mwangi', '--password=a-long-enough-passphrase']);

        $user = $this->users()->findOneByEmail('ada@example.test');
        self::assertNotNull($user);
        self::assertTrue($user->isVerified());
        self::assertTrue($user->isActive());
    }

    /** A second account on the same address is refused, and nothing is written. */
    public function testAnAddressThatIsAlreadyTakenIsRefused(): void
    {
        $this->collector()->run('team:user:create', ['ada@example.test', 'Ada', 'Mwangi', '--password=a-long-enough-passphrase']);

        $exit = $this->collector()->run('team:user:create', ['ada@example.test', 'Someone', 'Else', '--password=another-passphrase']);

        self::assertSame(1, $exit);
        self::assertSame('Ada Mwangi', $this->users()->findOneByEmail('ada@example.test')?->getFullName());
    }

    public function testATierNobodyHasIsRefused(): void
    {
        $exit = $this->collector()->run('team:user:create', [
            'ada@example.test', 'Ada', 'Mwangi', '--tier=emperor', '--password=a-long-enough-passphrase',
        ]);

        self::assertSame(1, $exit);
        self::assertNull($this->users()->findOneByEmail('ada@example.test'));
    }

    /**
     * THE CONTRACT MODELS NO OPTIONS, so the handler reads the tail itself —
     * and a tail missing a name is a refusal rather than an account with a
     * blank one.
     */
    public function testATailThatNamesNobodyIsRefused(): void
    {
        self::assertSame(1, $this->collector()->run('team:user:create', ['ada@example.test']));
        self::assertNull($this->users()->findOneByEmail('ada@example.test'));
    }

    public function testAnEmptyPasswordIsRefused(): void
    {
        $exit = $this->collector()->run('team:user:create', ['ada@example.test', 'Ada', 'Mwangi', '--password=']);

        self::assertSame(1, $exit);
        self::assertNull($this->users()->findOneByEmail('ada@example.test'));
    }

    /**
     * AN OPTION WRITTEN WITH A SPACE IS TOLD WHAT FORM TO TAKE.
     *
     * Only `--name=value` is parsed, because a two-token form would mean
     * knowing which options carry a value — the input definition this contract
     * refuses to grow. That narrowness is fine; misdiagnosing it was not. A
     * person who typed `--password x` had given all three names, so the
     * positional count came to five and they were told "Give an email address,
     * a first name and a last name" — an answer to a question they had not
     * asked. The stray token is named instead, and named first.
     */
    public function testAnOptionWrittenWithASpaceIsToldTheFormItShouldTake(): void
    {
        $io = new RecordingCommandIo();

        $exit = $this->collector()->run('team:user:create', [
            'ada@example.test', 'Ada', 'Mwangi', '--password', 'a-long-enough-passphrase',
        ], $io);

        self::assertSame(1, $exit);
        self::assertNull($this->users()->findOneByEmail('ada@example.test'));

        self::assertStringContainsString('--password', $io->diagnostics());
        self::assertStringContainsString('--tier=', $io->diagnostics(), 'The refusal shows the form an option takes.');
        self::assertStringNotContainsString('Give an email address', $io->diagnostics(), 'The names were all given; the missing-names guard is the wrong one to fire.');
    }

    /** Every stray token is named, not only the first one met. */
    public function testEveryOptionWrittenWithASpaceIsNamed(): void
    {
        $io = new RecordingCommandIo();

        $exit = $this->collector()->run('team:user:create', [
            'ada@example.test', 'Ada', 'Mwangi', '--tier', 'staff', '--password', 'a-long-enough-passphrase',
        ], $io);

        self::assertSame(1, $exit);
        self::assertStringNotContainsString('Give an email address', $io->diagnostics());
        self::assertMatchesRegularExpression('/--tier\b(?!=)/', $io->diagnostics(), 'The stray --tier is named as itself, not as part of the usage line.');
        self::assertMatchesRegularExpression('/--password\b(?!=)/', $io->diagnostics());
    }

    /** What is accepted is unchanged: the guard does not catch the `=` form. */
    public function testTheEqualsFormIsUntouchedByTheGuard(): void
    {
        $io = new RecordingCommandIo();

        $exit = $this->collector()->run('team:user:create', [
            'ada@example.test', 'Ada', 'Mwangi', '--tier=staff', '--password=a-long-enough-passphrase',
        ], $io);

        self::assertSame(0, $exit, $io->diagnostics());
        self::assertSame(TeamRoleEnum::Staff, $this->users()->findOneByEmail('ada@example.test')?->getTeamRole());
    }

    /**
     * WHAT IT CREATED IS SAID ON STANDARD OUTPUT, through the channel the
     * descriptor hands the handler — not written to \STDOUT behind the
     * console's back, where it would ignore `--quiet` and be invisible to
     * anything collecting the command's output.
     */
    public function testWhatItCreatedIsSaidOnStandardOutput(): void
    {
        $io = new RecordingCommandIo();

        $this->collector()->run('team:user:create', [
            'ada@example.test', 'Ada', 'Mwangi', '--password=a-long-enough-passphrase',
        ], $io);

        self::assertStringContainsString('Ada Mwangi', $io->output());
        self::assertStringContainsString('ada@example.test', $io->output());
        self::assertSame('', $io->diagnostics(), 'A command that succeeded has nothing to say on the error stream.');
    }

    /**
     * WHY IT REFUSED IS SAID ON STANDARD ERROR, the other stream — so a person
     * still reads it when the command's output is being piped somewhere, and so
     * it never lands in that pipe.
     */
    public function testWhyItRefusedIsSaidOnStandardError(): void
    {
        $io = new RecordingCommandIo();

        $exit = $this->collector()->run('team:user:create', [
            'ada@example.test', 'Ada', 'Mwangi', '--tier=emperor', '--password=a-long-enough-passphrase',
        ], $io);

        self::assertSame(1, $exit);
        self::assertStringContainsString('emperor', $io->diagnostics());
        self::assertSame('', $io->output(), 'A refusal is a diagnostic, and nothing goes to the output stream.');
    }

    /**
     * THE PASSPHRASE IS READ THROUGH THE CHANNEL when the option is absent, so
     * it need never appear in a shell history or a process list — and so the
     * handler reaches for no \STDIN of its own, which is the same escape from
     * the given process that the output channel closes.
     */
    public function testThePasswordIsReadFromStandardInputWhenTheOptionIsAbsent(): void
    {
        $io = new RecordingCommandIo(['a-piped-passphrase']);

        $exit = $this->collector()->run('team:user:create', ['ada@example.test', 'Ada', 'Mwangi'], $io);

        self::assertSame(0, $exit);

        $user = $this->users()->findOneByEmail('ada@example.test');
        self::assertNotNull($user);

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get('test_public.hasher');
        self::assertTrue($hasher->isPasswordValid($user, 'a-piped-passphrase'));
    }

    /** Input that ended without offering a line is no password given, and is refused. */
    public function testAnInputThatOffersNothingIsRefused(): void
    {
        $io = new RecordingCommandIo();

        $exit = $this->collector()->run('team:user:create', ['ada@example.test', 'Ada', 'Mwangi'], $io);

        self::assertSame(1, $exit);
        self::assertNull($this->users()->findOneByEmail('ada@example.test'));
        self::assertStringContainsString('password', strtolower($io->diagnostics()));
    }

    /**
     * NOTHING TYPED AT ALL, AND THE COMMAND ASKS. The person who runs this is
     * at the console of an installation with no accounts in it, and the tail
     * they are expected to have memorised is four things long. So a tail that
     * named nobody is a person, not a script, and a person is asked.
     */
    public function testATailThatNamesNothingIsAskedForEverything(): void
    {
        $io = new RecordingCommandIo(['ada@example.test', 'Ada', 'Mwangi', 'staff'], ['a-long-enough-passphrase']);

        $exit = $this->collector()->run('team:user:create', [], $io);

        self::assertSame(0, $exit, $io->diagnostics());

        $user = $this->users()->findOneByEmail('ada@example.test');
        self::assertNotNull($user);
        self::assertSame('Ada Mwangi', $user->getFullName());
        self::assertSame(TeamRoleEnum::Staff, $user->getTeamRole());

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get('test_public.hasher');
        self::assertTrue($hasher->isPasswordValid($user, 'a-long-enough-passphrase'));
    }

    /** An answer that is not given is the default, and the default is Super Admin. */
    public function testAnUnansweredTierIsTheDefaultOne(): void
    {
        $io = new RecordingCommandIo(['ada@example.test', 'Ada', 'Mwangi', ''], ['a-long-enough-passphrase']);

        self::assertSame(0, $this->collector()->run('team:user:create', [], $io), $io->diagnostics());
        self::assertSame(TeamRoleEnum::SuperAdmin, $this->users()->findOneByEmail('ada@example.test')?->getTeamRole());
    }

    /** What was given is not asked for again; only what is missing is. */
    public function testOnlyWhatWasNotGivenIsAskedFor(): void
    {
        $io = new RecordingCommandIo(['Ada', 'Mwangi', ''], ['a-long-enough-passphrase']);

        $exit = $this->collector()->run('team:user:create', ['ada@example.test'], $io);

        self::assertSame(0, $exit, $io->diagnostics());
        self::assertSame('Ada Mwangi', $this->users()->findOneByEmail('ada@example.test')?->getFullName());
        self::assertStringNotContainsString('Email address', $io->diagnostics(), 'The address was given on the command line and is not asked for again.');
        self::assertStringContainsString('First name', $io->diagnostics());
    }

    /**
     * A TYPED ANSWER THAT IS NOT A TIER IS ASKED AGAIN, unlike a `--tier=` that
     * is not one. A person mid-prompt has nowhere to go back to and no way to
     * correct a word except by typing another; a tail was written before the
     * command ran and can be written again.
     */
    public function testATierTypedWrongIsAskedAgain(): void
    {
        $io = new RecordingCommandIo(['ada@example.test', 'Ada', 'Mwangi', 'emperor', 'staff'], ['a-long-enough-passphrase']);

        $exit = $this->collector()->run('team:user:create', [], $io);

        self::assertSame(0, $exit, $io->diagnostics());
        self::assertSame(TeamRoleEnum::Staff, $this->users()->findOneByEmail('ada@example.test')?->getTeamRole());
        self::assertStringContainsString('emperor', $io->diagnostics());
    }

    /**
     * THE PROMPTS ARE ON THE ERROR STREAM, because a prompt is not what the
     * command produced — it must reach a person whose output is being piped
     * somewhere, without landing in that pipe.
     */
    public function testTheQuestionsAreAskedOnTheErrorStream(): void
    {
        $io = new RecordingCommandIo(['ada@example.test', 'Ada', 'Mwangi', ''], ['a-long-enough-passphrase']);

        $this->collector()->run('team:user:create', [], $io);

        self::assertStringContainsString('Email address', $io->diagnostics());
        self::assertStringNotContainsString('Email address', $io->output(), 'A question is not the command\'s result.');
    }

    /**
     * THE PASSPHRASE IS ASKED FOR THROUGH THE VERB THAT DOES NOT ECHO IT, so
     * that a person typing it at a prompt does not leave it in the scrollback.
     */
    public function testTheTypedPassphraseIsReadWithoutBeingEchoed(): void
    {
        $io = new RecordingCommandIo(['ada@example.test', 'Ada', 'Mwangi', ''], ['a-typed-passphrase']);

        self::assertSame(0, $this->collector()->run('team:user:create', [], $io), $io->diagnostics());

        $user = $this->users()->findOneByEmail('ada@example.test');
        self::assertNotNull($user);

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get('test_public.hasher');
        self::assertTrue($hasher->isPasswordValid($user, 'a-typed-passphrase'));
        self::assertStringNotContainsString('a-typed-passphrase', $io->diagnostics().$io->output(), 'A secret that was asked for is never said back.');
    }

    /** A prompt answered with nothing is no passphrase, and is refused. */
    public function testAPassphraseTypedEmptyIsRefused(): void
    {
        $io = new RecordingCommandIo(['ada@example.test', 'Ada', 'Mwangi', ''], ['']);

        $exit = $this->collector()->run('team:user:create', [], $io);

        self::assertSame(1, $exit);
        self::assertNull($this->users()->findOneByEmail('ada@example.test'));
        self::assertStringContainsString('password', strtolower($io->diagnostics()));
    }

    /** The option is the answer, so the question is never put. */
    public function testTheOptionBypassesThePassphrasePrompt(): void
    {
        $io = new RecordingCommandIo([], ['a-typed-passphrase']);

        $exit = $this->collector()->run('team:user:create', [
            'ada@example.test', 'Ada', 'Mwangi', '--password=a-long-enough-passphrase',
        ], $io);

        self::assertSame(0, $exit, $io->diagnostics());

        $user = $this->users()->findOneByEmail('ada@example.test');
        self::assertNotNull($user);

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get('test_public.hasher');
        self::assertTrue($hasher->isPasswordValid($user, 'a-long-enough-passphrase'));
        self::assertFalse($hasher->isPasswordValid($user, 'a-typed-passphrase'));
    }

    /**
     * A TAIL THAT NAMED EVERYTHING IS NEVER ASKED ANYTHING, and that is the
     * rule that keeps the piped form working. The descriptor cannot see a
     * terminal — the contract models no such thing — so the tail is the only
     * signal of intent it has: a tail carrying all three names was written by
     * something rather than typed by somebody, and a question put to a script
     * is a question answered by whatever the pipe held next.
     */
    public function testATailThatNamedEverythingIsAskedNothing(): void
    {
        $io = new RecordingCommandIo(['a-piped-passphrase']);

        $exit = $this->collector()->run('team:user:create', ['ada@example.test', 'Ada', 'Mwangi'], $io);

        self::assertSame(0, $exit, $io->diagnostics());
        self::assertSame('', $io->diagnostics(), 'Nothing is asked, so the pipe\'s one line is the passphrase and not a tier.');
        self::assertSame(TeamRoleEnum::SuperAdmin, $this->users()->findOneByEmail('ada@example.test')?->getTeamRole());
    }

    private function collector(): DevkitCommandCollector
    {
        $collector = static::getContainer()->get('test_public.devkit_commands');
        self::assertInstanceOf(DevkitCommandCollector::class, $collector);

        return $collector;
    }

    private function users(): UserRepository
    {
        $this->em->clear();

        return $this->service(UserRepository::class);
    }
}
