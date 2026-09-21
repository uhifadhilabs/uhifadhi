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

use Doctrine\ORM\Mapping as ORM;
use Uhifadhi\Bundle\TeamBundle\Entity\Trait\TimestampableTrait;
use Uhifadhi\Bundle\TeamBundle\Entity\Trait\UuidTrait;
use Uhifadhi\Bundle\TeamBundle\Repository\PositionTitleRepository;

/**
 * WHAT A POSITION MAY BE CALLED — a word, available to every department.
 *
 * A TITLE GRANTS NOTHING. It is not a position and it is not a permission set:
 * it is the vocabulary a position is written with, so that two departments
 * spelling the same job two ways is a thing an organization can stop doing.
 * What a position may DO is the matrix, on Positions.
 *
 * IT IS SHARED ON PURPOSE, and that is the whole reason it is a row rather
 * than a column on the position: `Analyst` legitimately exists in Ecology and
 * in Protection Service as two different jobs with different permissions, and
 * nothing here may merge them — a shared word is not a shared post.
 *
 * WHETHER IT LEADS A STATION is part of the word, because that is a property
 * of the job and not of the person filling it. It is read by whoever draws a
 * board; the posting still records who actually leads, since a title that
 * leads and a person who leads are different facts.
 */
#[ORM\Entity(repositoryClass: PositionTitleRepository::class)]
#[ORM\Table(name: 'team_position_title')]
#[ORM\UniqueConstraint(name: 'uniq_team_position_title_name', fields: ['name'])]
#[ORM\HasLifecycleCallbacks]
class PositionTitle
{
    use TimestampableTrait;
    use UuidTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    /** Unique across the installation: a title is one word, offered everywhere. */
    #[ORM\Column(length: 80)]
    private string $name;

    #[ORM\Column]
    private bool $leadsStation = false;

    public function __construct(string $name = '', bool $leadsStation = false)
    {
        $this->name = $name;
        $this->leadsStation = $leadsStation;
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

    public function leadsStation(): bool
    {
        return $this->leadsStation;
    }

    public function setLeadsStation(bool $leadsStation): static
    {
        $this->leadsStation = $leadsStation;

        return $this;
    }
}
