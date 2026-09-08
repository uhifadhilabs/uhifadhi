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

namespace Uhifadhi\Bundle\RegistryBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Uhifadhi\Bundle\RegistryBundle\Entity\Trait\TimestampableTrait;
use Uhifadhi\Bundle\RegistryBundle\Entity\Trait\UuidTrait;
use Uhifadhi\Bundle\RegistryBundle\Repository\AreaModuleRepository;
use Uhifadhi\Contracts\Entity\AreaInterface;

/**
 * A {@see Module} as one area holds it: on the area's sub-nav or parked in its
 * shop, at a position the area's own admin chose.
 *
 * SWITCHING IT OFF KEEPS THE ROW. That is the promise the customize screen makes
 * to an admin — "its data stays, it just leaves the area" — so switching a
 * module back on finds the area's history where it was rather than starting it
 * again. A pinned module is never switched off at all.
 *
 * THE AREA IS THE HOST'S. The association is mapped to {@see AreaInterface} and
 * a host resolves it to its own entity with `resolve_target_entities`; that is
 * what lets the registry own this table without defining — or requiring — anybody's
 * area model.
 */
#[ORM\Entity(repositoryClass: AreaModuleRepository::class)]
#[ORM\Table(name: 'area_module')]
#[ORM\UniqueConstraint(name: 'uniq_area_module', columns: ['aoi_id', 'module_id'])]
#[ORM\HasLifecycleCallbacks]
class AreaModule
{
    use TimestampableTrait;
    use UuidTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id')]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    #[ORM\ManyToOne(targetEntity: AreaInterface::class)]
    #[ORM\JoinColumn(name: 'aoi_id', nullable: false, onDelete: 'CASCADE')]
    private ?AreaInterface $area = null;

    #[ORM\ManyToOne(targetEntity: Module::class)]
    #[ORM\JoinColumn(name: 'module_id', nullable: false, onDelete: 'CASCADE')]
    private ?Module $module = null;

    /** On the area's sub-nav (true) or parked in its "add a module" shop (false). */
    #[ORM\Column(name: 'active')]
    private bool $active = true;

    /** Order within the area's sub-nav; a pinned module always leads. */
    #[ORM\Column(name: 'position')]
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArea(): ?AreaInterface
    {
        return $this->area;
    }

    public function setArea(AreaInterface $area): static
    {
        $this->area = $area;

        return $this;
    }

    public function getModule(): ?Module
    {
        return $this->module;
    }

    public function setModule(Module $module): static
    {
        $this->module = $module;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}
