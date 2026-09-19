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

namespace Uhifadhi\Bundle\ShellBundle\Contract;

/**
 * A PACKAGE WHOSE COMPONENTS ARE DRAWN INSIDE SOMEBODY ELSE'S PAGE, and
 * therefore cannot wait to be linked by it.
 *
 * THE BUG THIS EXISTS FOR. A surface that draws its own screens links
 * its own sheet in its own `stylesheets` block, and that is the normal
 * arrangement — it stays. But a COMPONENT is written into a page the
 * component's package has never heard of: `atlas_calendar()` on a
 * module's Calendar tab, `atlas_chart()` on a topic record. The page
 * cannot link a sheet for a component it does not know it is about to
 * render, and the component cannot link one for itself — a
 * `<link rel="stylesheet">` outside the head is not conforming HTML:
 *
 *   "Contexts in which this element can be used: Where metadata content
 *    is expected. … If the element is allowed in the body: where
 *    phrasing content is expected."
 *   — <https://html.spec.whatwg.org/multipage/semantics.html#the-link-element>
 *
 * and `stylesheet` is not a body-ok link type. So the head asks, before
 * the body is rendered, and this is the question it asks.
 *
 * WHAT TO REGISTER. The sheets a page needs in order to draw your
 * COMPONENTS — not the sheets your own screens link for themselves. A
 * package with no component drawn elsewhere registers nothing.
 *
 * ORDER IS THE TAG'S PRIORITY, not registration luck. A higher priority
 * is linked earlier, so a package whose rules another package decorates
 * says so in its own wiring:
 *
 *     ->tag(StylesheetSourceInterface::TAG, ['priority' => 10])
 *
 * The shell's own sheet is always first, and everything a page links in
 * its own block is always last, so this is the middle rung and the one
 * a component's vocabulary belongs on.
 *
 * @see \Uhifadhi\Bundle\AreaBundle\Overview\ContributesStylesheetInterface
 *      — the same problem one surface solved for itself, before it was
 *      clear that every component has it
 */
interface StylesheetSourceInterface
{
    /** The tag that puts a sheet in the head of every page. */
    public const string TAG = 'shell.stylesheet';

    /**
     * The served paths of the sheets this package's components need,
     * e.g. `bundles/atlas/calendar.css` — the package knows its own
     * bundle's name and the shell must not have to derive it.
     *
     * @return iterable<string>
     */
    public function stylesheets(): iterable;
}
