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

namespace Uhifadhi\Contracts\Shell;

/**
 * ONE ROW A BUNDLE HANGS UNDER AN AREA'S SCREEN IN THE SIDEBAR.
 *
 * A VALUE, NOT THE SHELL'S OWN ROW. The shell's `NavItem` is the shell's, and
 * a bundle that contributed one would have to depend on the shell to say the
 * name of a department. This carries the four things a contributed rung
 * actually needs, and whoever draws the tree turns it into the shell's row.
 */
final readonly class AreaNavChild
{
    public function __construct(
        /** What the row reads. */
        public string $label,
        /** Where it goes. */
        public string $url,
        /** Whether the viewer is on it — the contributor knows its own pages. */
        public bool $current = false,
        /**
         * THE CATEGORY THIS ROW WEARS — 1 to 9, its position in its own
         * declared order, and never a colour.
         *
         * A MODULE DOES NOT KNOW WHAT GREEN IS HERE. The product has one
         * palette, the host owns it, and it turns over with the theme and
         * again on imagery; a hex handed across this seam would be right
         * in one of those three and wrong in the other two. The host
         * resolves the index — see the shell's `[data-cat]`.
         *
         * NULL IS THE NORMAL CASE: most rows are not categorical and draw
         * the muted dot.
         */
        public ?int $cat = null,
    ) {
        if (null !== $cat && ($cat < 1 || $cat > 9)) {
            throw new \InvalidArgumentException(\sprintf('A category is its position in a declared order, 1 to 9; "%d" is not one. An order beyond nine wraps to 1, and that is the caller\'s to do.', $cat));
        }
    }
}
