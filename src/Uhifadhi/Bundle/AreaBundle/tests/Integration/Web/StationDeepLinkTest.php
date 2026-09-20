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
use Uhifadhi\Bundle\AreaBundle\Controller\StationConfigureController;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Entity\Station;
use Uhifadhi\Bundle\AreaBundle\Model\StationRegister;
use Uhifadhi\Bundle\AreaBundle\Service\StationService;

/**
 * A DEEP LINK INTO THE STATIONS REGISTER LANDS ON THE STATION IT NAMES.
 *
 * THE DEFECT THIS PINS. The register paginates eight cards to a page, and
 * `?open=<uuid>` only ever opened a card the FIRST page happened to hold. So
 * from the ninth station on, every link that names one — the record's "Edit
 * the station", the empty state's "Post somebody", the redirect after a form
 * posts — answered with page one and nothing open. A link that silently does
 * nothing is worse than a broken one: nobody reports it, they just conclude
 * there is no way to post anybody.
 *
 * THE PAGE IS DECIDED WHERE THE PAGING IS. The register service has just
 * filtered and ordered the set when it answers, so it is the only place that
 * can say which page a row is on without a second copy of the sort, the
 * filters and the page size.
 */
#[CoversClass(StationConfigureController::class)]
final class StationDeepLinkTest extends WebTestCase
{
    /** One more than a page holds, so the last one is on page two. */
    private const int STATIONS = 9;

    /**
     * THE NINTH STATION, NAMED IN THE ADDRESS, OPENS — on page two, which the
     * page was never asked for and worked out for itself.
     */
    public function testALinkToTheNinthStationLandsOnPageTwoWithItOpen(): void
    {
        $this->boot();
        $this->signIn();
        [$area, $ninth] = $this->nineStations();

        $url = $this->section($area).'?open='.$ninth->getUuidString();

        self::assertSame(['Post 09'], $this->listed($url), 'Page two holds the one row after the first eight.');
        self::assertMatchesRegularExpression(
            '#<details class="zcard on focusline"\s+open>#',
            $this->body($url),
            'And it is open, and marked as the one the address is about.',
        );
    }

    /** Asking for nothing in particular still answers with the first page. */
    public function testWithNoStationNamedTheRegisterOpensOnItsFirstPage(): void
    {
        $this->boot();
        $this->signIn();
        [$area] = $this->nineStations();

        $listed = $this->listed($this->section($area));

        self::assertCount(8, $listed, 'A page of the register is eight rows.');
        self::assertSame('Post 01', $listed[0]);
        self::assertNotContains('Post 09', $listed);
    }

    /**
     * AND A PAGE SOMEBODY ASKED FOR IS NOT OVERRULED BY A LINK THAT NAMES
     * NOTHING — the two halves of the address do not fight.
     */
    public function testAnAskedForPageStandsWhenNoStationIsNamed(): void
    {
        $this->boot();
        $this->signIn();
        [$area] = $this->nineStations();

        self::assertSame(['Post 09'], $this->listed($this->section($area).'?page=2'));
    }

    /**
     * THE PAGE IS WORKED OUT AGAINST THE ORDER IN FORCE, not against the
     * default one: reversed by most-posted or by zone, the ninth row by name
     * is somewhere else entirely, and the link must still land on it.
     */
    public function testThePageIsWorkedOutAgainstTheOrderInForce(): void
    {
        $this->boot();
        $this->signIn();
        [$area, $ninth] = $this->nineStations();

        $url = $this->section($area).'?sort=zone&open='.$ninth->getUuidString();

        self::assertContains('Post 09', $this->listed($url), 'Whatever the order puts it on, that page answers.');
        self::assertStringContainsString('open>', $this->body($url));
    }

    // ---------------------------------------------------------------- fixtures

    /** @return array{0: AreaOfInterest, 1: Station} the area, and the ninth station by name */
    private function nineStations(): array
    {
        $area = $this->anArea();
        $stations = static::getContainer()->get('test_public.area.stations');
        \assert($stations instanceof StationService);

        self::assertSame(8, StationRegister::PER_PAGE, 'This suite is about the row after the last of a page.');

        $ninth = null;
        for ($n = 1; $n <= self::STATIONS; ++$n) {
            $station = $stations->add($area, \sprintf('Post %02d', $n), -29.75 + ($n / 100), -3.2, \sprintf('ST-%02d', $n));
            if (self::STATIONS === $n) {
                $ninth = $station;
            }
        }
        $this->em->flush();

        self::assertInstanceOf(Station::class, $ninth);

        return [$area, $ninth];
    }

    private function section(AreaOfInterest $area): string
    {
        return '/areas/'.$area->getUuidString().'/stations/settings';
    }

    /**
     * THE REGISTER'S ROWS ALONE. The point picker's plate names EVERY post in
     * the area — deliberately, so a new point cannot be put on top of one —
     * so "this row is not on this page" is a question for the register and
     * not for the page.
     *
     * @return list<string>
     */
    private function listed(string $url): array
    {
        preg_match_all('#<a class="zc-nm"[^>]*>([^<]+)</a>#', $this->body($url), $found);

        return array_map(trim(...), $found[1]);
    }

    private function body(string $url): string
    {
        $this->browser()->request('GET', $url);

        return (string) $this->browser()->getResponse()->getContent();
    }
}
