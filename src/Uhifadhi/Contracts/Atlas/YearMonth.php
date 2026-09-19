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

namespace Uhifadhi\Contracts\Atlas;

/**
 * A MONTH, AS A THING RATHER THAN AS A DATE SOMEBODY AGREED TO TREAT AS ONE.
 *
 * WHY NOT A `DateTimeImmutable`. Every caller that passed one would have to
 * remember that only two of its fields matter, and every callee would have
 * to normalise it; the first one that forgot would answer for the wrong
 * month on the 31st. A month is two integers and that is all this is.
 *
 * NO TIMEZONE, DELIBERATELY. A month is not an instant. Which month a
 * VIEWER is looking at is the surface's question, asked before it gets
 * here; what a feed is being asked for is September 2026, everywhere.
 */
final readonly class YearMonth
{
    public function __construct(
        public int $year,
        public int $month,
    ) {
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException(\sprintf('A month is 1 to 12; "%d" is not one.', $month));
        }

        if ($year < 1) {
            throw new \InvalidArgumentException(\sprintf('A year is a positive number; "%d" is not one.', $year));
        }
    }

    /** The month a day falls in. */
    public static function of(\DateTimeInterface $day): self
    {
        return new self((int) $day->format('Y'), (int) $day->format('n'));
    }

    /** `2026-09`. */
    public function __toString(): string
    {
        return \sprintf('%04d-%02d', $this->year, $this->month);
    }

    public function previous(): self
    {
        return 1 === $this->month ? new self($this->year - 1, 12) : new self($this->year, $this->month - 1);
    }

    public function next(): self
    {
        return 12 === $this->month ? new self($this->year + 1, 1) : new self($this->year, $this->month + 1);
    }

    /** The first day, at midnight, for a query's lower bound. */
    public function firstDay(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(\sprintf('%04d-%02d-01 00:00:00', $this->year, $this->month));
    }

    /** The last day, at midnight — a bound to compare a DATE against, not an instant. */
    public function lastDay(): \DateTimeImmutable
    {
        return $this->firstDay()->modify('last day of this month');
    }

    public function days(): int
    {
        return (int) $this->firstDay()->format('t');
    }

    public function equals(self $other): bool
    {
        return $this->year === $other->year && $this->month === $other->month;
    }

    /** "September 2026" — what the stepper prints. */
    public function label(): string
    {
        return $this->firstDay()->format('F Y');
    }
}
