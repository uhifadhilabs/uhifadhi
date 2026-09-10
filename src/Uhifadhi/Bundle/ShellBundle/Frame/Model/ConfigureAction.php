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

namespace Uhifadhi\Bundle\ShellBundle\Frame\Model;

/**
 * THE ONE CONFIGURATION ENTRY A SURFACE HAS — the `Configure` action in the page
 * head, as data.
 *
 * It has two states and they are the same control, which is the whole point: on
 * a data page it opens the configure page; on the configure page it is LIT and
 * goes back to the surface's front door. A person therefore learns one control
 * instead of a Settings button here, a kinds link there and a "Back to
 * dashboard" somewhere else.
 *
 * THE FRAME RENDERS IT, NOT THE MODULE. It arrives at `page.html.twig` from the
 * same source the strip does, so a module writes no configuration button at all
 * — which is the only way "exactly one entry" can stay true across modules
 * nobody has written yet.
 */
final readonly class ConfigureAction
{
    /**
     * @param string $url     where the control goes: the configure page, or the
     *                        surface's front door when already configuring
     * @param bool   $current whether the viewer is on the configure page
     * @param string $hint    the title attribute, which differs by state
     */
    public function __construct(
        public string $url,
        public bool $current = false,
        public string $hint = '',
    ) {
    }
}
