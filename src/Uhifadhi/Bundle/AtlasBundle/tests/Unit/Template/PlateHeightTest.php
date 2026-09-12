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

namespace Uhifadhi\Bundle\AtlasBundle\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\AtlasBundle\Twig\MapPlateRuntime;

/**
 * A PLATE IS AS TALL AS IT SAYS IT IS, NEVER AS TALL AS ITS NEIGHBOUR.
 *
 * A plate lives in cards, grids and stretch rows. Sized with `min-height` and
 * `flex: 1` it grows to whatever the tallest sibling in the row happens to be,
 * which is how a case file's map came out over a thousand pixels tall next to a
 * long column of facts. So the height is a real `height`, off ONE custom
 * property with the plate's own default behind it, and the only thing allowed
 * to grow it is fullscreen.
 *
 * WHAT THAT HEIGHT SIZES IS THE MAP — the filter row and the imagery, the box
 * `.map-body` holds. The legend is drawn under that box and adds its own height
 * to the plate, so the number a screen states is the number of pixels of map it
 * gets.
 *
 * A TEXT CHECK OVER THE SHEET, and that is the limit of what it promises: it
 * catches the rule being written some other way, not a plate that renders
 * wrongly for some other reason. Rendered fidelity is a sweep, not a unit test.
 */
final class PlateHeightTest extends TestCase
{
    /** The height a plate takes when nothing overrides it — the imagery frame's own. */
    private const string DEFAULT_HEIGHT = 'min(58vh, 560px)';

    /**
     * THE DECLARED HEIGHT IS THE MAP'S — the filter row and the imagery under
     * it, which is the box `.map-body` is. The legend adds under that box, so a
     * plate is as tall as its declared height plus whatever its legend needs;
     * a screen that says 400px gets 400px of map, never 400px minus a legend.
     */
    public function testThePlateTakesItsHeightFromOneCustomProperty(): void
    {
        self::assertMatchesRegularExpression(
            '/\.map-plate > \.map-body \{[^}]*height: var\(--map-plate-height, '.preg_quote(self::DEFAULT_HEIGHT, '/').'\)/',
            self::stylesheet(),
        );
    }

    /**
     * AND THE PLATE ITSELF IS AS TALL AS WHAT IT HOLDS — `max-content`, which is
     * the map body's declared height plus the legend's own. CSS Box Sizing Level
     * 3, §5.1: "max-content — Use the max-content size in the relevant axis."
     * (https://www.w3.org/TR/css-sizing-3/#valdef-width-max-content). It is a
     * definite-enough height for the stretch refusal below and a height no
     * legend can be squeezed by.
     */
    public function testThePlateIsAsTallAsItsMapBodyAndItsLegendTogether(): void
    {
        self::assertMatchesRegularExpression(
            '/\.map-plate \{[^}]*height: max-content/',
            self::stylesheet(),
            'A plate that is not the height of its content is a plate whose legend is clipped or squeezed.',
        );
    }

    /** The map body fills a plate that is taller than it — fullscreen, or a card that grows it. */
    public function testTheMapBodyGrowsWithThePlate(): void
    {
        self::assertMatchesRegularExpression(
            '/\.map-plate > \.map-body \{[^}]*flex: 1 1 auto/',
            self::stylesheet(),
        );
    }

    /**
     * WHAT KEEPS A ROW FROM GROWING THE PLATE IS THE HEIGHT ITSELF, not an
     * alignment declaration. CSS Flexible Box Layout Level 1, §9.6 step 11:
     * "If a flex item has `align-self: stretch`, its computed cross size
     * property is `auto`, and neither of its cross-axis margins are `auto`, the
     * used outer cross size is the used cross size of its flex line […]
     * Otherwise, the used cross size is the item's hypothetical cross size."
     * The plate's cross size in a row is its `height`, and that is never `auto`
     * — so a stretch row cannot reach it.
     */
    public function testThePlateRefusesToStretchToItsRowOnTheStrengthOfItsHeight(): void
    {
        self::assertDoesNotMatchRegularExpression(
            '/\.map-plate \{[^}]*height: auto/',
            self::stylesheet(),
            'A plate whose height is auto is a plate a stretch row owns.',
        );
    }

    /**
     * AND THE PLATE IS THE WIDTH OF WHATEVER IT SITS IN. `align-self` names the
     * CROSS axis, which is the horizontal one inside a column — the shape every
     * card that stacks a plate under a heading has. So an `align-self` on the
     * plate cannot mean "do not grow taller" in a row without also meaning
     * "shrink to the imagery's own width" in a column, and the plate carries
     * none.
     */
    public function testThePlateCarriesNoSelfAlignmentThatWouldShrinkItInAColumn(): void
    {
        self::assertDoesNotMatchRegularExpression(
            '/\.map-plate \{[^}]*align-self:/',
            self::stylesheet(),
            'align-self on a plate shrinks it to its content width in every column card it sits in.',
        );
    }

    /** Fullscreen is the one thing that grows a plate. */
    public function testFullscreenIsTheOneThingThatGrowsAPlate(): void
    {
        self::assertStringContainsString('.map-plate:fullscreen { height: 100%;', self::stylesheet());
    }

    /**
     * The imagery caption class no template writes. A rule nothing selects is a
     * rule the next reader has to prove is dead before touching anything near it.
     */
    public function testTheSheetCarriesNoRuleNoTemplateSelects(): void
    {
        self::assertStringNotContainsString('.viewer .ol', self::stylesheet());
    }

    /**
     * A CUSTOM PROPERTY HANDED TO render_map() LANDS ON THE PLATE, not on the
     * map element: it is the plate that is being sized, and a custom property on
     * the canvas inside it would size nothing. What it lands as is asserted
     * through a real render, in the Twig integration test.
     */
    public function testTheRuntimeNamesTheCustomPropertyPrefixItLifts(): void
    {
        self::assertSame('--', MapPlateRuntime::CUSTOM_PROPERTY_PREFIX);
    }

    private static function stylesheet(): string
    {
        $sheet = file_get_contents(\dirname(__DIR__, 3).'/public/map.css');
        self::assertIsString($sheet);

        return $sheet;
    }
}
