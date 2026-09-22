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

namespace Uhifadhi\Bundle\TeamBundle\Model;

use Symfony\Component\HttpFoundation\Request;
use Uhifadhi\Bundle\TeamBundle\Entity\Department;

/**
 * WHAT THE READER ASKED THE DEPARTMENT REGISTER FOR, read off the query
 * string.
 *
 * THE REGISTER IS ONE TABLE (ruled 2026-09-22): four grouped dropdowns —
 * placement, module, goals, seats — a search on the name, and a sort on
 * exactly one column. Every answer is a query parameter, so a filtered and
 * sorted register is a link somebody can send and a page a browser can go
 * back to, and the whole bar works with no script.
 *
 * THE FOCUSED DEPARTMENT IS PART OF THE QUESTION. The sidebar's subtree
 * points at one row; which one is a place, so it rides in the address, is
 * rendered as the marked row and lights the entry in the tree.
 */
final readonly class DepartmentQuery
{
    public const string SEARCH = 'q';
    public const string PLACEMENT = 'placement';
    public const string MODULE = 'module';
    public const string GOALS = 'goals';
    public const string SEATS = 'seats';
    public const string SORT = 'sort';
    public const string DIRECTION = 'dir';
    public const string FOCUS = 'focus';
    /** The rows folded open, comma-separated uuids. */
    public const string OPEN = 'open';

    /** The one placement answer that is not an area: org-wide. */
    public const string ORG = 'org';
    /** A module answer for the departments that attach none. */
    public const string NO_MODULE = 'none';

    public const string GOALS_SOME = 'some';
    public const string GOALS_NONE = 'none';

    public const string SEATS_VACANT = 'vacant';
    public const string SEATS_FILLED = 'filled';

    public const string ASC = 'asc';
    public const string DESC = 'desc';

    /** The sortable columns, in the table's order; the first is the default. */
    public const array SORTS = ['name', 'modules', 'positions', 'seats', 'goals'];

    public function __construct(
        /** `org`, or an area's uuid; null is every placement. */
        public ?string $placement = null,
        /** A module slug, or `none`; null is any module. */
        public ?string $module = null,
        public ?string $goals = null,
        public ?string $seats = null,
        public string $search = '',
        public string $sort = 'name',
        public string $direction = self::ASC,
        public ?string $focus = null,
        /** @var list<string> */
        public array $open = [],
    ) {
    }

    public static function from(Request $request): self
    {
        $sort = trim($request->query->getString(self::SORT, 'name'));
        $direction = trim($request->query->getString(self::DIRECTION, self::ASC));
        $goals = trim($request->query->getString(self::GOALS));
        $seats = trim($request->query->getString(self::SEATS));

        return new self(
            placement: self::orNull($request->query->getString(self::PLACEMENT)),
            module: self::orNull($request->query->getString(self::MODULE)),
            // AN ANSWER THIS PAGE DOES NOT HAVE FILTERS NOTHING, rather than
            // emptying the register: a stale link should show the list.
            goals: \in_array($goals, [self::GOALS_SOME, self::GOALS_NONE], true) ? $goals : null,
            seats: \in_array($seats, [self::SEATS_VACANT, self::SEATS_FILLED], true) ? $seats : null,
            search: trim($request->query->getString(self::SEARCH)),
            sort: \in_array($sort, self::SORTS, true) ? $sort : 'name',
            direction: self::DESC === $direction ? self::DESC : self::ASC,
            focus: self::orNull($request->query->getString(self::FOCUS)),
            open: array_values(array_filter(array_map(trim(...), explode(',', $request->query->getString(self::OPEN))))),
        );
    }

    public function isOpen(string $uuid): bool
    {
        return \in_array($uuid, $this->open, true);
    }

    private static function orNull(string $value): ?string
    {
        $value = trim($value);

        return '' === $value ? null : $value;
    }

    /**
     * THE SEARCH OVER DEPARTMENTS THEMSELVES, for the pages that list cards
     * rather than rows — an area's own Departments tab reads the name the
     * same way the register's rows do.
     *
     * @param list<Department> $departments
     *
     * @return list<Department>
     */
    public function matching(array $departments): array
    {
        if ('' === $this->search) {
            return $departments;
        }

        $term = mb_strtolower($this->search);

        return array_values(array_filter(
            $departments,
            static fn (Department $department): bool => str_contains(mb_strtolower((string) $department->getName()), $term),
        ));
    }

    public function isFiltered(): bool
    {
        return null !== $this->placement || null !== $this->module || null !== $this->goals
            || null !== $this->seats || '' !== $this->search;
    }

    /**
     * Whether a row answers every filter at once.
     */
    public function matches(DepartmentRow $row): bool
    {
        if (null !== $this->placement && $this->placement !== $row->placementKey()) {
            return false;
        }
        if (null !== $this->module && !$row->attaches($this->module)) {
            return false;
        }
        if (self::GOALS_SOME === $this->goals && 0 === $row->goals) {
            return false;
        }
        if (self::GOALS_NONE === $this->goals && $row->goals > 0) {
            return false;
        }
        if (self::SEATS_VACANT === $this->seats && $row->vacant < 1) {
            return false;
        }
        if (self::SEATS_FILLED === $this->seats && $row->vacant > 0) {
            return false;
        }
        // THE SEARCH READS THE NAME, which is what somebody typing into a
        // register of nine things is looking for.
        if ('' !== $this->search && !str_contains(mb_strtolower($row->name), mb_strtolower($this->search))) {
            return false;
        }

        return true;
    }

    /**
     * The rows in the order the sort asks for — one column, one direction,
     * and the name as the tie-breaker so two equal rows never swap between
     * two loads of the same address.
     *
     * @param list<DepartmentRow> $rows
     *
     * @return list<DepartmentRow>
     */
    public function order(array $rows): array
    {
        $sign = self::DESC === $this->direction ? -1 : 1;
        usort($rows, function (DepartmentRow $a, DepartmentRow $b) use ($sign): int {
            $cmp = match ($this->sort) {
                'modules' => strcasecmp($a->modulesText(), $b->modulesText()),
                'positions' => $a->positions <=> $b->positions,
                // Unlimited seats sort after every counted set: a number is
                // always more comparable than "no ceiling".
                'seats' => ($a->seats ?? \PHP_INT_MAX) <=> ($b->seats ?? \PHP_INT_MAX),
                'goals' => $a->goals <=> $b->goals,
                default => strcasecmp($a->name, $b->name),
            };

            return $sign * $cmp ?: strcasecmp($a->name, $b->name);
        });

        return $rows;
    }

    /**
     * The direction a header link should ask for: clicking the sorted column
     * turns it over, clicking any other sorts it ascending.
     */
    public function directionFor(string $column): string
    {
        return $column === $this->sort && self::ASC === $this->direction ? self::DESC : self::ASC;
    }

    /**
     * The same question with one answer changed — how every option and every
     * header is a link rather than a script.
     *
     * @return array<string, string>
     */
    public function with(string $key, ?string $value): array
    {
        $params = array_filter([
            self::PLACEMENT => $this->placement,
            self::MODULE => $this->module,
            self::GOALS => $this->goals,
            self::SEATS => $this->seats,
            self::SEARCH => '' === $this->search ? null : $this->search,
            self::SORT => 'name' === $this->sort ? null : $this->sort,
            self::DIRECTION => self::ASC === $this->direction ? null : $this->direction,
            // THE FOCUS DOES NOT SURVIVE A FILTER. Marking a row the filter
            // just removed is a mark on nothing.
            self::FOCUS => self::FOCUS === $key ? null : $this->focus,
            // WHAT IS OPEN SURVIVES A SORT OR A FILTER: a reader who opened two
            // rows and then sorted still has them open.
            self::OPEN => [] === $this->open ? null : implode(',', $this->open),
        ], static fn (?string $v): bool => null !== $v);

        if (null === $value) {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }

        return $params;
    }

    /**
     * The address of the register sorted by a column, the direction turned
     * over when that column is already the sort.
     *
     * @return array<string, string>
     */
    public function sortedBy(string $column): array
    {
        $params = $this->with(self::SORT, 'name' === $column ? null : $column);
        unset($params[self::DIRECTION]);
        if (self::DESC === $this->directionFor($column)) {
            $params[self::DIRECTION] = self::DESC;
        }

        return $params;
    }
}
