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
use Uhifadhi\Bundle\AreaBundle\Enum\PositionSourceEnum;
use Uhifadhi\Bundle\AreaBundle\Repository\PersonPositionRepository;
use Uhifadhi\Contracts\Entity\UserInterface;

/**
 * ONE DUTY PING — where a phone was, once, during a watch.
 *
 * THE PROOF, NOT THE CLAIM. The check-in is what somebody said; these
 * are what the device reported while the watch was open. Nothing here
 * is a judgement: whether the pings bear the claim out is computed on
 * read, from the post's catchment, and a catchment corrected next month
 * re-derives every day that used it.
 *
 * APPEND-ONLY, AND KEYED BY THE HANDSET. A ping has the client's own
 * reference and is accepted once, so an offline stretch that uploads a
 * hundred pings twice stores a hundred. Nothing edits a ping and nothing
 * deletes one.
 *
 * THE SOURCE IS KEPT because it changes what the fix is worth: a
 * satellite fix and a cell-tower estimate are both positions, and only
 * one of them says anything about standing inside a ring two hundred
 * metres across.
 *
 * @see API-CONTRACT.md §13C
 */
#[ORM\Entity(repositoryClass: PersonPositionRepository::class)]
#[ORM\Table(name: 'duty_position')]
#[ORM\UniqueConstraint(name: 'uniq_duty_position_ref', columns: ['area_id', 'client_ref'])]
#[ORM\Index(name: 'idx_duty_position_watch', columns: ['checkin_id', 'recorded_at'])]
#[ORM\HasLifecycleCallbacks]
class PersonPosition
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    #[ORM\ManyToOne(targetEntity: AreaOfInterest::class)]
    #[ORM\JoinColumn(name: 'area_id', nullable: false, onDelete: 'CASCADE')]
    private ?AreaOfInterest $area = null;

    #[ORM\ManyToOne(targetEntity: UserInterface::class)]
    #[ORM\JoinColumn(name: 'person_id', nullable: false, onDelete: 'CASCADE')]
    private ?UserInterface $person = null;

    /**
     * THE WATCH THIS PING BELONGS TO. A ping with no watch is never
     * produced by the handset, and one that arrived without a claim
     * would be a position nobody asked for.
     */
    #[ORM\ManyToOne(targetEntity: CheckIn::class)]
    #[ORM\JoinColumn(name: 'checkin_id', nullable: false, onDelete: 'CASCADE')]
    private ?CheckIn $checkIn = null;

    #[ORM\Column(name: 'client_ref', length: 64)]
    private string $clientRef = '';

    #[ORM\Column(name: 'recorded_at', type: 'datetimetz_immutable')]
    private ?\DateTimeImmutable $recordedAt = null;

    #[ORM\Column(type: 'point')]
    private ?string $position = null;

    #[ORM\Column(name: 'accuracy_m')]
    private float $accuracyM = 0.0;

    /** What the phone had left, where it said. */
    #[ORM\Column(name: 'battery_pct', nullable: true)]
    private ?int $batteryPct = null;

    #[ORM\Column(length: 16, enumType: PositionSourceEnum::class)]
    private PositionSourceEnum $source = PositionSourceEnum::Gps;

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

    public function getPerson(): ?UserInterface
    {
        return $this->person;
    }

    public function setPerson(UserInterface $person): static
    {
        $this->person = $person;

        return $this;
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

    public function getRecordedAt(): ?\DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function setRecordedAt(\DateTimeImmutable $recordedAt): static
    {
        $this->recordedAt = $recordedAt;

        return $this;
    }

    public function getPosition(): ?string
    {
        return $this->position;
    }

    public function setPosition(string $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getAccuracyM(): float
    {
        return $this->accuracyM;
    }

    public function setAccuracyM(float $accuracyM): static
    {
        $this->accuracyM = $accuracyM;

        return $this;
    }

    public function getBatteryPct(): ?int
    {
        return $this->batteryPct;
    }

    public function setBatteryPct(?int $batteryPct): static
    {
        $this->batteryPct = $batteryPct;

        return $this;
    }

    public function getSource(): PositionSourceEnum
    {
        return $this->source;
    }

    public function setSource(PositionSourceEnum $source): static
    {
        $this->source = $source;

        return $this;
    }
}
