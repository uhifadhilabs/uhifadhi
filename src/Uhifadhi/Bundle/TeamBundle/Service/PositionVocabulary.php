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

namespace Uhifadhi\Bundle\TeamBundle\Service;

use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Repository\PositionRepository;

/**
 * THE WORDS THIS INSTALLATION WRITES ITS POSITIONS WITH, and there is one
 * list of them.
 *
 * A POSITION'S NAME IS UNIQUE ACROSS THE ORGANIZATION. It used to be unique
 * only inside a department, so the same word twice was legal and this service
 * existed largely to NAME the collisions - `Analyst` in Ecology and `Analyst`
 * in Protection were two jobs sharing a word, and an organization had to be
 * shown that it meant it. A position belongs to no department now, so there
 * is one Analyst, the collision cannot be written, and what is left is the
 * flat vocabulary itself.
 */
final readonly class PositionVocabulary
{
    public function __construct(
        private PositionRepository $positions,
    ) {
    }

    /**
     * @return array{names: list<string>, positions: int}
     */
    public function read(): array
    {
        $positions = $this->positions->findAllOrdered();

        return [
            'names' => array_map(static fn (Position $p): string => (string) $p->getName(), $positions),
            'positions' => \count($positions),
        ];
    }
}
