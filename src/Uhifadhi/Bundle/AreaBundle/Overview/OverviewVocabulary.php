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

namespace Uhifadhi\Bundle\AreaBundle\Overview;

/**
 * THE CLASSES A CONTRIBUTED CELL MAY WRITE, AND THIS SURFACE DEFINES.
 *
 * A MODULE'S CELL IS DRAWN ON SOMEBODY ELSE'S PAGE, and some of what it
 * wears is the page's rather than the module's: the contributor tag on every
 * card, the live dot, the attention row, the flow bar, the honest
 * not-installed slot. They are the surface's because the surface is what
 * decides that every module's cell reads the same way — a module shipping
 * its own copy of the flow bar would be two flows that drift apart, and one
 * shipping none rendered its segments as blue underlined links, which is
 * what happened.
 *
 * SO THE LIST IS PUBLISHED rather than discovered by reading the area's
 * stylesheet. A module's vocabulary test can assert against this constant —
 * "the classes my cells write are either mine or the surface's" — and the
 * same constant is what the documentation prints, so the two cannot drift.
 *
 * THE SHELL'S COMPONENTS ARE NOT LISTED HERE. `.c`, `.tab`, `.use`, `.kpi`,
 * `.tbl`, `.rln`, `.chip`, `.more` and the rest belong to every surface in
 * the product and are frozen in
 * {@see \Uhifadhi\Bundle\ShellBundle\Contract\LayoutContract::COMPONENTS};
 * this is only what the AREA OVERVIEW adds on top of them.
 */
final class OverviewVocabulary
{
    /**
     * The entry classes. Their parts — `.ao-flow a .n`,
     * `.ao-slotrow .st` — belong to the entry and are not listed separately,
     * because a part without its entry is not a thing a module can write.
     *
     * @var list<string>
     */
    public const array HOST_CLASSES = [
        // THE CONTRIBUTOR TAG every card on this surface wears, so a reader
        // knows whose figure they are looking at.
        'ao-by',

        // ONLY A CELL THAT ACTUALLY POLLS may wear the live dot.
        'ao-live',

        // RECORDS BY WHERE THEY HAVE GOT TO: one bar, one segment per state.
        'ao-flow',

        // THE HONEST NOT-INSTALLED-HERE AFFORDANCE, and one row of it.
        'ao-slot',
        'ao-slotrow',

        // A HEADING OVER ONE MODULE'S STACK OF CELLS, and the stack itself,
        // so the seam between two modules is a thing a reader can point at.
        'ao-col',
        'ao-colstack',
    ];

    private function __construct()
    {
    }
}
