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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Integration\Performance;

use PHPUnit\Framework\Attributes\CoversClass;
use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Service\PositionService;
use Uhifadhi\Bundle\TeamBundle\Service\PositionVacancy;
use Uhifadhi\Bundle\TeamBundle\Service\UserService;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\IntegrationTestCase;

/**
 * SINCE WHEN A POST HAS STOOD EMPTY.
 *
 * "RANGER, UNFILLED SINCE 15 MAY — 96 DAYS" is a sentence the core could
 * not say: a position knows who holds it now and nothing about when the
 * last person stopped. The day it fell vacant is written when it falls,
 * because it cannot be recovered afterwards.
 *
 * NULL MEANS UNKNOWN, NOT TODAY. A position that was already empty when
 * this shipped has no day to state, and every surface reads that as
 * "unknown" rather than starting its clock at the upgrade — which would
 * make an eighteen-month vacancy look like a fresh one.
 *
 * FILLING IT CLEARS THE DAY. A post somebody stands in is not vacant, and
 * the next vacancy is its own, dated from the day it happens.
 */
#[CoversClass(PositionVacancy::class)]
final class PositionVacancyTest extends IntegrationTestCase
{
    /** A position is born empty, and the day it was born is the day it fell vacant. */
    public function testAPositionIsVacantFromTheDayItIsCreated(): void
    {
        $ranger = $this->positions()->create('Ranger');

        self::assertNotNull($ranger->getVacantSince());
    }

    public function testSeatingSomebodyClearsTheDay(): void
    {
        $ranger = $this->positions()->create('Ranger');
        $this->accounts()->assignPosition($this->person(), $ranger);

        self::assertNull($ranger->getVacantSince());
    }

    /** And unseating them starts it again, from today rather than from before. */
    public function testUnseatingTheLastHolderStartsTheDayAgain(): void
    {
        $ranger = $this->positions()->create('Ranger');
        $person = $this->person();
        $this->accounts()->assignPosition($person, $ranger);
        $this->accounts()->assignPosition($person, null);

        $since = $ranger->getVacantSince();
        self::assertNotNull($since);
        self::assertSame(0, $since->diff(new \DateTimeImmutable())->days);
    }

    /** A post two people share is still held when one of them leaves. */
    public function testAPostSomebodyElseStillHoldsIsNotVacant(): void
    {
        $ranger = $this->positions()->create('Ranger');
        $first = $this->person('a.mollel@example.test');
        $second = $this->person('j.ngowi@example.test');
        $this->accounts()->assignPosition($first, $ranger);
        $this->accounts()->assignPosition($second, $ranger);

        $this->accounts()->assignPosition($first, null);

        self::assertNull($ranger->getVacantSince());
    }

    /** Deactivating the only holder empties the post as surely as unseating them. */
    public function testDeactivatingTheLastHolderMakesItVacant(): void
    {
        $ranger = $this->positions()->create('Ranger');
        $person = $this->person();
        $this->accounts()->assignPosition($person, $ranger);

        $this->accounts()->deactivate($person);

        self::assertNotNull($ranger->getVacantSince());
    }

    /** And bringing them back fills it again. */
    public function testReactivatingTheHolderFillsItAgain(): void
    {
        $ranger = $this->positions()->create('Ranger');
        $person = $this->person();
        $this->accounts()->assignPosition($person, $ranger);
        $this->accounts()->deactivate($person);

        $this->accounts()->reactivate($person);

        self::assertNull($ranger->getVacantSince());
    }

    /** How long, in days — and null where nobody wrote the day down. */
    public function testItSaysHowManyDaysAPostHasStoodEmpty(): void
    {
        $ranger = $this->positions()->create('Ranger');
        $ranger->setVacantSince(new \DateTimeImmutable('-96 days'));
        $this->em->flush();

        self::assertSame(96, $this->vacancy()->daysVacant($ranger));

        $unknown = $this->positions()->create('Warden');
        $unknown->setVacantSince(null);
        $this->em->flush();

        self::assertNull($this->vacancy()->daysVacant($unknown));
    }

    /** How many posts have stood empty longer than the installation allows. */
    public function testItCountsThePostsOverTheThreshold(): void
    {
        $long = $this->positions()->create('Ranger');
        $long->setVacantSince(new \DateTimeImmutable('-96 days'));
        $short = $this->positions()->create('Warden');
        $short->setVacantSince(new \DateTimeImmutable('-3 days'));
        $this->em->flush();

        self::assertSame(1, $this->vacancy()->overThreshold([$long, $short], 60));
        self::assertSame(2, $this->vacancy()->overThreshold([$long, $short], 1));
    }

    private function person(string $email = 'a.mollel@example.test'): User
    {
        return $this->accounts()->create($email, 'Asha', 'Mollel', bin2hex(random_bytes(16)), TeamRoleEnum::Staff, null);
    }

    private function positions(): PositionService
    {
        /** @var PositionService $service */
        $service = static::getContainer()->get('test_public.'.PositionService::class);

        return $service;
    }

    private function accounts(): UserService
    {
        /** @var UserService $service */
        $service = static::getContainer()->get('test_public.'.UserService::class);

        return $service;
    }

    private function vacancy(): PositionVacancy
    {
        /** @var PositionVacancy $service */
        $service = static::getContainer()->get('test_public.'.PositionVacancy::class);

        return $service;
    }
}
