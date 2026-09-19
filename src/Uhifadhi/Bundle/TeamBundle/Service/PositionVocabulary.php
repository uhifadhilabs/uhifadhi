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

use Uhifadhi\Bundle\TeamBundle\Repository\DepartmentRepository;
use Uhifadhi\Bundle\TeamBundle\Repository\PositionRepository;

/**
 * THE WORDS THIS INSTALLATION WRITES ITS POSITIONS WITH, read per department.
 *
 * A POSITION'S NAME IS UNIQUE INSIDE ITS DEPARTMENT AND NOWHERE ELSE, so the
 * same word twice is legal and is the case the vocabulary exists to allow:
 * `Analyst` in Ecology and `Analyst` in Protection Service are two different
 * jobs with different permissions. Nothing here may merge them — the shared
 * words are NAMED instead, so an organisation can see the collision and
 * decide whether it meant it.
 *
 * ONE PASS OVER THE POSITIONS, NOT ONE QUERY PER DEPARTMENT. Walking a
 * department's inverse collection is a lazy load per row, and the inverse of
 * a OneToMany is only as true as whoever maintained it; the owning side is
 * the fact.
 */
final readonly class PositionVocabulary
{
    public function __construct(
        private DepartmentRepository $departments,
        private PositionRepository $positions,
    ) {
    }

    /**
     * @return array{
     *     rows: list<array{name: string, titles: list<string>}>,
     *     positions: int,
     *     sharedWords: list<string>,
     * }
     */
    public function read(): array
    {
        $positions = $this->positions->findAllOrdered();

        $byDepartment = [];
        foreach ($this->departments->findAllOrdered() as $department) {
            $byDepartment[(string) $department->getUuidString()] = [
                'name' => (string) $department->getName(),
                'titles' => [],
            ];
        }

        // A POSITION FILED UNDER NO DEPARTMENT IS A ROW, not a rounding: it is
        // legal, and it is exactly the one whose name is unique against
        // nothing.
        $loose = ['name' => 'No department', 'titles' => []];
        $shared = [];

        foreach ($positions as $position) {
            $name = (string) $position->getName();
            $key = $position->getDepartment()?->getUuidString();

            if (null === $key || !isset($byDepartment[$key])) {
                $loose['titles'][] = $name;
                continue;
            }

            $byDepartment[$key]['titles'][] = $name;
            $shared[$name][$byDepartment[$key]['name']] = true;
        }

        if ([] !== $loose['titles']) {
            $byDepartment[] = $loose;
        }

        return [
            'rows' => array_values($byDepartment),
            'positions' => \count($positions),
            'sharedWords' => array_keys(array_filter($shared, static fn (array $in): bool => \count($in) > 1)),
        ];
    }
}
