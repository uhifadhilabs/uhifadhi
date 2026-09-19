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
use Uhifadhi\Bundle\AreaBundle\Controller\ZoneRecordController;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Entity\Zone;
use Uhifadhi\Bundle\AreaBundle\Enum\PostingSource;
use Uhifadhi\Bundle\AreaBundle\Service\PostingService;
use Uhifadhi\Bundle\AreaBundle\Service\StationService;
use Uhifadhi\Bundle\AreaBundle\Tests\Integration\Web\Fixtures\HostUser;

/**
 * ONE ZONE, READ.
 *
 * A RECORD, NOT A TAB. A picked zone wears the station page's treatment: its
 * bare name as the h1, its context demoted to the subline, the crumb one step
 * longer, its own identity band — and no tab strip, because a zone is a thing
 * inside the area rather than one of the ways of looking at the area.
 *
 * ITS FIGURES ARE IN ITS BAND. A record page states them there the way the
 * station page does; there is no card row on a record.
 */
#[CoversClass(ZoneRecordController::class)]
final class ZoneRecordTest extends WebTestCase
{
    public function testTheZoneIsDrawnAsARecordWithItsBandItsGroundAndItsPeople(): void
    {
        $this->boot();
        $this->signIn();
        [$area, $zone] = $this->aWorkedZone();

        $body = $this->body($this->record($area, $zone));

        self::assertStringContainsString('Western Sector', $body);
        self::assertStringContainsString('Extent', $body);
        self::assertStringContainsString('Stations', $body);
        self::assertStringContainsString('Seneto Gate Post', $body);
        self::assertStringContainsString('J. Mollel', $body);
        self::assertStringContainsString('map-plate', $body);
        // A record, so no tab strip and no figure cards.
        self::assertStringNotContainsString('class="atabs"', $body);
        self::assertStringNotContainsString('class="c kpi"', $body);
    }

    /** The way back is the set it belongs to. */
    public function testTheBandLeadsBackToTheWholeSet(): void
    {
        $this->boot();
        $this->signIn();
        [$area, $zone] = $this->aWorkedZone();

        self::assertStringContainsString(
            '/areas/'.$area->getUuidString().'/zones"',
            $this->body($this->record($area, $zone)),
        );
    }

    /** No module publishes about this ground, and the band says so rather than printing a nought. */
    public function testTheModuleFactsSayNobodyPublishesThem(): void
    {
        $this->boot();
        $this->signIn();
        [$area, $zone] = $this->aWorkedZone();

        self::assertStringContainsString('no module publishes this', $this->body($this->record($area, $zone)));
    }

    /** A zone with no post on it is an ordinary state and says which. */
    public function testAZoneWithNoPostSaysSo(): void
    {
        $this->boot();
        $this->signIn();
        $area = $this->anArea();
        // The eastern half: ground with no post standing on it.
        $zone = $this->aZone($area, 'Eastern Sector', '{"type":"MultiPolygon","coordinates":[[[[-29.5,-3.6],[-29.0,-3.6],[-29.0,-2.8],[-29.5,-2.8],[-29.5,-3.6]]]]}');

        $body = $this->body($this->record($area, $zone));

        self::assertStringContainsString('No station in Eastern Sector', $body);
        self::assertStringContainsString('Nobody works out of Eastern Sector', $body);
    }

    /** The stations card searches, and the search is in the address. */
    public function testTheStationsCardSearchesWithinTheZone(): void
    {
        $this->boot();
        $this->signIn();
        [$area, $zone] = $this->aWorkedZone();
        $this->stations()->add($area, 'Lerai Ranger Post', -29.8, -3.2);

        self::assertSame(['Seneto Gate Post'], $this->listed($this->record($area, $zone).'?q=seneto'));
        self::assertCount(2, $this->listed($this->record($area, $zone)));
    }

    /** A zone of another area is not this page's subject. */
    public function testAZoneOfAnotherAreaIsRefused(): void
    {
        $this->boot();
        $this->signIn();
        [, $zone] = $this->aWorkedZone();
        $other = $this->anArea('Second Reserve');

        $this->browser()->request('GET', '/areas/'.$other->getUuidString().'/zones/'.$zone->getUuidString());

        self::assertSame(Response::HTTP_FORBIDDEN, $this->browser()->getResponse()->getStatusCode());
    }

    public function testAViewerWhoMayNotSeeTheAreaIsRefused(): void
    {
        $this->boot([]);
        $this->signIn();
        [$area, $zone] = $this->aWorkedZone();

        $this->browser()->request('GET', $this->record($area, $zone));

        self::assertSame(Response::HTTP_FORBIDDEN, $this->browser()->getResponse()->getStatusCode());
    }

    // ---------------------------------------------------------------- fixtures

    private function record(AreaOfInterest $area, Zone $zone): string
    {
        return '/areas/'.$area->getUuidString().'/zones/'.$zone->getUuidString();
    }

    private function body(string $url): string
    {
        $this->browser()->request('GET', $url);

        return (string) $this->browser()->getResponse()->getContent();
    }

    /** @return list<string> */
    private function listed(string $url): array
    {
        preg_match_all('#<td class="zname">.*?<b>([^<]+)</b>#s', $this->body($url), $found);

        return array_map(trim(...), $found[1]);
    }

    /** @return array{0: AreaOfInterest, 1: Zone} */
    private function aWorkedZone(): array
    {
        $area = $this->anArea();
        $zone = $this->aZone($area, 'Western Sector', self::A_WEST_HALF);
        $station = $this->stations()->add($area, 'Seneto Gate Post', -29.75, -3.2);

        $lead = $this->postings()->post($station, $this->aPerson('J.', 'Mollel'), PostingSource::WrittenHere);
        $this->postings()->appointLeader($lead);

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
