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

use Uhifadhi\Contracts\ModuleProviderInterface;

/**
 * A MODULE THAT ALSO ANSWERS AT ORGANISATION LEVEL.
 *
 * An area module draws the same screens for every area; the organisation
 * wants those screens ONCE, across all of them — who is due today anywhere,
 * every patrol out now, every incident open. That is not a second product
 * surface, it is the area page with the area filter widened.
 *
 * SO THE MODULE CONTRIBUTES THE PAGE SET AND THE SHELL MOUNTS IT. The module
 * writes no chrome — no sidebar item, no tab strip, no scope control — and
 * the host writes no module code: adding a second module at organisation
 * level is one more provider, not a new section.
 *
 * DECLARED BESIDE {@see ModuleProviderInterface}, never instead of it. This
 * says something MORE about a module that already exists; a module without it
 * is an area module and nothing is missing.
 *
 * EVERY FIGURE IS THE AREA QUERY ONE SCOPE WIDER, and that is the rule this
 * interface exists to make possible: the module's own service takes a
 * {@see Scope}, the area page passes one area and the org page passes the
 * organisation. A module that grew a second aggregate for this would have two
 * numbers for one question and no way to say which was right.
 *
 * WHAT IT IS GATED ON is the module's own business, in its own controllers,
 * exactly as its area pages are — the shell asks nothing about the viewer.
 */
interface OrgPagesInterface
{
    /**
     * The module's organisation-level screens, in the order they are read —
     * the first is the one its sidebar row and its own name open on.
     *
     * AN EMPTY LIST IS NOT AN ANSWER: a module that has no org-level reading
     * does not implement this. Returning nothing would put a row in the
     * sidebar that opens onto a page that does not exist.
     *
     * @return list<OrgPage>
     */
    public function orgPages(): array;
}
