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
use Uhifadhi\Bundle\AreaBundle\Repository\CheckInCorrectionRepository;

/**
 * WHAT BECAME TRUE LATER — a second claim on the same day, from its own
 * moment.
 *
 * A CORRECTION APPENDS AND NEVER REWRITES. A ranger who checked in at
 * post at 06:08 and left on an escort at 07:40 made two true statements,
 * not one mistaken one: the 06:08 claim stays exactly as it was said,
 * and this says what was true from 07:40. Folding it into the claim
 * would lose the morning.
 *
 * IT HAS ITS OWN CLIENT REF, so a retry from the handset's queue cannot
 * append the same correction twice — the same rule as the claim itself.
 *
 * @see API-CONTRACT.md §13B
 */
#[ORM\Entity(repositoryClass: CheckInCorrectionRepository::class)]
#[ORM\Table(name: 'duty_checkin_correction')]
#[ORM\UniqueConstraint(name: 'uniq_duty_correction_ref', columns: ['checkin_id', 'client_ref'])]
#[ORM\HasLifecycleCallbacks]
class CheckInCorrection
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    #[ORM\ManyToOne(targetEntity: CheckIn::class, inversedBy: 'corrections')]
    #[ORM\JoinColumn(name: 'checkin_id', nullable: false, onDelete: 'CASCADE')]
    private ?CheckIn $checkIn = null;

    #[ORM\Column(name: 'client_ref', length: 64)]
    private string $clientRef = '';

    /** When this became true — not when it was sent. */
    #[ORM\Column(name: 'effective_from', type: 'datetimetz_immutable')]
    private ?\DateTimeImmutable $effectiveFrom = null;

    #[ORM\ManyToOne(targetEntity: CheckInStatus::class)]
    #[ORM\JoinColumn(name: 'status_id', nullable: false, onDelete: 'RESTRICT')]
    private ?CheckInStatus $status = null;

    #[ORM\ManyToOne(targetEntity: Station::class)]
    #[ORM\JoinColumn(name: 'station_id', nullable: true, onDelete: 'SET NULL')]
    private ?Station $station = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCheckIn(): ?CheckIn
    {
        return $this->checkIn;
    }

    public function setCheckIn(CheckIn $checkIn): static
    {
        $this->checkIn = $checkIn;

        return $this;
    }

    public function getClientRef(): string
    {
        return $this->clientRef;
    }

    public function setClientRef(string $clientRef): static
    {
        $this->clientRef = $clientRef;

        return $this;
    }

    public function getEffectiveFrom(): ?\DateTimeImmutable
    {
        return $this->effectiveFrom;
    }

    public function setEffectiveFrom(\DateTimeImmutable $effectiveFrom): static
    {
        $this->effectiveFrom = $effectiveFrom;

        return $this;
    }

    public function getStatus(): ?CheckInStatus
    {
        return $this->status;
    }

    public function setStatus(CheckInStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getStation(): ?Station
    {
        return $this->station;
    }

    public function setStation(?Station $station): static
    {
        $this->station = $station;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }
}
