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

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * A PLATE FILLS ITS FRAME WITH WHAT IT IS ABOUT.
 *
 * TWO THINGS HAD TO BE TRUE AND NEITHER WAS. The plate must know what it is
 * about — that is the subject, stated in PHP and carried in the payload — and
 * it must be able to zoom finely enough to reach the frame: measured on the
 * zones tab, an area fitted at whole zoom levels drew its boundary at half
 * the plate's height, because one level further in overflowed the card and
 * Leaflet's default step is a factor of two.
 *
 * SO THE PLATE ZOOMS IN QUARTER LEVELS, and the +/- controls step by the
 * same amount — a control that moved by a whole level on a map that fits by
 * a quarter is two different ideas of a zoom.
 *
 * A TEXT CHECK OVER THE SHIPPED ASSET, and that is its limit: it catches the
 * options being dropped or the two falling out of step. Whether the fitted
 * plate LOOKS right is a rendered check.
 */
#[CoversNothing]
final class PlateFitTest extends TestCase
{
    public function testThePlateZoomsInFractionsSoAFitCanReachTheFrame(): void
    {
        $controller = self::controller();

        self::assertMatchesRegularExpression(
            '/const ZOOM_SNAP = 0\.25;/',
            $controller,
            'Whole zoom levels cannot fill a frame: one is too far in and the next is half the size.',
        );
        self::assertStringContainsString('zoomSnap: ZOOM_SNAP,', $controller);
        self::assertStringContainsString('zoomDelta: ZOOM_SNAP,', $controller);
    }

    /** The padding between the subject and the plate's edge is stated once. */
    public function testThePaddingIsOneNamedValue(): void
    {
        $controller = self::controller();

        self::assertMatchesRegularExpression('/const FIT_PADDING = \[26, 26\];/', $controller);
        self::assertStringContainsString('padding: FIT_PADDING', $controller);
        self::assertSame(
            1,
            preg_match_all('/padding: FIT_PADDING/', $controller),
            'One fit, one padding — a second copy is how two plates come to be inset differently.',
        );
    }

    /**
     * AND THE FIT IS TAKEN AGAIN once the card has finished being laid out.
     * The first fit happens while the filter row and the legend are still
     * taking their height, so the frame it measured is not the frame it ends
     * up in — which is the other half of why the boundary overflowed.
     */
    public function testThePlateSettlesIntoTheFrameItEndsUpIn(): void
    {
        $controller = self::controller();

        self::assertStringContainsString('invalidateSize', $controller);
        self::assertStringContainsString('requestAnimationFrame(this.settle)', $controller);
        self::assertStringContainsString('new ResizeObserver(', $controller);
        self::assertStringContainsString('this.frameWatch?.disconnect();', $controller);
    }

    private static function controller(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 3).'/assets/controllers/map_plate_controller.js');
    }
}
