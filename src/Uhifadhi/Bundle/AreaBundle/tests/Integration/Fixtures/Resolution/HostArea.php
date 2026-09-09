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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration\Fixtures\Resolution;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Uhifadhi\Contracts\Entity\AreaInterface;

/**
 * AN INSTALLATION THAT DISAGREES, played by a fixture.
 *
 * The rule this suite is about is "whoever knows the answer states the
 * resolution", and its second half is that an installation may know better: a
 * deployment whose areas are its own entity — its own columns, its own history,
 * its own name for the thing — names that class in its own config and wins,
 * because prepended configuration loses to the application's.
 *
 * So this class exists only to be named. It answers the area contract and
 * nothing else, which is all the platform ever asked for — the identity the
 * association is built on, plus the name and public uuid the contract now
 * carries.
 */
#[ORM\Entity]
#[ORM\Table(name: 'host_area')]
#[ORM\HasLifecycleCallbacks]
class HostArea implements AreaInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $uuid = null;

    #[ORM\Column(length: 120)]
    private string $name = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getUuidString(): ?string
    {
        return $this->uuid?->toRfc4122();
    }

    #[ORM\PrePersist]
    public function generateUuid(): void
    {
        $this->uuid ??= Uuid::v7();
    }
}
