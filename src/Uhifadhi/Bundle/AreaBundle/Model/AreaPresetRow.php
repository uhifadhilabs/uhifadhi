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

namespace Uhifadhi\Bundle\AreaBundle\Model;

use Uhifadhi\Bundle\AreaBundle\Overview\AttentionItem;

/**
 * ONE AREA AS THE DENSER PRESETS DRAW IT — a register {@see AreaRow} enriched
 * with the two things the attention board and the flagship read that a card does
 * not: the area's actual attention items (not just their count) and its zone
 * count.
 *
 * THE WALL, THE REGISTER AND THE MAP DRAW STRAIGHT FROM THE AreaRow — a card
 * face, the operational figures, the alert COUNT. The attention board groups
 * areas by whether they are asking for the operator and prints each area's items
 * in full; the flagship features one area with its zone count in the factband.
 * Neither figure belongs on the card model, so this wraps the row rather than
 * fattening it — and both still arrive through the same overview contributions, so the
 * the area page names no module's content here either.
 */
final readonly class AreaPresetRow
{
    /** @param list<AttentionItem> $attention this area's attention items, most urgent first */
    public function __construct(
        public AreaRow $row,
        public array $attention = [],
        public int $zoneCount = 0,
    ) {
    }
}
