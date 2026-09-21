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

namespace Uhifadhi\Bundle\TeamBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Uhifadhi\Contracts\Entity\AreaInterface;

/**
 * WHERE ONE PERSON IS PLACED - the second half of every permission check, and
 * the half that belongs to them rather than to their position.
 *
 * TWO DIMENSIONS, AND THEY ARE ANSWERED SEPARATELY:
 *
 *   - THE GROUND: the whole organization, or one or more named areas.
 *   - THE DEPARTMENTS: all of them, or a named set, and several are allowed.
 *
 * The case that settles the second is the ordinary one. A data scientist
 * supporting Ecology and Protection but not ICT is still ONE position - Data
 * Analyst - placed against two departments. So DEPARTMENT MEMBERSHIP FOLLOWS
 * THE PLACEMENT: somebody is in the departments their placement names, and in
 * no others.
 *
 * IT FAILS CLOSED, AND THE TWO BOOLEANS ARE WHY. "Everywhere" is a thing
 * somebody decided and wrote down, not the shape an empty list happens to
 * have: a row whose area set failed to save would otherwise read as
 * organization-wide. So the breadth is stored as its own answer, the set is
 * read only when the answer is "named", and a placement that names nothing
 * and claims nothing reaches nowhere.
 *
 * THE AREAS ARE THE PLATFORM'S, not this bundle's: the association points at
 * {@see AreaInterface} and the area bundle resolves it, so the team bundle
 * keeps a real relation to ground it does not own.
 */
#[ORM\Entity]
#[ORM\Table(name: 'team_placement')]
class Placement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    /** Whether the ground is the whole organization. False means {@see $areas} is the answer. */
    #[ORM\Column(name: 'whole_organization', options: ['default' => false])]
    private bool $wholeOrganization = false;

    /** @var Collection<int, AreaInterface> */
    #[ORM\ManyToMany(targetEntity: AreaInterface::class)]
    #[ORM\JoinTable(name: 'team_placement_area')]
    #[ORM\JoinColumn(name: 'placement_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'area_id', onDelete: 'CASCADE')]
    private Collection $areas;

    /** Whether every department is covered. False means {@see $departments} is the answer. */
    #[ORM\Column(name: 'all_departments', options: ['default' => false])]
    private bool $allDepartments = false;

    /** @var Collection<int, Department> */
    #[ORM\ManyToMany(targetEntity: Department::class)]
    #[ORM\JoinTable(name: 'team_placement_department')]
    #[ORM\JoinColumn(name: 'placement_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'department_id', onDelete: 'CASCADE')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $departments;

    public function __construct()
    {
        $this->areas = new ArrayCollection();
        $this->departments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    // --- the ground -------------------------------------------------------

    public function isWholeOrganization(): bool
    {
        return $this->wholeOrganization;
    }

    /** The whole organization: every area, including ones gazetted after today. */
    public function acrossTheOrganization(): static
    {
        $this->wholeOrganization = true;
        $this->areas->clear();

        return $this;
    }

    /**
     * Named ground, and at least one piece of it.
     *
     * @param list<AreaInterface> $areas
     *
     * @throws \InvalidArgumentException when no area is named
     */
    public function inAreas(array $areas): static
    {
        if ([] === $areas) {
            throw new \InvalidArgumentException('A placement at named areas names at least one. To place somebody everywhere, say so - an empty list is not a way of saying "all".');
        }

        $this->wholeOrganization = false;
        $this->areas->clear();
        foreach ($areas as $area) {
            if (!$this->areas->contains($area)) {
                $this->areas->add($area);
            }
        }

        return $this;
    }

    /**
     * THE NAMED AREAS, or null when the ground is the whole organization -
     * which is the shape the ruling states, and the shape that makes "null
     * means everything" impossible to confuse with "nothing was named".
     *
     * @return list<AreaInterface>|null
     */
    public function getAreas(): ?array
    {
        return $this->wholeOrganization ? null : array_values($this->areas->toArray());
    }

    /**
     * THE SECOND QUESTION: does the placement cover this ground? A null target
     * is "no area in context" - a nav question, a "may I ever?" flag - and is
     * covered by any placement that reaches some ground at all, with the real
     * per-area gate applying once an area is named.
     */
    public function coversArea(?AreaInterface $area): bool
    {
        if ($this->wholeOrganization) {
            return true;
        }

        if ($this->areas->isEmpty()) {
            return false;
        }

        if (null === $area) {
            return true;
        }

        foreach ($this->areas as $placed) {
            if (self::sameArea($placed, $area)) {
                return true;
            }
        }

        return false;
    }

    // --- the departments --------------------------------------------------

    public function isAllDepartments(): bool
    {
        return $this->allDepartments;
    }

    public function acrossAllDepartments(): static
    {
        $this->allDepartments = true;
        $this->departments->clear();

        return $this;
    }

    /**
     * A named set, and several are allowed - that is the whole reason a
     * department stopped being something a position carried.
     *
     * @param list<Department> $departments
     *
     * @throws \InvalidArgumentException when no department is named
     */
    public function inDepartments(array $departments): static
    {
        if ([] === $departments) {
            throw new \InvalidArgumentException('A placement against named departments names at least one. To place somebody across all of them, say so - an empty list is not a way of saying "all".');
        }

        $this->allDepartments = false;
        $this->departments->clear();
        foreach ($departments as $department) {
            if (!$this->departments->contains($department)) {
                $this->departments->add($department);
            }
        }

        return $this;
    }

    /**
     * THE DEPARTMENTS THIS PERSON IS IN, or null when they are in all of them.
     *
     * @return list<Department>|null
     */
    public function getDepartments(): ?array
    {
        return $this->allDepartments ? null : array_values($this->departments->toArray());
    }

    /**
     * THE THIRD QUESTION: does the placement cover this department? A null
     * department is a concern that belongs to none, and the question does not
     * arise - so it is answered yes, by the same reasoning that makes the
     * question conditional in the ruling.
     */
    public function coversDepartment(?Department $department): bool
    {
        if (null === $department) {
            return true;
        }

        if ($this->allDepartments) {
            return true;
        }

        foreach ($this->departments as $placed) {
            if ($placed === $department || (null !== $placed->getId() && $placed->getId() === $department->getId())) {
                return true;
            }
        }

        return false;
    }

    /**
     * THE DEPARTMENTS IN ONE FRAGMENT, for the places a row has one cell for
     * them - a board, a directory facet, a chip.
     *
     * HERE RATHER THAN IN A TEMPLATE, because several surfaces have to spell
     * it the same way and a person may now be in more than one department:
     * the first name plus a count is the fragment, the full set is the
     * person's record.
     */
    public function departmentsLabel(): ?string
    {
        if ($this->allDepartments) {
            return 'All departments';
        }

        $names = array_values(array_map(
            static fn (Department $d): string => (string) $d->getName(),
            $this->departments->toArray(),
        ));

        return match (\count($names)) {
            0 => null,
            1 => $names[0],
            default => \sprintf('%s +%d', $names[0], \count($names) - 1),
        };
    }

    /**
     * WHETHER IT REACHES ANYTHING AT ALL. A placement whose ground is nowhere
     * and whose departments are none is a row that grants its holder nothing,
     * and a surface says so in those words rather than drawing an empty list.
     */
    public function reachesNothing(): bool
    {
        return !$this->wholeOrganization && $this->areas->isEmpty();
    }

    /**
     * The two areas are the same one - compared on the public address (uuid)
     * first, then the persistence id, so it holds whether or not the two are
     * the same managed instance.
     */
    private static function sameArea(AreaInterface $one, AreaInterface $other): bool
    {
        $oneUuid = $one->getUuidString();
        $otherUuid = $other->getUuidString();
        if (null !== $oneUuid && null !== $otherUuid) {
            return $oneUuid === $otherUuid;
        }

        $oneId = $one->getId();

        return null !== $oneId && $oneId === $other->getId();
    }
}
