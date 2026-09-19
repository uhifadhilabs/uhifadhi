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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration\Web;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Response;
use Uhifadhi\Bundle\AreaBundle\Controller\ZoneController;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Entity\Zone;
use Uhifadhi\Bundle\AreaBundle\Enum\PostingSource;
use Uhifadhi\Bundle\AreaBundle\Service\PostingService;
use Uhifadhi\Bundle\AreaBundle\Service\StationService;
use Uhifadhi\Bundle\AreaBundle\Tests\Integration\Web\Fixtures\HostUser;

/**
 * THE ZONES TAB, OVER REAL HTTP — how an area is divided, read.
 *
 * PICK ALL ZONES OR ONE. This is the all-zones state: the area's band, the
 * area's figures, the whole ground on one plate, and under it the stations
 * and the people those zones account for. Picking one is the zone record,
 * which is the same surface one step in.
 *
 * A LENS, NOT A FENCE. Reading it is for anybody who may see the area; every
 * write is on the configure section, and the only control here that changes
 * anything is the Configure action the frame draws.
 */
#[CoversClass(ZoneController::class)]
final class ZonesTabTest extends WebTestCase
{
    public function testTheTabDrawsTheBandTheFiguresThePlateAndBothCards(): void
    {
        $this->boot();
        $this->signIn();
        [$area] = $this->anAreaWithAZonedPost();

        $body = $this->body($this->tab($area));

        self::assertStringContainsString('All zones', $body);
        self::assertStringContainsString('the whole area', $body);
        self::assertStringContainsString('People posted', $body);
        // The ground, wearing the house map contract.
        self::assertStringContainsString('map-plate', $body);
        self::assertStringContainsString('map-legend', $body);
        // And the two cards under it.
        self::assertStringContainsString('Seneto Gate Post', $body);
        self::assertStringContainsString('J. Mollel', $body);
        self::assertStringContainsString('class="atabs"', $body);
    }

    /**
     * NO MODULE PUBLISHES ABOUT A ZONE HERE, so the cards that would carry a
     * module's figure say so and keep their slot — a row of three where the
     * design has five is a different design.
     */
    public function testTheModuleFiguresSayNobodyPublishesRatherThanBeingDropped(): void
    {
        $this->boot();
        $this->signIn();
        [$area] = $this->anAreaWithAZonedPost();

        $body = $this->body($this->tab($area));

        self::assertSame(5, substr_count($body, 'class="c kpi"'));
        self::assertStringContainsString('no module publishes this', $body);
    }

    /** An area nobody has zoned says what a zone is, rather than drawing an empty table. */
    public function testAnAreaWithNoZoneSaysWhatAZoneIs(): void
    {
        $this->boot();
        $this->signIn();

        $body = strtolower($this->body($this->tab($this->anArea())));

        self::assertStringContainsString('no zones yet', $body);
        self::assertStringContainsString('lens', $body);
    }

    /** The stations card carries the house filter row, and the address is its state. */
    public function testTheStationsCardFiltersOffTheAddress(): void
    {
        $this->boot();
        $this->signIn();
        [$area] = $this->anAreaWithAZonedPost();
        $this->stations()->add($area, 'Eastern Outpost', -29.25, -3.2);

        self::assertSame(['Seneto Gate Post'], $this->listed($this->tab($area).'?q=seneto'));
        self::assertSame(['Eastern Outpost'], $this->listed($this->tab($area).'?zone=unzoned'));
        self::assertSame(['Eastern Outpost'], $this->listed($this->tab($area).'?lead=no'));
    }

    /** The people card searches by name, on a key of its own. */
    public function testThePeopleCardSearchesByName(): void
    {
        $this->boot();
        $this->signIn();
        [$area] = $this->anAreaWithAZonedPost();

        $body = $this->body($this->tab($area).'?person=ndosi');

        self::assertStringContainsString('T. Ndosi', $body);
        self::assertStringNotContainsString('>J. Mollel<', $body);
    }

    public function testAViewerWhoMayNotSeeTheAreaIsRefused(): void
    {
        $this->boot([]);
        $this->signIn();
        [$area] = $this->anAreaWithAZonedPost();

        $this->browser()->request('GET', $this->tab($area));

        self::assertSame(Response::HTTP_FORBIDDEN, $this->browser()->getResponse()->getStatusCode());
    }

    // ---------------------------------------------------------------- fixtures

    private function tab(AreaOfInterest $area): string
    {
        return '/areas/'.$area->getUuidString().'/zones';
    }

    private function body(string $url): string
    {
        $this->browser()->request('GET', $url);

        return (string) $this->browser()->getResponse()->getContent();
    }

    /**
     * The stations card's rows alone — the plate names every post in the
     * area on purpose, so "not in the card" is asked of the card.
     *
     * @return list<string>
     */
    private function listed(string $url): array
    {
        preg_match_all('#<td class="zname">.*?<b>([^<]+)</b>#s', $this->body($url), $found);

        return array_map(trim(...), $found[1]);
    }

    /** @return array{0: AreaOfInterest, 1: Zone} */
    private function anAreaWithAZonedPost(): array
    {
        $area = $this->anArea();
        $zone = $this->aZone($area, 'Western Sector', self::A_WEST_HALF);
        $station = $this->stations()->add($area, 'Seneto Gate Post', -29.75, -3.2);

        $lead = $this->postings()->post($station, $this->aPerson('J.', 'Mollel'), PostingSource::WrittenHere);
        $this->postings()->appointLeader($lead);
        $this->postings()->post($station, $this->aPerson('T.', 'Ndosi'), PostingSource::FromTheirPage);

        return [$area, $zone];
    }

    private function aPerson(string $first, string $last): HostUser
    {
        $person = new HostUser()->named($first, $last);
        $this->em->persist($person);
        $this->em->flush();

        return $person;
    }

    private function stations(): StationService
    {
        /** @var StationService $service */
        $service = static::getContainer()->get('test_public.area.stations');

        return $service;
    }

    private function postings(): PostingService
    {
        /** @var PostingService $service */
        $service = static::getContainer()->get('test_public.area.postings');

        return $service;
    }
}
