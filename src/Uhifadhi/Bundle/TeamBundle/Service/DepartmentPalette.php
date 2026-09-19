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

use Uhifadhi\Bundle\TeamBundle\Repository\DepartmentRepository;

/**
 * WHICH CATEGORY EACH DEPARTMENT IS — said once, so every surface that marks
 * one marks it the same.
 *
 * A DEPARTMENT NAMES A CATEGORY, NEVER A COLOUR. The nine `--dept-<slug>`
 * tokens are aliases of the palette's categorical nine and the shell resolves
 * `data-cat` to the hue; this bundle hands over an INDEX, because a hex is
 * right in one theme and wrong in the other, and wrong again on imagery.
 *
 * THE INDEX IS THE POSITION IN THE REGISTER'S OWN ORDER, over every
 * department and not only the active ones: a hue that changed the day
 * somebody wound a department down would make the tree and the card disagree
 * about the same thing on the same afternoon.
 *
 * BEYOND NINE IT WRAPS, which the palette says is the caller's to do. An
 * installation with more than nine departments repeats a hue rather than
 * drawing a tenth colour nobody chose — a category mark is a hint, and two
 * departments sharing one is a smaller lie than a colour outside the palette.
 */
final readonly class DepartmentPalette
{
    /** The categorical set is nine, and so is this. */
    public const int CATEGORIES = 9;

    public function __construct(
        private DepartmentRepository $departments,
    ) {
    }

    /**
     * Every department's category index, keyed by uuid.
     *
     * @return array<string, int>
     */
    public function indexes(): array
    {
        $indexes = [];
        $position = 0;
        foreach ($this->departments->findAllOrdered() as $department) {
            $uuid = $department->getUuidString();
            if (null !== $uuid) {
                $indexes[$uuid] = $position % self::CATEGORIES + 1;
                ++$position;
            }
        }

        return $indexes;
    }

    /**
     * THE PALETTE TOKEN A ROW'S DOT IS PAINTED WITH — the one form the shell
     * accepts beside a hex, and the one that survives the theme turning over.
     */
    public static function token(int $index): string
    {
        return \sprintf('var(--cat-%d)', $index);
    }
}
