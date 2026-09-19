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
 * ONE AREA, NAMED — the least a cross-area board needs to offer it as a choice.
 *
 * AN AREA WITH NO STATION IS STILL AN AREA. It is offered in the filter row
 * reading nought, because a choice that vanishes when its count reaches zero
 * is a choice nobody can tell from one that was never there.
 */
final readonly class DirectoryArea
{
    public function __construct(
        public string $uuid,
        public string $name,
    ) {
    }
}
