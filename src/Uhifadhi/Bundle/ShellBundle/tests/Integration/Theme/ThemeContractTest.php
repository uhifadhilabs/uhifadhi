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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Integration\Theme;

use PHPUnit\Framework\Attributes\DataProvider;
use Uhifadhi\Bundle\ShellBundle\Contract\LayoutContract;
use Uhifadhi\Bundle\ShellBundle\Service\Theme;
use Uhifadhi\Bundle\ShellBundle\Tests\Integration\ContractTestCase;

/**
 * SPEC 4 — THE THEME CONTRACT.
 *
 * The tokens are a socket list exactly like the blocks, and they are frozen the
 * same way and for the same reason. A module's stylesheet is written against
 * these names: `background: rgb(var(--c-p1))` in a patrol card is a promise the
 * shell made, and the day the shell renames --c-p1 that card renders
 * transparent on a page nobody was looking at.
 *
 * LIGHT AND DARK ARE BOTH FIRST-CLASS. Not "dark mode" as an overlay on a light
 * design — two complete palettes, and a token that exists in only one of them
 * is a bug this file catches. That is why the token list and the THEMED list
 * are separate: most tokens must be redefined per theme, and the few that must
 * NOT be (the brand tokens, which ride the channels of others, and the fonts,
 * which are not colours) are named as such rather than left to be noticed.
 *
 * WHAT IS DELIBERATELY NOT HERE: the map chrome tokens (--z-ink, --z-paper,
 * --z-imagery, --z-aoi). They belong to AtlasBundle, which is the ring
 * that owns how a layer draws, and a legend palette that lived in the shell
 * would be a palette the map bundle could not change without a shell release.
 * See the map legend contract.
 */
final class ThemeContractTest extends ContractTestCase
{
    /**
     * THE TOKEN LIST, VERSION 1. Typed out, for the reason the block list is:
     * a list derived from the stylesheet agrees with the stylesheet.
     *
     * @return list<string>
     */
    public static function contractV1(): array
    {
        return [
            // SURFACES — the four grounds a page is built from, deepest first.
            '--c-cv',       // canvas: the page behind everything
            '--c-p1',       // plate, gradient high
            '--c-p2',       // plate, gradient low
            '--c-raised',   // chips, fields, anything sitting on a plate

            // INK — three weights, and only three. A fourth grey is how a
            // design system starts to disagree with itself.
            '--c-tx',       // primary text
            '--c-fog',      // secondary
            '--c-dim',      // tertiary

            // ACCENT — one, and the text that survives on it.
            '--c-acc',
            '--c-accT',

            // STATE — three, matching the platform's chip vocabulary.
            '--c-ok',
            '--c-warn',
            '--c-fail',

            // EDGES AND DEPTH
            '--c-ln',       // hairline
            '--c-ln2',      // emphasised hairline
            '--glass',      // the translucent ground under sticky chrome
            '--shadow',     // the one elevation
            '--accGlow',    // the accent's halo

            // DERIVED SEMANTIC ALIASES — the design's own token names (--tx/--fail
            // /…), mapped once from the channels above so components and module
            // sheets read them directly. Ride the channels; never per-theme (below).
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

            // BRAND — derived, never redefined per theme. See the themed test.
            '--logo-tile',
            '--logo-child',
            '--logo-accent',

            // TYPE — three faces, and no per-theme variation, because a
            // typeface that changed with the lights would be a different brand
            // after dark.
            '--font-display',
            '--font-body',
            '--font-mono',
        ];
    }

    /**
     * The tokens that MUST carry a different value under dark. Everything in
     * the list above that is not here is either derived from these or not a
     * colour at all.
     *
     * @return list<string>
     */
    public static function themed(): array
    {
        return array_values(array_diff(self::contractV1(), [
            // Derived — ride the channels, single definition, no dark redefinition.
            '--cv', '--p1', '--p2', '--raised', '--tx', '--fog', '--dim',
            '--acc', '--accT', '--ok', '--warn', '--fail', '--ln', '--ln2',
            '--logo-tile', '--logo-child', '--logo-accent',
            '--font-display', '--font-body', '--font-mono',
        ]));
    }

    public function testTheTokenListIsExactlyThis(): void
    {
        self::assertSame(self::contractV1(), LayoutContract::TOKENS, <<<'WHY'
            A theme token was added, removed or renamed. Module stylesheets
            across the platform are written against these names — the same change
            policy applies as to the blocks (see docs/changing-the-contract.md).
            WHY);
    }

    /**
     * Every promised token is actually defined, in light. The list is a
     * promise; the stylesheet is the keeping of it.
     */
    #[DataProvider('lightTokens')]
    public function testEveryTokenIsDefinedInTheLightPalette(string $token): void
    {
        self::assertMatchesRegularExpression(
            '/:root\s*\{[^}]*'.preg_quote($token, '/').'\s*:/s',
            $this->stylesheet(),
            \sprintf('%s is promised by the contract and defined nowhere.', $token),
        );
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function lightTokens(): \Generator
    {
        foreach (self::contractV1() as $token) {
            yield $token => [$token];
        }
    }

    /**
     * AND IN DARK. This is the test that makes dark first-class rather than an
     * afterthought: a new token cannot be added to the light palette alone,
     * because the contract list drives both halves.
     *
     * @return \Generator<string, array{string}>
     */
    public static function themedTokens(): \Generator
    {
        foreach (self::themed() as $token) {
            yield $token => [$token];
        }
    }

    #[DataProvider('themedTokens')]
    public function testEveryThemedTokenIsRedefinedInTheDarkPalette(string $token): void
    {
        self::assertMatchesRegularExpression(
            '/html\.dark\s*\{[^}]*'.preg_quote($token, '/').'\s*:/s',
            $this->stylesheet(),
            \sprintf('%s has no dark value. Dark is a palette, not a filter.', $token),
        );
    }

    /**
     * THE DERIVED TOKENS ARE NOT REDEFINED, and the mechanism is the interesting
     * part: the brand mark rides the channels of --c-acc and --c-cv, so it
     * lands deep jade on the light canvas and mint on the dark one with no
     * per-theme override at all. A dark-mode value for --logo-tile would be a
     * second place the brand colour is decided, and the two would drift.
     */
    public function testTheBrandTokensRideTheChannelsRatherThanBeingRestated(): void
    {
        $css = $this->stylesheet();
        $dark = '';
        if (1 === preg_match('/html\.dark\s*\{(.*?)\}/s', $css, $matches)) {
            $dark = $matches[1];
        }
        self::assertNotSame('', $dark, 'There is no dark palette at all.');

        foreach (['--logo-tile', '--logo-child', '--logo-accent'] as $token) {
            self::assertStringNotContainsString($token, $dark, \sprintf(
                '%s is derived from --c-acc/--c-cv and must not be restated per theme.',
                $token,
            ));
        }
    }

    /**
     * DARK IS A CLASS ON <html>, and it is applied before first paint. The
     * selector is part of the contract because a module's own stylesheet writes
     * it too — `html.dark .patrol-card { … }` — and if the shell ever moved to
     * a data attribute every module sheet would silently stop
     * theming.
     */
    public function testTheDarkSelectorIsPartOfTheContract(): void
    {
        self::assertSame('html.dark', LayoutContract::DARK_SELECTOR);
        self::assertStringContainsString('html.dark', $this->stylesheet());
    }

    /**
     * NO FLASH OF THE WRONG THEME. The choice is applied by an inline script in
     * the document head, before the stylesheet paints — not by a Stimulus
     * controller that connects after the first frame. A visitor who chose dark
     * must not be shown a white page first, and today they are.
     */
    public function testTheChosenThemeIsAppliedBeforeTheFirstPaint(): void
    {
        $html = $this->render('@fixtures/bare_document_page.html.twig');

        $script = strpos($html, 'shell-theme');
        $sheet = strpos($html, 'shell/shell');

        self::assertIsInt($script, 'The document must resolve the theme inline, in <head>.');
        self::assertIsInt($sheet);
        self::assertLessThan($sheet, $script);
    }

    /**
     * THE THIRD ANSWER IS REAL. "system" is not a synonym for light — a visitor
     * who has told their operating system which they want has already answered,
     * and the shell honours it rather than overriding it with a default.
     */
    public function testTheSystemPreferenceIsHonouredRatherThanFlattenedToTheDefault(): void
    {
        self::assertStringContainsString('prefers-color-scheme', $this->render('@fixtures/bare_document_page.html.twig'));
    }

    /**
     * A LINK MAY CARRY THE ANSWER. `?theme=dark` forces a theme for that view —
     * which is how a screenshot, a projector, a support call and a design review
     * ask for the other palette without touching anybody's saved preference.
     *
     * It is resolved in the same inline script, before the first paint, for the
     * same reason everything else about the theme is: an override applied after
     * the first frame is a flash of the theme the visitor explicitly did not
     * ask for.
     */
    public function testALinkMayOverrideTheThemeForOneViewWithoutChangingTheChoice(): void
    {
        $head = $this->render('@fixtures/bare_document_page.html.twig');

        self::assertStringContainsString('theme', $head);
        self::assertMatchesRegularExpression(
            '/URLSearchParams|searchParams/',
            $head,
            'The pre-paint script must read the URL override, not a controller that connects later.',
        );
    }

    /**
     * THE KEY IS PUBLISHED. The head READS the choice and the shell's own theme
     * controller WRITES it, and the two are in different languages in different
     * directories — so the name is a constant rather than a string each side
     * types for itself. It was published when the toggle lived in the host; it
     * stays published now that the toggle ships here, for the same reason.
     */
    public function testTheKeyTheChoiceIsRememberedUnderIsPublished(): void
    {
        self::assertSame('shell-theme', Theme::CHOICE_KEY);
        self::assertStringContainsString(Theme::CHOICE_KEY, $this->render('@fixtures/bare_document_page.html.twig'));
    }

    /**
     * A MODULE MAY RELY ON THESE AND ON NOTHING ELSE. The stylesheet certainly
     * contains other custom properties — internal ones, for the shell's own
     * furniture. The contract is that a module uses the listed names; the
     * counter-promise is that the shell marks its private ones so nobody
     * mistakes one for public. Prefix, not honour system.
     */
    public function testThePrivateTokensAreMarkedAsPrivate(): void
    {
        $declared = [];
        preg_match_all('/^\s*(--[a-zA-Z0-9-]+)\s*:/m', $this->stylesheet(), $matches);
        foreach ($matches[1] as $token) {
            $declared[$token] = true;
        }

        $undocumented = array_values(array_filter(
            array_keys($declared),
            static fn (string $token): bool => !\in_array($token, LayoutContract::TOKENS, true)
                && !str_starts_with($token, '--_'),
        ));

        self::assertSame([], $undocumented, 'A token a module can read is a token a module will read. List it, or prefix it --_ as private.');
    }
}
