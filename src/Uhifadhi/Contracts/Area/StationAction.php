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

namespace Uhifadhi\Contracts\Area;

/**
 * ONE THING A CONTRIBUTED SECTION OFFERS: a word, and where it goes.
 *
 * ALREADY RESOLVED TO A URL. The contributor owns the route and its
 * parameters; the area cannot generate an address inside a module it does
 * not require, and a route NAME handed over here would make the area
 * responsible for a module's URL scheme.
 */
final readonly class StationAction
{
    public function __construct(
        public string $label,
        public string $url,
    ) {
        if ('' === trim($label) || '' === trim($url)) {
            throw new \InvalidArgumentException('An action on a station section needs both a word and somewhere to go.');
        }
    }
}
