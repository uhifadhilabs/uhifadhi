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

namespace Uhifadhi\Bundle\AtlasBundle\Tests\Unit\Assets;

use PHPUnit\Framework\TestCase;

/**
 * A LINK THAT CHANGES THE PLATE, WHEREVER IT SITS.
 *
 * The plate already answered a filter change without navigating, but only
 * while it was fullscreen and only for links inside its own filter row. Every
 * other "show me this one on the map" — a ranger in the roster's rail, a
 * station in a list, a zone in a register — was a full navigation: the page
 * rebuilt, the scroll position lost, the map torn down and mounted again, for
 * a change the server answers with the same page it always answers with.
 *
 * THE RULED VERB. A same-origin link wearing `data-atlas-swap` swaps the
 * plate's subtrees out of the fetched page wherever the link sits and whether
 * or not the plate is fullscreen. It may name ONE more region to bring across
 * (`data-atlas-swap-also="#selector"`) so a caller's own marked row moves with
 * the map. The `href` stays the no-JS fallback, and history is PUSHED, because
 * which thing the map is about is somewhere the viewer went.
 *
 * THE SERVER STILL DECIDES EVERYTHING — what is focused, what the plate frames
 * itself on, which row is marked. A module writes an attribute and no
 * JavaScript and no map maths, which is the ruling this verb has to keep.
 *
 * A TEXT CHECK OVER THE SHIPPED ASSET, and that is its limit: it catches the
 * seam being unpicked — the fullscreen guard creeping back, the fallback
 * dropped, pushState turned into replaceState, the extra region forgotten.
 * There is no JS runner in this project.
 */
final class PlateSwapVerbTest extends TestCase
{
    public function testTheVerbAndItsCompanionArePublishedAsAttributes(): void
    {
        $js = self::controllerJs();

        self::assertStringContainsString("const SWAP = 'data-atlas-swap';", $js);
        self::assertStringContainsString("const SWAP_ALSO = 'data-atlas-swap-also';", $js);
    }

    /**
     * THE VERB IS DELEGATED FROM THE DOCUMENT, because the links wearing it
     * are somebody else's markup and are very often not inside the plate at
     * all. A listener bound to the plate could only ever hear its own chips,
     * which is the limitation being lifted.
     */
    public function testTheClickIsHeardFromTheDocumentAndNotOnlyInsideThePlate(): void
    {
        $js = self::controllerJs();

        self::assertStringContainsString("document.addEventListener('click', this.onSwapClick);", $js);
        self::assertStringContainsString("document.removeEventListener('click', this.onSwapClick);", $js);
        self::assertMatchesRegularExpression(
            '/swapLink\(event\)\s*\{.*?event\.target\.closest\(`a\[href\]\[\$\{SWAP\}\]`\)/s',
            $js,
            'The handler finds the link by the verb, anywhere in the document.',
        );
    }

    /**
     * AND IT DOES NOT ASK WHETHER THE PLATE IS FULLSCREEN. That guard is what
     * made the old swap a fullscreen-only trick; a swap outside fullscreen is
     * the ordinary case now.
     */
    public function testTheSwapDoesNotDependOnFullscreen(): void
    {
        $handler = self::method('swapLink');

        self::assertStringNotContainsString('isFullscreen', $handler, 'A swap is not a fullscreen-only trick any more.');
    }

    /**
     * THE LINK STILL WORKS AS A LINK. Only a plain left click on a same-origin
     * link with no target of its own is taken; anything else is the viewer
     * asking for a real navigation or a second tab.
     */
    public function testOnlyAPlainSameOriginLeftClickIsTaken(): void
    {
        $handler = self::method('swapLink');

        self::assertStringContainsString('isPlainClick(event)', $handler);
        self::assertStringContainsString('link.target', $handler);
        self::assertStringContainsString('address.origin !== window.location.origin', $handler);
        self::assertStringContainsString('event.preventDefault();', $handler);
    }

    /**
     * AND WHERE THE ANSWER IS NOT A PAGE THIS PLATE IS IN, the browser goes
     * where the link said — the fallback is the behaviour that was
     * intercepted, never a dead click.
     */
    public function testAnAnswerWithoutThePlateFallsBackToTheNavigation(): void
    {
        $swapTo = self::method('swapTo');

        self::assertStringContainsString('window.location.assign(address);', $swapTo);
        self::assertStringContainsString('history.pushState(history.state, \'\', address);', $swapTo);
        self::assertStringNotContainsString('replaceState', $swapTo, 'Which thing the map is about is a place, so Back must reach the one before.');
    }

    /** ONE MORE REGION, named by the link, taken from the same answer. */
    public function testOneRegionBesideThePlateComesAcrossWithIt(): void
    {
        $swapTo = self::method('swapTo');
        $also = self::method('swapAlso');

        self::assertStringContainsString('this.swapAlso(page, alsoSelector);', $swapTo);
        self::assertStringContainsString('page.querySelector(selector)', $also);
        self::assertStringContainsString('document.querySelector(selector)', $also);
        self::assertStringContainsString('live.replaceWith(document.importNode(next, true));', $also);
    }

    /**
     * A REGION THE ANSWER DOES NOT HAVE IS LEFT ALONE, rather than removed:
     * the page fetched is the authority on what a list CONTAINS, not on
     * whether somebody else's list exists.
     */
    public function testARegionMissingFromTheAnswerIsLeftStanding(): void
    {
        self::assertMatchesRegularExpression(
            '/if \(!next \|\| !live\) \{\s*return;/',
            self::method('swapAlso'),
        );
    }

    /**
     * WHICH PLATE A LINK IS ABOUT, and the refusal that matters: two plates
     * and a link that names neither is a question with two answers, so the
     * browser navigates instead of the page guessing.
     */
    public function testAnAmbiguousLinkIsNotSwappedAtAll(): void
    {
        $resolver = self::function('plateElementFor');

        self::assertStringContainsString('link.getAttribute(SWAP)', $resolver);
        self::assertStringContainsString('link.closest(`[${PLATE}]`)', $resolver);
        self::assertMatchesRegularExpression('/1 === plates\.length \? plates\[0\] : null/', $resolver);
    }

    /** The whole answer is parsed once, because the plate and the region share a request. */
    public function testThePlateAndTheRegionComeFromOneRequest(): void
    {
        $js = self::controllerJs();

        self::assertStringContainsString('async fetchPage(address)', $js);
        self::assertStringContainsString("credentials: 'same-origin'", $js);
        self::assertMatchesRegularExpression(
            '/async fetchPlate\(address\)\s*\{\s*const page = await this\.fetchPage\(address\);/',
            $js,
            'The filter path reads the same parsed answer rather than fetching its own.',
        );
    }

    /** The verb is documented where a module author looks for what Atlas offers. */
    public function testTheVerbIsDocumentedInTheComponentsDoc(): void
    {
        $doc = (string) file_get_contents(\dirname(__DIR__, 3).'/docs/components.md');

        self::assertStringContainsString('data-atlas-swap', $doc);
        self::assertStringContainsString('data-atlas-swap-also', $doc);
    }

    /** One method's body, from its signature to its own closing brace. */
    private static function method(string $name): string
    {
        return self::block('/\n    (?:async )?'.preg_quote($name, '/').'\((?:[^)]*)\)\s*\{/', "\n    }\n");
    }

    /** One module-level function's body — closed at column one, not at four. */
    private static function function(string $name): string
    {
        return self::block('/\nfunction '.preg_quote($name, '/').'\((?:[^)]*)\)\s*\{/', "\n}\n");
    }

    private static function block(string $opening, string $closing): string
    {
        $js = self::controllerJs();
        self::assertSame(1, preg_match($opening, $js, $found, \PREG_OFFSET_CAPTURE), 'the block is there to be read');
        $from = (int) $found[0][1];
        $rest = substr($js, $from + \strlen((string) $found[0][0]));
        $end = strpos($rest, $closing);

        return false === $end ? $rest : substr($rest, 0, $end);
    }

    private static function controllerJs(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 3).'/assets/controllers/map_plate_controller.js');
    }
}
