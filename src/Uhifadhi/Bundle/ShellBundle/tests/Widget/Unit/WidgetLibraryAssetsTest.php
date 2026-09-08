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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Widget\Unit;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\ShellBundle\ShellBundle;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\Widget;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetDom;

/**
 * The registry between the PHP side of a widget library and assets/widgets.js.
 *
 * This exists because of a real bug in the patrols module: the controller
 * validated a CSRF token, the template rendered one — and the script never sent
 * it. Every server-side test passed, because each one builds its own header;
 * only a browser ever hit the 403. Nothing in a test that talks HTTP can catch
 * that, so these assertions read the shipped asset as TEXT and check that the
 * names on both sides of the registry are literally the same string.
 *
 * If a name here has to change, it changes in two places at once — which is the
 * point.
 */
final class WidgetLibraryAssetsTest extends TestCase
{
    private static function widgetsJs(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 3).'/assets/widgets.js');
    }

    public function testTheScriptSendsTheCsrfHeaderThePhpSideReads(): void
    {
        $js = self::widgetsJs();

        self::assertStringContainsString(
            "export const CSRF_HEADER = '".WidgetDom::CSRF_HEADER."';",
            $js,
            'widgets.js must send the header WidgetDom declares.',
        );
        // Declaring it is not sending it: every write must actually attach it.
        self::assertStringContainsString('headers[CSRF_HEADER] = csrfToken();', $js);
        // Two CALL SITES, not counting the declaration: the optimistic save, and
        // commit(), which every other write (apply, copy, create, rename, delete,
        // reset) goes through. A write that built its own fetch() would be a
        // seventh way to forget the header — which is how a silent 403 shipped.
        self::assertSame(
            3,
            substr_count($js, 'postHeaders('),
            'Every write must go through postHeaders(): its declaration, the save, and commit() for the rest.',
        );
        self::assertSame(1, substr_count($js, 'function postHeaders('), 'one declaration, so the count above is two call sites');
        self::assertSame(
            2,
            substr_count($js, 'fetch('),
            'Only the optimistic save and commit() may talk to the server.',
        );
    }

    /**
     * THE SCRIPT ARMS ITSELF, and this is the half that did not ship.
     *
     * A real installation reported it as "clicking a preset does not show its
     * preview". Everything in this file was correct: the cards carried
     * `data-preset-kind` and `data-preset-id`, the catalogue was embedded, the
     * widgets were in their templates — and nothing ever called
     * `initWidgetLibrary`. The module exported it and left the call to the
     * installation; no recipe wrote that call, the README never asked for one,
     * and so every library page in existence rendered inert cards. AN EXPORT
     * NOBODY IS TOLD TO CALL IS AN EXPORT NOBODY CALLS.
     *
     * Importing the module is the whole contract now.
     */
    public function testTheScriptArmsItselfSoImportingItIsTheWholeContract(): void
    {
        $js = self::widgetsJs();

        // Ready or not: a module imported before the body parsed must wait, and
        // one imported after must not wait for an event that has been and gone.
        self::assertStringContainsString("if ('loading' === document.readyState) {", $js);
        self::assertStringContainsString("document.addEventListener('DOMContentLoaded'", $js);
        self::assertSame(
            2,
            substr_count($js, 'initWidgetLibrary();'),
            'Both arms of the readiness check must actually arm the library.',
        );
        // Guarded, because a module may be evaluated where there is no document.
        self::assertStringContainsString("'undefined' !== typeof document", $js);
    }

    public function testTheScriptBuildsPresetUrlsFromTheTemplatesTheTemplateRenders(): void
    {
        $js = self::widgetsJs();

        // The component draws cards that did not exist when the page was
        // rendered, so it BUILDS their URLs from the route templates on the root
        // rather than reading an href off a card. The placeholder it substitutes
        // into is the one PHP puts there.
        self::assertStringContainsString('presetUrl: \''.WidgetDom::PRESET_URL."',", $js);
        self::assertStringContainsString('presetCopyUrl: \''.WidgetDom::PRESET_COPY_URL."',", $js);
        self::assertStringContainsString('template.replace(def.placeholder', $js);
    }

    public function testTheScriptReadsTheCatalogueAndClonesTheRenderedWidgets(): void
    {
        $js = self::widgetsJs();

        // Previewing a preset is a client-side RE-COMPOSITION over the catalogue
        // the page embedded — a preview that costs a round trip is a preview
        // nobody clicks twice.
        self::assertStringContainsString("catalog: '".WidgetDom::CATALOG."',", $js);
        self::assertStringContainsString('JSON.parse(catalogEl.textContent)', $js);
        // And the picture of a widget is the widget: cloned from the <template>
        // its own Twig partial rendered, never rebuilt in JavaScript.
        self::assertStringContainsString("template: '".WidgetDom::TEMPLATE."',", $js);
        self::assertStringContainsString('source.content.cloneNode(true)', $js);
    }

    public function testTheScriptNeverOffersAnEditWhileABuiltInIsActive(): void
    {
        $js = self::widgetsJs();

        // BUILT-INS ARE IMMUTABLE, and the component enforces it by not drawing
        // the chrome at all: `editable` is true only for one of the person's own
        // presets, or a new one being composed.
        self::assertStringContainsString("editable: 'new' === preview.kind,", $js);
        self::assertStringContainsString("editable: 'mine' === def.active.kind,", $js);
        // The one door out of a shipped design.
        self::assertStringContainsString('data-preset-copy', $js);
    }

    public function testTheScriptReadsTheTokenFromTheRootTheTemplateRenders(): void
    {
        $js = self::widgetsJs();

        self::assertStringContainsString("root: '".WidgetDom::ROOT."',", $js);
        self::assertStringContainsString("csrfToken: '".WidgetDom::CSRF_TOKEN."',", $js);
        // The token is read from the very element that carries the two URLs.
        self::assertStringContainsString('root.getAttribute(ATTR.csrfToken)', $js);
        self::assertStringContainsString("export const ROOT_SELECTOR = '[' + ATTR.root + ']';", $js);
    }

    public function testEveryAttributeOfTheContractIsUsedByTheScript(): void
    {
        $js = self::widgetsJs();

        foreach (WidgetDom::attributes() as $attribute) {
            self::assertStringContainsString(
                "'".$attribute."'",
                $js,
                \sprintf('widgets.js must use %s.', $attribute),
            );
        }
    }

    public function testTheScriptInventsNoAttributeTheContractDoesNotDeclare(): void
    {
        // The other direction of the same contract: a hook the script drives the page
        // through but PHP never declares is a hook no template will ever render.
        preg_match_all("/'(data-widget-[a-z-]+)'/", self::widgetsJs(), $matches);
        $used = array_values(array_unique($matches[1]));
        sort($used);
        $declared = WidgetDom::attributes();
        sort($declared);

        self::assertSame($declared, $used);
    }

    public function testResetAsksThroughTheHostsSharedConfirmModal(): void
    {
        $js = self::widgetsJs();

        // The library states WHAT to ask; the host's controller owns the dialog,
        // so every surface asks the same way. confirm() is not acceptable here.
        self::assertStringContainsString("'confirm-modal:confirmed'", $js);
        self::assertStringNotContainsString('window.confirm(', $js);
    }

    public function testTheSlideDurationMatchesTheStylesheetsTransition(): void
    {
        // The FLIP is split across two files: widgets.js writes the inverted
        // transform and public/widget.css plays it back. A duration that drifts leaves the
        // cards mid-slide when the script strips the class.
        self::assertStringContainsString('const SLIDE_MS = 160;', self::widgetsJs());
        self::assertStringContainsString(
            '.w-sliding { transition: transform 160ms ease; }',
            (string) file_get_contents(\dirname(__DIR__, 3).'/public/widget.css'),
        );
    }

    public function testNoFrameworkClassSpellsATailwindUtility(): void
    {
        // A real defect, seen in the browser: the span classes were .w-12/.w-9/
        // .w-6/.w-3, which ARE Tailwind's width utilities on this host — the
        // utility layer out-cascaded the grid rule and a full-width widget
        // rendered 48px wide. The spans read .w-span-N now; this proves no `w-`
        // class in the framework block is a bare Tailwind scale name again.
        $css = (string) file_get_contents(\dirname(__DIR__, 3).'/public/widget.css');
        // From the END of the block's banner (its own commentary names the
        // retired classes), then with the remaining comments stripped: rules only.
        $block = substr($css, (int) strpos($css, 'WIDGET FRAMEWORK'));
        $block = substr($block, (int) strpos($block, '*/'));
        $block = (string) preg_replace('#/\*.*?\*/#s', '', $block);

        preg_match_all('/\.(w-(?:\d+|full|auto|fit|min|max|px|screen|(?:\d?x?s|\d?x?l|sm|md|lg|xl)))\b/', $block, $matches);

        self::assertSame([], array_values(array_unique($matches[1])));
        self::assertStringContainsString('.w-grid > .w-cell.w-span-12 {', $block);
    }

    public function testEverySpanTheGridOffersHasARuleAndAChipLabel(): void
    {
        // THE SPAN VOCABULARY IS SPLIT ACROSS THREE FILES: the model says which
        // spans exist, public/widget.css lays each of them out on the dashboard, in the
        // library and on the canvas, and widgets.js draws the width chip that
        // picks one. A span added to the model alone would be offered by the
        // chips and then laid out as a full row.
        $css = (string) file_get_contents(\dirname(__DIR__, 3).'/public/widget.css');
        $js = self::widgetsJs();

        foreach (Widget::GRID_SPANS as $span) {
            self::assertStringContainsString(
                \sprintf('.w-grid > .w-cell.w-span-%d { grid-column: span %d; }', $span, $span),
                $css,
                \sprintf('The dashboard grid must lay out a span of %d.', $span),
            );
            // The library card's own base rule IS the full row, so only the
            // narrower spans get an attribute-scoped rule of their own.
            if (12 !== $span) {
                self::assertStringContainsString(
                    \sprintf('.w-card[data-widget-cols="%d"] { grid-column: span %d; }', $span, $span),
                    $css,
                    \sprintf('The library must lay a card out at a span of %d.', $span),
                );
                // Mid-drag the drop slot copies the card's data-widget-cols, so
                // its footprint must match the span the widget will land at.
                // Without a rule of its own it falls back to the base full row
                // and promises the wrong landing.
                self::assertStringContainsString(
                    \sprintf('.w-dropslot[data-widget-cols="%d"] { grid-column: span %d; }', $span, $span),
                    $css,
                    \sprintf('The drop slot must show a footprint of %d.', $span),
                );
            }
            self::assertMatchesRegularExpression(
                \sprintf('/SPAN_LABELS = \{[^}]*\b%d:/', $span),
                $js,
                \sprintf('The width chips must have a label for a span of %d.', $span),
            );
        }
    }

    /**
     * A BUNDLE CANNOT WRITE AN IMPORTMAP ENTRY — that file belongs to the
     * installation. What a bundle can do is register the directory under an
     * AssetMapper namespace, which is what makes
     * `@uhifadhi/shell-bundle/widgets` resolvable at all; the installation
     * then names it in its own importmap.php. This asserts the half that is
     * ours, because a namespace typo fails only in a browser.
     */
    public function testTheScriptDirectoryIsRegisteredUnderTheBundlesNamespace(): void
    {
        $bundle = (string) file_get_contents(\dirname(__DIR__, 3).'/ShellBundle.php');

        self::assertStringContainsString("__DIR__.'/assets' => self::ASSET_NAMESPACE,", $bundle);
        self::assertFileExists(\dirname(__DIR__, 3).'/assets/widgets.js');
    }

    /**
     * The stylesheet the library wears, named once as a constant because the
     * page rendering the library has to link it and nobody should retype a
     * vendor path.
     */
    /**
     * EVERY constant() A TEMPLATE NAMES ACTUALLY RESOLVES.
     *
     * This exists because of a real bug a real install found: `_library.html.twig`
     * still named `Uhifadhi\Service\WidgetService::NAME_MAX`, the namespace this
     * code had BEFORE it was extracted from the host into a bundle. Twig resolves
     * a constant at RENDER time, so nothing failed until a page was actually
     * requested — and then it failed with a 500 on the whole library rather than
     * a missing value in one field.
     *
     * A rename is exactly the change that leaves one of these behind, and static
     * analysis cannot see inside a Twig string. So the templates are read as TEXT
     * and every constant they name is resolved here.
     */
    public function testEveryConstantTheTemplatesNameResolves(): void
    {
        $templates = glob(\dirname(__DIR__, 3).'/templates/widget/*.twig') ?: [];
        self::assertNotSame([], $templates, 'The sweep found no templates to sweep.');

        $found = 0;
        foreach ($templates as $template) {
            preg_match_all(
                "/constant\\(\\s*'([^']+)'/",
                (string) file_get_contents($template),
                $matches,
            );

            foreach ($matches[1] as $reference) {
                ++$found;
                // Twig writes a PHP namespace separator escaped for the Twig
                // string literal; unescape it before asking PHP.
                $constant = str_replace('\\\\', '\\', $reference);
                self::assertTrue(
                    \defined($constant),
                    \sprintf('%s names %s, which does not exist.', basename($template), $constant),
                );
            }
        }

        self::assertGreaterThan(0, $found, 'The sweep matched nothing, so it proves nothing.');
    }

    public function testTheStylesheetConstantNamesTheFileTheBundleShips(): void
    {
        self::assertSame('bundles/shell/widget.css', ShellBundle::WIDGET_STYLESHEET);
        self::assertFileExists(\dirname(__DIR__, 3).'/public/widget.css');
    }
}
