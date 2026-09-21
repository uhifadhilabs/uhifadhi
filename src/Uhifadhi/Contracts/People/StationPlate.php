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

namespace Uhifadhi\Contracts\People;

/**
 * THE GROUND AROUND A STATION, DRAWN — rendered by whoever owns the ground and
 * handed over as markup, so a page that knows nothing about maps can put it
 * where the design puts it. The markup brings its own behaviour; the host
 * only places it.
 */
final readonly class StationPlate
{
    public function __construct(
        /** The rendered plate: a complete element, safe to print as-is. */
        public string $html,
    ) {
    }
}
