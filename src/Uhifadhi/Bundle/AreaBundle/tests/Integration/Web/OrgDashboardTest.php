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

    /**
     * A MODULE'S CELL REACHES THE DASHBOARD, drawn from the MODULE'S OWN
     * partial and reading its own figures under `by.<slug>` — the contract,
     * exercised end to end. A page rendered with only the host's cells
     * proves the host and nothing about the seam it exists for.
     */
    public function testAModulesCellIsDrawnFromItsOwnPartialOnItsOwnFigures(): void
    {
        $this->boot();
        $this->signIn();
        $this->aLiveArea('Northern Conservation Reserve');

        $cell = $this->crawl('/')->filter('[data-w="patrols_org"]');

        self::assertCount(1, $cell, 'The contributed cell is on the grid.');
        self::assertStringContainsString('96 km walked today, everywhere', $cell->text(), 'It read the scope it was handed.');
    }

    /**
     * AND IT NAMES ITSELF. Every contributed cell carries its contributor, so
     * that the day the module is uninstalled its disappearance reads as the
     * system working rather than as a bug.
     */
    public function testAContributedCellStatesWhoseFigureItIs(): void
    {
        $this->boot();
        $this->signIn();
        $this->aLiveArea('Northern Conservation Reserve');

        self::assertStringContainsString(
            'patrols',
            $this->crawl('/')->filter('[data-w="patrols_org"] .ao-by')->text(),
        );
    }

    /**
     * THE ROW IS THE MODULES', AND THE ORGANISATION ONLY FILLS IT. The
     * design's four figures are a module's each; the host's "Areas" tile is
     * there so a fresh installation does not show a row of one, and it takes
     * a slot no module wanted rather than the first one.
     */
    public function testAModulesFigureLeadsTheStripAndTheHostsOnlyFillsIt(): void
    {
        $this->boot();
        $this->signIn();
        $this->aLiveArea('Northern Conservation Reserve');

        $strip = $this->crawl('/')->filter('[data-w="kpis"] .kpi');

        self::assertCount(4, $strip);
        self::assertStringContainsString('Patrols out', $strip->eq(0)->text(), 'The module’s figure leads.');
        self::assertStringContainsString('Areas', $strip->eq(1)->text(), 'The organisation’s fills the next slot.');
    }

    /**
     * AND A MODULE'S FIGURE IS NEVER DISPLACED BY THE HOST'S. With four
     * publishing, the host tile goes: an installation that put it first lost
     * a module's figure off the end of the row, which is the defect this
     * rule exists for.
     */
    public function testFourModuleFiguresPushTheHostsTileOut(): void
    {
        $this->boot(figures: 4);
        $this->signIn();
        $this->aLiveArea('Northern Conservation Reserve');

        $strip = $this->crawl('/')->filter('[data-w="kpis"] .kpi');

        self::assertCount(4, $strip, 'Still four to a row.');
        self::assertStringNotContainsString(
            'Areas',
            implode(' | ', $strip->each(static fn (Crawler $one): string => $one->text())),
            'Every slot is a module’s, so the filler is not drawn at all.',
        );
    }

    /** And the module's cell is counted as ITS contribution, not the host's. */
    public function testTheHostsOwnCellsAreNotCountedAsAnybodysContribution(): void
    {
        $this->boot();

        self::assertSame(['patrols' => 1], $this->catalogue()->widgetCounts());
        self::assertSame(OrgOverviewWidgets::SLUG, $this->catalogue()->contributorOf()['areas']);
        self::assertSame('patrols', $this->catalogue()->contributorOf()['patrols_org']);
    }

    /** A contributed cell brings its own stylesheet, and the page links it. */
    public function testAContributedCellBringsItsSheet(): void
    {
        $this->boot();
        $this->signIn();

        self::assertStringContainsString(
            'bundles/patrols/patrols.css',
            (string) $this->browser()->request('GET', '/')->filter('head')->html(),
        );
    }

    /**
     * THE AREAS TABLE'S OPERATIONAL COLUMNS ARE THE MODULES' OWN, read from
     * the same now-tiles the areas register reads. The host writes the name,
     * the ground and the module count and states no figure it does not own —
     * so a column exists only where a module contributed one, rather than
     * five columns of noughts on an installation running nothing.
     */
    public function testTheAreasTableCarriesTheModulesOwnColumns(): void
    {
        $this->boot();
        $this->signIn();
        $this->aLiveArea('Northern Conservation Reserve');

        $headers = $this->crawl('/')->filter('[data-w="areas"] table.tbl th')->each(
            static fn (Crawler $one): string => trim($one->text()),
        );

        self::assertSame('area', $headers[0]);
        self::assertSame('modules', $headers[1]);
        self::assertSame('state', end($headers));
        self::assertContains('open incidents', $headers, 'A column a module published.');
        self::assertContains('team on duty', $headers);
    }

    /** With nothing switched on anywhere, those columns are simply not there. */
    public function testWithNothingRunningTheTableDrawsNoOperationalColumns(): void
    {
        $this->boot();
        $this->signIn();
        $this->anArea('Registered And Empty');

        $headers = $this->crawl('/')->filter('[data-w="areas"] table.tbl th')->each(
            static fn (Crawler $one): string => trim($one->text()),
        );

        self::assertSame(['area', 'modules', 'state'], $headers);
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

        // `text()` on a node list is the FIRST node's, so the whole row is
        // read here rather than asserted against whichever tile leads.
        $row = implode(' | ', $strip->each(static fn (Crawler $one): string => $one->text()));
        self::assertStringContainsString('nothing measured', $row);
        self::assertStringContainsString('no area registered', $row, 'The organisation’s own tile says why.');
        self::assertStringContainsString(
            'no module publishes this',
            $strip->last()->text(),
            'And a slot nobody filled says so rather than going missing.',
        );
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
        self::assertStringContainsString(
            'sections are the contributors',
            $page->filter('div.pgbody .pghint')->text(),
            'The library says why it is grouped by module and not by direction.',
        );
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
