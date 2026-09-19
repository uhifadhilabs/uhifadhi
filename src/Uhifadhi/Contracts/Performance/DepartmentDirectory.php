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
     * THE ROWS A MODULE'S MATRIX HAS: the departments that attach it AND
     * can be asked about it. A department that attaches the module in an
     * area nobody runs it in is not a row of empties — it is not a row.
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
