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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Integration\Api;

use Uhifadhi\Bundle\TeamBundle\Entity\ApiToken;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Repository\ApiTokenRepository;
use Uhifadhi\Bundle\TeamBundle\Service\ApiTokenManager;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\IntegrationTestCase;

/**
 * THE CREDENTIAL STORE, AGAINST WHAT IS ACTUALLY STORED.
 *
 * Everything here is a claim about a row: that the plaintext never becomes one,
 * that the same handset signing in again rotates the row it already has, and
 * that three different failures are one answer.
 */
final class ApiTokenManagerTest extends IntegrationTestCase
{
    private const string DEVICE = '7f1c2b90-0000-4000-8000-000000000001';

    /**
     * THE PLAINTEXT EXISTS ONCE, IN THE ANSWER. What is stored is its hash, so
     * a leaked database yields nothing a handset could present.
     */
    public function testTheTokenIsHandedBackOnceAndOnlyItsHashIsStored(): void
    {
        $user = $this->person();
        [$plaintext, $token] = $this->manager()->issue($user);

        self::assertNotSame('', $plaintext);
        self::assertNotSame($plaintext, $token->getTokenHash());
        self::assertSame(hash('sha256', $plaintext), $token->getTokenHash());
        self::assertSame(ApiToken::HASH_LENGTH, \strlen($token->getTokenHash()));

        $this->em->clear();
        $stored = $this->service(ApiTokenRepository::class)->findOneByHash(hash('sha256', $plaintext));
        self::assertInstanceOf(ApiToken::class, $stored);
        self::assertSame($user->getId(), $stored->getOwner()->getId());
    }

    public function testAPresentedTokenNamesItsOwner(): void
    {
        $user = $this->person();
        [$plaintext] = $this->manager()->issue($user);

        $found = $this->manager()->find($plaintext);

        self::assertInstanceOf(User::class, $found);
        self::assertSame($user->getId(), $found->getId());
    }

    /**
     * UNKNOWN, WITHDRAWN AND EXPIRED ARE ONE ANSWER. Saying which of the three
     * it was tells whoever is guessing whether their guess exists.
     */
    public function testAnUnknownAWithdrawnAndAnExpiredTokenAllNameNobody(): void
    {
        $user = $this->person();

        self::assertNull($this->manager()->find('nothing-was-ever-issued-for-this'));

        [$withdrawn, $token] = $this->manager()->issue($user, self::DEVICE);
        $this->manager()->revoke($token);
        self::assertNull($this->manager()->find($withdrawn));

        [$expired, $second] = $this->manager()->issue($this->person('second@example.test'));
        $second->setExpiresAt(new \DateTimeImmutable('-1 second'));
        $this->em->flush();
        self::assertNull($this->manager()->find($expired));
    }

    /**
     * SIGNING IN AGAIN ON THE SAME HANDSET ROTATES ITS ROW. A second row per
     * attempt would leave a trail of live credentials behind every wipe.
     */
    public function testReSigningInOnTheSameDeviceRotatesTheOneRow(): void
    {
        $user = $this->person();
        [$first, $original] = $this->manager()->issue($user, self::DEVICE, 'the spare handset');
        [$second, $rotated] = $this->manager()->issue($user, self::DEVICE, 'the spare handset');

        self::assertSame($original->getId(), $rotated->getId(), 'one row, rotated');
        self::assertNull($this->manager()->find($first), 'the old credential stops working');
        self::assertNotNull($this->manager()->find($second));

        $this->em->clear();
        self::assertCount(1, $this->service(ApiTokenRepository::class)->findBy(['owner' => $user->getId()]));
    }

    /** A different handset gets its own row, so one can be withdrawn alone. */
    public function testADifferentDeviceGetsItsOwnRow(): void
    {
        $user = $this->person();
        $this->manager()->issue($user, self::DEVICE);
        $this->manager()->issue($user, '7f1c2b90-0000-4000-8000-000000000002');

        $this->em->clear();
        self::assertCount(2, $this->service(ApiTokenRepository::class)->findBy(['owner' => $user->getId()]));
    }

    public function testAWithdrawnTokenStopsWorkingOnTheNextRequest(): void
    {
        $user = $this->person();
        [$plaintext, $token] = $this->manager()->issue($user);
        self::assertNotNull($this->manager()->find($plaintext));

        $this->manager()->revoke($token);

        $this->em->clear();
        self::assertNull($this->manager()->find($plaintext));
    }

    public function testUsingATokenIsRecorded(): void
    {
        [$plaintext, $token] = $this->manager()->issue($this->person());
        self::assertNull($token->getLastUsedAt());

        $this->manager()->touch($plaintext);

        $this->em->clear();
        $stored = $this->service(ApiTokenRepository::class)->findOneByHash(hash('sha256', $plaintext));
        self::assertInstanceOf(ApiToken::class, $stored);
        self::assertNotNull($stored->getLastUsedAt());
    }

    /**
     * SEEN AT MOST ONCE A DAY. A timestamp per request would make every
     * authenticated READ a database WRITE — a real cost on a burst of hundreds
     * — for a field nobody reads to the minute.
     */
    public function testUsingATokenAgainTheSameDayWritesNothing(): void
    {
        [$plaintext, $token] = $this->manager()->issue($this->person());

        $earlier = new \DateTimeImmutable('-1 hour');
        $token->setLastUsedAt($earlier);
        $this->em->flush();

        $this->manager()->touch($plaintext);

        $this->em->clear();
        $stored = $this->service(ApiTokenRepository::class)->findOneByHash(hash('sha256', $plaintext));
        self::assertInstanceOf(ApiToken::class, $stored);
        self::assertEqualsWithDelta($earlier->getTimestamp(), $stored->getLastUsedAt()?->getTimestamp(), 1);
    }

    /** Touching a string that names nobody is not an error — it was already refused. */
    public function testRecordingAUseOfAStringThatNamesNobodyDoesNothing(): void
    {
        $this->manager()->touch('nothing-was-ever-issued-for-this');

        $this->em->clear();
        self::assertSame([], $this->service(ApiTokenRepository::class)->findAll());
    }

    private function manager(): ApiTokenManager
    {
        return $this->service(ApiTokenManager::class);
    }

    private function person(string $email = 'w.mbise@example.test'): User
    {
        $user = new User()
            ->setEmail($email)
            ->setFirstName('Witness')
            ->setLastName('Mbise')
            ->setPassword('x')
            ->setTeamRole(TeamRoleEnum::Staff);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
