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
 * THE PILLS ARE THE CREATE CHOOSER'S OWN WORDS, in the same order: All,
 * Org-wide, Area-level — and picking Area-level reveals the area picker that
 * narrows it further. A register whose filter and whose create control named
 * the same two things differently would be two vocabularies for one model.
 *
 * SERVER-SIDE, AND THE ADDRESS IS THE STATE. A filtered register is a link
 * somebody can send and a page a browser can go back to, and it works with no
 * script — which a list filtered in the browser is not.
 *
 * THE FOCUSED DEPARTMENT IS PART OF THE QUESTION. The sidebar's subtree
 * points at one card; which one is a place, so it rides in the address, is
 * rendered as the marked card and lights the row in the tree.
 */
final readonly class DepartmentQuery
{
    public const string SEARCH = 'q';
    public const string SCOPE = 'scope';
    public const string AREA = 'area';
    public const string FOCUS = 'focus';

    public const string ALL = 'all';
    public const string ORG = 'org';

    public function __construct(
        public string $scope = self::ALL,
        public ?string $area = null,
        public string $search = '',
        public ?string $focus = null,
    ) {
    }

    public static function from(Request $request): self
    {
        $scope = trim($request->query->getString(self::SCOPE, self::ALL));
        $area = trim($request->query->getString(self::AREA));
        $focus = trim($request->query->getString(self::FOCUS));

        return new self(
            // AN ANSWER THIS PAGE DOES NOT HAVE FILTERS NOTHING, rather than
            // emptying the register: a stale link should show the list.
            scope: \in_array($scope, [self::ALL, self::ORG, self::AREA], true) ? $scope : self::ALL,
            // The area narrows only the area-level view; naming one anywhere
            // else is a question the pills have already answered.
            area: self::AREA === $scope && '' !== $area ? $area : null,
            search: trim($request->query->getString(self::SEARCH)),
            focus: '' === $focus ? null : $focus,
        );
    }

    /**
     * THE SEARCH READS THE NAME, which is what somebody typing into a register
     * of nine things is looking for.
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
        return self::ALL !== $this->scope || '' !== $this->search;
    }

    /**
     * The same question with one answer changed — how every pill is a link
     * rather than a script.
     *
     * @return array<string, string>
     */
    public function with(string $key, ?string $value): array
    {
        $params = array_filter([
            self::SCOPE => self::ALL === $this->scope ? null : $this->scope,
            self::AREA => $this->area,
            self::SEARCH => '' === $this->search ? null : $this->search,
            // THE FOCUS DOES NOT SURVIVE A FILTER. Marking a card the filter
            // just removed is a mark on nothing.
            self::FOCUS => self::FOCUS === $key ? null : $this->focus,
        ], static fn (?string $v): bool => null !== $v);

        if (null === $value) {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }

        return $params;
    }
}
