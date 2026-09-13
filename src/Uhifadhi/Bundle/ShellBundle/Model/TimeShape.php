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

namespace Uhifadhi\Bundle\ShellBundle\Model;

/**
 * THE SHAPES A `<time>` MAY ASK FOR, as the one list both sides read.
 *
 * The rewriting happens in the browser, so the names live in JavaScript; a
 * template that types a name the controller does not answer gets the default
 * shape instead of the one the design draws, silently and only on a rendered
 * page. Naming them here turns that into a build failure: the conformance base
 * checks every template against these cases, and a shell test checks these
 * cases against the controller, so neither side can drift from the other.
 *
 * The values are the attribute values, verbatim.
 *
 * @see \Uhifadhi\Bundle\ShellBundle\Test\TimeConformanceTestCase
 * @see ShellBundle/assets/controllers/localtime_controller.js
 * @see ShellBundle/docs/theming.md — "A time reads in the reader's zone"
 */
enum TimeShape: string
{
    /** Intl's own full reading — "Sep 12, 2026, 1:49 PM" in en-US. */
    case Datetime = 'datetime';

    /** Intl's own date — "Sep 12, 2026". */
    case Date = 'date';

    /** Intl's own time — "1:49 PM". */
    case Time = 'time';

    /** The compact stamp a table cell draws — "12 sep · 13:49". */
    case Stamp = 'stamp';

    /** The stamp with its weekday — "sat 12 sep · 13:49". */
    case Daystamp = 'daystamp';

    /** A 24-hour clock alone — "13:49". */
    case Clock = 'clock';

    /** The clock with its seconds, for a tail that is watched live — "13:49:07". */
    case Clocks = 'clocks';

    /** A compact date — "12 sep 2026". */
    case Day = 'day';

    /** A compact date with its weekday — "sat 12 sep 2026". */
    case Daylong = 'daylong';

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_map(static fn (self $shape): string => $shape->value, self::cases());
    }
}
