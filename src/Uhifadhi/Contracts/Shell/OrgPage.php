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

namespace Uhifadhi\Contracts\Shell;

/**
 * ONE SCREEN OF A MODULE'S ORGANIZATION-LEVEL PAGE SET.
 *
 * The same shape as the module's own area tabs, because it IS the same set
 * one scope wider: Overview, Today, Week, Day board, Calendar, Live. A reader
 * who has learnt the module inside an area has learnt it across the
 * organization.
 *
 * A ROUTE NAME, NEVER A URL. The addresses belong to the application — it may
 * mount the module under a prefix — so the shell generates from the name and
 * a module that typed a path would be wrong in the first installation that
 * did.
 */
final readonly class OrgPage
{
    public function __construct(
        /** Stable, and what a stored preference or a deep link keys on. */
        public string $key,
        /** What the tab says, in the module's own words. */
        public string $label,
        /** The route that draws it, mounted by the application. */
        public string $route,
    ) {
    }
}
