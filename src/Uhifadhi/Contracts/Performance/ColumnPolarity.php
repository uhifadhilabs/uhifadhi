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
 * WHICH WAY IS GOOD, FOR ONE COLUMN.
 *
 * A MATRIX TINTS A PLACING AND COLOURS A MOVEMENT, and both are nonsense
 * without this: more patrols is better, more incidents is worse, and more
 * positions is neither — it is the size of the department. A column that
 * did not say would have the host guessing from its label, which gets it
 * wrong the first time a module publishes "days to settle".
 *
 * NONE IS NOT A MISSING ANSWER. A column with no polarity is never tinted
 * and its delta is drawn without colour: the figure moved, and the page
 * makes no claim about whether that is good.
 */
enum ColumnPolarity: string
{
    /** Higher is better. */
    case Up = 'up';

    /** Lower is better. */
    case Down = 'down';

    /** Neither — a size, a count of things that simply are. */
    case None = 'none';

    /** Whether a movement in this column may be coloured at all. */
    public function judges(): bool
    {
        return self::None !== $this;
    }

    /**
     * Whether a change of this sign is an improvement. Null where the
     * column makes no claim.
     */
    public function isGood(float $delta): ?bool
    {
        return match ($this) {
            self::Up => $delta > 0,
            self::Down => $delta < 0,
            self::None => null,
        };
    }
}
