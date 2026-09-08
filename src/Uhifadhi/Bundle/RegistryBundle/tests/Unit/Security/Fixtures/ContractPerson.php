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

namespace Uhifadhi\Bundle\RegistryBundle\Tests\Unit\Security\Fixtures;

use Uhifadhi\Contracts\Entity\UserInterface;

/**
 * A person made of nothing but the seven answers the user contract asks for.
 *
 * Contracts-level on purpose: what stands behind the resolver has to be
 * provable without an account entity, a database, or the package that keeps
 * people — that independence is the thing under test.
 */
final readonly class ContractPerson implements UserInterface
{
    public function __construct(
        private ?int $id = 1,
        private ?string $uuid = '01926f3e-0000-7000-8000-000000000001',
        private ?string $email = 'w.mbise@example.test',
        private ?string $firstName = 'Witness',
        private ?string $lastName = 'Mbise',
        private ?string $rangerCode = 'sl-0142',
    ) {
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
        return trim(($this->firstName ?? '').' '.($this->lastName ?? ''));
    }

    public function getRangerCode(): ?string
    {
        return $this->rangerCode;
    }
}
