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
use Uhifadhi\Bundle\RegistryBundle\Entity\Module;
use Uhifadhi\Bundle\RegistryBundle\Enum\ModuleCategory;
use Uhifadhi\Bundle\RegistryBundle\Enum\ModuleStatus;

/**
 * MODULE LAYERS ON THE HOST'S ONE PLATE — gathered through the map-layer seam,
 * drawn over the area's own boundary, and each with its own legend group.
 *
 * ONE PLATE, MANY OWNERS. The host draws the map and owns the boundary and the
 * zones; every operational layer over it belongs to the module that owns the
 * data and arrives through {@see \Uhifadhi\Bundle\AreaBundle\Overview\MapLayerProviderInterface}.
 * A layer is asked for ONLY where its module is switched on, so uninstalling a
 * module takes its layer AND its legend group off the plate with no host edit.
 *
 * EVERY LAYER SHIPS A LEGEND, and the legend is grouped by contributor — the only
 * way a person can tell why a layer vanished. A layer on by default is drawn; one
 * off by default still ships its legend entry, switched off, so the legend is a
 * statement about the plate rather than about this morning's data.
 */
final class AreaMapLayersTest extends WebTestCase
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

    private function install(AreaOfInterest $area, string $slug): void
    {
        /** @var \Uhifadhi\Bundle\RegistryBundle\Service\AreaModuleService $modules */
        $modules = static::getContainer()->get('test_public.registry.area_modules');
        $modules->install($area, $slug);
    }

    private function aCatalogue(): void
    {
        foreach ([
            ['patrols', 'Patrols', ModuleCategory::Pressure, ModuleStatus::Live, 'GPS field tracks'],
            ['incidents', 'Incidents', ModuleCategory::Pressure, ModuleStatus::Live, 'field reports'],
        ] as $i => [$slug, $name, $category, $status, $source]) {
            $this->em->persist(new Module()
                ->setSlug($slug)
                ->setName($name)
                ->setCategory($category)
                ->setStatus($status)
                ->setDataSource($source)
                ->setPosition($i));
        }
        $this->em->flush();
    }

    /**
     * A LAYER FROM AN INSTALLED MODULE IS DRAWN AND LEGENDED. Its group heading
     * carries the module's name, its on-by-default layer is switched on, and its
     * geometry travels to the browser on the plate's payload.
     */
    public function testAnInstalledModulesLayerIsGatheredOntoThePlate(): void
    {
        $this->boot();
        $area = $this->anArea('Northern Conservation Reserve');
        $this->aCatalogue();
        $this->install($area, 'patrols');

        $body = $this->body('/areas/'.$area->getUuidString());

        // The contributor's own legend group, headed with its module name...
        self::assertStringContainsString('Patrols', $body);
        self::assertStringContainsString('Out right now', $body);
        // ...and the geometry travels to the browser on the plate's payload.
        self::assertStringContainsString('patrols.live', $body);
        self::assertStringContainsString('LineString', $body);
    }

    /**
     * ON BY DEFAULT IS DRAWN; OFF BY DEFAULT STILL SHIPS ITS LEGEND ENTRY. The
     * coverage buffer is demoted, not deleted: its row is present and switched
     * off, exactly where it was.
     */
    public function testADemotedLayerKeepsItsLegendEntrySwitchedOff(): void
    {
        $this->boot();
        $area = $this->anArea('Northern Conservation Reserve');
        $this->aCatalogue();
        $this->install($area, 'patrols');

        $body = $this->body('/areas/'.$area->getUuidString());

        self::assertStringContainsString('2 km coverage buffer', $body);
        // An off-by-default row wears the off state.
        self::assertMatchesRegularExpression('/class="lay off"[^>]*data-[^>]*patrols\.buffer/', $body);
    }

    /**
     * ASKED ONLY WHERE THE MODULE IS ON. Incidents has a registered map-layer
     * provider but is not switched on in this area, so neither its layer nor its
     * legend group appears — the disappearance of an uninstalled module's layer
     * reads as the system working, not as a bug.
     */
    public function testALayerFromAModuleThatIsOffHereIsNotDrawn(): void
    {
        $this->boot();
        $area = $this->anArea('Northern Conservation Reserve');
        $this->aCatalogue();
        $this->install($area, 'patrols');

        $body = $this->body('/areas/'.$area->getUuidString());

        // Patrols is on; incidents is not.
        self::assertStringContainsString('patrols.live', $body);
        self::assertStringNotContainsString('incidents.live', $body);
    }

    /**
     * EVERY LEGEND ROW IS A TOGGLE. The host's own boundary and zones rows and
     * every module layer row carry the wiring that shows and hides that layer —
     * toggling a legend entry is how "every layer ships a legend" becomes
     * something a person can act on.
     */
    public function testEveryLegendRowIsWiredToToggleItsLayer(): void
    {
        $this->boot();
        $area = $this->anArea('Northern Conservation Reserve');
        $this->aCatalogue();
        $this->install($area, 'patrols');

        $body = $this->body('/areas/'.$area->getUuidString());

        // The controller lives on the map card, so the legend rows are in scope.
        self::assertStringContainsString('data-controller="uhifadhi--area-bundle--area-map"', $body);
        // The host's own boundary/zones rows toggle...
        self::assertStringContainsString('area-map-layer-param="area.boundary"', $body);
        self::assertStringContainsString('area-map-layer-param="area.zones"', $body);
        // ...and so does a contributed layer.
        self::assertStringContainsString('area-map-layer-param="patrols.live"', $body);
        // The click is wired to the toggle action.
        self::assertStringContainsString('area-map#toggleLayer', $body);
    }

    /**
     * A MODULE THAT DRAWS NOTHING PUTS NOTHING ON THE PLATE. An area with a
     * boundary but no module switched on renders the host's own "The area" group
     * and no contributed group at all.
     */
    public function testWithNoModuleOnThePlateShowsOnlyTheAreasOwnGroup(): void
    {
        $this->boot();
        $area = $this->anArea('Northern Conservation Reserve');

        $body = $this->body('/areas/'.$area->getUuidString());

        self::assertStringContainsString('The area', $body);
        self::assertStringNotContainsString('patrols.live', $body);
    }
}
