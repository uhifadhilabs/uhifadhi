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
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\FakeStationDirectory;
use Uhifadhi\Contracts\Area\DirectoryArea;
use Uhifadhi\Contracts\Area\PostedStation;
use Uhifadhi\Contracts\Area\StationPost;

/**
 * THE POSTINGS TAB — who is posted where, across every area, read-only.
 *
 * THE JOIN IS THE POINT. The ground publishes stations and the uuids standing
 * at them; this bundle says who those uuids are, what rank they hold and which
 * department the rank belongs to. Neither half can draw the page alone, and
 * both halves are asserted here.
 */
final class TeamPostingsTest extends WebTestCaseWithSchema
{
    protected function setUp(): void
    {
        parent::setUp();
        FakeStationDirectory::clear();
    }

    protected function tearDown(): void
    {
        FakeStationDirectory::clear();
        parent::tearDown();
    }

    /** One band a station, and the person's rank and department beside them. */
    public function testEachStationIsABandAndItsPeopleAreTheRowsUnderIt(): void
    {
        $this->ground();
        $crawler = $this->visit('/team/postings');

        self::assertSame(
            ['Seneto Gate Post · ST-01 · Crater · 2 posted', 'Naiyobi Outpost · ST-08 · Naiyobi · nobody posted'],
            $crawler->filter('tr.sxgrp td')->each(static fn (Crawler $c): string => trim(preg_replace('/\s+/', ' ', $c->text()) ?? '')),
        );

        $first = $crawler->filter('table.tbl tbody tr')->eq(1);
        self::assertStringContainsString('Joseph Mollel', $first->text());
        self::assertStringContainsString('Sergeant', $first->text());
        self::assertStringContainsString('Protection Service', $first->text());
    }

    /** The one who leads says so, and nobody else does. */
    public function testTheLeaderWearsTheChipAndNobodyElseDoes(): void
    {
        $this->ground();

        self::assertSame(
            ['leads'],
            $this->visit('/team/postings')->filter('table.tbl .chip')->each(static fn (Crawler $c): string => $c->text()),
        );
    }

    /**
     * A STATION NOBODY STANDS AT KEEPS ITS BAND and says so in one line.
     * Dropping it would hide the very thing the board's own figure counts.
     */
    public function testAStationWithNobodyKeepsItsBandAndSaysSo(): void
    {
        $this->ground();

        self::assertStringContainsString(
            'Nobody is posted here',
            $this->visit('/team/postings')->filter('table.tbl tbody')->text(),
        );
    }

    /** The five facts, of the whole installation and not of the filtered view. */
    public function testTheBandStatesTheFiveFacts(): void
    {
        $this->ground();

        self::assertSame(
            ['Postings', 'Areas', 'Stations', 'Leaders', 'Departments'],
            $this->visit('/team/postings')->filter('.factband .f .k')->each(static fn (Crawler $c): string => $c->text()),
        );
    }

    /**
     * AN AREA WITH NO STATION IS STILL OFFERED, reading nought: that an
     * installation has an area nobody has built out is the reading the board
     * is open for.
     */
    public function testEveryAreaIsOfferedIncludingTheOneWithNoStation(): void
    {
        $this->ground();

        $options = $this->visit('/team/postings')->filter('.lfilt details')->eq(0)->filter('.i-ddopt');

        self::assertSame(
            ['all areas', 'Ngorongoro', 'Amboni Caves'],
            $options->each(static fn (Crawler $c): string => trim($c->filter('.i-ddopt-l')->text())),
        );
        self::assertSame('0', $options->last()->filter('.i-ddopt-n')->text());
    }

    /** Picking a rank keeps the people who hold it and drops the rest. */
    public function testFilteringByRankKeepsOnlyThePeopleWhoHoldIt(): void
    {
        $this->ground();

        $rows = $this->visit('/team/postings?rank=Ranger')->filter('table.tbl tbody tr:not(.sxgrp)');

        self::assertCount(1, $rows);
        self::assertStringContainsString('Tumaini Ndosi', $rows->text());
    }

    /** The search reads a person's name and a station's alike. */
    public function testTheSearchFindsAPersonAndAStation(): void
    {
        $this->ground();

        self::assertStringContainsString(
            'Joseph Mollel',
            $this->visit('/team/postings?q=mollel')->filter('table.tbl tbody')->text(),
        );
        self::assertStringContainsString(
            'Naiyobi',
            $this->visit('/team/postings?q=naiyobi')->filter('table.tbl tbody')->text(),
        );
    }

    /** Asking for the empty stations leaves the staffed ones out. */
    public function testAskingForTheEmptyStationsLeavesTheStaffedOnesOut(): void
    {
        $this->ground();

        self::assertSame(
            ['Naiyobi Outpost · ST-08 · Naiyobi · nobody posted'],
            $this->visit('/team/postings?posted=no')->filter('tr.sxgrp td')
                ->each(static fn (Crawler $c): string => trim(preg_replace('/\s+/', ' ', $c->text()) ?? '')),
        );
    }

    /**
     * AN INSTALLATION WITH NO GROUND PACKAGE IS A REAL INSTALLATION. The board
     * says the installation has no station rather than failing, which is what
     * makes the seam optional the way every other one is.
     */
    public function testAnInstallationWithNoStationSaysSo(): void
    {
        $this->administrator();

        self::assertStringContainsString(
            'No station has been recorded on this installation yet.',
            $this->visit('/team/postings')->filter('table.tbl tbody')->text(),
        );
    }

    /** THE BOARD WRITES NOTHING: a posting is made on the station, in the area. */
    public function testTheBoardCarriesNoForm(): void
    {
        $this->ground();
        $crawler = $this->visit('/team/postings');

        self::assertCount(0, $crawler->filter('form[method="post"]'));
        self::assertStringContainsString('Team reads it, never writes it.', $crawler->filter('.sxfoot')->text());
    }

    /**
     * The ground, and the people standing on it: two at Seneto — one leading —
     * and a station with nobody, in an installation whose second area has no
     * station at all.
     */
    private function ground(): void
    {
        $protection = $this->department('Protection Service');
        $sergeant = $this->position('Sergeant');
        $ranger = $this->position('Ranger');

        $joseph = $this->person('Joseph', 'Mollel')->setPosition($sergeant);
        $tumaini = $this->person('Tumaini', 'Ndosi')->setPosition($ranger);
        // THE DEPARTMENT BESIDE A NAME IS READ OFF THE PLACEMENT. It used to
        // arrive through the position; the ruling put it on the person, so
        // the board only has one to print once somebody has been placed.
        $this->place($joseph, null, [$protection]);
        $this->place($tumaini, null, [$protection]);
        $this->administrator();

        FakeStationDirectory::$areas = [
            new DirectoryArea('area-ngorongoro', 'Ngorongoro'),
            new DirectoryArea('area-amboni', 'Amboni Caves'),
        ];
        FakeStationDirectory::$stations = [
            new PostedStation(
                uuid: 'st-01',
                name: 'Seneto Gate Post',
                code: 'ST-01',
                areaUuid: 'area-ngorongoro',
                areaName: 'Ngorongoro',
                zoneName: 'Crater',
                posts: [
                    new StationPost(self::uuid($joseph), new \DateTimeImmutable('2026-01-04'), true),
                    new StationPost(self::uuid($tumaini), new \DateTimeImmutable('2026-02-11')),
                ],
            ),
            new PostedStation(
                uuid: 'st-08',
                name: 'Naiyobi Outpost',
                code: 'ST-08',
                areaUuid: 'area-ngorongoro',
                areaName: 'Ngorongoro',
                zoneName: 'Naiyobi',
            ),
        ];
    }

    private static function uuid(User $person): string
    {
        return (string) $person->getUuidString();
    }

    private function visit(string $path): Crawler
    {
        $crawler = $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();

        return $crawler;
    }
}
