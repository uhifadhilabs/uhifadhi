<?php

declare(strict_types=1);

/*
 * This file is part of the Uhifadhi core.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
namespace Uhifadhi\Bundle\TeamBundle\Model;

/**
 * SOMEBODY STANDING IN A POSITION, as the holders list reads them: the name,
 * where they stand, and since when.
 *
 * READ-ONLY WHEREVER IT IS DRAWN. A position is given on the person's own
 * record, so every one of these is a door to that record and nothing here
 * changes who holds what.
 */
final readonly class HolderRow
{
    public function __construct(
        public string $uuid,
        public string $name,
        public string $initials,
        public string $where,
        public ?\DateTimeImmutable $since,
    ) {
    }
}
