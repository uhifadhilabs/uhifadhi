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

namespace Uhifadhi\Bundle\TeamBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Uhifadhi\Bundle\TeamBundle\Entity\Department;
use Uhifadhi\Bundle\TeamBundle\Entity\DepartmentPeriodFigure;
use Uhifadhi\Bundle\TeamBundle\Repository\DepartmentPeriodFigureRepository;

/**
 * WHAT THE FIGURES WERE, PERIOD BY PERIOD — the only thing in the core that
 * remembers.
 *
 * A CLOSED PERIOD CANNOT BE RECOMPUTED, so every movement on the
 * performance page is a comparison against something written down while it
 * was still true. This is the writing and the reading of it, in one place,
 * so the snapshot command and the page cannot disagree about what a period
 * is called or where its figures live.
 *
 * PERIOD KEYS ARE SORTABLE STRINGS and this class is where they are minted:
 * `2026-08`, `2026-Q3`, `2026`. A page that built its own would be one
 * typo away from a history it cannot find.
 */
final readonly class PerformanceHistory
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DepartmentPeriodFigureRepository $figures,
    ) {
    }

    /** The month an instant falls in, as a key. */
    public static function monthKey(\DateTimeImmutable $when): string
    {
        return $when->format('Y-m');
    }

    /** The quarter an instant falls in — the calendar's, not a fiscal year's. */
    public static function quarterKey(\DateTimeImmutable $when): string
    {
        return \sprintf('%s-Q%d', $when->format('Y'), (int) ceil(((int) $when->format('n')) / 3));
    }

    public static function yearKey(\DateTimeImmutable $when): string
    {
        return $when->format('Y');
    }

    /**
     * THE RUN OF MONTHS ENDING AT ONE, oldest first — what a sparkline is
     * drawn over and what "the same period last year" is found in.
     *
     * @return list<string>
     */
    public static function monthsEndingAt(\DateTimeImmutable $last, int $count): array
    {
        $keys = [];
        for ($back = $count - 1; $back >= 0; --$back) {
            $keys[] = self::monthKey($last->modify(\sprintf('-%d months', $back)));
        }

        return $keys;
    }

    /**
     * WRITE ONE FIGURE DOWN. Running the snapshot twice for a period
     * CORRECTS the history rather than doubling it: a scheduled run and a
     * hand-run are the same act, and the second one is usually the one
     * somebody wanted.
     */
    public function record(Department $department, string $periodKey, string $figureKey, ?float $value): void
    {
        $figure = $this->figures->findOne($department, $periodKey, $figureKey)
            ?? new DepartmentPeriodFigure()
                ->setDepartment($department)
                ->setPeriodKey($periodKey)
                ->setFigureKey($figureKey);

        $figure->setValue($value)->setWrittenAt(new \DateTimeImmutable());

        $this->entityManager->persist($figure);
        $this->entityManager->flush();
    }

    /** What one figure was, or null where nobody wrote it down. */
    public function valueAt(Department $department, string $figureKey, string $periodKey): ?float
    {
        return $this->figures->findOne($department, $periodKey, $figureKey)?->getValue();
    }

    /**
     * ONE FIGURE OVER SEVERAL PERIODS, KEEPING ITS HOLES. A run that
     * quietly closed up over a month nobody wrote would draw a line that
     * never happened.
     *
     * @param list<string> $periodKeys
     *
     * @return array<string, float|null>
     */
    public function runFor(Department $department, string $figureKey, array $periodKeys): array
    {
        $written = $this->figures->run($department, $figureKey, $periodKeys);

        $run = [];
        foreach ($periodKeys as $key) {
            $run[$key] = $written[$key] ?? null;
        }

        return $run;
    }

    /**
     * ONE FIGURE, ONE PERIOD, EVERY DEPARTMENT — a matrix column in one read.
     *
     * @return array<string, float|null> department uuid to what was written
     */
    public function acrossDepartments(string $figureKey, string $periodKey): array
    {
        return $this->figures->acrossDepartments($figureKey, $periodKey);
    }

    /** Whether this installation has written anything down at all. */
    public function isEmpty(): bool
    {
        return 0 === $this->figures->count([]);
    }
}
