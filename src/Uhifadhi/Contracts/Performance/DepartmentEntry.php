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

namespace Uhifadhi\Contracts\Performance;

/**
 * ONE DEPARTMENT, AS A TOPIC NEEDS TO KNOW IT: who it is, what it is
 * placed among, what it attaches, and since when each of those modules
 * has actually been running where it can see them.
 *
 * THE LAST PART IS THE ONE THAT IS EASY TO MISS. A department attaching
 * Patrols is not the same as a department that can be asked about
 * patrols: the module has to be switched on in an area the department
 * reads. Where it is not, the cell is `notMine` — not an empty figure,
 * and certainly not a nought.
 *
 * AND `runningSince` DATES THE HOLES. A module switched on in March has
 * nothing to say about February, and a sparkline that drew a gap there
 * is telling the truth; one that drew a nought would be inventing a
 * quiet month.
 */
final readonly class DepartmentEntry
{
    /**
     * @param list<string>                           $attached     the module slugs this department attaches
     * @param array<string, \DateTimeImmutable|null> $runningSince slug to when the first area this department reads switched it on; null where no area it reads runs it at all
     */
    public function __construct(
        public string $uuid,
        public string $name,
        /** Null for an organisation-wide department. */
        public ?string $areaUuid,
        /** "Org-wide", or the area's name — what this row is placed among. */
        public string $band,
        public array $attached = [],
        public array $runningSince = [],
        /** The two letters every surface draws it by. */
        public string $mark = '',
    ) {
    }

    /** Whether this department attaches the module at all. */
    public function attaches(string $slug): bool
    {
        return \in_array($slug, $this->attached, true);
    }

    /**
     * WHETHER THIS MODULE IS A QUESTION THIS DEPARTMENT CAN BE ASKED.
     *
     * False in both of the ways that matter: it attaches nothing of the
     * kind, or it attaches it and no area it reads is running it. A topic
     * draws `MatrixCell::notMine()` for the second exactly as for the
     * first, because in both the department was never asked.
     */
    public function canAnswerFor(string $slug): bool
    {
        return $this->attaches($slug) && null !== ($this->runningSince[$slug] ?? null);
    }
}
