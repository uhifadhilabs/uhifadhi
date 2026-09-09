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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Integration\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Repository\UserRepository;
use Uhifadhi\Bundle\TeamBundle\Service\PasswordResetService;
use Uhifadhi\Bundle\TeamBundle\Service\UserService;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\IntegrationTestCase;

/**
 * WHAT A RECOVERY WRITES TO THE TABLE — the token a link is addressed by, and
 * the credential the firewall will meet afterwards.
 *
 * The token has to be findable by the value in the URL, because that lookup is
 * the whole of how a link identifies its account. The new password has to
 * verify through the service a firewall uses, because a hash that only this
 * bundle believes in is an account nobody can sign into.
 */
#[CoversClass(PasswordResetService::class)]
final class PasswordResetServiceTest extends IntegrationTestCase
{
    public function testTheIssuedTokenFindsTheAccountItAddresses(): void
    {
        $user = $this->account();

        $token = $this->resets()->begin($user);

        $found = $this->stored()->findOneBy(['passwordResetToken' => $token]);
        self::assertInstanceOf(User::class, $found);
        self::assertSame('ada@example.test', $found->getEmail());
    }

    public function testTheNewPasswordVerifiesThroughTheFrameworksOwnHasher(): void
    {
        $user = $this->account();
        $this->resets()->begin($user);

        $this->resets()->complete($user, 'a-brand-new-passphrase');

        $stored = $this->stored()->findOneByEmail('ada@example.test');
        self::assertNotNull($stored);

        $hasher = static::getContainer()->get('test_public.hasher');
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);
        self::assertTrue($hasher->isPasswordValid($stored, 'a-brand-new-passphrase'));
        self::assertFalse($hasher->isPasswordValid($stored, 'a-long-enough-passphrase'));
    }

    /** Spending the link stores the spending, so the same URL cannot be walked back to. */
    public function testTheSpentTokenIsGoneFromTheTable(): void
    {
        $user = $this->account();
        $token = $this->resets()->begin($user);

        $this->resets()->complete($user, 'a-brand-new-passphrase');

        self::assertNull($this->stored()->findOneBy(['passwordResetToken' => $token]));
    }

    public function testAcceptedInvitationsStoreTheNameThePersonGaveAndSpendTheInvitation(): void
    {
        $invited = $this->accounts()->invite('bea@example.test', null, null);
        $token = (string) $invited->getVerificationToken();

        $this->resets()->accept($invited, 'Bea Kimaro', 'a-long-enough-passphrase');

        self::assertNull($this->stored()->findOneBy(['verificationToken' => $token]));

        $stored = $this->stored()->findOneByEmail('bea@example.test');
        self::assertNotNull($stored);
        self::assertSame('Bea Kimaro', $stored->getFullName());
        self::assertTrue($stored->isVerified());
    }

    private function account(): User
    {
        return $this->accounts()->create('ada@example.test', 'Ada', 'Mwangi', 'a-long-enough-passphrase');
    }

    private function accounts(): UserService
    {
        return $this->service(UserService::class);
    }

    private function resets(): PasswordResetService
    {
        return $this->service(PasswordResetService::class);
    }

    private function stored(): UserRepository
    {
        $this->em->clear();

        return $this->service(UserRepository::class);
    }
}
