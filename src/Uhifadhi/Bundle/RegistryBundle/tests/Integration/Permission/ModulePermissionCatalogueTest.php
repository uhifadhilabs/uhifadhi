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

namespace Uhifadhi\Bundle\RegistryBundle\Tests\Integration\Permission;

use Uhifadhi\Bundle\RegistryBundle\Service\ModulePermissionCatalogue;
use Uhifadhi\Bundle\RegistryBundle\Tests\Integration\InstallationTestCase;
use Uhifadhi\Contracts\ModulePermission;

/**
 * SPEC 6 — PERMISSIONS DECLARED BY MODULES, SURFACED THROUGH THE REGISTRY.
 *
 * A module DECLARES a permission; it never grants one. Declaring makes the
 * value assignable — it appears in the host's permission matrix and its voter
 * recognises it — and that is the whole of it: no role, no default holders.
 * Installing a module must never hand an existing user a new power, and this
 * suite is where that stays true.
 *
 * WHAT THE REGISTRY OWNS, HONESTLY. The host's own permission catalogue merges two
 * sources: its own built-in enum (Areas, Ingestion, Modules, Team — the host's, because
 * the host owns areas and team) and whatever modules declare. The registry owns
 * only the second half. It collects the declarations and hands them over
 * grouped; the host folds them into its catalogue and remains the only thing
 * that decides who holds what. That split is deliberate — a runtime that also
 * owned the host's built-in permissions would own the host's team model with it.
 *
 * A DELTA WORTH KNOWING: a declaration is deployment-wide, not per area. A
 * module installed on the deployment declares its permissions even in areas
 * where it is switched off — an admin can therefore assign a permission that
 * currently guards nothing anywhere. That is the honest state of the registry, and
 * it is pinned below rather than quietly fixed here, because narrowing it is a
 * ruling about the team model and not the registry's to make.
 */
final class ModulePermissionCatalogueTest extends InstallationTestCase
{
    private function permissions(): ModulePermissionCatalogue
    {
        $catalogue = $this->service('registry.permissions');
        \assert($catalogue instanceof ModulePermissionCatalogue);

        return $catalogue;
    }

    public function testAnInstallationWithNoModulesDeclaresNoPermissions(): void
    {
        $this->install([]);

        self::assertSame([], $this->permissions()->declared());
        self::assertSame([], $this->permissions()->groupedByUmbrella());
    }

    public function testMostModulesDeclareNone(): void
    {
        $this->install(['sightings']);

        self::assertSame([], $this->permissions()->declared());
    }

    public function testADeclaredPermissionSurfacesWithItsUmbrellaAndAction(): void
    {
        $this->install(['sightings' => ['permissions' => [
            new ModulePermission('sightings.verify', 'Sightings', 'Verify', 'Confirm or reject somebody else’s sighting.'),
        ]]]);

        $declared = $this->permissions()->declared();

        self::assertCount(1, $declared);
        self::assertSame('sightings.verify', $declared[0]->value);
        self::assertSame('Sightings', $declared[0]->umbrella);
        self::assertSame('Verify', $declared[0]->action);
        self::assertTrue($this->permissions()->has('sightings.verify'));
    }

    /**
     * The matrix's shape: grouped under the umbrella heading the module chose.
     * The registry groups and does not sort by anything it invents — the umbrella
     * is the module's own word for itself.
     */
    public function testDeclarationsAreGroupedByUmbrellaForTheMatrix(): void
    {
        $this->install([
            'sightings' => ['permissions' => [
                new ModulePermission('sightings.verify', 'Sightings', 'Verify', 'Confirm or reject somebody else’s sighting.'),
                new ModulePermission('sightings.export', 'Sightings', 'Export', 'Take the sightings out of the product as a file.'),
            ]],
            'ferries' => ['permissions' => [
                new ModulePermission('ferries.dispatch', 'Ferries', 'Dispatch', 'Send a ferry out and record that it went.'),
            ]],
        ]);

        $grouped = $this->permissions()->groupedByUmbrella();

        self::assertSame(['Sightings', 'Ferries'], array_keys($grouped));
        self::assertCount(2, $grouped['Sightings']);
        self::assertCount(1, $grouped['Ferries']);
    }

    /**
     * FIRST DECLARATION WINS on a collision. Two modules claiming one value is a
     * naming accident, and the answer that keeps the matrix stable is the
     * earlier registration — never a merge, never a relabelling, and never a
     * fatal error at boot, because a third-party module must not be able to take
     * an installation down by picking a string.
     */
    public function testTwoModulesCollidingOnAValueDoNotFightOverIt(): void
    {
        $this->install([
            'sightings' => ['permissions' => [new ModulePermission('shared.verify', 'Sightings', 'Verify', 'Confirm or reject somebody else’s sighting.')]],
            'ferries' => ['permissions' => [new ModulePermission('shared.verify', 'Ferries', 'Approve', 'Sign off a ferry crossing.')]],
        ]);

        $declared = $this->permissions()->declared();

        self::assertCount(1, $declared);
        self::assertSame('Sightings', $declared[0]->umbrella, 'the earlier registration holds');
    }

    /**
     * THE DECLARATION DIES WITH THE MODULE. Uninstalling the bundle removes the
     * provider, and the permission has to leave the matrix with it — a value
     * left behind is a power an admin can still assign, guarding code that is
     * no longer installed.
     */
    public function testUninstallingAModuleTakesItsPermissionsWithIt(): void
    {
        $this->install(['sightings' => ['permissions' => [
            new ModulePermission('sightings.verify', 'Sightings', 'Verify', 'Confirm or reject somebody else’s sighting.'),
        ]]]);
        self::assertTrue($this->permissions()->has('sightings.verify'));

        $this->install([], freshDatabase: false);

        self::assertFalse($this->permissions()->has('sightings.verify'));
    }

    /**
     * A DECLARATION CARRIES NO HOLDERS. The registry hands the host a value, an
     * umbrella, an action and the sentence printed under them — and nothing that
     * could name a role, a position or a default grant, because there is nowhere
     * in the contract to put one. This asserts the shape of what crosses the
     * registry, which is the only place the "never grants" rule can actually be
     * enforced rather than promised.
     *
     * The description joined the shape in the contracts package v0.3.0 and changes
     * nothing about that rule: it is prose for the administrator reading the
     * matrix, not a fourth thing the module gets to decide about who holds what.
     */
    public function testADeclarationCannotCarryAGrant(): void
    {
        $this->install(['sightings' => ['permissions' => [
            new ModulePermission('sightings.verify', 'Sightings', 'Verify', 'Confirm or reject somebody else’s sighting.'),
        ]]]);

        $properties = array_map(
            static fn (\ReflectionProperty $property): string => $property->getName(),
            new \ReflectionClass($this->permissions()->declared()[0])->getProperties(),
        );

        self::assertSame(['value', 'umbrella', 'action', 'description'], $properties);
    }

    /**
     * The delta named in this class's docblock, pinned so that changing it is a
     * decision rather than a drift: declarations are deployment-wide. A module
     * switched off for every area still declares.
     */
    public function testADeclarationIsDeploymentWideNotPerArea(): void
    {
        $this->install(['sightings' => ['permissions' => [
            new ModulePermission('sightings.verify', 'Sightings', 'Verify', 'Confirm or reject somebody else’s sighting.'),
        ]]]);
        $this->area();
        $this->reconcile();

        // Parked in the only area there is — and still declared.
        self::assertTrue($this->permissions()->has('sightings.verify'));
    }
}
