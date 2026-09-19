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

namespace Uhifadhi\Contracts;

/**
 * HOW A BUNDLE THAT IS NOT A MODULE DECLARES A PERMISSION.
 *
 * A MODULE DECLARES ITS OWN THROUGH
 * {@see ModuleProviderInterface::permissions()}, and that is the
 * ordinary case: a permission arrives with the module that enforces it
 * and leaves when the module does. But not everything that owns a
 * capability is a module. The area bundle owns the ground, the posts
 * and the day a ranger checks in on; it is core, it is in the catalogue
 * of nothing, and it still needs to say that `duty.checkin` exists so
 * an administrator can hand it to a position.
 *
 * WHOEVER ENFORCES A PERMISSION DECLARES IT. The alternative was to add
 * it to the host's own enum, which would put a word the AREA enforces
 * in the bundle that owns PEOPLE — and the next module-shaped
 * capability in core would go there too, until the enum was a list of
 * everything anybody checks.
 *
 * IT CARRIES NO HOLDERS AND NO ROLE. Declaring a permission hands
 * nobody anything: the matrix gains a row, and who ticks it is the
 * organisation's business.
 *
 * A reusable bundle is not autoconfigured, so the tag goes on by hand:
 *
 *     $services->set('area.permissions', AreaPermissions::class)
 *         ->tag(PermissionDeclarationInterface::TAG);
 */
interface PermissionDeclarationInterface
{
    /** The tag that puts a declaration in the host's catalogue. */
    public const string TAG = 'uhifadhi.permissions';

    /**
     * The permissions this bundle enforces.
     *
     * @return list<ModulePermission>
     */
    public function permissions(): array;
}
