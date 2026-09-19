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

use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Model\PostingStation;
use Uhifadhi\Bundle\TeamBundle\Model\SectionBar;
use Uhifadhi\Bundle\TeamBundle\Model\SectionFact;
use Uhifadhi\Bundle\TeamBundle\Model\SectionKpi;
use Uhifadhi\Bundle\TeamBundle\Model\SectionLine;
use Uhifadhi\Bundle\TeamBundle\Repository\PositionRepository;
use Uhifadhi\Bundle\TeamBundle\Repository\UserRepository;

/**
 * WHAT THE TEAM SECTION'S OVERVIEW READS, AND OWNS NOTHING OF.
 *
 * Every figure here exists somewhere else already — on the register, on
 * Positions, on the station in the area. The overview is a reading of them, so
 * this service reads and never writes, and the page it feeds has no control
 * that changes anybody. That is what makes it safe to open first.
 *
 * ONE PASS OVER THE PEOPLE AND ONE OVER THE POSITIONS. Every figure below is
 * counted from two lists rather than from a query per department; walking a
 * department's inverse collection is a lazy load per card, and the inverse of a
 * OneToMany is only as true as whoever maintained it.
 *
 * THE POSTINGS FIGURE IS THE ONE THAT CROSSES A BUNDLE BOUNDARY, and it
 * crosses read-only, through the board. An installation with no ground package
 * reads nought stations, which is true rather than broken.
 *
 * NO MOVEMENT IS CLAIMED. The drawn row carries a delta pill against the
 * previous period, and this installation records no previous figure for any of
 * these — a pill reading zero would be a claim it cannot make, so the pill is
 * absent until the figure has a history to compare against.
 */
final readonly class TeamSectionOverview
{
    /** How many rows a bounded card shows before it hands the rest to a register. */
    private const int BOUND = 5;

    /** The chart's axis climbs in eights, so its half is a whole number too. */
    private const int AXIS_STEP = 8;

    public function __construct(
        private UserRepository $users,
        private PositionRepository $positions,
        private PostingBoard $board,
    ) {
    }

    /**
     * @return array{
     *     facts: list<SectionFact>,
     *     kpis: list<SectionKpi>,
     *     byDepartment: list<SectionBar>,
     *     people: int,
     *     seats: list<SectionBar>,
     *     departments: int,
     *     postingsByArea: list<array{name: string, postings: int, height: float}>,
     *     postingsAxis: list<int>,
     *     postings: int,
     *     emptyStations: list<SectionLine>,
     *     emptyStationsTotal: int,
     *     stations: int,
     *     holdsNothing: list<SectionLine>,
     *     holdsNothingTotal: int,
     *     neverSignedIn: list<SectionLine>,
     *     neverSignedInTotal: int,
     *     active: int,
     * }
     */
    public function read(): array
    {
        $people = $this->users->findAllByName();
        $positions = $this->positions->findAllOrdered();
        $stations = $this->board->board();

        $held = self::heldPositionIds($people);
        $byDepartment = self::peopleByDepartment($people);
        $seats = self::seatsByDepartment($positions, $held);

        $active = \count(array_filter($people, static fn (User $u): bool => $u->isActive()));
        $departments = \count(array_unique(array_filter(
            array_map(static fn (Position $p): ?string => $p->getDepartment()?->getUuidString(), $positions),
        )));

        $postings = 0;
        $emptyStations = [];
        $areas = [];
        foreach ($stations as $station) {
            $postings += \count($station->rows);
            $areas[$station->areaName] = ($areas[$station->areaName] ?? 0) + \count($station->rows);
            if ($station->isEmpty()) {
                $emptyStations[] = new SectionLine(
                    label: $station->name,
                    note: $station->note(),
                );
            }
        }

        $holdsNothing = self::linesFor($this->users->findActiveWithoutPosition());
        $neverSignedIn = self::linesFor(array_filter($people, static fn (User $u): bool => !$u->isVerified()));

        return [
            'facts' => $this->facts($people, $positions, $held, $stations, $postings),
            'kpis' => $this->kpis($people, $positions, $held, $postings, $stations),
            'byDepartment' => $byDepartment,
            'people' => \count($people),
            'seats' => $seats,
            'departments' => $departments,
            'postingsByArea' => self::postingsByArea($areas),
            'postingsAxis' => self::axisFor($areas),
            'postings' => $postings,
            'emptyStations' => \array_slice($emptyStations, 0, self::BOUND),
            'emptyStationsTotal' => \count($emptyStations),
            'stations' => \count($stations),
            'holdsNothing' => \array_slice($holdsNothing, 0, self::BOUND),
            'holdsNothingTotal' => \count($holdsNothing),
            'neverSignedIn' => \array_slice($neverSignedIn, 0, self::BOUND),
            'neverSignedInTotal' => \count($neverSignedIn),
            'active' => $active,
        ];
    }

    /**
     * @param list<User>           $people
     * @param list<Position>       $positions
     * @param array<int, true>     $held
     * @param list<PostingStation> $stations
     *
     * @return list<SectionFact>
     */
    private function facts(array $people, array $positions, array $held, array $stations, int $postings): array
    {
        $active = \count(array_filter($people, static fn (User $u): bool => $u->isActive()));
        $unheld = \count($positions) - \count($held);
        $departments = \count(array_unique(array_filter(
            array_map(static fn (Position $p): ?string => $p->getDepartment()?->getUuidString(), $positions),
        )));

        return [
            new SectionFact('People', (string) \count($people), \sprintf('%d active · %d deactivated', $active, \count($people) - $active)),
            new SectionFact('Positions', (string) \count($positions), \sprintf('in %d departments', $departments)),
            new SectionFact(
                'Held',
                (string) \count($held),
                \sprintf('of %d · %d nobody holds', \count($positions), $unheld),
            ),
            new SectionFact('Postings', (string) $postings, \sprintf('%d stations', \count($stations))),
            new SectionFact(
                'Roles',
                (string) \count(TeamRoleEnum::cases()),
                \sprintf('tiers · %d may administer', self::mayAdminister($people)),
            ),
        ];
    }

    /**
     * THE FIVE KPI CARDS, FIVE OR NONE — the area overview's own row.
     *
     * @param list<User>           $people
     * @param list<Position>       $positions
     * @param array<int, true>     $held
     * @param list<PostingStation> $stations
     *
     * @return list<SectionKpi>
     */
    private function kpis(array $people, array $positions, array $held, int $postings, array $stations): array
    {
        $active = \count(array_filter($people, static fn (User $u): bool => $u->isActive()));
        $departments = \count(array_unique(array_filter(
            array_map(static fn (Position $p): ?string => $p->getDepartment()?->getUuidString(), $positions),
        )));
        $empty = \count(array_filter($stations, static fn (PostingStation $s): bool => $s->isEmpty()));
        $holdsNothing = \count($this->users->findActiveWithoutPosition());

        return [
            new SectionKpi('People', (string) \count($people), qualifier: \sprintf('%d active · %d deactivated', $active, \count($people) - $active)),
            new SectionKpi('Positions', (string) \count($positions), qualifier: \sprintf('in %d departments · names unique inside one', $departments)),
            /*
             * A SEAT IS A POSITION, FOR NOW. The drawn card counts SEATS — a
             * position that can be held by more than one person — and the model
             * has no such number: a position is one post, held or not. The
             * figure is therefore positions, which is the truth this
             * installation can state; the multi-seat position is a model change
             * that rewrites the positions screen too and is not made here.
             */
            new SectionKpi(
                'Seats filled',
                (string) \count($held),
                of: \sprintf('of %d', \count($positions)),
                qualifier: \sprintf('%d vacant · %d hold none', \count($positions) - \count($held), $holdsNothing),
            ),
            new SectionKpi('Postings', (string) $postings, qualifier: \sprintf('%d stations · %d with nobody', \count($stations), $empty)),
            new SectionKpi(
                'Roles',
                (string) \count(TeamRoleEnum::cases()),
                qualifier: \sprintf('tiers · %d of %d may administer', self::mayAdminister($people), \count($people)),
                hot: true,
            ),
        ];
    }

    /**
     * WHO MAY ADMINISTER THE TEAM — the two tiers that stand above the matrix,
     * plus everybody whose position carries the grant. It is not the tier
     * column, and a page that read it off the tier column would be wrong on
     * every installation that delegates.
     *
     * @param list<User> $people
     */
    private static function mayAdminister(array $people): int
    {
        $count = 0;
        foreach ($people as $person) {
            if (!$person->isActive()) {
                continue;
            }
            if ($person->getTeamRole()->canManageContent()
                || true === $person->getPosition()?->hasPermission(PermissionEnum::TeamManage)) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * EVERY POSITION SOMEBODY SITS IN, by id. Held is a fact about the PEOPLE
     * and not a column on the position, so it is read from the one side that
     * knows.
     *
     * @param list<User> $people
     *
     * @return array<int, true>
     */
    private static function heldPositionIds(array $people): array
    {
        $held = [];
        foreach ($people as $person) {
            $id = $person->isActive() ? $person->getPosition()?->getId() : null;
            if (null !== $id) {
                $held[$id] = true;
            }
        }

        return $held;
    }

    /**
     * PEOPLE BY DEPARTMENT, LONGEST FIRST, and the share of the installation
     * each one is. A person with no position has no department, and that is a
     * row rather than a rounding: it is exactly the person the attention card
     * below is about.
     *
     * @param list<User> $people
     *
     * @return list<SectionBar>
     */
    private static function peopleByDepartment(array $people): array
    {
        $counts = [];
        foreach ($people as $person) {
            $name = $person->getDepartment()?->getName() ?? 'No department';
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }
        arsort($counts);

        $total = \count($people);
        $largest = 0;
        foreach ($counts as $count) {
            $largest = max($largest, $count);
        }

        $bars = [];
        foreach ($counts as $name => $count) {
            $share = $total > 0 ? (int) round($count / $total * 100) : 0;
            $bars[] = new SectionBar(
                label: (string) $name,
                value: $count,
                total: $count,
                people: $count,
                note: \sprintf('<b>%d</b> · %d %%', $count, $share),
                // The loop runs only where there is a row, so the largest row
                // is at least one and the scale cannot divide by nothing.
                filledWidth: round($count / $largest * 100, 1),
            );
        }

        return $bars;
    }

    /**
     * POSITIONS HELD AND UNHELD, by department. A position with no holder is a
     * permission set sitting ready rather than an error, so the gap is drawn
     * beside the fill rather than reported as a fault.
     *
     * @param list<Position>   $positions
     * @param array<int, true> $held
     *
     * @return list<SectionBar>
     */
    private static function seatsByDepartment(array $positions, array $held): array
    {
        $totals = $filled = [];
        foreach ($positions as $position) {
            $name = $position->getDepartment()?->getName() ?? 'No department';
            $totals[$name] = ($totals[$name] ?? 0) + 1;
            $filled[$name] ??= 0;
            if (isset($held[(int) $position->getId()])) {
                ++$filled[$name];
            }
        }
        arsort($totals);

        $largest = 0;
        foreach ($totals as $total) {
            $largest = max($largest, $total);
        }

        $bars = [];
        foreach ($totals as $name => $total) {
            $unheld = $total - $filled[$name];
            $bars[] = new SectionBar(
                label: (string) $name,
                value: $filled[$name],
                total: $total,
                people: $filled[$name],
                note: \sprintf('<b>%d</b>/%d%s', $filled[$name], $total, $unheld > 0 ? \sprintf(' · %d unheld', $unheld) : ''),
            )->scaledTo($largest);
        }

        return $bars;
    }

    /**
     * POSTINGS BY AREA, as the heights a bar chart draws. The scale is worked
     * out here and not in the template: a percentage computed in Twig is a
     * percentage nothing can test.
     *
     * AN AREA WITH NONE KEEPS ITS COLUMN, drawn as the hairline a nought is —
     * three areas with no station yet is the reading the card exists for.
     *
     * @param array<string, int> $areas
     *
     * @return list<array{name: string, postings: int, height: float}>
     */
    private static function postingsByArea(array $areas): array
    {
        ksort($areas);
        $top = self::axisFor($areas)[0];

        $columns = [];
        foreach ($areas as $name => $postings) {
            $columns[] = [
                'name' => $name,
                'postings' => $postings,
                'height' => $top > 0 ? round($postings / $top * 100, 1) : 0.0,
            ];
        }

        return $columns;
    }

    /**
     * THE CHART'S THREE TICKS — the top, its half and nought.
     *
     * THE TOP IS A ROUND NUMBER ABOVE THE TALLEST BAR, not the tallest bar
     * itself: an axis whose top is 31 tells a reader to do arithmetic, and a
     * bar drawn to the very top of its own frame reads as clipped.
     *
     * @param array<string, int> $areas
     *
     * @return list<int>
     */
    private static function axisFor(array $areas): array
    {
        $largest = 0;
        foreach ($areas as $postings) {
            $largest = max($largest, $postings);
        }

        if (0 === $largest) {
            return [0, 0, 0];
        }

        $step = self::AXIS_STEP;
        $top = (int) (ceil($largest / $step) * $step);

        return [$top, intdiv($top, 2), 0];
    }

    /**
     * @param iterable<User> $people
     *
     * @return list<SectionLine>
     */
    private static function linesFor(iterable $people): array
    {
        $lines = [];
        foreach ($people as $person) {
            $lines[] = new SectionLine(
                label: $person->getFullName(),
                uuid: $person->getUuidString(),
                note: implode(' · ', array_filter([
                    $person->getTeamRole()->label(),
                    $person->isVerified() ? 'verified' : 'never signed in',
                    $person->isActive() ? null : 'deactivated',
                    null === $person->getPosition() ? 'no position' : null,
                ])),
                tone: $person->isActive() && !$person->isVerified() ? 'w' : 'd',
            );
        }

        return $lines;
    }
}
