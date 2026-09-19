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

namespace Uhifadhi\Contracts\Area;

/**
 * ONE SECTION A MODULE PUTS ON A STATION — its heading, and the template
 * that fills it.
 *
 * THE AREA DRAWS THE BAND; THE MODULE DRAWS WHAT IS IN IT. The card, the
 * heading row, the contributor tag beside the heading and the summary line
 * are the surface's chrome and are written once, by the bundle that owns
 * the page. A section that drew its own would be a second copy of a card
 * idiom that then drifts from the one beside it — which is exactly how a
 * restated shell selector goes wrong. So a contributor names a heading, a
 * line of context, the actions it wants offered, and a template that writes
 * rows and nothing around them.
 *
 * THE HEADING IS THE MODULE'S WORD. "Watch and presence" is the roster's
 * phrase; the area prints it and has no vocabulary of its own to impose.
 *
 * THE ID IS AN ANCHOR. The record page gives the band `id="<id>"`, so a
 * module can link somebody straight to its own section of somebody else's
 * page.
 */
final readonly class StationSection
{
    /**
     * @param string               $id        stable within one module, e.g. "watch" — the band's anchor
     * @param string               $label     the heading, in the module's own words
     * @param string               $template  the twig the surface includes inside the band
     * @param array<string, mixed> $variables what that template is given, and all it is given
     * @param string|null          $summary   one line of context beside the heading, or null
     * @param list<StationAction>  $actions   what the band offers, drawn as the surface's own buttons
     */
    public function __construct(
        public string $id,
        public string $label,
        public string $template,
        public array $variables = [],
        public ?string $summary = null,
        public array $actions = [],
    ) {
        if ('' === trim($id)) {
            throw new \InvalidArgumentException('A station section is addressed by its id: it cannot be empty.');
        }

        if ('' === trim($label)) {
            throw new \InvalidArgumentException(\sprintf('The "%s" station section says nothing in its heading: it cannot have an empty label.', $id));
        }

        if ('' === trim($template)) {
            throw new \InvalidArgumentException(\sprintf('The "%s" station section names no template, so the surface has nothing to render for it.', $id));
        }
    }
}
