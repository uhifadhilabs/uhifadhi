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
use Uhifadhi\Bundle\AreaBundle\Controller\StationsController;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Entity\Station;
use Uhifadhi\Bundle\AreaBundle\Enum\PostingSource;
use Uhifadhi\Bundle\AreaBundle\Service\PostingService;
use Uhifadhi\Bundle\AreaBundle\Service\StationService;
use Uhifadhi\Bundle\AreaBundle\Tests\Integration\Web\Fixtures\HostUser;

/**
 * THE STATIONS TAB, OVER REAL HTTP.
 *
 * A TAB AND NOT A CONFIGURE SCREEN: the strip, the identity band, the
 * figures, the plate, one flat table and the people posted — and every
 * control that would change something points at Configure.
 *
 * FIVE FIGURES OR NONE. Two of the five are whatever modules publish about a
 * post; where none does, the cards say so rather than being dropped, because
 * a row of three is a different design.
 */
#[CoversClass(StationsController::class)]
final class StationsTabTest extends WebTestCase
{
    public function testTheTabDrawsTheBandTheFiguresThePlateAndTheTable(): void
    {
        $this->boot();
        $this->signIn();
        [$area] = $this->aStaffedPost();

        $body = $this->body($this->tab($area));

        self::assertStringContainsString('All stations', $body);
        self::assertStringContainsString('Leaders appointed', $body);
        self::assertStringContainsString('People posted', $body);
        self::assertStringContainsString('Seneto Gate Post', $body);
        self::assertStringContainsString('ST-01', $body);
        self::assertStringContainsString('Western Sector', $body);
        self::assertStringContainsString('map-plate', $body);
        self::assertStringContainsString('map-legend', $body);
        // A tab, so the strip is there and the Stations entry is on it.
        self::assertStringContainsString('class="atabs"', $body);
    }

    /** The people posted in the area are listed with where they stand. */
    public function testThePostedPeopleAreListedWithTheirPost(): void
    {
        $this->boot();
        $this->signIn();
        [$area] = $this->aStaffedPost();

        $body = $this->body($this->tab($area));

        self::assertStringContainsString('Posted people', $body);
        self::assertStringContainsString('J. Mollel', $body);
        self::assertStringContainsString('Leads', $body);
    }

    /**
     * NO MODULE PUBLISHES HERE, so the last two cards say which rather than
     * leaving a row of three.
     */
    public function testTheModuleCardsSayNobodyPublishesRatherThanBeingDropped(): void
    {
        $this->boot();
        $this->signIn();
        [$area] = $this->aStaffedPost();

        $body = $this->body($this->tab($area));

        self::assertSame(5, substr_count($body, 'class="c kpi"'));
        self::assertStringContainsString('no module publishes figures for these posts', $body);
    }

    /**
     * THE PLATE IS A FIXED HEIGHT ON A TAB, stated by the page rather than
     * left to the atlas's default: a card in a stack that sized itself to the
     * viewport would be a different card on every screen, and the design
     * draws this one at 420 with its key below it.
     */
    public function testThePlateIsDrawnAtTheHeightTheDesignFixes(): void
    {
        $this->boot();
        $this->signIn();
        [$area] = $this->aStaffedPost();

        self::assertStringContainsString('--map-plate-height:420px', $this->body($this->tab($area)));
    }

    /** WHO LEADS IS A FILTER TOO: a post with no lead appointed is a thing to look for. */
    public function testTheTableIsFilteredByWhetherALeadIsAppointed(): void
    {
        $this->boot();
        $this->signIn();
        [$area] = $this->aStaffedPost();
        $unled = $this->stations()->add($area, 'Eastern Outpost', -29.25, -3.2);
        $this->postings()->post($unled, $this->aPerson('B.', 'Mwita'), PostingSource::WrittenHere);

        self::assertSame(['Seneto Gate Post'], $this->listed($this->tab($area).'?lead=yes'));
        self::assertSame(['Eastern Outpost'], $this->listed($this->tab($area).'?lead=no'));
        // And the chip is on the bar, offering both answers.
        $bar = $this->body($this->tab($area));
        self::assertStringContainsString('Lead appointed', $bar);
        self::assertStringContainsString('No lead', $bar);
    }

    public function testTheTableIsFilteredByTheAddress(): void
    {
        $this->boot();
        $this->signIn();
        [$area] = $this->aStaffedPost();
        $this->stations()->add($area, 'Eastern Outpost', -29.25, -3.2);

        self::assertSame(['Seneto Gate Post'], $this->listed($this->tab($area).'?zone='.$this->zoneUuid($area)));
        self::assertSame(['Eastern Outpost'], $this->listed($this->tab($area).'?posted=no'));
        self::assertSame(['Eastern Outpost'], $this->listed($this->tab($area).'?q=eastern'));
    }

    public function testTheTableIsPagedEightAtATime(): void
    {
        $this->boot();
        $this->signIn();
        $area = $this->anArea();
        for ($i = 1; $i <= 9; ++$i) {
            $this->stations()->add($area, \sprintf('Post %02d', $i), -29.75, -3.2);
        }

        self::assertCount(8, $this->listed($this->tab($area)));
        self::assertSame(['Post 09'], $this->listed($this->tab($area).'?page=2'));
    }

    /** An area with no post says what a station is rather than drawing an empty table. */
    public function testAnAreaWithNoPostSaysSoAndPointsAtConfigure(): void
    {
        $this->boot();
        $this->signIn();

        $body = $this->body($this->tab($this->anArea()));

        self::assertStringContainsString('No station in this area yet', $body);
        self::assertStringContainsString('Configure stations', $body);
    }

    public function testAViewerWhoMayNotSeeTheAreaIsRefused(): void
    {
        $this->boot([]);
        $this->signIn();
        [$area] = $this->aStaffedPost();

        $this->browser()->request('GET', $this->tab($area));

        self::assertSame(Response::HTTP_FORBIDDEN, $this->browser()->getResponse()->getStatusCode());
    }

    // ---------------------------------------------------------------- fixtures

    private function tab(AreaOfInterest $area): string
    {
        return '/areas/'.$area->getUuidString().'/stations';
    }

    private function body(string $url): string
    {
        $this->browser()->request('GET', $url);

        return (string) $this->browser()->getResponse()->getContent();
    }

    /**
     * THE TABLE'S ROWS ALONE: the plate names every post in the area, on
     * purpose, so "this row is not in the table" is asked of the table.
     *
     * @return list<string>
     */
    private function listed(string $url): array
    {
        preg_match_all('#<td><b>([^<]+)</b>#', $this->body($url), $found);

        return array_map(trim(...), $found[1]);
    }

    private function zoneUuid(AreaOfInterest $area): string
    {
        return (string) $this->em->getRepository(\Uhifadhi\Bundle\AreaBundle\Entity\Zone::class)
            ->findOneBy(['area' => $area])?->getUuidString();
    }

    /** @return array{0: AreaOfInterest, 1: Station} */
    private function aStaffedPost(): array
    {
        $area = $this->anArea();
        $this->aZone($area, 'Western Sector', self::A_WEST_HALF);
        $station = $this->stations()->add($area, 'Seneto Gate Post', -29.75, -3.2);

        $lead = $this->postings()->post($station, $this->aPerson('J.', 'Mollel'), PostingSource::WrittenHere);
        $this->postings()->appointLeader($lead);
        $this->postings()->post($station, $this->aPerson('T.', 'Ndosi'), PostingSource::FromTheirPage);

        return [$area, $station];
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
