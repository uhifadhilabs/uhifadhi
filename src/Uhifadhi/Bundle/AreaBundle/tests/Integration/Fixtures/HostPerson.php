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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Uhifadhi\Contracts\Entity\UserInterface;

/**
 * THE INSTALLATION'S END OF THE USER CONTRACT, played by a fixture.
 *
 * A POSTING POINTS AT A PERSON, and who a person IS belongs to another bundle
 * — which this kernel deliberately does not install. So the contract is
 * answered here, exactly as an installation answers it with its own account
 * class under `resolve_target_entities`, and the suite proves the area can
 * hold a posting without depending on the team bundle to do it.
 *
 * It answers the published contract and nothing more. Anything beyond it would
 * be this suite testing an account rather than a posting.
 */
#[ORM\Entity]
#[ORM\Table(name: 'fixture_host_person')]
class HostPerson implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    #[ORM\Column(length: 36, unique: true)]
    private string $uuid;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(length: 80)]
    private string $firstName = '';

    #[ORM\Column(length: 80)]
    private string $lastName = '';

    /** Never set by this fixture: the contract allows it and nothing here needs one. */
    #[ORM\Column(length: 32, nullable: true)]
    private ?string $rangerCode = null; // @phpstan-ignore property.unusedType (the contract's own nullable shape)

    public function __construct()
    {
        $this->uuid = Uuid::v7()->toRfc4122();
        $this->email = $this->uuid.'@example.org';
    }

    public function named(string $first, string $last): static
    {
        $this->firstName = $first;
        $this->lastName = $last;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuidString(): ?string
    {
        return $this->uuid;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function getFullName(): string
    {
        return trim($this->firstName.' '.$this->lastName);
    }

    public function getRangerCode(): ?string
    {
        return $this->rangerCode;
    }
}
