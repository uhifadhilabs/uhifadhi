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
 * THE DEPARTMENTS A TOPIC IS ABOUT, ANSWERED ONCE FOR A WHOLE PAGE.
 *
 * ONE READ, NOT TWO. A topic needs who the departments are, what each
 * attaches and since when each module has been running where they can
 * see it — and those live in two bundles. Handing a module two seams
 * would make every module write the same join, and the second module
 * would write it differently.
 */
final readonly class DepartmentDirectory
{
    /**
     * @param list<DepartmentEntry> $entries in the order the surfaces read them
     */
    public function __construct(public array $entries = [])
    {
    }

    /**
     * THE ROWS A MODULE'S MATRIX HAS: the departments that ATTACH it.
     *
     * ATTACHING IS WHAT MAKES A ROW. A department that attached the
     * module said this is work it leads for; leaving it off the matrix
     * because no area it reads is running the module yet would hide the
     * very department somebody is about to ask about. It is a row, and
     * its cells are {@see MatrixCell::notMine()} until the module is
     * running somewhere it can see.
     *
     * A DEPARTMENT THAT ATTACHES NOTHING OF THE KIND is not a row at
     * all: the module is not its work, and a dash across a whole row
     * would be the old board's mistake in miniature.
     *
     * @return list<DepartmentEntry>
     */
    public function attaching(string $slug): array
    {
        return array_values(array_filter(
            $this->entries,
            static fn (DepartmentEntry $entry): bool => $entry->attaches($slug),
        ));
    }

    /**
     * THE ROWS WHOSE CELLS ARE REAL — those that attach the module AND
     * can be asked about it, which is the set a module actually computes
     * figures for.
     *
     * Not the matrix's rows: {@see attaching()} is. This is what a
     * provider loops over when it queries, and what a page counts when
     * it says how many departments are measuring.
     *
     * @return list<DepartmentEntry>
     */
    public function answeringFor(string $slug): array
    {
        return array_values(array_filter(
            $this->entries,
            static fn (DepartmentEntry $entry): bool => $entry->canAnswerFor($slug),
        ));
    }

    /**
     * THE BANDS, IN ORDER — org-wide first, then each area. A tint is a
     * placing inside one band, so this is what a matrix groups by.
     *
     * @return list<string>
     */
    public function bands(): array
    {
        $bands = [];
        foreach ($this->entries as $entry) {
            if (!\in_array($entry->band, $bands, true)) {
                $bands[] = $entry->band;
            }
        }

        return $bands;
    }
}
