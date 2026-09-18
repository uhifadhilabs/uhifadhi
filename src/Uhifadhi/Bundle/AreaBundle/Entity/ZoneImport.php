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

/**
 * WHERE A ZONING SCHEME CAME FROM — one row per import, and the answer to the
 * question somebody asks two years later: who put these twelve polygons here,
 * when, out of what, and how were they named?
 *
 * THE FILE IS NOT HERE, AND THAT IS THE RULING. An uploaded file is read to
 * geometry and let go: the geometry lives in PostGIS, where it can be queried,
 * and a copy of the document in a column or on a disk is a second version of
 * the truth that drifts from the first the moment a zone is redrawn. What is
 * worth keeping is the PROVENANCE — the name of the file somebody chose, the
 * moment, the person, the count, and which property supplied the names — and
 * that is exactly these five columns.
 *
 * ONE ROW PER IMPORT, NOT FIVE COLUMNS PER ZONE. Every fact here is a fact
 * about the FILE rather than about any one zone: the count is the scheme's, the
 * name property is the file's. Written onto each zone they would be the same
 * value repeated a dozen times, and a second import into the same area could no
 * longer be told from the first.
 *
 * THE PERSON IS A STRING, deliberately. The core's account model lives in
 * another bundle and an installation may replace it, so a foreign key here
 * would tie this table to a class this bundle must not name; the identifier is
 * recorded as it stood. It also survives the account being removed, which is
 * what provenance is for. Null where no user was known — a fixture loader or an
 * installer has no session.
 *
 * NO UUID, unlike the area and the zone. Those two are addressed from outside —
 * a URL, the field API — and an import is not: it is read through the zones it
 * made and has no page of its own, so a public identifier would be an index
 * nothing looks anything up by.
 *
 * ZONES THAT PREDATE THE IMPORTER KEEP NO PROVENANCE. The association on
 * {@see Zone} is nullable, and a zone drawn or seeded before this row existed
 * simply has none; a null there says "not recorded", never "not imported".
 */
#[ORM\Entity]
#[ORM\Table(name: 'zone_import')]
class ZoneImport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    /** The import lives and dies with its area — the FK cascades in the database. */
    #[ORM\ManyToOne(targetEntity: AreaOfInterest::class)]
    #[ORM\JoinColumn(name: 'area_id', nullable: false, onDelete: 'CASCADE')]
    private ?AreaOfInterest $area = null;

    /** The name the file had when somebody uploaded it — not a path, and not a stored file. */
    #[ORM\Column(length: 255)]
    private ?string $fileName = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $importedAt = null;

    /** Null where no user was known. */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $importedBy = null;

    #[ORM\Column]
    private ?int $zoneCount = null;

    /** Which of the accepted property spellings supplied the zone names. */
    #[ORM\Column(length: 64)]
    private ?string $nameProperty = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): static
    {
        $this->fileName = $fileName;

        return $this;
    }

    public function getImportedAt(): ?\DateTimeImmutable
    {
        return $this->importedAt;
    }

    public function setImportedAt(\DateTimeImmutable $importedAt): static
    {
        $this->importedAt = $importedAt;

        return $this;
    }

    public function getImportedBy(): ?string
    {
        return $this->importedBy;
    }

    public function setImportedBy(?string $importedBy): static
    {
        $this->importedBy = $importedBy;

        return $this;
    }

    public function getZoneCount(): ?int
    {
        return $this->zoneCount;
    }

    public function setZoneCount(int $zoneCount): static
    {
        $this->zoneCount = $zoneCount;

        return $this;
    }

    public function getNameProperty(): ?string
    {
        return $this->nameProperty;
    }

    public function setNameProperty(string $nameProperty): static
    {
        $this->nameProperty = $nameProperty;

        return $this;
    }
}
