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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration\Web\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface as SecurityUserInterface;
use Uhifadhi\Contracts\Entity\UserInterface;

/**
 * THE INSTALLATION'S PEOPLE, PLAYED BY A STAND-IN.
 *
 * The shell keeps a dashboard layout per person and points the association at
 * {@see UserInterface}, which it does not resolve: whoever owns the real
 * account class states the resolution, and in a running installation that is
 * TeamBundle. This bundle must not require Team — the two are siblings and
 * nothing here reads a person — so the kernel that renders these screens plays
 * the installation's end of that resolution itself, which is exactly what an
 * installation whose people are its own entity does.
 *
 * It answers the published contract and nothing more: anything beyond it would
 * be this suite testing an account rather than an area's pages.
 *
 * IT IS ALSO THE PRINCIPAL. Two user interfaces meet on a screen — Symfony's,
 * "who is signed in", and the contracts', "whose record is this" — and an
 * installation's account class satisfies both, which is exactly what makes a
 * widget layout storable for whoever is looking. So the stand-in satisfies both
 * too; playing only half of it would be this suite proving something no real
 * installation is.
 */
#[ORM\Entity]
#[ORM\Table(name: 'fixture_host_user')]
class HostUser implements UserInterface, SecurityUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    #[ORM\Column(length: 36, unique: true)]
    private string $uuid;

    /** @var non-empty-string */
    #[ORM\Column(length: 180, unique: true)]
    private string $email = 'nobody@example.org';

    #[ORM\Column(length: 80)]
    private string $firstName = '';

    #[ORM\Column(length: 80)]
    private string $lastName = '';

    public function __construct()
    {
        $this->uuid = bin2hex(random_bytes(16));
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
        return null;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }
}
