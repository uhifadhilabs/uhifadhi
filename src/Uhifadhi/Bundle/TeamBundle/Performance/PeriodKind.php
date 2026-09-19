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

namespace Uhifadhi\Bundle\TeamBundle\Performance;

use Uhifadhi\Contracts\Kpi\FigurePeriod;

/**
 * THE THREE WINDOWS THE SEGMENTED GROUP OFFERS — month, quarter, year.
 *
 * RULED: a reader who wants this quarter should not have to open a menu
 * to get it. Three buttons, and everything earlier behind the chip
 * beside them.
 *
 * THE VALUE IS WHAT GOES IN THE ADDRESS, so a period is a place a reader
 * can go back to and send to somebody. An address naming a window that
 * does not exist reads as the month rather than as an error: a stale
 * link should open the page, not a wall.
 */
enum PeriodKind: string
{
    case Month = 'month';
    case Quarter = 'quarter';
    case Year = 'year';

    /** What the address asked for, or the month it defaults to. */
    public static function fromRequest(string $value): self
    {
        return self::tryFrom($value) ?? self::Month;
    }

    /**
     * The three segments, in the order they read.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::Month->value => 'Month',
            self::Quarter->value => 'Quarter',
            self::Year->value => 'Year',
        ];
    }

    /** The window this kind means, around an instant. */
    public function period(\DateTimeImmutable $now): FigurePeriod
    {
        return match ($this) {
            self::Month => FigurePeriod::month($now),
            self::Quarter => FigurePeriod::quarter($now),
            self::Year => FigurePeriod::year($now),
        };
    }
}
