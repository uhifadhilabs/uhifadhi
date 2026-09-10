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
 * THE FROZEN MANIFEST — the shell's public API, as data.
 *
 * Three frames, twenty-three sockets, twenty-three theme tokens and one
 * version number. Everything a module is allowed to know about the
 * shell is on this class, and everything on this class is pinned by a test
 * that types the same list out by hand (tests/Sockets/BlockContractTest and
 * tests/Theme/ThemeContractTest). The manifest and the test disagree loudly, on
 * purpose: a list derived from the templates would agree with whatever the
 * templates happen to say, and would have nothing to report the day somebody
 * renames a block.
 *
 * The change policy is in docs/changing-the-contract.md: adding is a
 * minor version and a new row in the frozen test; renaming is a major version, a
 * deprecation cycle, and the old name kept as an alias block for one release.
 */
final class LayoutContract
{
    /**
     * The socket list's version, so a module can require a shell that
     * has the blocks it fills. Without a number, "the shell supports
     * shell_page_tabs" is a fact nobody can assert except by rendering.
     */
    public const int VERSION = 1;

    /** The document: html, head, theme, the four Symfony block names. */
    public const string DOCUMENT = '@Shell/document.html.twig';

    /** The shell: the furniture — sidebar, top bar, footer — around a page. */
    public const string SHELL = '@Shell/shell.html.twig';

    /** The page frame: the module author's rung, and the reason this exists. */
    public const string PAGE = '@Shell/page.html.twig';

    /** The catalogue picture: cards in groups. Data in; no grouping, no URLs. */
    public const string MODULE_GRID = '@Shell/_module_grid.html.twig';

    /**
     * The dark palette's selector. Part of the contract because a module's own
     * stylesheet writes it too — `html.dark .some-card { … }` — so a move to a
     * data attribute would silently unstyle every module stylesheet.
     */
    public const string DARK_SELECTOR = 'html.dark';

    /**
     * Partials a host or a module may include directly. A partial extends
     * nothing and declares no socket; it is a drawing, handed data.
     *
     * @var list<string>
     */
    public const array PARTIALS = [
        self::MODULE_GRID,
        '@Shell/_brand_mark.html.twig',
        '@Shell/_area_tabs.html.twig',
        '@Shell/_nav.html.twig',
        '@Shell/_flashes.html.twig',
    ];

    /**
     * THE TWENTY-THREE SOCKETS, in ladder order: the document's, the shell's,
     * the page frame's. Grouped and annotated in the frozen test, which is the
     * copy a module author should read.
     *
     * @var list<string>
     */
    public const array BLOCKS = [
        // The document — the four standard block names, unchanged.
        'title',
        'stylesheets',
        'javascripts',
        'importmap',
        'body',

        // The shell — the host's furniture. A module fills none of these.
        'shell_banner',
        'shell_sidebar',
        'shell_sidebar_brand',
        'shell_sidebar_nav',
        'shell_sidebar_footer',
        'shell_topbar',
        'shell_topbar_actions',
        'shell_main',
        'content',
        'shell_footer',

        // The page frame — the module author's sockets.
        'shell_breadcrumbs',
        'shell_page_head',
        'shell_page_title',
        'shell_page_subtitle',
        'shell_page_actions',
        'shell_page_tabs',
        'shell_flashes',
        'shell_page',
    ];

    /**
     * THE TWENTY-THREE THEME TOKENS. A module's stylesheet is written against
     * these names, so they are frozen exactly as the blocks are.
     *
     * The last six are DERIVED or non-colour and must not be restated per
     * theme: the brand trio rides the channels of --c-acc and --c-cv, and a
     * typeface that changed with the lights would be a different brand after
     * dark.
     *
     * @var list<string>
     */
    public const array TOKENS = [
        // surfaces
        '--c-cv',
        '--c-p1',
        '--c-p2',
        '--c-raised',

        // ink — three weights, and only three
        '--c-tx',
        '--c-fog',
        '--c-dim',

        // accent
        '--c-acc',
        '--c-accT',

        // state
        '--c-ok',
        '--c-warn',
        '--c-fail',

        // edges and depth
        '--c-ln',
        '--c-ln2',
        '--glass',
        '--shadow',
        '--accGlow',

        // derived semantic aliases — the design's own token names, mapped ONCE
        // from the channels above so components and module sheets read them
        // directly (--tx not rgb(var(--c-tx))). They ride the channels and are
        // never redefined per theme, exactly like the brand trio below.
        '--cv',
        '--p1',
        '--p2',
        '--raised',
        '--tx',
        '--fog',
        '--dim',
        '--acc',
        '--accT',
        '--ok',
        '--warn',
        '--fail',
        '--ln',
        '--ln2',

        // brand — derived from the channels above
        '--logo-tile',
        '--logo-child',
        '--logo-accent',

        // type
        '--font-display',
        '--font-body',
        '--font-mono',
    ];

    /**
     * THE COMPONENT VOCABULARY — the class names a module may write.
     *
     * The tokens say what jade is; these say what a KPI plate is. Both are
     * public API and both are frozen, for the same reason: four modules
     * independently wrote `class="kpi"` against a rule that lived in none of
     * them, and the day the shell renames it they all render as running text.
     *
     * These are the ENTRY classes. Their parts — `.kpi .sub`, `.rdf-page .pg`,
     * `.tbl .num`, the chip's and the grid's modifiers — are documented in
     * docs/components.md rather than listed here, because a part without its
     * entry is not a thing a module can write.
     *
     * Note what is NOT on the list, and why: `.pgbody`, `.pghead`, `.crumb`,
     * `.atabs`, `.side`, `.topbar` and the rest of the furniture. The shell
     * writes those itself, from its own templates; a module that typed one
     * would be drawing the frame instead of filling it.
     *
     * @var list<string>
     */
    public const array COMPONENTS = [
        // the plate and its vocabulary
        'c',
        'chip',
        'cta',
        'grid',

        // type, and the colour words
        'mono',
        'disp',
        'fog',
        'acc',
        'g',
        'w',
        'r',
        'd',
        'muted',

        // the card's tab, and the line saying what the card is for
        'tab',
        'use',

        // the identity band a detail screen opens with
        'factband',

        // the KPI plate, and the strip it sits in
        'kpi',
        'kstrip',

        // the register table, the meta row, and the pager under them
        'tbl',
        'rln',
        'rdf-foot',
        'rdf-page',

        // the person's mark, and the two quiet buttons
        'avatar',
        'open-btn',
        'tgl',

        // the form field every filter/search input is drawn as
        'fld',
    ];
}
