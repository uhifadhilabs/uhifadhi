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

namespace Uhifadhi\Bundle\RegistryBundle\Service;

use Uhifadhi\Contracts\ModulePermission;
use Uhifadhi\Contracts\ModuleProviderInterface;

/**
 * THE PERMISSIONS INSTALLED MODULES DECLARE — gathered, and handed over.
 *
 * A module DECLARES a permission; it never grants one. Declaring makes the value
 * assignable — a host folds it into its matrix and its voter recognises it — and
 * that is the whole of it: no role, no default holders. Installing a module must
 * never hand an existing user a new power, and the shape of what crosses this
 * registry is the only place that can be enforced rather than promised: what is
 * handed over is a value, an umbrella and an action, and there is nowhere in it
 * to put a grant.
 *
 * THE MODULE HALF, AND ONLY THAT. A host has permissions of its own — for the
 * things it owns — and it remains the only thing that decides who holds what.
 * The registry collects declarations. A runtime that also owned the host's built-in
 * permissions would own its team model with them.
 *
 * A DECLARATION IS DEPLOYMENT-WIDE, NOT PER AREA. A module switched off in every
 * area still declares, so an admin can assign a permission that currently guards
 * nothing anywhere. That is the honest state of the registry; narrowing it is a
 * ruling about the team model, and not the registry's to make.
 *
 * IT DIES WITH THE MODULE. Uninstalling the bundle removes the provider, and the
 * declaration goes with it — a value left behind is a power an admin can still
 * assign over code that is no longer installed.
 */
final readonly class ModulePermissionCatalogue
{
    /**
     * @param iterable<ModuleProviderInterface> $providers every tagged provider, in registration order
     */
    public function __construct(
        private iterable $providers = [],
    ) {
    }

    /**
     * Every declaration, in registration order.
     *
     * FIRST DECLARATION WINS on a collision. Two modules claiming one value is a
     * naming accident, and the answer that keeps a matrix stable is the earlier
     * registration — never a merge, never a relabelling, and never a fatal at
     * boot, because a third-party module must not be able to take an
     * installation down by picking a string.
     *
     * @return list<ModulePermission>
     */
    public function declared(): array
    {
        $declared = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->permissions() as $permission) {
                $declared[$permission->value] ??= $permission;
            }
        }

        return array_values($declared);
    }

    public function has(string $value): bool
    {
        foreach ($this->declared() as $permission) {
            if ($permission->value === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * A matrix's shape: grouped under the umbrella heading the module chose. The
     * grouping is the whole of it — no sorting by anything the registry invents,
     * because the umbrella is the module's own word for itself.
     *
     * @return array<string, list<ModulePermission>>
     */
    public function groupedByUmbrella(): array
    {
        $grouped = [];
        foreach ($this->declared() as $permission) {
            $grouped[$permission->umbrella][] = $permission;
        }

        return $grouped;
    }
}
