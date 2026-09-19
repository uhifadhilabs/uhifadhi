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
 * A CONTRIBUTOR THAT BRINGS ITS OWN STYLESHEET.
 *
 * A MODULE'S CELL IS DRAWN ON SOMEBODY ELSE'S PAGE, and the page links the
 * sheets it knows about: the shell's, the atlas's, the widget grid's and the
 * area's. A module built against its own sheet renders on the overview with
 * every class it wrote undefined — measured on a rendered page: an incident
 * cell's flow bar came out as blue underlined links, because the rules live
 * in the incident module's own stylesheet and nothing linked it there.
 *
 * SO A CONTRIBUTOR PUBLISHES WHAT ITS CELLS NEED and the surface links it.
 * Not the other way about: copying a module's rules into the core would make
 * the core hold the vocabulary of every module ever written, and the copy
 * would drift from the original on the module's next release.
 *
 * SEPARATE FROM {@see OverviewContributorInterface} ON PURPOSE. A module
 * written before this existed contributes cells and no sheet, exactly as it
 * does today; adding the method to the contributor contract would have made
 * every installed module fatal on upgrade. A contributor that needs styling
 * implements both.
 *
 * WHAT IS RETURNED IS WHAT `asset()` TAKES — the module's own served path,
 * usually its bundle's `STYLESHEET` constant. The surface links each one ONCE
 * and AFTER its own sheet, so a module may tune what it owns and the page's
 * own vocabulary is not open to being restated by whatever is installed.
 */
interface OverviewStylesheetsInterface
{
    /**
     * The stylesheets this contributor's cells need, in the order they should
     * be linked.
     *
     * @return list<string>
     */
    public function stylesheets(): array;
}
