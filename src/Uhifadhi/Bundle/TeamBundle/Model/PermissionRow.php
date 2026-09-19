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

namespace Uhifadhi\Bundle\TeamBundle\Model;

/**
 * ONE PERMISSION ON THE ROLES TAB — both its names, and how far it actually
 * reaches.
 *
 * BOTH NAMES, ALWAYS: the catalogue's label and the machine value the voter
 * checks. An administrator who can only see one of them cannot match what a
 * screen refuses to what a position grants.
 *
 * THE TWO FIGURES ARE DIFFERENT QUESTIONS. How many POSITIONS carry it is the
 * shape of the matrix; how many PEOPLE hold it is who could act today, and a
 * permission on three positions nobody sits in reaches nobody at all.
 */
final readonly class PermissionRow
{
    public function __construct(
        public string $label,
        public string $value,
        public string $description,
        public int $positions,
        public int $people,
    ) {
    }
}
