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

use Uhifadhi\Contracts\Kpi\DepartmentKpi;

/**
 * ONE ZONE, AS THE TABLE READS IT: its ground, what stands on it, and what
 * the modules publish for it.
 *
 * A FIGURE IS EITHER PUBLISHED OR ABSENT, never nought. `covered` and the
 * per-module figures are null when no installed module answered for this
 * zone, and the row says so in the product's own words.
 */
final readonly class ZoneListRow
{
    /**
     * @param array<string, DepartmentKpi|null> $figures one per module the area runs, keyed by slug
     */
    public function __construct(
        public string $uuid,
        public string $name,
        public string $hue,
        public int $km2,
        public ?DepartmentKpi $covered,
        public int $stations,
        public int $people,
        public array $figures = [],
    ) {
    }
}
