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

use Uhifadhi\Bundle\TeamBundle\Entity\Department;
use Uhifadhi\Contracts\Entity\AreaInterface;

/**
 * ONE ROW OF THE DEPARTMENTS REGISTER — the facts the table sorts and filters
 * on, computed once so the query, the counts and the template all read the
 * same numbers.
 *
 * A DEPARTMENT OWNS NO POSITION AND HOLDS NOBODY DIRECTLY: the positions are
 * the distinct ones its members hold, the people are counted through them,
 * and the seats are those positions' seat counts — null when any of them is
 * unlimited, because a ceiling with a hole in it is not a ceiling.
 */
final readonly class DepartmentRow
{
    /**
     * @param list<string> $modules the attached modules' names, in catalogue order
     * @param list<string> $slugs   the same modules' slugs
     */
    public function __construct(
        public Department $department,
        public string $uuid,
        public string $name,
        public ?AreaInterface $area,
        public ?string $kind,
        public array $modules,
        public array $slugs,
        public int $positions,
        public int $filled,
        public ?int $seats,
        public int $goals,
        public bool $active,
        public ?int $category,
    ) {
        $this->vacant = null === $seats ? 0 : max(0, $seats - $filled);
    }

    /*
     * TWIG READS A NULL PROPERTY AS ABSENT — its attribute lookup asks
     * isset() and then for a method — so the three facts that may be null
     * are also offered as methods, which is what a template reaches.
     */
    public function getArea(): ?AreaInterface
    {
        return $this->area;
    }

    public function getKind(): ?string
    {
        return $this->kind;
    }

    public function getSeats(): ?int
    {
        return $this->seats;
    }

    /** `org`, or the area's uuid — the placement dropdown's own value. */
    public function placementKey(): string
    {
        return $this->area?->getUuidString() ?? DepartmentQuery::ORG;
    }

    public function attaches(string $module): bool
    {
        if (DepartmentQuery::NO_MODULE === $module) {
            return [] === $this->slugs;
        }

        return \in_array($module, $this->slugs, true);
    }

    /** Seats nobody fills; nought when the seats are unlimited or all taken. */
    public int $vacant;

    public function modulesText(): string
    {
        return implode(', ', $this->modules);
    }
}
