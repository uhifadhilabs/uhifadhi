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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Widget\Integration\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface as SecurityUserInterface;
use Uhifadhi\Contracts\Entity\UserInterface;

/**
 * THE INSTALLATION'S PEOPLE, PLAYED BY A STAND-IN.
 *
 * The shell stores a dashboard layout against a person and points the
 * association at {@see UserInterface}, which it does not resolve: whoever owns
 * the real account class states the resolution, and in a running installation
 * that is TeamBundle. The shell must never require it — Team depends on the
 * shell, not the other way round — so this suite plays the installation's end
 * of that resolution itself.
 *
 * It is a stub in the sanctioned sense: it impersonates nobody's FQCN, it
 * implements the published contract and nothing more, and it exists only in
 * autoload-dev. Every question the contract asks, it answers; anything it
 * answered beyond them would be this suite testing a person's account rather
 * than a stored layout.
 */
#[ORM\Entity]
#[ORM\Table(name: 'host_user')]
class HostUser implements UserInterface, SecurityUserInterface, PasswordAuthenticatedUserInterface
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

    #[ORM\Column(length: 255)]
    private string $password = '';

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

    /**
     * @param non-empty-string $email
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->firstName.' '.$this->lastName);
    }

    public function getRangerCode(): ?string
    {
        return null;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return list<string>
     */
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
