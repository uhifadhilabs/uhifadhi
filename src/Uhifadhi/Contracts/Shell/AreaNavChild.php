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
        /** The row's own dot colour as a hex value, when it has one. */
        public ?string $swatch = null,
    ) {
    }
}
