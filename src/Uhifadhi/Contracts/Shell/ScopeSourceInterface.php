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
 * WHAT THIS VIEWER MAY LOOK AT, in the order the control offers it.
 *
 * THE SHELL DRAWS THE CONTROL AND KNOWS NO AREAS. It holds no domain and no
 * authorization service, so the list arrives from whoever has both: the HOST,
 * which has the areas, the viewer and the voters, and folds those three into
 * "these slices, in this order".
 *
 * GATING IS THE SOURCE'S, exactly as it is for the sidebar. An org-level page
 * reads every area, so somebody scoped to one gets that one selected and a
 * control holding only it — the page is the same page. A scope the viewer may
 * not open is simply not in what you return; there is no "disabled" option,
 * because a disabled option is a list of the things somebody is not allowed
 * to see.
 *
 * ORGANISATION FIRST WHERE IT IS OFFERED. It is the widest reading and the
 * one an org-level page opens on, and a control whose first row was an area
 * would make the whole organisation look like one more of them.
 *
 * READ LIVE, NEVER CACHED — an area added today is in the control today.
 *
 * Tag the service `shell.scope_source` by hand, in your own extension,
 * because a reusable bundle's services are not autoconfigured.
 */
interface ScopeSourceInterface
{
    /**
     * @return iterable<Scope>
     */
    public function scopes(): iterable;
}
