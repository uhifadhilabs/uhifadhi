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

use Symfony\Component\HttpFoundation\Response;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;

/**
 * THE SCREENS, RENDERED — against a real database, through the real shell frame,
 * with a real firewall in front of them.
 *
 * What is asserted here is what a person would see: the register lists the areas
 * and says so when there are none; an area's overview states what the area IS
 * and is honest about everything nobody contributed; the zones page explains a
 * zone rather than drawing an empty table. And every screen is refused to
 * somebody who does not hold its permission — refused, not quietly emptied.
 */
final class AreaPagesTest extends WebTestCase
{
    private function get(string $path): Response
    {
        $this->browser()->request('GET', $path);

        return $this->browser()->getResponse();
    }

    private function body(string $path): string
    {
        return (string) $this->get($path)->getContent();
    }

    public function testTheRegisterListsEveryArea(): void
    {
        $this->boot();
        $this->anArea('Northern Conservation Reserve');
        $this->anArea('Western Reserve');

        $body = $this->body('/areas');

        self::assertStringContainsString('Northern Conservation Reserve', $body);
        self::assertStringContainsString('Western Reserve', $body);
        // Every card opens the area it names.
        self::assertStringContainsString('Open', $body);
    }

    /**
     * THE REGISTER IS A WALL OF WORKSPACES, NOT A TABLE. The graduated design
     * draws one card per area — a thumbnail, its identity and its operational
     * pulse — so the register renders card markup and no table.
     */
    public function testTheRegisterDrawsCardsNotATable(): void
    {
        $this->boot();
        $this->anArea();

        $body = $this->body('/areas');

        self::assertStringContainsString('ax-card', $body);
        self::assertStringContainsString('ax-cards', $body);
        self::assertStringNotContainsString('<table', $body);
    }

    /**
     * THE CARD FACE CARRIES THE AREA'S REAL BOUNDARY. A boundaried area's card
     * draws its own outline, projected into the face's viewBox — not a stand-in
     * image. (The satellite RASTER under it is deferred; the outline is real.).
     */
    public function testABoundariedAreasCardDrawsItsOwnOutline(): void
    {
        $this->boot();
        $this->anArea();

        $body = $this->body('/areas');

        self::assertStringContainsString('ax-outline', $body);
        self::assertStringContainsString('<path d="M', $body);
    }

    /**
     * A BOUNDARY-ONLY AREA READS HONESTLY, NOT AS A GRID OF NOUGHTS. An area with
     * a boundary but no module switched on is new, not broken: its card says so
     * and points at the first thing to do, rather than drawing four zeroes.
     */
    public function testABoundariedAreaWithNoModulesRendersAsAwaitingSetup(): void
    {
        $this->boot();
        $this->anArea();

        $body = $this->body('/areas');

        self::assertStringContainsString('not active yet', $body);
        self::assertStringContainsString('no modules yet', $body);
        self::assertStringContainsString('set up a module to begin', $body);
        // No invented operational grid where nothing is switched on.
        self::assertStringNotContainsString('modules live', $body);
    }

    /**
     * AN INSTALLATION WITH NO AREAS IS NEW, NOT BROKEN. A table with no rows
     * reads as a page that failed to load; this says what is true.
     */
    public function testAnEmptyRegisterSaysSoInWordsRatherThanDrawingAnEmptyTable(): void
    {
        $this->boot();

        $body = $this->body('/areas');

        self::assertStringContainsString('No areas yet', $body);
        self::assertStringNotContainsString('<table', $body);
    }

    /**
     * THE REGISTER SHOWS ONLY WHAT THIS BUNDLE OWNS. Forest cover, tree-cover
     * loss, the trend and the alerts chip are a future ingestion module's
     * figures; a column reserved for them here would be this bundle knowing what
     * that module measures. Absent, not empty — there is not even a dashed cell.
     */
    public function testTheRegisterReservesNoFigureAnotherModuleOwns(): void
    {
        $this->boot();
        $this->anArea();

        $body = $this->body('/areas');

        // No module is switched on here, so no operational figure is drawn — and
        // the area page names none of a specific module's figures on its own. "Alerts"
        // is this bundle's vocabulary now (the attention contribution), not a module's figure, so
        // it is allowed; a module's own labels are not hard-coded here.
        foreach (['forest', 'no ingest yet', 'ha/yr', 'patrols this wk', 'open incidents'] as $foreign) {
            self::assertStringNotContainsStringIgnoringCase(
                $foreign,
                $body,
                'the register must not name a figure another module owns',
            );
        }
    }

    /**
     * THE "NEW AREA" BUTTON LEADS SOMEWHERE. Its href is a generated route
     * rather than a literal, and the click is served — so a button that stopped
     * being answered fails here rather than in somebody's browser. Its
     * permission gate and the import itself are {@see AreaCreateTest}'s.
     */
    public function testTheNewAreaButtonRendersAndItsDestinationIsServed(): void
    {
        $this->boot();
        $this->anArea();

        self::assertStringContainsString('New area', $this->body('/areas'));
        self::assertSame(200, $this->get('/areas/new')->getStatusCode());
    }

    /** Search, the filter pills and the fixed activity sort are on the page, over cards already rendered. */
    public function testTheRegisterCarriesItsWorkingControls(): void
    {
        $this->boot();
        $this->anArea();

        $body = $this->body('/areas');

        self::assertStringContainsString('data-controller="uhifadhi--area-bundle--area-register"', $body);
        self::assertStringContainsString('Search areas', $body);
        self::assertStringContainsString('data-filter="live"', $body);
        // The card gallery has no sortable headers; the sort is fixed and stated.
        self::assertStringContainsString('sort: last activity', $body);
    }

    /**
     * THE PILLS ARE THE GRADUATED DESIGN'S FOUR, AND EACH CAN MOVE. All, Live,
     * With alerts and Awaiting setup — live and setup from the registry's ledger,
     * alerts from the attention contribution. None is a pill for one specific module,
     * which would be the area page knowing what that module measures.
     */
    public function testTheFilterPillsAreTheOnesThatCanMove(): void
    {
        $this->boot();
        $this->anArea();

        $body = $this->body('/areas');

        self::assertStringContainsString('data-filter="all"', $body);
        self::assertStringContainsString('data-filter="live"', $body);
        self::assertStringContainsString('data-filter="alerts"', $body);
        self::assertStringContainsString('data-filter="setup"', $body);
        // No pill named for a particular module.
        self::assertStringNotContainsString('data-filter="fire"', $body);
        self::assertStringNotContainsString('data-filter="patrols"', $body);
    }

    /**
     * THE WIDGET-LIBRARY ADOPT-CONTROL IS PRESENT. The library itself is a
     * follow-up slice; the header carries the button wired to where that route
     * will live, the same control in the same place as every widget surface.
     */
    public function testTheRegisterCarriesTheWidgetLibraryAction(): void
    {
        $this->boot();
        $this->anArea();

        self::assertStringContainsString('Widget library', $this->body('/areas'));
    }

    /** An empty register has nothing to search, so it carries no controls either. */
    public function testAnEmptyRegisterOffersNoControlsToWorkWith(): void
    {
        $this->boot();

        self::assertStringNotContainsString('data-controller="uhifadhi--area-bundle--area-register"', $this->body('/areas'));
    }

    /** The size is PostGIS's, measured on the spheroid — not a bounding box. */
    public function testTheRegisterPrintsTheMeasuredSize(): void
    {
        $this->boot();
        $this->anArea();

        // The test boundary is roughly 1° by 0.8° at 3°S — about 9,900 km².
        self::assertMatchesRegularExpression('/9,\d{3}/', $this->body('/areas'));
    }

    public function testAnAreasOverviewStatesWhatTheAreaIs(): void
    {
        $this->boot();
        $area = $this->anArea('Northern Conservation Reserve');
        $area->setIucnCategory('VI')->setEstablishedYear(1959);
        $this->em->flush();

        $body = $this->body('/areas/'.$area->getUuidString());

        self::assertStringContainsString('Northern Conservation Reserve', $body);
        self::assertStringContainsString('VI', $body);
        self::assertStringContainsString('1959', $body);
    }

    /**
     * THE BOUNDARY FACT REFLECTS THE GEOMETRY, NOT THE PROVENANCE. An area
     * imported through the upload screen carries source "upload" while its geom
     * column holds a full MultiPolygon; the overview once printed that word as the
     * Boundary value, so a gazetted area read "Boundary: upload" — a prompt to do
     * the thing already done. The fact now answers from the geometry.
     */
    public function testAnUploadedAreasOverviewReportsItsBoundaryRatherThanAskingForOne(): void
    {
        $this->boot();
        $area = $this->anArea('Northern Conservation Reserve', 'upload');

        $body = $this->body('/areas/'.$area->getUuidString());

        // The band states the boundary is present...
        self::assertStringContainsString('Boundary', $body);
        self::assertStringContainsString('on file', $body);
        // ...and never renders the raw provenance token as the boundary value.
        self::assertStringNotContainsString('>upload<', $body);
    }

    /**
     * THE PLATE IS INFRASTRUCTURE, AND THE BOUNDARY IS ALWAYS DRAWN. An area
     * with a boundary on file opens on a real map — the operational plate the
     * atlas draws, carrying the stored geometry for the browser to draw. This is
     * the area's own base content: it is there whether or not any module is
     * switched on.
     */
    public function testTheOverviewDrawsTheBoundaryPlateWhenTheAreaHasABoundary(): void
    {
        $this->boot();
        $area = $this->anArea('Northern Conservation Reserve');

        $body = $this->body('/areas/'.$area->getUuidString());

        // The plate, wired to the atlas's one map controller...
        self::assertStringContainsString('data-controller="uhifadhi--atlas-bundle--map-plate"', $body);
        // ...carrying the stored geometry for the browser to draw...
        self::assertStringContainsString('MultiPolygon', $body);
        // ...and the always-present "The area" legend group, boundary on.
        self::assertStringContainsString('The area', $body);
    }

    /**
     * NO BOUNDARY IS A STATE, NOT A BROKEN MAP. An area with no geometry on file
     * is not handed an empty plate that renders as a grey box; hasBoundary drives
     * the plate, so the card says there is nothing to draw yet and points at
     * where a boundary is added.
     */
    public function testTheOverviewShowsANoBoundaryStateWhenTheAreaHasNone(): void
    {
        $this->boot();
        $area = new AreaOfInterest()->setName('Unmapped Reserve');
        $this->em->persist($area);
        $this->em->flush();

        $body = $this->body('/areas/'.$area->getUuidString());

        // No map controller is wired where there is nothing to draw.
        self::assertStringNotContainsString('data-controller="uhifadhi--atlas-bundle--map-plate"', $body);
        // The plate says so, in words.
        self::assertStringContainsString('no boundary on file', $body);
    }

    /**
     * ZONES ARE THE AREA'S OWN BASE CONTENT — drawn on the plate beside the
     * boundary and counted in "The area" legend group, never a module's layer.
     */
    public function testTheBoundaryPlateCarriesTheAreasZones(): void
    {
        $this->boot();
        $area = $this->anArea();
        $this->aZone($area, 'Northern Highlands');

        $body = $this->body('/areas/'.$area->getUuidString());

        // The zone's geometry travels to the plate for it to draw...
        self::assertStringContainsString('Northern Highlands', $body);
        // ...and "The area" legend counts the zones.
        self::assertStringContainsString('Zones', $body);
    }

    /**
     * NOTHING IS DRAWN WITH INVENTED DATA. With no module switched on there are
     * no tiles and no attention rows — and the page says that in words instead
     * of drawing a strip full of noughts.
     */
    public function testAnOverviewWithNoModulesIsHonestRatherThanEmpty(): void
    {
        $this->boot();
        $area = $this->anArea();

        $body = $this->body('/areas/'.$area->getUuidString());

        self::assertStringContainsString('Not installed in this area', $body);
        self::assertStringContainsString('drawn with invented data', $body);
        self::assertStringContainsString('Nothing is asking for attention', $body);
        // Absent, not zero: no tile claiming it measured and found none.
        self::assertStringNotContainsString('class="disp"', $body);
    }

    /** An unzoned area is the normal state, and the page explains what a zone is. */
    public function testTheZonesPageOfAnUnzonedAreaExplainsRatherThanEmpties(): void
    {
        $this->boot();
        $area = $this->anArea();

        $body = $this->body('/areas/'.$area->getUuidString().'/zones');

        self::assertStringContainsString('has no zones yet', $body);
        self::assertStringContainsString('a lens, not a fence', $body);
        self::assertStringNotContainsString('<table', $body);
    }

    public function testTheZonesPageListsTheZonesWhenThereAreSome(): void
    {
        $this->boot();
        $area = $this->anArea();
        $this->aZone($area, 'Northern Highlands');

        $body = $this->body('/areas/'.$area->getUuidString().'/zones');

        self::assertStringContainsString('Northern Highlands', $body);
        self::assertStringNotContainsString('has no zones yet', $body);
    }

    /**
     * THE RECORD MOVED ONTO THE CONFIGURE PAGE and lost nothing on the way: it
     * is the Area settings section now, which is the page's own bare address.
     */
    public function testTheAreaSettingsSectionShowsTheRecordAndDashesWhatIsUnrecorded(): void
    {
        $this->boot();
        $area = $this->anArea();

        $body = $this->body('/areas/'.$area->getUuidString().'/configure');

        self::assertStringContainsString((string) $area->getUuidString(), $body);
        // A dash means UNRECORDED, never zero.
        self::assertStringContainsString('&mdash;', $body);
    }

    /**
     * THE CONFIGURE PAGE SHOWS ITS SECTIONS WHERE A DATA PAGE SHOWS ITS TABS:
     * one strip, one component, one position — and the area's data tabs are not
     * among them.
     */
    public function testTheConfigurePageShowsItsSectionStripAndNotTheAreasDataTabs(): void
    {
        $this->boot();
        $area = $this->anArea();

        $strip = $this->tabStrip($this->body('/areas/'.$area->getUuidString().'/configure'));

        self::assertStringContainsString('Widget library', $strip);
        self::assertStringContainsString('Area settings', $strip);
        self::assertStringNotContainsString('Zones', $strip);
        self::assertStringNotContainsString('Modules', $strip);
    }

    /** The strip above the page body, whatever is currently in it. */
    private function tabStrip(string $body): string
    {
        $strip = preg_split('#<div class="atabs">#', $body, 2);
        self::assertIsArray($strip);
        self::assertCount(2, $strip, 'the page renders a tab strip');

        return (string) strstr($strip[1], '</div>', true);
    }

    /** The section named in the URL is the section lit and the section drawn. */
    public function testTheWidgetLibrarySectionHasItsOwnAddressAndIsLitThere(): void
    {
        $this->boot();
        $area = $this->anArea();

        $body = $this->body('/areas/'.$area->getUuidString().'/configure/widgets');

        self::assertStringContainsString('href="/areas/'.$area->getUuidString().'/configure/widgets" class="on"', $body);
        self::assertStringContainsString('Dashboard composition', $body);
    }

    /**
     * ONE CONFIGURATION ENTRY, AND IT IS LIT WHILE YOU ARE INSIDE IT. The same
     * control opens the configure page from the area's own page and takes you
     * back from it.
     */
    public function testTheConfigureActionIsOnTheAreaPageAndLitOnTheConfigurePage(): void
    {
        $this->boot();
        $area = $this->anArea();
        $uuid = (string) $area->getUuidString();

        $page = $this->body('/areas/'.$uuid);
        self::assertStringContainsString('href="/areas/'.$uuid.'/configure"', $page);
        self::assertStringContainsString('Configure', $page);

        $configure = $this->body('/areas/'.$uuid.'/configure');
        self::assertStringContainsString('class="tgl on" href="/areas/'.$uuid.'"', $configure);
    }

    /**
     * THE OLD SETTINGS ADDRESS IS PERMANENTLY MOVED, not deleted: it is in
     * bookmarks and in whatever an installation typed into its own links, and
     * 301 is what tells all of them where it went for good.
     */
    public function testTheOldSettingsAddressRedirectsPermanentlyToTheConfigurePage(): void
    {
        $this->boot();
        $area = $this->anArea();

        $response = $this->get('/areas/'.$area->getUuidString().'/settings');

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/areas/'.$area->getUuidString().'/configure', $response->headers->get('Location'));
    }

    /**
     * ADDRESSED BY UUID, so the sequential key is not a URL anybody can walk.
     */
    public function testASequentialKeyIsNotAnAddress(): void
    {
        $this->boot();
        $this->anArea();

        self::assertSame(404, $this->get('/areas/1')->getStatusCode());
    }

    public function testAnUnknownAreaIsAFourOhFour(): void
    {
        $this->boot();

        self::assertSame(404, $this->get('/areas/0192f7a0-0000-7000-8000-000000000000')->getStatusCode());
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function closedDoors(): iterable
    {
        yield 'the register needs area.view' => ['/areas', []];
        yield 'an overview needs area.view' => ['/areas/{uuid}', []];
        yield 'zones need area.view' => ['/areas/{uuid}/zones', []];
        yield 'the settings redirect needs area.edit' => ['/areas/{uuid}/settings', ['area.view']];
    }

    /**
     * REFUSED, NOT QUIETLY EMPTIED. A screen somebody may not have answers 403;
     * it does not render with the rows removed, which would leak that the screen
     * exists and tell them nothing about why it is bare.
     *
     * @param list<string> $grants
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('closedDoors')]
    public function testAScreenIsRefusedToSomebodyWithoutItsPermission(string $path, array $grants): void
    {
        $this->boot($grants);
        $area = $this->anArea();

        $status = $this->get(str_replace('{uuid}', (string) $area->getUuidString(), $path))->getStatusCode();

        self::assertContains($status, [401, 403], 'a screen without its permission must refuse, not render');
    }
}
