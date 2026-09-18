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
use Uhifadhi\Bundle\AreaBundle\Enum\ZoneEventKind;
use Uhifadhi\Bundle\AreaBundle\Repository\ZoneEventRepository;

/**
 * ONE LINE OF AN AREA'S ZONE HISTORY — what happened, who did it, and when.
 *
 * A LINE, NOT A VERSION. The superseded geometry is not kept: a zone set has
 * one live state, and a second copy of a ring that is no longer the truth is a
 * second truth. So an entry is read, never restored, and an earlier scheme is
 * seen again only by importing its file again — which is what the export is
 * for.
 *
 * THE SENTENCE IS STORED, BECAUSE THE SUBJECT MAY NOT SURVIVE IT. "Removed
 * 'Oldiani'" has to still say Oldiani after the zone is gone, and an entry that
 * pointed at the row would have nothing left to point at. The same reasoning
 * makes the person a string rather than a foreign key, exactly as
 * {@see ZoneImport} records them: the core's account model lives in another
 * bundle, an installation may replace it, and provenance has to survive the
 * account being removed.
 *
 * TWO PARTS, BECAUSE THE PAGE PRINTS TWO. The headline is what happened; the
 * detail is the qualification that follows it. Splitting them here is what
 * keeps the template from parsing a sentence back apart.
 *
 * THE ENTRIES GO WITH THE AREA. Delete an area and its zones, its imports and
 * its history all go; the foreign key cascades in the database.
 */
#[ORM\Entity(repositoryClass: ZoneEventRepository::class)]
#[ORM\Table(name: 'zone_event')]
#[ORM\Index(name: 'idx_zone_event_area_time', columns: ['area_id', 'occurred_at'])]
class ZoneEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    #[ORM\ManyToOne(targetEntity: AreaOfInterest::class)]
    #[ORM\JoinColumn(name: 'area_id', nullable: false, onDelete: 'CASCADE')]
    private ?AreaOfInterest $area = null;

    #[ORM\Column(length: 32, enumType: ZoneEventKind::class)]
    private ?ZoneEventKind $kind = null;

    /**
     * 512, NOT 255: a sentence can hold two zone names and a zone name is 128
     * characters, so the column is sized for the longest line the vocabulary
     * can compose rather than for the longest anybody has written.
     */
    #[ORM\Column(length: 512)]
    private ?string $headline = null;

    /** The qualification after the headline, or null where the headline says it all. */
    #[ORM\Column(length: 512, nullable: true)]
    private ?string $detail = null;

    /** Null where no user was known — a fixture loader or an installer has no session. */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $actor = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $occurredAt = null;

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

    public function getKind(): ?ZoneEventKind
    {
        return $this->kind;
    }

    public function setKind(ZoneEventKind $kind): static
    {
        $this->kind = $kind;

        return $this;
    }

    public function getHeadline(): ?string
    {
        return $this->headline;
    }

    public function setHeadline(string $headline): static
    {
        $this->headline = $headline;

        return $this;
    }

    public function getDetail(): ?string
    {
        return $this->detail;
    }

    public function setDetail(?string $detail): static
    {
        $this->detail = $detail;

        return $this;
    }

    public function getActor(): ?string
    {
        return $this->actor;
    }

    public function setActor(?string $actor): static
    {
        $this->actor = $actor;

        return $this;
    }

    public function getOccurredAt(): ?\DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(\DateTimeImmutable $occurredAt): static
    {
        $this->occurredAt = $occurredAt;

        return $this;
    }
}
