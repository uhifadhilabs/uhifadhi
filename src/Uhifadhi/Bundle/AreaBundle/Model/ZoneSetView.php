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

use Uhifadhi\Bundle\AreaBundle\Entity\ZoneImport;

/**
 * THE WHOLE SET, AS THE CONFIGURE PAGE STATES IT: the zones, how much of the
 * area they account for, and where the last of them came from.
 *
 * THE UNZONED REMAINDER IS PART OF THE FACT. "6,790 of 8,271 km²" says what a
 * bare count cannot — that an area is partly zoned, which is the ordinary
 * state and not a gap somebody left.
 *
 * THE GROUND IS THE BOUNDARY AND THE ZONES TOGETHER, not the boundary alone.
 * A zone may reach past the gazetted line, and against the boundary alone a
 * set that does would read as more than a hundred percent of its own area —
 * a page stating something impossible. The union counts every piece of ground
 * this area accounts for exactly once, whichever of the two named it.
 *
 * THE LAST IMPORT MAY BE NULL twice over: an area nobody has imported into, and
 * an area whose zones predate the importer. Neither is an error and neither is
 * worth inventing a row for.
 */
final readonly class ZoneSetView
{
    /** @param list<ZoneRow> $rows */
    public function __construct(
        public array $rows,
        public int $zonedKm2,
        public ?int $groundKm2,
        public ?ZoneImport $lastImport,
    ) {
    }

    public function count(): int
    {
        return \count($this->rows);
    }

    public function isEmpty(): bool
    {
        return [] === $this->rows;
    }
}
