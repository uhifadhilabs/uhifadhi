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

namespace Uhifadhi\Bundle\AreaBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Uhifadhi\Bundle\AreaBundle\Entity\Trait\TimestampableTrait;
use Uhifadhi\Bundle\AreaBundle\Entity\Trait\UuidTrait;
use Uhifadhi\Bundle\AreaBundle\Repository\ZoneRepository;

/**
 * A ZONE — a named polygon subdividing one {@see AreaOfInterest}, and the
 * SPATIAL lens the way a department is the organisational one. Zones are data an
 * admin draws or uploads, never code: modules ask generic questions of them
 * ("which zone is this point in?") and must never name one, because the names
 * are one installation's geography and nobody else's.
 *
 * ZONES LIVE INSIDE AREAS. The area is not a property a zone happens to carry,
 * it is what makes the zone addressable at all — so the foreign key is NOT NULL
 * and cascades in the database. Delete an area and its zones go with it.
 *
 * AN AREA WITH NO ZONES IS THE NORMAL STATE, not a configuration somebody
 * forgot. Every consumer treats "unzoned" as a first-class answer; see
 * {@see \Uhifadhi\Bundle\AreaBundle\Service\ZoneService::zoneOf()}, which returns null
 * without complaint.
 *
 * Sibling zones may touch along an edge and may leave gaps between them, but
 * never share interior. That invariant is not expressible as a column
 * constraint — it lives in {@see \Uhifadhi\Bundle\AreaBundle\Service\ZoneService}, which is
 * the only supported way to create a zone or replace its geometry. Names are
 * unique per area, so two areas may each have a "North". `geom` is a
 * MultiPolygon in WGS84 like the area's.
 *
 * THE TABLE NAME IS A COMPATIBILITY PROMISE, the same one `area_of_interest`
 * carries: `zone` is what an installation that wrote this entity by hand
 * already has, so adopting this module keeps its rows.
 */
#[ORM\Entity(repositoryClass: ZoneRepository::class)]
#[ORM\Table(name: 'zone')]
#[ORM\UniqueConstraint(name: 'uniq_zone_area_name', columns: ['area_id', 'name'])]
#[ORM\HasLifecycleCallbacks]
class Zone
{
    use TimestampableTrait;
    use UuidTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    #[ORM\Column(length: 128)]
    private ?string $name = null;

    /** The zone lives and dies with its area — the FK cascades in the database. */
    #[ORM\ManyToOne(targetEntity: AreaOfInterest::class)]
    #[ORM\JoinColumn(name: 'area_id', nullable: false, onDelete: 'CASCADE')]
    private ?AreaOfInterest $area = null;

    #[ORM\Column(type: 'multipolygon')]
    private ?string $geom = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getArea(): ?AreaOfInterest
    {
        return $this->area;
    }

    public function setArea(AreaOfInterest $area): static
    {
        $this->area = $area;

        return $this;
    }

    public function getGeom(): ?string
    {
        return $this->geom;
    }

    public function setGeom(string $geom): static
    {
        $this->geom = $geom;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
