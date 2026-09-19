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

namespace Uhifadhi\Bundle\ShellBundle\Model;

/**
 * A ROW IN THE SIDEBAR, and possibly a branch of it.
 *
 * A value object on purpose, and the reason the shell can draw the contract's
 * answers without depending on the registry: there is nothing on this class to
 * branch on that is not also on every other row. The moment a domain entity is
 * in scope inside a Twig file, somebody writes `{% if row.entity.slug == … %}`
 * and the module-blindness the platform promises is gone.
 *
 * `url` is nullable and that is a product decision, not laziness: a surface
 * whose route has not merged yet renders visible, dimmed and inert, so the
 * product says "this is planned" instead of pretending it was never planned.
 * Compare {@see AreaTab}, where the opposite rule holds and for a stated reason.
 */
final class NavItem
{
    /**
     * `tone` is the module row's identity-dot class, and it is a presentation
     * passthrough exactly like `icon`: an opaque string the shell prints onto the
     * dot (`<i class="mdot {tone}">`) and never interprets. A module carries its
     * own hue the map-legend way — it declares `.ntree .ntm .mdot.<tone>` in its
     * own stylesheet — and the source hands the shell that class name. Null keeps
     * the shell's jade default, which is exactly what the design gives a module
     * with no colour of its own. The shell names no module either way.
     *
     * `swatch` IS THE OTHER WAY TO COLOUR THAT DOT, for a row whose hue is a
     * VALUE rather than a class. A module's colour is fixed and can be one rule
     * in that module's stylesheet; a zone's comes from a palette by its position
     * in its area's set, so there is no class to declare and no sheet that could
     * know how many zones an installation will have. Declaring them as classes
     * would put the palette in two places, and the plate, the key and the cards
     * already read it from one.
     *
     * The shell interprets neither: one is printed into `class`, the other into
     * `style`. It is checked on the way in — see the constructor — because the
     * one thing a `style` passthrough must not become is a hole to write CSS
     * through.
     *
     * @param string      $label    what the row says
     * @param string|null $url      where it goes, or null for an inert row
     * @param string|null $icon     a ux-icons name, e.g. "shell:map"
     * @param string|null $hint     the title attribute — why a row is inert
     * @param bool        $current  whether the viewer is here — accented on the
     *                              row they are on, and drawn quieter on the
     *                              place rung they are inside (see
     *                              `_nav_item.html.twig`, which spends the accent
     *                              once per sidebar)
     * @param bool        $open     whether this row's children are unfolded
     * @param list<self>  $children the branch under this row, if any
     * @param string|null $tone     a module row's dot class, or null for jade
     * @param string|null $swatch   a row's own dot colour, as a hex value or as
     *                              the palette token the host resolved a category
     *                              into; null when it has none
     */
    public function __construct(
        public string $label,
        public ?string $url = null,
        public ?string $icon = null,
        public ?string $hint = null,
        public bool $current = false,
        public bool $open = true,
        public array $children = [],
        public ?string $tone = null,
        public ?string $swatch = null,
    ) {
        /*
         * ONLY A COLOUR. The value is printed into a `style` attribute, so
         * anything else a source handed over would be CSS written into the page
         * through a hole the shell opened for it. Checked here rather than
         * escaped at the template, because a row that cannot be drawn is a
         * mistake in the source and should fail where it was made.
         */
        /*
         * A HEX, OR ONE OF THE PALETTE'S OWN CATEGORY TOKENS. The second is
         * what the host writes when a contributor handed it a CATEGORY rather
         * than a colour: a module never knows what green is here — the palette
         * turns over with the theme and again on imagery — so it says "the
         * third category" and the resolution happens on this side.
         */
        if (null !== $swatch
            && 1 !== preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $swatch)
            && 1 !== preg_match('/^var\\(--cat-[1-9]\\)$/', $swatch)
        ) {
            throw new \InvalidArgumentException(\sprintf('A nav row\'s swatch is a hex colour or a category token the shell prints onto its dot; "%s" is neither.', $swatch));
        }
    }
}
