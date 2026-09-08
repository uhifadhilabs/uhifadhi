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

namespace Uhifadhi\Bundle\RegistryBundle\Tests\Integration\Area;

use Uhifadhi\Bundle\RegistryBundle\Entity\AreaModule;
use Uhifadhi\Bundle\RegistryBundle\Service\AreaModuleLedger;
use Uhifadhi\Bundle\RegistryBundle\Service\AreaModuleService;
use Uhifadhi\Bundle\RegistryBundle\Tests\Integration\InstallationTestCase;
use Uhifadhi\Entity\AreaOfInterest;

/**
 * SPEC 3 — PER-AREA INSTALL STATE: the record of what each area actually has.
 *
 * The catalogue says what exists in this deployment. This says what THIS AREA
 * has switched on, and the two are deliberately different tables: a module can
 * be installed on the deployment and wanted by only one of its areas.
 *
 * The vocabulary, because it is easy to blur: a module is INSTALLED on the
 * deployment (its bundle is in composer.json) and ACTIVE on an area (an admin
 * switched it on there). "Uninstalling for an area" is the second thing —
 * parking. It never deletes the area's data, and it never removes the module
 * from the deployment.
 */
final class AreaModuleInstallationTest extends InstallationTestCase
{
    private function areaModules(): AreaModuleService
    {
        $service = $this->service('registry.area_modules');
        \assert($service instanceof AreaModuleService);

        return $service;
    }

    private function ledger(): AreaModuleLedger
    {
        $ledger = $this->service('registry.area_module_ledger');
        \assert($ledger instanceof AreaModuleLedger);

        return $ledger;
    }

    /**
     * @return list<string>
     */
    private function activeSlugs(AreaOfInterest $area): array
    {
        return array_map(
            static fn (AreaModule $areaModule): string => (string) $areaModule->getModule()?->getSlug(),
            $this->areaModules()->activeFor($area),
        );
    }

    /**
     * AN INSTALLABLE MODULE ARRIVES PARKED. The offer — "this area may not want
     * this" — is the whole reason the per-area table exists, and installing a
     * bundle must never switch a capability on for people who did not ask for
     * it.
     */
    public function testAnInstallableModuleIsSeededParkedForEveryArea(): void
    {
        $area = $this->areaAfterInstalling(['sightings']);

        self::assertSame([], $this->activeSlugs($area));
        self::assertContains('sightings', array_map(
            static fn (array $row): string => $row['slug'],
            $this->ledger()->for($area)['absent'],
        ));
    }

    /**
     * A CORE MODULE ARRIVES ON, in every area, because parking it offers a
     * choice that is not real: an area with the machinery other surfaces import
     * switched off does not have fewer features, it has broken screens.
     */
    public function testABaseModuleIsSeededActiveForEveryArea(): void
    {
        $area = $this->areaAfterInstalling(['ferries' => ['base' => true]]);

        self::assertSame(['ferries'], $this->activeSlugs($area));
    }

    public function testInstallingAndUninstallingFlipTheStatePerArea(): void
    {
        $area = $this->areaAfterInstalling(['sightings']);

        $this->areaModules()->install($area, 'sightings');
        self::assertSame(['sightings'], $this->activeSlugs($area));

        $this->areaModules()->uninstall($area, 'sightings');
        self::assertSame([], $this->activeSlugs($area));
    }

    /**
     * PER AREA MEANS PER AREA. Two areas of one deployment answer the same
     * question differently, and switching a module on for one must not switch
     * it on for the other — this is the property the whole table exists for, and
     * the one a caching mistake breaks first.
     */
    public function testOneAreasChoiceIsNotAnotherAreas(): void
    {
        $this->install(['sightings']);
        $north = $this->area('North');
        $south = $this->area('South');
        $this->warmUp();

        $this->areaModules()->install($north, 'sightings');

        self::assertSame(['sightings'], $this->activeSlugs($north));
        self::assertSame([], $this->activeSlugs($south));
    }

    /**
     * THE ATTENTION-LIST PROMISE. Every contribution a module makes to a shared
     * surface — an attention item, a now-tile, a map layer, a KPI — is keyed by
     * its module slug, and the promise the platform makes to an area manager is
     * that switching a module off takes its contributions off the page. Not on
     * the next deploy, not after a cache warm: the same day, on the next read.
     *
     * The registry cannot test the attention list itself (it draws nothing, and it
     * knows no module). What it CAN pin is the thing that list is derived from:
     * the moment a module is uninstalled for an area, that area's ledger stops
     * counting it as present and starts counting it as absent, from the
     * database, with no interval in between. Anything that caches this reading
     * breaks the promise, and this test is where that would show.
     */
    public function testUninstallingRemovesTheModulesPresenceImmediately(): void
    {
        $area = $this->areaAfterInstalling(['sightings']);
        $this->areaModules()->install($area, 'sightings');

        $before = $this->ledger()->for($area);
        self::assertSame(['sightings'], array_column($before['installed'], 'slug'));
        self::assertSame([], array_column($before['absent'], 'slug'));

        $this->areaModules()->uninstall($area, 'sightings');

        $after = $this->ledger()->for($area);
        self::assertSame([], array_column($after['installed'], 'slug'), 'gone from the area the moment it is switched off');
        self::assertSame(['sightings'], array_column($after['absent'], 'slug'), 'and named as absent, not silently missing');
        self::assertSame(1, $after['catalogueCount'], 'the catalogue is unchanged — the deployment still has the module');
        self::assertSame(0, $after['installedCount']);
    }

    /**
     * ITS DATA STAYS, IT JUST LEAVES THE AREA. Parking is not deletion: the row
     * survives, so switching the module back on finds the area's history where
     * it was rather than starting it again. This is the sentence the customize
     * screen makes to an admin, and it has to be true.
     */
    public function testUninstallingKeepsTheAreasRowAndItsData(): void
    {
        $area = $this->areaAfterInstalling(['sightings']);
        $this->areaModules()->install($area, 'sightings');
        $installedAt = $this->areaModules()->assignmentFor($area, 'sightings')?->getCreatedAt();

        $this->areaModules()->uninstall($area, 'sightings');
        $this->areaModules()->install($area, 'sightings');

        self::assertEquals(
            $installedAt,
            $this->areaModules()->assignmentFor($area, 'sightings')?->getCreatedAt(),
            'reinstalling resumes the same assignment rather than starting a new history',
        );
    }

    /**
     * A PINNED MODULE CAN NEVER BE SWITCHED OFF. Pinned is the hub every area
     * leads with; an area without it has no front door. Note what the registry
     * knows here and what it does not: it enforces the FLAG, and has no idea
     * which module carries it.
     */
    public function testAPinnedModuleCannotBeUninstalledForAnArea(): void
    {
        $area = $this->areaAfterInstalling(['hub' => ['pinned' => true, 'base' => true]]);

        $this->areaModules()->uninstall($area, 'hub');

        self::assertSame(['hub'], $this->activeSlugs($area), 'the pinned module stays, and no exception is thrown at the admin');
    }

    /**
     * Installing twice is installing once. The customize screen posts a form and
     * a form gets double-submitted; an area must not end up with two rows for
     * one module, because two rows are how the sub-nav grows a duplicate tab.
     */
    public function testInstallingIsIdempotent(): void
    {
        $area = $this->areaAfterInstalling(['sightings']);

        $this->areaModules()->install($area, 'sightings');
        $this->areaModules()->install($area, 'sightings');

        self::assertSame(['sightings'], $this->activeSlugs($area));
    }

    /**
     * An area created AFTER the seed ran owns no rows at all, and must still see
     * the whole shop — availability is derived from the catalogue, not from
     * rows. Otherwise a new area is born with nothing on offer until someone
     * re-runs a command.
     */
    public function testAnAreaCreatedAfterTheSeedStillSeesTheWholeCatalogue(): void
    {
        $this->install(['sightings', 'ferries']);
        $newArea = $this->area('Created later');

        self::assertSame(
            ['sightings', 'ferries'],
            array_column($this->ledger()->for($newArea)['absent'], 'slug'),
        );
    }

    /**
     * Ordering is the area's, not the catalogue's: an admin who drags the
     * sub-nav is stating a preference about their own area. Modules left out of
     * the submitted order keep their relative order behind the ones named.
     */
    public function testAnAreaOrdersItsOwnSubNav(): void
    {
        $area = $this->areaAfterInstalling(['sightings', 'ferries', 'tides']);
        foreach (['sightings', 'ferries', 'tides'] as $slug) {
            $this->areaModules()->install($area, $slug);
        }

        $this->areaModules()->reorder($area, ['tides', 'sightings']);

        self::assertSame(['tides', 'sightings', 'ferries'], $this->activeSlugs($area));
    }

    /**
     * @param array<string, array<string, mixed>>|list<string> $modules
     */
    private function areaAfterInstalling(array $modules): AreaOfInterest
    {
        $this->install($modules);
        $area = $this->area();
        // The area exists before the sync reaches it, exactly as a real one
        // created between two deploys does.
        $this->warmUp();

        return $area;
    }
}
