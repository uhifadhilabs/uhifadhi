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
use Symfony\Component\DomCrawler\Crawler;
use Uhifadhi\Bundle\AreaBundle\Controller\OrgDashboardController;
use Uhifadhi\Bundle\AreaBundle\Service\OrgOverviewCatalogue;
use Uhifadhi\Bundle\AreaBundle\Shell\OrgDashboardNavigation;
use Uhifadhi\Bundle\AreaBundle\Widget\OrgOverviewWidgets;

/**
 * THE ORGANISATION DASHBOARD — `/`, composed rather than authored.
 *
 * WHAT IS WORTH PINNING is not the markup but the composition: that the page
 * is a widget surface, that its cells come from contributors, that the five
 * designs are the design's to the twelfth with E the one it ships on, and
 * that an installation with nothing in it still draws a page that reports
 * rather than one that breaks.
 */
#[CoversClass(OrgDashboardController::class)]
#[CoversClass(OrgOverviewCatalogue::class)]
#[CoversClass(OrgOverviewWidgets::class)]
#[CoversClass(OrgDashboardNavigation::class)]
final class OrgDashboardTest extends WebTestCase
{
    /** `/` is a page in the frame, and it is a widget grid. */
    public function testTheFrontDoorIsAComposedDashboard(): void
    {
        $this->boot();
        $this->signIn();
        $this->aLiveArea('Northern Conservation Reserve');

        $page = $this->crawl('/');

        self::assertCount(1, $page->filter('aside.side'), 'It renders in the frame like every other page.');
        self::assertCount(1, $page->filter('main.main div.page'));
        self::assertCount(1, $page->filter('div.pgbody .w-grid'), 'The page is a grid the surface composes.');
        self::assertCount(0, $page->filter('div.atabs'), 'It is not inside a place, so it has no tab strip.');
    }

    /**
     * THE WIDGET-LIBRARY DOOR IS THE ONE STANDARD LINK, identical on every
     * surface in the product — never "Customize widgets" and never a plus
     * icon. Editing does not happen on the dashboard itself.
     */
    public function testTheLibraryDoorIsTheHouseOne(): void
    {
        $this->boot();
        $this->signIn();

        $door = $this->crawl('/')->filter('div.pgact a.tgl.w-act');

        self::assertCount(1, $door);
        self::assertStringContainsString('Widget library', $door->text());
        self::assertSame('/widgets', $door->attr('href'));
    }

    /** THERE IS NO SCOPE CONTROL (ruled): the dashboard IS the organisation. */
    public function testThereIsNoScopeControlOnTheDashboard(): void
    {
        $this->boot();
        $this->signIn();
        $this->aLiveArea('Northern Conservation Reserve');

        self::assertCount(0, $this->crawl('/')->filter('.ov-ctl'));
    }

    /**
     * THE FIVE COMPOSITIONS ARE THE DESIGN'S, to the twelfth — layouts and
     * spans exactly as the workspace declares them.
     */
    public function testTheFiveCompositionsAreTheDesignsOwn(): void
    {
        $presets = [];
        foreach (OrgOverviewCatalogue::presets() as $preset) {
            $presets[$preset->id] = $preset->layout;
        }

        self::assertSame(['a', 'b', 'c', 'd', 'e'], array_keys($presets));
        self::assertSame(['kpis' => 12, 'attention' => 12, 'plate' => 12, 'areas' => 6, 'watches' => 6, 'patrols' => 12], $presets['a']);
        self::assertSame(['kpis' => 12, 'areas' => 12, 'plate' => 6, 'watches' => 6, 'attention' => 12], $presets['b']);
        self::assertSame(['kpis' => 12, 'attention' => 12, 'incidents' => 6, 'watches' => 6, 'plate' => 12], $presets['c']);
        self::assertSame(['kpis' => 12, 'goals' => 6, 'areas' => 6, 'incidents' => 6, 'modules' => 6], $presets['d']);
        self::assertSame(
            ['kpis' => 12, 'attention' => 12, 'plate' => 12, 'areas' => 6, 'watches' => 6, 'patrols' => 6, 'incidents' => 6, 'goals' => 6, 'files' => 6, 'modules' => 12],
            $presets['e'],
        );
    }

    /** E IS THE ONE IT SHIPS ON (ruled) — everything, in contributor order. */
    public function testEverythingIsTheShippedDefault(): void
    {
        $this->boot();

        self::assertSame('e', $this->catalogue()->catalog()->defaultPresetId());
    }

    /**
     * A PRESET MAY NAME A CELL THIS INSTALLATION HAS NOT GOT, and that is the
     * design rather than an oversight: a preset is a SHAPE, so the same five
     * get richer as an installation grows instead of being rewritten per
     * deployment. The resolver drops what nobody contributes.
     */
    public function testAPresetNamingAnUninstalledCellStillComposes(): void
    {
        $this->boot();
        $this->signIn();
        $this->aLiveArea('Northern Conservation Reserve');

        $cells = $this->crawl('/')->filter('div.pgbody .w-grid > .w-cell');

        self::assertGreaterThan(0, $cells->count(), 'The cells this installation does have are drawn.');
        self::assertCount(0, $this->crawl('/')->filter('[data-w="watches"]'), 'And a module cell nobody ships is simply absent.');
    }

    /** Every cell the core ships is the organisation's own, and drawn from its own partial. */
    public function testTheOrganisationsOwnCellsAreTheOnlyOnesHere(): void
    {
        $this->boot();

        $contributors = array_values(array_unique(array_values($this->catalogue()->contributorOf())));

        self::assertSame([OrgOverviewWidgets::SLUG], $contributors, 'With no module installed, every cell is the host’s.');
        self::assertSame([], $this->catalogue()->widgetCounts(), 'And nothing is counted as a module’s contribution.');
    }

    // ---------------------------------------------------------- the empty state

    /**
     * AN INSTALLATION WITH NO AREAS STILL DRAWS A PAGE (ruled). The figures
     * keep their slots and say nothing was measured; four cards saying so is
     * a report, four cards missing is a broken page.
     */
    public function testWithNoAreasTheFiguresStateThatNothingWasMeasured(): void
    {
        $this->boot();
        $this->signIn();

        $strip = $this->crawl('/')->filter('[data-w="kpis"] .kpi');

        self::assertCount(4, $strip, 'A figure row is four to a row, filled or not.');
        self::assertStringContainsString('nothing measured', $strip->first()->text());
        self::assertStringContainsString('no area registered', $strip->first()->text());
    }

    /** AND NO PLATE: a map of no areas is a map of nothing. */
    public function testWithNoAreasThereIsNoPlate(): void
    {
        $this->boot();
        $this->signIn();

        self::assertCount(0, $this->crawl('/')->filter('[data-w="plate"]'));
    }

    /** THE ONE DOOR OUT is the page that says what this installation gives you. */
    public function testTheEmptyDashboardPointsAtWhatToDoNext(): void
    {
        $this->boot();
        $this->signIn();

        $hint = $this->crawl('/')->filter('div.pgbody .pghint');

        self::assertCount(1, $hint);
        self::assertStringContainsString('Nothing to show yet', $hint->text());
        self::assertStringContainsString('Add an area', $hint->text());
    }

    /** With areas, the plate is there and the hint is the composed-not-authored one. */
    public function testWithAnAreaThePlateIsDrawnAndTheHintChanges(): void
    {
        $this->boot();
        $this->signIn();
        $this->aLiveArea('Northern Conservation Reserve');

        $page = $this->crawl('/');

        self::assertCount(1, $page->filter('[data-w="plate"]'));
        self::assertStringContainsString('composed, not authored', $page->filter('div.pgbody .pghint')->text());
    }

    // ------------------------------------------------------------- the sidebar

    /**
     * DASHBOARD IS THE FIRST ROW OF OBSERVATORY, because `/` is a page now
     * and a page needs a door.
     */
    public function testDashboardIsTheFirstRowOfTheFirstGroup(): void
    {
        $this->boot();
        $this->signIn();

        $rows = $this->crawl('/')->filter('nav.nav > a.nav-item, nav.nav > span.nav-item');

        self::assertGreaterThan(1, $rows->count());
        self::assertSame('Dashboard', trim($rows->first()->filter('span')->first()->text()));
    }

    /**
     * AND IT IS LIT ONLY ON `/`. Its address is the prefix of every address
     * there is, so the usual "is the path inside mine" test would light it
     * everywhere and the sidebar would answer "where am I" with "everywhere".
     */
    public function testTheDashboardRowIsLitOnlyOnTheDashboard(): void
    {
        $this->boot();
        $this->signIn();

        self::assertCount(1, $this->crawl('/')->filter('nav.nav > a.nav-item.on'));
        self::assertSame('Dashboard', trim($this->crawl('/')->filter('nav.nav > a.nav-item.on span')->first()->text()));

        $elsewhere = $this->crawl('/areas')->filter('nav.nav > a.nav-item.on span');
        self::assertNotSame('Dashboard', $elsewhere->count() > 0 ? trim($elsewhere->first()->text()) : '');
    }

    /** THE BRANDMARK POINTS AT THE DASHBOARD, which is the other half of the same ruling. */
    public function testTheBrandmarkPointsAtTheDashboard(): void
    {
        $this->boot();
        $this->signIn();

        self::assertSame('/', $this->crawl('/areas')->filter('aside.side a.brand')->attr('href'));
    }

    // -------------------------------------------------------------- the library

    /**
     * The library is the shell's shared component, on this surface's
     * catalogue. It needs a real principal, not merely a signed-in one: a
     * layout belongs to somebody, and an in-memory user is nobody.
     */
    public function testTheLibraryRendersTheSurfacesOwnCatalogue(): void
    {
        $this->boot();
        $this->signInAsPerson();

        $page = $this->crawl('/widgets');

        self::assertCount(1, $page->filter('[data-widget-root]'), 'The framework’s library root.');
        self::assertStringContainsString('widget library', $page->filter('h1.pg')->text());
    }

    private function catalogue(): OrgOverviewCatalogue
    {
        $catalogue = static::getContainer()->get('test_public.area.org_catalogue');
        \assert($catalogue instanceof OrgOverviewCatalogue);

        return $catalogue;
    }

    private function crawl(string $url): Crawler
    {
        return $this->browser()->request('GET', $url);
    }
}
