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
use Uhifadhi\Bundle\AreaBundle\Enum\PostingSource;
use Uhifadhi\Bundle\AreaBundle\Repository\PostingRepository;
use Uhifadhi\Contracts\Entity\UserInterface;

/**
 * A PERSON WORKS OUT OF A STATION. That is the whole of it.
 *
 * PEOPLE ARE NOT POSTED TO ZONES, and the model refuses to let anybody try. A
 * zone is ground and a station is a place with a door; the area can say who
 * covers where only because a station sits inside a zone by geometry and a
 * person is posted to the station. Two joins, each a fact somebody recorded,
 * and neither kept in step by hand.
 *
 * THE PERSON IS THE PUBLISHED CONTRACT, not somebody's class. An installation
 * whose people are its own entity names it under `resolve_target_entities`
 * and this mapping follows, which is the whole reason the area can hold a
 * posting without depending on the team bundle.
 *
 * IT IS A RELATION AND NOT A STRING, unlike {@see ZoneImport}'s record of who
 * imported a file, and the difference is what the row is FOR. Provenance
 * records who did a thing once and must survive them leaving; a posting is a
 * live statement about who works somewhere, read on their page and on the
 * station's, with their name and their face on it. A string would be a name
 * that goes stale the day somebody marries.
 *
 * A POSTING ENDS, IT IS NOT DELETED. Who was posted where last year is how
 * last year's patrol has a crew, so `endedAt` takes a row out of the standing
 * set and nothing takes it out of the table.
 *
 * ONE POSTING A PERSON, AMONG THE STANDING ONES — ruled: one station, one
 * area. A posting is where somebody WORKS and they work in one place, so
 * moving them is two acts and not one: end the posting they have, make the
 * one they are going to. Enforced in
 * {@see \Uhifadhi\Bundle\AreaBundle\Service\PostingService::post()}, for
 * the same reason as the rule below — what expresses it is a partial unique
 * index (`WHERE ended_at IS NULL`) the ORM mapping cannot declare.
 *
 * ONE LEADER PER STATION, AMONG THE STANDING POSTINGS — enforced by
 * {@see \Uhifadhi\Bundle\AreaBundle\Service\PostingService}, which is the only
 * supported way this column is written. It is not a database constraint
 * because the one that would express it is a PARTIAL unique index
 * (`WHERE leader AND ended_at IS NULL`) and the ORM mapping cannot declare
 * one, so an index added by hand is an index `migrations:diff` removes on the
 * next run. The rule is therefore a transaction and a test; tightening it to
 * a database constraint is named in the bundle's design decisions.
 */
#[ORM\Entity(repositoryClass: PostingRepository::class)]
#[ORM\Table(name: 'posting')]
#[ORM\HasLifecycleCallbacks]
class Posting
{
    use TimestampableTrait;
    use UuidTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    /** The posting lives and dies with its station — a post that is removed had nobody at it. */
    #[ORM\ManyToOne(targetEntity: Station::class)]
    #[ORM\JoinColumn(name: 'station_id', nullable: false, onDelete: 'CASCADE')]
    private ?Station $station = null;

    /**
     * WHOSE POSTING IT IS. Removing the account takes the postings with it:
     * a posting is a statement about a person, and a statement about nobody is
     * not a smaller statement.
     */
    #[ORM\ManyToOne(targetEntity: UserInterface::class)]
    #[ORM\JoinColumn(name: 'person_id', nullable: false, onDelete: 'CASCADE')]
    private ?UserInterface $person = null;

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $since = null;

    /** Null is STANDING. A date is the day it stopped, and the row stays. */
    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;

    #[ORM\Column]
    private bool $leader = false;

    #[ORM\Column(length: 16, enumType: PostingSource::class)]
    private ?PostingSource $source = null;

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

    public function getPerson(): ?UserInterface
    {
        return $this->person;
    }

    public function setPerson(UserInterface $person): static
    {
        $this->person = $person;

        return $this;
    }

    public function getSince(): ?\DateTimeImmutable
    {
        return $this->since;
    }

    public function setSince(\DateTimeImmutable $since): static
    {
        $this->since = $since;

        return $this;
    }

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function setEndedAt(?\DateTimeImmutable $endedAt): static
    {
        $this->endedAt = $endedAt;

        return $this;
    }

    public function isStanding(): bool
    {
        return null === $this->endedAt;
    }

    public function isLeader(): bool
    {
        return $this->leader;
    }

    public function setLeader(bool $leader): static
    {
        $this->leader = $leader;

        return $this;
    }

    public function getSource(): ?PostingSource
    {
        return $this->source;
    }

    public function setSource(PostingSource $source): static
    {
        $this->source = $source;

        return $this;
    }
}
