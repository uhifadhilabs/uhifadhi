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

use Uhifadhi\Contracts\Area\StationSection;

/**
 * A CONTRIBUTED SECTION, AND WHO CONTRIBUTED IT.
 *
 * THE SLUG IS NOT DECORATION. Every band on a station wears the tag of the
 * module that put it there, for the same reason every widget on an area
 * overview does: when the module is parked and the band disappears, a person
 * has to be able to read that as the system working rather than as a bug.
 * The contributor does not print it — the surface does, from this — so no
 * module can leave it off or spell it its own way.
 */
final readonly class ContributedStationSection
{
    public function __construct(
        public string $moduleSlug,
        public StationSection $section,
    ) {
    }
}
