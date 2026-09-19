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
use Uhifadhi\Bundle\AreaBundle\Enum\StationEventKind;
use Uhifadhi\Bundle\AreaBundle\Repository\StationEventRepository;

/**
 * ONE LINE OF A STATION'S HISTORY — what happened, who did it, and when.
 *
 * A LINE, NOT A VERSION. A station has one live state: where it stands, what
 * it is called, who works there. The log says what changed and when, and no
 * superseded point or name is kept anywhere — an entry is read, never
 * restored.
 *
 * THE GROUND WRITES HERE TOO. Most of these lines are somebody's doing, but
 * "the zone was re-derived" is the map's: an import moved the ring under a
 * post that nobody touched, and the station's own page is where that should
 * be visible.
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
 * THE ENTRIES GO WITH THE STATION, which goes with its area. A post that is
 * closed is DEACTIVATED and keeps every line; only deleting the area takes
 * them, which takes the station too.
 */
#[ORM\Entity(repositoryClass: StationEventRepository::class)]
#[ORM\Table(name: 'station_event')]
#[ORM\Index(name: 'idx_station_event_station_time', columns: ['station_id', 'occurred_at'])]
class StationEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    #[ORM\ManyToOne(targetEntity: Station::class)]
    #[ORM\JoinColumn(name: 'station_id', nullable: false, onDelete: 'CASCADE')]
    private ?Station $station = null;

    #[ORM\Column(length: 32, enumType: StationEventKind::class)]
    private ?StationEventKind $kind = null;

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

    public function getStation(): ?Station
    {
        return $this->station;
    }

    public function setStation(Station $station): static
    {
        $this->station = $station;

        return $this;
    }

    public function getKind(): ?StationEventKind
    {
        return $this->kind;
    }

    public function setKind(StationEventKind $kind): static
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
