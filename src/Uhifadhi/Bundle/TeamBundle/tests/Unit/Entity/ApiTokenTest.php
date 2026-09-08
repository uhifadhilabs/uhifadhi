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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Unit\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\TeamBundle\Entity\ApiToken;
use Uhifadhi\Bundle\TeamBundle\Entity\User;

/**
 * ONE STATE AUTHENTICATES, AND THE ROW IS WHAT MAKES THE OTHER TWO POSSIBLE.
 *
 * A token measured in months cannot be a self-contained blob: a lost handset
 * would stay authorised until the expiry it carries. Being a row is what makes
 * withdrawal possible at all, and these are the two ways a token stops working
 * — withdrawn, and past its expiry — plus the rotation that keeps re-signing in
 * on the same handset from leaving live credentials behind.
 */
#[CoversClass(ApiToken::class)]
final class ApiTokenTest extends TestCase
{
    private const string HASH = 'a1b2c3';
    private const string LATER_HASH = 'd4e5f6';

    public function testAFreshTokenAuthenticatesUntilItsExpiry(): void
    {
        $token = self::token(expiresIn: '+1 day');

        self::assertTrue($token->isUsableAt(new \DateTimeImmutable()));
    }

    public function testAWithdrawnTokenAuthenticatesNobody(): void
    {
        $token = self::token(expiresIn: '+1 day')->revoke();

        self::assertFalse($token->isUsableAt(new \DateTimeImmutable()));
        self::assertNotNull($token->getRevokedAt());
    }

    /** Withdrawing twice keeps the FIRST moment: when it stopped working is a fact. */
    public function testWithdrawingTwiceKeepsTheMomentItFirstStopped(): void
    {
        $first = new \DateTimeImmutable('2026-01-01T08:00:00Z');
        $token = self::token(expiresIn: '+1 day')->revoke($first)->revoke(new \DateTimeImmutable('2026-02-01T08:00:00Z'));

        self::assertEquals($first, $token->getRevokedAt());
    }

    public function testAnExpiredTokenAuthenticatesNobody(): void
    {
        $token = self::token(expiresIn: '-1 second');

        self::assertFalse($token->isUsableAt(new \DateTimeImmutable()));
    }

    /**
     * RE-SIGNING IN ON THE SAME HANDSET ROTATES THE ROW. A second row per
     * attempt would leave a trail of live credentials behind every wipe, and
     * the withdrawal screen would show a device several times over.
     */
    public function testReissuingRotatesTheRowRatherThanLeavingTheOldOneLive(): void
    {
        $token = self::token(expiresIn: '-1 second');
        $token->revoke()->setLastUsedAt(new \DateTimeImmutable('2026-01-01T08:00:00Z'));

        $token->reissue(self::LATER_HASH, new \DateTimeImmutable('+180 days'));

        self::assertSame(self::LATER_HASH, $token->getTokenHash());
        self::assertNull($token->getRevokedAt(), 'a rotated row is not still withdrawn');
        self::assertNull($token->getLastUsedAt(), 'the new credential has never been seen');
        self::assertTrue($token->isUsableAt(new \DateTimeImmutable()));
    }

    public function testTheDeviceIsRememberedSoOneHandsetCanBeWithdrawnAlone(): void
    {
        $token = self::token(expiresIn: '+1 day')
            ->setDeviceId('7f1c2b90-0000-4000-8000-000000000001')
            ->setDeviceName('the spare handset');

        self::assertSame('7f1c2b90-0000-4000-8000-000000000001', $token->getDeviceId());
        self::assertSame('the spare handset', $token->getDeviceName());
    }

    private static function token(string $expiresIn): ApiToken
    {
        $owner = new User()
            ->setEmail('w.mbise@example.test')
            ->setFirstName('Witness')
            ->setLastName('Mbise')
            ->setPassword('x');

        return new ApiToken($owner, self::HASH, new \DateTimeImmutable($expiresIn));
    }
}
