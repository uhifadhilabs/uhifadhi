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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Functional;

use Symfony\Component\DomCrawler\Crawler;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;

/**
 * THE ROLES TAB — the two things that decide authority, and nothing invented.
 *
 * THE TIER AND THE PERMISSION. There is no Role entity, none is proposed, and
 * the page asks for none; what it adds is the aggregation neither of them
 * carried — how many positions hold a permission, and how many people sit in
 * those positions.
 */
final class TeamRolesTest extends WebTestCaseWithSchema
{
    /** The three tiers, with the people in them counted and the small ones named. */
    public function testTheThreeTiersAreCountedAndTheSmallOnesAreNamed(): void
    {
        $this->installation();
        $rows = $this->visit('/team/roles')->filter('.c')->eq(0)->filter('tbody tr');

        self::assertSame(
            ['Super Admin', 'Admin', 'Staff'],
            $rows->each(static fn (Crawler $c): string => $c->filter('td')->eq(0)->text()),
        );
        self::assertSame('Naomi Kileo', $rows->eq(0)->filter('td')->eq(2)->text());
    }

    /**
     * A TIER IS NOT A ROLE, and the page says so where the reader is about to
     * mistake one for the other.
     */
    public function testThePageSaysATierIsNotARole(): void
    {
        $this->installation();

        self::assertStringContainsString(
            'A tier is not a role and grants no capability by itself',
            $this->visit('/team/roles')->filter('.sxlead')->text(),
        );
    }

    /** Both names on every permission: the label, and the value the voter checks. */
    public function testEveryPermissionIsShownByBothNames(): void
    {
        $this->installation();
        $table = $this->visit('/team/roles')->filter('.c')->eq(1)->filter('tbody')->text();

        self::assertStringContainsString('Team · Manage', $table);
        self::assertStringContainsString('team.manage', $table);
    }

    /**
     * THE TWO FIGURES ARE DIFFERENT QUESTIONS: how many positions carry it, and
     * how many people sit in those positions.
     */
    public function testAPermissionCountsItsPositionsAndItsPeopleSeparately(): void
    {
        $this->installation();

        $row = $this->rowFor('team.manage');
        self::assertSame('1', $row->filter('td')->eq(1)->text(), 'One position carries team.manage.');
        self::assertSame('2', $row->filter('td')->eq(2)->text(), 'Two active people sit in it.');
    }

    /** A permission no position carries reaches nobody, and reads as nought. */
    public function testAPermissionNoPositionCarriesReadsAsNought(): void
    {
        $this->installation();

        $row = $this->rowFor(PermissionEnum::AreaDelete->value);
        self::assertSame('0', $row->filter('td')->eq(1)->text());
        self::assertSame('0', $row->filter('td')->eq(2)->text());
    }

    /** The bands say where a permission came from, the host's own first. */
    public function testThePermissionsAreBandedByWhoDeclaredThem(): void
    {
        $this->installation();

        $bands = $this->visit('/team/roles')->filter('.c')->eq(1)->filter('tr.sxgrp')
            ->each(static fn (Crawler $c): string => trim(preg_replace('/\s+/', ' ', $c->text()) ?? ''));

        self::assertNotSame([], $bands);
        foreach ($bands as $band) {
            self::assertMatchesRegularExpression('/· (the host|[a-z-]+) · /', $band);
        }
    }

    /** The five facts the band opens with. */
    public function testTheBandStatesTheFiveFacts(): void
    {
        $this->installation();

        self::assertSame(
            ['Tiers', 'Core permissions', 'Module permissions', 'Positions', 'May administer'],
            $this->visit('/team/roles')->filter('.factband .f .k')->each(static fn (Crawler $c): string => $c->text()),
        );
    }

    /**
     * WHO MAY ADMINISTER IS NOT THE TIER COLUMN. It is the two tiers that stand
     * above the matrix plus everybody whose position carries the grant, and the
     * fact says which is which.
     */
    public function testWhoMayAdministerSeparatesTheTiersFromTheGrants(): void
    {
        $this->installation();

        $fact = $this->visit('/team/roles')->filter('.factband .f')->last();
        self::assertStringContainsString('2 by tier, 1 by grant', $fact->text());
    }

    /** THE TAB WRITES NOTHING: the matrix is edited on Positions. */
    public function testTheTabCarriesNoForm(): void
    {
        $this->installation();

        self::assertCount(0, $this->visit('/team/roles')->filter('form[method="post"]'));
    }

    /**
     * One Super Admin, one Admin, and two Staff — one of whom administers the
     * team because their position carries the grant, which is the whole point
     * of the distinction the page draws.
     */
    private function installation(): void
    {
        $administration = $this->department('Administration');
        $warden = $this->position('Warden', [PermissionEnum::TeamManage->value]);
        $ranger = $this->position('Ranger');

        $this->person('Salum', 'Mwaipopo', TeamRoleEnum::Admin)->setPosition($warden);
        $this->person('Frank', 'Massawe')->setPosition($warden);
        $this->person('Tumaini', 'Ndosi')->setPosition($ranger);
        $this->administrator();
    }

    private function rowFor(string $value): Crawler
    {
        return $this->visit('/team/roles')->filter('.c')->eq(1)->filter('tbody tr')
            ->reduce(static fn (Crawler $c): bool => str_contains($c->text(), $value))
            ->first();
    }

    private function visit(string $path): Crawler
    {
        $crawler = $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();

        return $crawler;
    }
}
