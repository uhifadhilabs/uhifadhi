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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\AtlasBundle\Twig\MapPlateRuntime;

/**
 * A FILTER CHANGE MUST NOT THROW THE VIEWER OUT OF FULLSCREEN.
 *
 * The filter row is a GET form inside the plate, which is what carries the chips
 * into fullscreen — and also what took the viewer back out of it, because a form
 * submission is a navigation and a navigation ends fullscreen. Somebody
 * comparing two filters on a wall-sized map had to expand the plate again
 * between every chip.
 *
 * THE RULED BEHAVIOUR. While the plate is fullscreen a filter change fetches the
 * same address with the new query, takes the plate's own subtrees out of the
 * answer, swaps them in place, and writes the new address into the bar without
 * navigating. Leaving fullscreen afterwards navigates once, because the log, the
 * counts and everything else outside the plate are still showing the old query.
 * Outside fullscreen nothing is intercepted and the form submits as it always
 * did.
 *
 * A TEXT CHECK OVER THE SHIPPED ASSET AND TEMPLATE, and that is the limit of
 * what it promises: it catches the seam being unpicked — the guard dropped, a
 * subtree left out of the swap, the address or the catch-up navigation quietly
 * removed. There is no JS runner in this project, and whether the swapped plate
 * LOOKS right is a rendered check, not a unit test.
 */
final class PlateFilterFullscreenTest extends TestCase
{
    /** The plate's own subtrees — everything a filter change can change inside the plate. */
    private const array SWAPPED = ['.map-filters', '.map-canvas', '.map-legend'];

    public function testThePlateRootCarriesTheHookTheSwapAddressesItBy(): void
    {
        // The template writes the hook PHP publishes, so the spelling lives in
        // one place on that side; what it renders to is RenderMapTest's.
        self::assertMatchesRegularExpression(
            '/<div class="map-plate"[^>]*\{\{ hook \}\}>/',
            self::template(),
            'Without a hook on the plate root, a fetched page offers no way to find the same plate in it.',
        );
        self::assertStringContainsString(
            MapPlateRuntime::PLATE_HOOK,
            self::controllerJs(),
            'The controller finds its counterpart in the answer by the same hook the plate is marked with.',
        );
    }

    /** The filter row declares the interception; the plate's controller owns it. */
    public function testTheFilterRowHandsItsSubmissionToThePlate(): void
    {
        self::assertMatchesRegularExpression(
            '/class="map-filters"[^>]*data-action="[^"]*submit->\{\{ controller \}\}#filter\b/',
            self::template(),
        );
    }

    /**
     * THE GUARD IS THE WHOLE POINT. Outside fullscreen the form is left alone:
     * a plain submission reloads the page, which is what keeps every other thing
     * on it in step with the filter.
     */
    public function testNothingIsInterceptedOutsideFullscreen(): void
    {
        $js = self::controllerJs();

        self::assertStringContainsString('document.fullscreenElement', $js);
        self::assertMatchesRegularExpression(
            '/filter\(event\) \{\n\s+if \(!this\.isFullscreen\(\)\) \{\n\s+return;/',
            $js,
            'The first thing the interception does is decline to intercept anywhere but fullscreen.',
        );
    }

    /**
     * A CHIP IS AS OFTEN A LINK AS A BUTTON, and both are the same act. A filter
     * row built of `<a href="?type=…">` chips navigates on a click exactly as a
     * form navigates on a submission, so it leaves fullscreen exactly as a form
     * did — the plate answers the click the same way and by the same path.
     */
    public function testAChipThatIsALinkIsAnsweredTheSameWay(): void
    {
        self::assertMatchesRegularExpression(
            '/class="map-filters"[^>]*data-action="submit->\{\{ controller \}\}#filter click->\{\{ controller \}\}#filterLink"/',
            self::template(),
        );

        $js = self::controllerJs();

        self::assertMatchesRegularExpression(
            '/filterLink\(event\) \{\n\s+if \(!this\.isFullscreen\(\)\) \{\n\s+return;/',
            $js,
            'The link path declines outside fullscreen for the same reason the form path does.',
        );
        self::assertStringContainsString("closest('a[href]')", $js);
        self::assertSame(
            2,
            substr_count($js, 'this.refilter(address)'),
            'Both chips reach the same fetch, the same swap and the same address — one path, two ways in.',
        );
    }

    /**
     * AND ONLY A PLAIN LEFT CLICK IS TAKEN. Opening a filter in a new tab or a
     * new window is the viewer asking for a second page, and a plate that
     * swallowed that would have broken something that worked.
     *
     * @param string $refused a way of clicking that is not the plate's to answer
     */
    #[DataProvider('otherWaysOfClicking')]
    public function testOnlyAPlainLeftClickIsIntercepted(string $refused): void
    {
        self::assertStringContainsString(
            $refused,
            self::controllerJs(),
            'A modified click belongs to the browser, not to the plate.',
        );
    }

    /** @return iterable<string, array{string}> */
    public static function otherWaysOfClicking(): iterable
    {
        foreach (['event.button', 'event.metaKey', 'event.ctrlKey', 'event.shiftKey', 'event.altKey'] as $refused) {
            yield $refused => [$refused];
        }
    }

    /**
     * @param string $part one of the plate's own subtrees
     */
    #[DataProvider('swappedParts')]
    public function testEverySubtreeOfThePlateIsSwapped(string $part): void
    {
        self::assertStringContainsString(
            "'".$part."'",
            self::controllerJs(),
            'A subtree left out of the swap keeps the old query on screen beside the new map.',
        );
    }

    /** @return iterable<string, array{string}> */
    public static function swappedParts(): iterable
    {
        foreach (self::SWAPPED as $part) {
            yield $part => [$part];
        }
    }

    /**
     * THE ADDRESS FOLLOWS THE CHIPS WITHOUT A NAVIGATION — `replaceState`, never
     * `pushState`: a filter is not a place in the viewer's history, and twenty
     * chips must not become twenty presses of the back button.
     */
    public function testTheAddressBarFollowsWithoutNavigating(): void
    {
        $js = self::controllerJs();

        self::assertStringContainsString('history.replaceState', $js);
        self::assertStringNotContainsString('history.pushState', $js);
    }

    /**
     * AND LEAVING FULLSCREEN CATCHES THE REST OF THE PAGE UP. Only the plate was
     * swapped, so the log and the counts behind it are still answering the old
     * query; the page is navigated once, to the address the chips wrote.
     */
    public function testLeavingFullscreenAfterAChangeNavigatesOnce(): void
    {
        $js = self::controllerJs();

        self::assertStringContainsString("addEventListener('fullscreenchange'", $js);
        self::assertMatchesRegularExpression(
            '/this\.stale = false;\n\s+window\.location\.reload\(\);/',
            $js,
            'The flag is cleared before the navigation, so one exit is one navigation.',
        );
    }

    /** The plate asks for a page, from its own origin, and reads one plate out of it. */
    public function testTheRefetchIsASameOriginRequestForTheSamePage(): void
    {
        $js = self::controllerJs();

        self::assertStringContainsString("credentials: 'same-origin'", $js);
        self::assertStringContainsString('new DOMParser()', $js);
    }

    /**
     * THE SWAP IS THE PLATE'S OWN, not a navigation library's. Installations do
     * not ship Turbo, and a plate that needed it would be a plate that only
     * works in some of them.
     */
    public function testThePlateBringsNoNavigationLibraryWithIt(): void
    {
        self::assertDoesNotMatchRegularExpression(
            '/^import .*turbo/mi',
            self::controllerJs(),
        );
    }

    private static function controllerJs(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 3).'/assets/controllers/map_plate_controller.js');
    }

    private static function template(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 3).'/templates/plate.html.twig');
    }
}
