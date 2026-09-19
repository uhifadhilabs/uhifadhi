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

namespace Uhifadhi\Contracts\Kpi;

/**
 * THE WINDOW A SET OF FIGURES COVERS — half-open, so a month is every instant
 * from its first to the first of the next and no row is counted twice at a
 * boundary.
 *
 * IT IS ASKED FOR AND IT IS ANSWERED WITH. A caller states the window it wants
 * a page to be about; the answer states the window it could actually give,
 * because a provider asked for August that can only measure a rolling ninety
 * days must be able to say so. A card captioned "aug" over ninety days of data
 * is a lie with nothing on the page to catch it.
 *
 * THE LABEL IS THE PERIOD'S OWN WORDS, so a caption never has to format a date
 * range and two surfaces cannot word the same window differently.
 */
final readonly class FigurePeriod
{
    public function __construct(
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $until,
        public string $label,
    ) {
        if ($until <= $from) {
            throw new \InvalidArgumentException('A period ends after it starts.');
        }

        if ('' === trim($label)) {
            throw new \InvalidArgumentException('A period must carry the words a caption prints.');
        }
    }

    /** The whole calendar month containing an instant — what the zones surfaces ask for. */
    public static function month(\DateTimeImmutable $now): self
    {
        $from = $now->modify('first day of this month')->setTime(0, 0);

        return new self($from, $from->modify('+1 month'), $from->format('F Y'));
    }

    /**
     * A ROLLING WINDOW OF SO MANY DAYS, ending now — what a card inside
     * something else asks for.
     *
     * NOT EVERY SURFACE ASKS THE SAME QUESTION, deliberately. A tab states a
     * month because a month is what a report is written about; a card opened
     * inside a configure screen is asking "is this place busy", which a
     * calendar boundary answers badly on the second of the month. The label
     * says which window it is, so two cards on one screen can differ and
     * neither has to be read as the other.
     */
    public static function days(int $days, \DateTimeImmutable $now): self
    {
        if ($days < 1) {
            throw new \InvalidArgumentException('A window of days is at least one day long.');
        }

        return new self($now->modify(\sprintf('-%d days', $days)), $now, \sprintf('%d days', $days));
    }

    /** The period before this one, of the same length — what a move is measured against. */
    public function previous(): self
    {
        $length = $this->from->diff($this->until);
        $from = $this->from->sub($length);

        return new self($from, $this->from, $from->format('F Y'));
    }

    public function contains(\DateTimeImmutable $instant): bool
    {
        return $instant >= $this->from && $instant < $this->until;
    }
}
