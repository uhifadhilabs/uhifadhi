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
use Uhifadhi\Bundle\TeamBundle\Entity\Trait\TimestampableTrait;
use Uhifadhi\Bundle\TeamBundle\Entity\Trait\UuidTrait;
use Uhifadhi\Bundle\TeamBundle\Repository\DepartmentKindRepository;

/**
 * A WAY OF GROUPING DEPARTMENTS FOR READING — Operational, Scientific, Support.
 *
 * IT IS A LENS OVER THE REGISTER AND NOTHING ELSE. A kind grants no permission,
 * confines nothing to an area and changes no figure: it orders and bands a
 * list. That is the whole of it, and it is written here rather than as a PHP
 * enum precisely because a tenth organization wanting an eleventh kind should
 * add a row and not a release.
 *
 * A DEPARTMENT WITHOUT A KIND IS LEGAL and reads as unkinded. Removing a kind
 * therefore leaves its departments standing — SET NULL, never cascade — because
 * deleting a word must never delete the thing it described.
 *
 * NOT TO BE CONFUSED WITH THE SCOPE. The two scopes — org-wide and area-level —
 * are the model: they are what every query a module writes filters on, and a
 * third would be a third rule in all of them. They are not a list and are not
 * edited. A kind is the opposite: it is a list, and it filters nothing.
 */
#[ORM\Entity(repositoryClass: DepartmentKindRepository::class)]
#[ORM\Table(name: 'team_department_kind')]
#[ORM\UniqueConstraint(name: 'uniq_team_department_kind_name', fields: ['name'])]
#[ORM\HasLifecycleCallbacks]
class DepartmentKind
{
    use TimestampableTrait;
    use UuidTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    /** Unique across the installation: a kind is one word for one grouping. */
    #[ORM\Column(length: 80)]
    private string $name;

    /**
     * WHAT IT MEANS, in the reader's words. A kind whose meaning lives only in
     * the head of whoever added it gets a second kind next year that means the
     * same thing, so the meaning is a column and not a convention.
     */
    #[ORM\Column(length: 160, nullable: true)]
    private ?string $meaning = null;

    /** @var Collection<int, Department> */
    #[ORM\OneToMany(targetEntity: Department::class, mappedBy: 'kind')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $departments;

    public function __construct(string $name = '', ?string $meaning = null)
    {
        $this->name = $name;
        $this->meaning = $meaning;
        $this->departments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getMeaning(): ?string
    {
        return $this->meaning;
    }

    public function setMeaning(?string $meaning): static
    {
        $this->meaning = $meaning;

        return $this;
    }

    /** @return Collection<int, Department> */
    public function getDepartments(): Collection
    {
        return $this->departments;
    }
}
