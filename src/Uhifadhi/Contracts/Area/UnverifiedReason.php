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
 * WHY A DAY AT POST IS UNVERIFIED — three different things, and a page
 * that showed one word for all of them would be accusing somebody.
 */
enum UnverifiedReason: string
{
    /** No position arrived at all: no signal, or the fix never landed. */
    case NoFix = 'no_fix';

    /** The post has no catchment, so it has no inside to be in. */
    case NoRing = 'no_ring';

    /** A position arrived and it was outside the post's ring. */
    case OutsideRing = 'outside_ring';

    public function label(): string
    {
        return match ($this) {
            self::NoFix => 'no position reported',
            self::NoRing => 'the post has no catchment set',
            self::OutsideRing => 'the position was outside the post',
        };
    }
}
