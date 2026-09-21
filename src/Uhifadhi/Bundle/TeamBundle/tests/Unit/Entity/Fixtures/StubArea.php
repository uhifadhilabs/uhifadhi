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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Unit\Entity\Fixtures;

use Uhifadhi\Contracts\Entity\AreaInterface;

/**
 * A PIECE OF GROUND, answering the two questions the contract asks and
 * nothing else - which is all a placement ever needs from an area.
 *
 * It is a stub rather than the real entity because the area bundle owns
 * that one and a unit test should not need a kernel to ask whether a
 * placement covers a piece of ground.
 */
final readonly class StubArea implements AreaInterface
{
    public function __construct(
        private ?int $id,
        private ?string $uuid,
        private ?string $name = 'An area',
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getUuidString(): ?string
    {
        return $this->uuid;
    }
}
