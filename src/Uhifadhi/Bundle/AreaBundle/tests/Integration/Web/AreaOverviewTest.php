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
use Uhifadhi\Bundle\AreaBundle\Controller\AreaController;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Service\StationService;

/**
 * THE AREA OVERVIEW — what is happening in this area right now.
 *
 * THE PAGE OWNS THE IDENTITY AND NOTHING OPERATIONAL. The band is the area's
 * own: what it measures, how it is divided, where it is and what stands on
 * it. Everything else is a module's contribution, and where nobody
 * contributed the page says so rather than drawing noughts.
 *
 * THE STRIP IS FIVE OR NONE, like every figure row in the product: a row of
 * three where the design has five is a different design, so the cards a
 * module has not filled say which.
 */
#[CoversClass(AreaController::class)]
final class AreaOverviewTest extends WebTestCase
{
    /**
     * THE BAND NAMES WHERE THE AREA IS AND WHAT STANDS ON IT. The centroid is
     * the database's answer, not a number computed from degrees in PHP, and
     * it is what somebody reads out to say roughly where this place is.
     */
    public function testTheBandStatesTheStationsAndWhereTheAreaIs(): void
    {
        $this->boot();
        $this->signIn();
        $area = $this->anArea();
        $this->stations()->add($area, 'Seneto Gate Post', -29.75, -3.2);

        $body = $this->body($area);

        self::assertStringContainsString('Stations', $body);
        self::assertStringContainsString('Centroid', $body);
        // The boundary of this fixture sits south of the equator and west of
        // Greenwich, and the band says so in the hemispheres, never in signs.
        self::assertMatchesRegularExpression('/\d+\.\d°S \d+\.\d°W/u', $body);
    }

    /** An area with no boundary has no centroid, and the cell says so. */
    public function testAnAreaWithNoBoundaryStatesNoCentroid(): void
    {
        $this->boot();
        $this->signIn();

        $area = new AreaOfInterest()->setName('Unmapped Reserve')->setSource('WDPA');
        $this->em->persist($area);
        $this->em->flush();

        self::assertStringContainsString('not set', $this->body($area));
    }

    /**
     * FIVE TILES OR NONE. Three of the five are typically a module's; where
     * no module publishes one, the card keeps its slot and says so.
     */
    public function testTheRightNowStripIsAlwaysFiveTiles(): void
    {
        $this->boot();
        $this->signIn();

        $body = $this->body($this->anArea());

        self::assertSame(5, substr_count($body, 'class="c kpi'));
        self::assertStringContainsString('no module publishes this', $body);
    }

    /**
     * THE STRIP IS THE SHELL'S, AT THE SHELL'S WIDTH. This bundle used to
     * restate the track at 168px, which squeezed five cards where the design
     * fits them at 196.
     */
    public function testTheStripTakesTheShellsOwnTrack(): void
    {
        $this->boot();
        $this->signIn();

        self::assertStringContainsString('class="grid kstrip dp-kstrip"', $this->body($this->anArea()));
    }

    /**
     * THE PLATE STATES ITS OWN HEIGHT, like every other plate in the product.
     *
     * IT IS THE DESIGN'S OWN EXPRESSION AND NOT A NUMBER: this card is the
     * page's subject, so it takes a share of the screen — capped, so that a
     * tall monitor gets a map and not a wall. The record and the tabs state
     * fixed pixels because their plates sit inside a stack of cards; this one
     * is the stack's reason. Either way the PAGE says it: a plate that
     * inherited the atlas's default would change height the day that default
     * did, on a page nobody was looking at.
     */
    public function testThePlateStatesTheHeightTheDesignDrawsItAt(): void
    {
        $this->boot();
        $this->signIn();

        self::assertStringContainsString('--map-plate-height:min(58vh, 560px)', $this->body($this->anArea()));
    }

    private function body(AreaOfInterest $area): string
    {
        $this->browser()->request('GET', '/areas/'.$area->getUuidString());

        return (string) $this->browser()->getResponse()->getContent();
    }

    private function stations(): StationService
    {
        /** @var StationService $service */
        $service = static::getContainer()->get('test_public.area.stations');

        return $service;
    }
}
