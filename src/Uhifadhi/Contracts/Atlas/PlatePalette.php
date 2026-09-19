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

namespace Uhifadhi\Contracts\Atlas;

/**
 * WHAT A MODULE MAY COLOUR A MAP LAYER WITH — token NAMES, never values.
 *
 * A MODULE DOES NOT KNOW WHAT GREEN IS HERE, and on imagery it is not
 * even the green the rest of the product uses: a plate's palette is
 * picked to survive satellite ground, so `--plate-ok` and `--c-ok` are
 * two different colours on purpose. A module that published `#3ED9A8`
 * would be right on today's basemap and wrong the day the imagery or
 * the theme moved, and nothing would tell it.
 *
 * SO THE NAME CROSSES THE SEAM AND THE VALUE NEVER DOES. The shell
 * declares the tokens, the plate resolves them in the browser at the
 * moment it draws — and again when the theme flips — and a module says
 * only which of them it means.
 *
 * THESE ARE THE SEMANTIC FIVE. A category — an incident kind, a patrol
 * type, a zone — is NOT one of them: a category takes its position in
 * its own declared order, 1 to 9, and the host resolves that to
 * `--cat-n`. Reach for one of these when the layer MEANS something
 * ("this one is in trouble"), and for a category when it is simply one
 * of a set.
 */
final readonly class PlatePalette
{
    /** The subject of the plate — the thing the reader came to see. */
    public const string ACCENT = 'var(--plate-acc)';

    /** Good: done, closed, within target. */
    public const string OK = 'var(--plate-ok)';

    /** Wants looking at, and has not gone wrong yet. */
    public const string WARN = 'var(--plate-warn)';

    /** Went wrong. */
    public const string FAIL = 'var(--plate-fail)';

    /** Context: present, and not what the reader is here for. */
    public const string DIM = 'var(--plate-dim)';

    /** Every name a layer may be coloured with. */
    public const array NAMES = [self::ACCENT, self::OK, self::WARN, self::FAIL, self::DIM];

    /**
     * WHETHER A SWATCH IS A NAME AND NOT A VALUE — what the value
     * objects refuse a literal with.
     *
     * Any `var(--…)` passes rather than only the five above: the nine
     * categories resolve to `var(--cat-n)` through the same door, and
     * an installation may declare a token of its own. What is refused
     * is a HEX — a colour a module decided.
     */
    public static function isToken(string $swatch): bool
    {
        return 1 === preg_match('/^var\(--[a-z0-9-]+\)$/', $swatch);
    }

    /**
     * THE TOKEN A CATEGORY'S POSITION RESOLVES TO ON A PLATE, 1 to 9.
     *
     * THE PLATE READING, `--cat-p-n`, AND NOT `--cat-n`. Imagery is dark
     * under both themes, so a plate has its own reading of the nine; the
     * shell swaps the two for anything wearing `[data-cat]` inside a
     * `.viewer`, but a colour handed to Leaflet is resolved in
     * JavaScript and never passes through that rule. Naming the plate's
     * own token is how a ring on the map matches its row in the key.
     *
     * A POSITION BEYOND NINE IS THE CALLER'S TO WRAP, exactly as the
     * shell's own category seams require — and what should happen to the
     * tenth is a question for the owner, not a default invented here.
     */
    public static function category(int $position): string
    {
        if ($position < 1 || $position > 9) {
            throw new \InvalidArgumentException(\sprintf('A category is its position in a declared order, 1 to 9; "%d" is not one. An order beyond nine wraps to 1, and that is the caller\'s to do.', $position));
        }

        return \sprintf('var(--cat-p-%d)', $position);
    }
}
