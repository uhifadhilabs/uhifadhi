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
 * WHAT ONE MODULE COMPUTED FOR A SET OF ZONES, keyed by the zone it is about.
 *
 * KEYED, NOT ORDERED. The caller asked about a set and draws them in its own
 * order; a list would make every surface match figures to zones by position,
 * which is the kind of coupling that survives until the day a provider skips
 * a zone it has nothing for.
 *
 * A ZONE THE PROVIDER HAD NOTHING FOR IS AN EMPTY LIST, and an empty list is
 * not a zero — it is "this module has nothing to say about that ground", which
 * every surface renders as the honest absence rather than as a measured naught.
 *
 * THE PERIOD IS THE ONE ACTUALLY COVERED, which may not be the one asked for;
 * see {@see FigurePeriod}.
 */
final readonly class ZoneFigures
{
    /**
     * @param array<string, list<DepartmentKpi>> $byZone zone uuid to that zone's figures
     */
    public function __construct(
        public array $byZone,
        public FigurePeriod $period,
    ) {
    }

    /** A provider that measured nothing at all — an honest answer, and a common one. */
    public static function none(FigurePeriod $period): self
    {
        return new self([], $period);
    }

    /** @return list<DepartmentKpi> */
    public function forZone(string $zoneUuid): array
    {
        return $this->byZone[$zoneUuid] ?? [];
    }

    public function isEmpty(): bool
    {
        foreach ($this->byZone as $figures) {
            if ([] !== $figures) {
                return false;
            }
        }

        return true;
    }
}
