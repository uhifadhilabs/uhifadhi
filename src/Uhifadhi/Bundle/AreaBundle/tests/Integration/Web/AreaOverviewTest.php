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
use Uhifadhi\Bundle\RegistryBundle\Entity\Module;
use Uhifadhi\Bundle\RegistryBundle\Enum\ModuleCategory;
use Uhifadhi\Bundle\RegistryBundle\Enum\ModuleStatus;
use Uhifadhi\Bundle\RegistryBundle\Service\AreaModuleService;

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
     * THE STRIP IS THE SHELL'S, WHOLE — its track and its spacing. This
     * bundle used to restate the track at 168px, which squeezed five cards
     * where the design fits them at 196, and then zeroed the strip's bottom
     * margin, which closed the twenty pixels between it and the card below.
     */
    public function testTheStripTakesTheShellsOwnTrack(): void
    {
        $this->boot();
        $this->signIn();

        self::assertStringContainsString('class="grid kstrip"', $this->body($this->anArea()));
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

    /**
     * THE PAGE IS COMPOSED, NOT AUTHORED — and this is the test that says so.
     *
     * The grid is the shell's, every cell is a contributor's, and the order
     * and the spans are the assembled default preset's: the area's own four
     * full-width cells, then the pair that read side by side. A module
     * switched on here adds its own cell to the same grid without this
     * bundle naming it.
     */
    public function testThePageIsAGridOfContributedCellsInTheDesignsOrder(): void
    {
        $this->boot();
        $this->signIn();

        $body = $this->body($this->anArea());

        self::assertStringContainsString('class="w-grid"', $body);
        self::assertSame(
            ['w-span-12', 'w-span-12', 'w-span-12', 'w-span-12', 'w-span-6', 'w-span-6'],
            self::spansOf($body),
        );
    }

    /**
     * A MODULE'S CELL RENDERS, AND IT RENDERS BY THE PUBLISHED CONTRACT.
     *
     * THE CONTRACT IS `by.<slug>`. A contributed partial is rendered with
     * `with_context: false` and everything it needs in ONE map, its own
     * figures under its own slug — the module templates say so in their
     * headers and the contract document says so in its table. A host that
     * merged those figures flat into the page's own context rendered its
     * own cells perfectly and fataled on every module's, and no test caught
     * it because the only area the suite rendered had nothing switched on.
     */
    public function testAModulesCellRendersFromItsOwnSlugInTheContext(): void
    {
        $this->boot();
        $this->signIn();
        $area = $this->anArea();
        $this->aModuleInstalledIn($area);

        $body = $this->body($area);

        self::assertStringContainsString('data-w="pl_now"', $body);
        self::assertStringContainsString('3 open', $body);
        self::assertStringContainsString('96 km walked today', $body);
    }

    /**
     * AND THE SHARED HALF OF THE MAP IS ALL OF IT. A module template is
     * written against the names the contract publishes — `area`, `now`,
     * `tiles`, `attention`, `layers`, `legend` — so the host supplies every
     * one, not merely the ones today's modules happen to read. The next
     * module to read `legend` should not be the one that discovers it is
     * missing.
     */
    public function testTheSharedHalfOfTheContextCarriesEveryPublishedName(): void
    {
        $this->boot();
        $this->signIn();
        $area = $this->anArea();
        $this->aModuleInstalledIn($area);

        // The fixture's partial reads one of them and `by`; the rest are
        // proven by the page rendering with strict_variables on, which is
        // how a missing name fails here rather than in an installation.
        self::assertStringContainsString('data-w="pl_now"', $this->body($area));

        $context = new \ReflectionClass(AreaController::class);
        $source = (string) file_get_contents((string) $context->getFileName());
        foreach (["'area' =>", "'now' =>", "'tiles' =>", "'attention' =>", "'layers' =>", "'legend' =>", "'by' =>"] as $name) {
            self::assertStringContainsString($name, $source, \sprintf('The shared map does not carry %s.', $name));
        }
    }

    /** And it joins the grid at the span its module asked for. */
    public function testAModulesCellTakesTheSpanItAskedFor(): void
    {
        $this->boot();
        $this->signIn();
        $area = $this->anArea();
        $this->aModuleInstalledIn($area);

        self::assertSame(
            ['w-span-12', 'w-span-12', 'w-span-12', 'w-span-12', 'w-span-6', 'w-span-6', 'w-span-6'],
            self::spansOf($this->body($area)),
        );
    }

    /**
     * THE ROSTER'S SLOT IS HELD OPEN, not dropped. The design's composition
     * has a roster card in that half-row; until roster publishes one the
     * cell says which module owes it, so the row keeps its shape and the
     * absence is legible rather than silent.
     */
    public function testTheRostersCellStatesItsAbsenceRatherThanVanishing(): void
    {
        $this->boot();
        $this->signIn();

        $body = $this->body($this->anArea());

        self::assertStringContainsString('Stations &amp; who is on', $body);
        self::assertStringContainsString('awaiting the roster', $body);
    }

    /** The registry's own cell says what is on here, out of what there is. */
    public function testTheModulesCellStatesWhatIsOnAgainstTheCatalogue(): void
    {
        $this->boot();
        $this->signIn();

        $body = $this->body($this->anArea());

        self::assertStringContainsString('Modules in this area', $body);
        self::assertStringContainsString('in the catalogue', $body);
    }

    /**
     * THE ATTENTION CARD IS BOUNDED. A list as long as the modules' day made
     * the card two thousand pixels tall and pushed the ground off the
     * screen; it draws the most urgent few and says what out of.
     */
    public function testTheAttentionCardIsBoundedAndSaysWhatOutOf(): void
    {
        $this->boot(attention: 9);
        $this->signIn();
        $area = $this->anArea();
        $this->aModuleInstalledIn($area);

        $body = $this->body($area);

        self::assertSame(6, substr_count($body, 'class="ao-att'));
        self::assertStringContainsString('6 of 9', $body);
        // AND NO LINK TO A PAGE THAT DOES NOT EXIST: an item belongs to the
        // module that raised it, so that is where the card sends you.
        self::assertStringContainsString('the rest are in the modules that raised them', $body);
    }

    /**
     * A MODULE SWITCHED ON HERE, so the stand-in's contributions reach the
     * page: nothing a module contributes is drawn for an area that does not
     * run it.
     */
    private function aModuleInstalledIn(AreaOfInterest $area): void
    {
        $this->em->persist(new Module()
            ->setSlug('patrols')
            ->setName('Patrols')
            ->setCategory(ModuleCategory::Pressure)
            ->setStatus(ModuleStatus::Live)
            ->setDataSource('GPS field tracks')
            ->setPosition(0));
        $this->em->flush();

        /** @var AreaModuleService $modules */
        $modules = static::getContainer()->get('test_public.registry.area_modules');
        $modules->install($area, 'patrols');
    }

    /** @return list<string> */
    private static function spansOf(string $body): array
    {
        preg_match_all('#<div class="w-cell (w-span-\d+)"#', $body, $found);

        return $found[1];
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
