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
        /**
         * WHAT THIS PERIOD IS BEING READ AGAINST, where somebody chose.
         * Null is the ordinary case and means the period before it.
         */
        private ?self $comparison = null,
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
     * THE PERIOD'S OWN WORDS, SHORTENED — what a band prints under a
     * figure when there is room for one word and not three.
     *
     * IT IS THE PERIOD'S AND NOT A TEMPLATE'S, for the same reason
     * {@see $label} is: a surface that wrote `|date(\'M\')` would be
     * formatting an instant in whatever zone the server runs in, and
     * the one thing this platform refuses is a date a reader cannot
     * place. A period is not an instant — it is a named window — so it
     * names itself here, short and long, and nothing downstream
     * formats either.
     */
    public function shortLabel(): string
    {
        $days = (int) $this->from->diff($this->until)->days;

        return mb_strtolower(match (true) {
            $days > 200 => $this->from->format('Y'),
            $days > 45 => \sprintf('Q%d', (int) ceil(((int) $this->from->format('n')) / 3)),
            default => $this->from->format('M'),
        });
    }

    /**
     * THE CALENDAR QUARTER an instant falls in — the second of the three
     * windows the performance page offers.
     *
     * A CALENDAR QUARTER AND NOT NINETY DAYS. A reader asking for "this
     * quarter" is asking about the quarter the organization reports in;
     * a rolling window would answer a different question with a number
     * that looks like the answer to this one.
     */
    public static function quarter(\DateTimeImmutable $now): self
    {
        $first = $now->modify('first day of this month')->setTime(0, 0);
        $quarter = intdiv((int) $first->format('n') - 1, 3);
        $from = $first->setDate((int) $first->format('Y'), $quarter * 3 + 1, 1);

        return new self($from, $from->modify('+3 months'), \sprintf('Quarter %d %s', $quarter + 1, $from->format('Y')));
    }

    /** The calendar year an instant falls in — the widest of the three. */
    public static function year(\DateTimeImmutable $now): self
    {
        $from = $now->setDate((int) $now->format('Y'), 1, 1)->setTime(0, 0);

        return new self($from, $from->modify('+1 year'), $from->format('Y'));
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

    /**
     * WHAT EVERY MOVEMENT ON A PAGE IS MEASURED AGAINST — the period
     * before, unless a reader asked for another.
     *
     * A PROVIDER READS THIS AND NEVER `previous()` DIRECTLY. That is
     * the whole of what makes "compare with the same period last year"
     * a real control rather than a caption: the page states the
     * comparison once, on the period, and every figure on it is read
     * the same way. A topic that hardcoded "one month back" would
     * compare a QUARTER against a month and be wrong by three.
     */
    public function against(): self
    {
        return $this->comparison ?? $this->previous();
    }

    /** The same period, read against another one. */
    public function comparedWith(self $against): self
    {
        return new self($this->from, $this->until, $this->label, $against);
    }

    /**
     * THE SAME WINDOW ONE YEAR EARLIER — what "against last year" means
     * for a month, a quarter and a year alike, because each is the same
     * calendar shape moved back twelve months.
     */
    public function sameLastYear(): self
    {
        $from = $this->from->modify('-1 year');

        return new self($from, $this->until->modify('-1 year'), $from->format(
            $this->from->format('m-d') === $this->until->format('m-d') ? 'Y' : 'F Y',
        ));
    }

    /** The period before this one, of the same length — the default comparison. */
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
