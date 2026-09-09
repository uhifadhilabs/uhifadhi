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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Exception\PasswordTooShortException;
use Uhifadhi\Bundle\TeamBundle\Service\PasswordResetService;

/**
 * THE LIFE OF A RECOVERY LINK — issued, live, spent, dead.
 *
 * Every one of those is a fact about the account object and the clock, so it is
 * asked here, where the clock can be moved by writing a different time onto the
 * record rather than by waiting an hour.
 */
#[CoversClass(PasswordResetService::class)]
final class PasswordResetServiceTest extends TestCase
{
    public function testAskingIssuesALinkThatIsLive(): void
    {
        $user = self::account();

        $token = self::service()->begin($user);

        self::assertTrue(self::service()->isLive($user, $token));
    }

    /** Asking again is what makes an older email in an inbox inert. */
    public function testAskingAgainReplacesThePreviousLink(): void
    {
        $user = self::account();
        $first = self::service()->begin($user);

        $second = self::service()->begin($user);

        self::assertNotSame($first, $second);
        self::assertFalse(self::service()->isLive($user, $first));
        self::assertTrue(self::service()->isLive($user, $second));
    }

    public function testALinkOlderThanItsHourIsDead(): void
    {
        $user = self::account();
        $token = self::service()->begin($user);

        $user->setPasswordResetRequestedAt(new \DateTimeImmutable('-'.(PasswordResetService::LIFETIME_SECONDS + 60).' seconds'));

        self::assertFalse(self::service()->isLive($user, $token));
    }

    /**
     * A DEACTIVATED ACCOUNT HAS NO LIVE LINK. A reset that let somebody back
     * through a door the firewall closes would be a reset that undoes a
     * deactivation.
     */
    public function testADeactivatedAccountHasNoLiveLink(): void
    {
        $user = self::account();
        $token = self::service()->begin($user);

        $user->deactivate();

        self::assertFalse(self::service()->isLive($user, $token));
    }

    public function testSpendingALinkReplacesTheCredentialAndConsumesTheToken(): void
    {
        $user = self::account();
        $token = self::service()->begin($user);

        self::service()->complete($user, 'a-long-enough-passphrase');

        self::assertSame('hashed:a-long-enough-passphrase', $user->getPassword());
        self::assertNull($user->getPasswordResetToken());
        self::assertFalse(self::service()->isLive($user, $token));
    }

    /** Choosing a password is what proves the address reaches the person. */
    public function testSpendingALinkVerifiesTheAccount(): void
    {
        $user = self::account()->setVerified(false);
        self::service()->begin($user);

        self::service()->complete($user, 'a-long-enough-passphrase');

        self::assertTrue($user->isVerified());
    }

    public function testAPasswordUnderTheOneRuleIsRefusedAndNothingIsWritten(): void
    {
        $user = self::account();
        $token = self::service()->begin($user);

        try {
            self::service()->complete($user, 'short');
            self::fail('A password under the minimum has to be refused.');
        } catch (PasswordTooShortException) {
            self::assertTrue(self::service()->isLive($user, $token), 'A refused reset must not spend the link.');
        }
    }

    public function testAcceptingAnInvitationTakesTheLastWordAsTheFamilyName(): void
    {
        $user = self::account()->setFirstName('')->setLastName('')->setVerified(false)->setVerificationToken('a-token');

        self::service()->accept($user, 'Ada Nkechi Mwangi', 'a-long-enough-passphrase');

        self::assertSame('Ada Nkechi', $user->getFirstName());
        self::assertSame('Mwangi', $user->getLastName());
    }

    /** One word is a whole name, and the family-name column stays empty. */
    public function testAOneWordNameIsAWholeName(): void
    {
        $user = self::account()->setFirstName('')->setLastName('');

        self::service()->accept($user, 'Ada', 'a-long-enough-passphrase');

        self::assertSame('Ada', $user->getFirstName());
        self::assertSame('', $user->getLastName());
    }

    public function testAcceptingSpendsTheInvitationAndVerifiesTheAccount(): void
    {
        $user = self::account()->setVerified(false)->setVerificationToken('a-token');

        self::service()->accept($user, 'Ada Mwangi', 'a-long-enough-passphrase');

        self::assertTrue($user->isVerified());
        self::assertNull($user->getVerificationToken());
    }

    private static function account(): User
    {
        return new User()
            ->setEmail('ada@example.test')
            ->setFirstName('Ada')
            ->setLastName('Mwangi')
            ->setPassword('whatever-was-there-before')
            ->setVerified(true);
    }

    private static function service(): PasswordResetService
    {
        $hasher = self::createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturnCallback(
            static fn (PasswordAuthenticatedUserInterface $user, string $plain): string => 'hashed:'.$plain,
        );

        return new PasswordResetService(self::createStub(EntityManagerInterface::class), $hasher);
    }
}
