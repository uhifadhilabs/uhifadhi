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
use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Exception\PasswordTooShortException;
use Uhifadhi\Bundle\TeamBundle\Service\SuperAdminInvariant;
use Uhifadhi\Bundle\TeamBundle\Service\UserService;

/**
 * WHAT AN ACCOUNT IS THE MOMENT IT IS MADE, with no database in the room.
 *
 * The service's collaborators are all doubles here: the point of the suite is
 * the SHAPE of the record — which fields are set, which are deliberately left
 * null, whether the credential was hashed — and none of that is a question about
 * storage. What actually reaches the table is asked next door, against a real
 * one.
 */
#[CoversClass(UserService::class)]
final class UserServiceTest extends TestCase
{
    public function testAnAccountCreatedWithAPasswordCanSignInImmediately(): void
    {
        $user = self::service()->create('Ada@Example.test', 'Ada', 'Mwangi', 'a-long-enough-passphrase');

        self::assertTrue($user->isVerified(), 'An administrator who typed the password has already proved the account is real.');
        self::assertTrue($user->isActive());
        self::assertSame('Ada Mwangi', $user->getFullName());
    }

    /** The email IS the identifier, so a different capitalisation is the same person. */
    public function testTheAddressIsFoldedToLowerCase(): void
    {
        $user = self::service()->create('Ada@Example.TEST', 'Ada', 'Mwangi', 'a-long-enough-passphrase');

        self::assertSame('ada@example.test', $user->getEmail());
    }

    public function testThePasswordIsNeverStoredAsItWasTyped(): void
    {
        $user = self::service()->create('ada@example.test', 'Ada', 'Mwangi', 'a-long-enough-passphrase');

        self::assertNotSame('a-long-enough-passphrase', $user->getPassword());
        self::assertNotSame('', $user->getPassword());
    }

    public function testAnAccountIsStaffUnlessAnotherTierIsAsked(): void
    {
        self::assertSame(TeamRoleEnum::Staff, self::service()->create('ada@example.test', 'Ada', 'Mwangi', 'a-long-enough-passphrase')->getTeamRole());
        self::assertSame(
            TeamRoleEnum::SuperAdmin,
            self::service()->create('bea@example.test', 'Bea', 'Kimaro', 'a-long-enough-passphrase', TeamRoleEnum::SuperAdmin)->getTeamRole(),
        );
    }

    public function testAPositionIsOptionalAndItsAbsenceIsARealChoice(): void
    {
        $position = new Position()->setName('Analyst');

        self::assertNull(self::service()->create('ada@example.test', 'Ada', 'Mwangi', 'a-long-enough-passphrase')->getPosition());
        self::assertSame($position, self::service()->create('bea@example.test', 'Bea', 'Kimaro', 'a-long-enough-passphrase', position: $position)->getPosition());
    }

    public function testAPasswordShorterThanTheOneRuleIsRefused(): void
    {
        $this->expectException(PasswordTooShortException::class);

        self::service()->create('ada@example.test', 'Ada', 'Mwangi', 'short');
    }

    /**
     * AN INVITED ACCOUNT IS THE OPPOSITE OF A CREATED ONE: nobody here knows its
     * password, it carries no usable credential, and it has no name until the
     * person supplies their own spelling of it.
     */
    public function testAnInvitedAccountCarriesNoUsableCredentialAndNoName(): void
    {
        $user = self::service()->invite('Ada@Example.test', null, null);

        self::assertFalse($user->isVerified());
        self::assertSame('ada@example.test', $user->getEmail());
        self::assertSame('', $user->getFirstName());
        self::assertSame('', $user->getLastName());
        self::assertNotSame('', $user->getPassword(), 'An empty hash is a hash some verifier will one day accept.');
        self::assertNotNull($user->getVerificationToken());
    }

    public function testAnInvitationRecordsWhoSentIt(): void
    {
        $inviter = new User()->setEmail('ada@example.test');

        self::assertSame($inviter, self::service()->invite('bea@example.test', null, $inviter)->getInvitedBy());
        self::assertNull(self::service()->invite('cara@example.test', null, null)->getInvitedBy());
    }

    /**
     * CREATED DIRECTLY IS NOT INVITED, and the roster reads the difference off
     * this null.
     */
    public function testAnAccountCreatedWithAPasswordWasInvitedByNobody(): void
    {
        $user = self::service()->create('ada@example.test', 'Ada', 'Mwangi', 'a-long-enough-passphrase');

        self::assertNull($user->getInvitedAt());
        self::assertNull($user->getInvitedBy());
    }

    /**
     * The invariant is built without its own collaborator because nothing here
     * reaches it: a tier is only questioned when one is CHANGED, and that
     * question counts rows, which is a question for the suite with a database.
     */
    private static function service(): UserService
    {
        $hasher = self::createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturnCallback(
            static fn (PasswordAuthenticatedUserInterface $user, string $plain): string => 'hashed:'.$plain,
        );

        return new UserService(
            self::createStub(EntityManagerInterface::class),
            $hasher,
            new \ReflectionClass(SuperAdminInvariant::class)->newInstanceWithoutConstructor(),
        );
    }
}
