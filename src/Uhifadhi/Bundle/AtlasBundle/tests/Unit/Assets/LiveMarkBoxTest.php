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
 * THE BREATHING RING IS NEVER CLIPPED.
 *
 * A live position is drawn as a marker, and a marker has only the box it is
 * given: an SVG clips to its own viewport, so a box sized to the DOT cut the
 * ring off at the top, the bottom and the left, and the pulse came out as a
 * square-ish flicker rather than the round one the design draws. The
 * design\'s own dots live inside the plate\'s big SVG, where there is room —
 * which is exactly why the defect did not show there.
 *
 * SO THE BOX IS SIZED BY THE RING AT FULL BREATH, and this is the arithmetic
 * that says so, checked against the numbers the controller actually ships. It
 * is computed rather than asserted as a literal: somebody changing the radius
 * or the keyframe\'s scale gets a failure here instead of a clipped ring on a
 * test bed.
 */
final class LiveMarkBoxTest extends TestCase
{
    /** The ring\'s widest moment, from the sheet\'s own keyframe. */
    private const float FULL_BREATH = 2.05;

    /** And the stale ring\'s, which is still and smaller. */
    private const float STALE = 1.35;

    /**
     * THE BOX IS SIZED FROM THE RING'S SCALE, in the controller and not only
     * in this test's arithmetic. Without this the check below would pass on
     * a controller that sized the box to the dot: it would be measuring a
     * formula the test itself had written.
     */
    public function testTheControllerSizesTheBoxByTheRingAndNotByTheDot(): void
    {
        self::assertMatchesRegularExpression(
            '/const LIVE_REACH = Math\.ceil\(LIVE_RING_SCALE \* \(LIVE_R \+ LIVE_RING_STROKE \/ 2\)\);/',
            self::controllerJs(),
            'The reach is the ring at full breath. Sized to the dot, the pulse is clipped square.',
        );
        self::assertMatchesRegularExpression('/cx: LIVE_REACH,\s*\n\s*cy: LIVE_REACH,/', self::controllerJs());
        self::assertStringContainsString('iconAnchor: [cx, cy],', self::controllerJs(), 'the anchor stays on the core');
    }

    public function testTheBoxHoldsTheRingAtEveryPointOfTheKeyframe(): void
    {
        $mark = self::liveMark();
        $reach = self::FULL_BREATH * ($mark['r'] + self::stroke() / 2);

        // 0%, 35% and 70% of lv-breathe: scale runs 1 → 2.05 and stops.
        foreach ([1.0, (1.0 + self::FULL_BREATH) / 2, self::FULL_BREATH] as $scale) {
            $at = $scale * ($mark['r'] + self::stroke() / 2);

            self::assertGreaterThanOrEqual($at, (float) $mark['cy'], \sprintf('the ring at %.2f× fits above the core', $scale));
            self::assertGreaterThanOrEqual($at, (float) $mark['height'] - $mark['cy'], 'and below it');
            self::assertGreaterThanOrEqual($at, (float) $mark['cx'], 'and to its left');
        }

        self::assertGreaterThanOrEqual($reach, (float) $mark['cy'], 'the box is sized by the ring, not by the dot');
    }

    /** The stale ring is still and smaller, and fits inside the same box. */
    public function testTheStaleRingFitsToo(): void
    {
        $mark = self::liveMark();
        $at = self::STALE * ($mark['r'] + self::stroke() / 2);

        self::assertGreaterThanOrEqual($at, (float) $mark['cy']);
        self::assertGreaterThanOrEqual($at, (float) $mark['cx']);
    }

    /** And the age label still has its room to the right of the dot. */
    public function testTheAgeLabelStillHasItsRoom(): void
    {
        $mark = self::liveMark();

        self::assertGreaterThanOrEqual(
            30,
            (float) $mark['width'] - ($mark['cx'] + $mark['r'] + 3),
            '"4 h 21" at 8px needs about thirty pixels beside the dot',
        );
    }

    /**
     * AND THE BOX DOES NOT STEAL THE CURSOR. It is nearly four times the dot
     * now, so a marker that captured clicks over all of it would take them
     * from whatever is under it on the map.
     */
    public function testTheBoxPassesPointerEventsThroughAndTheDotTakesThemBack(): void
    {
        $css = (string) file_get_contents(\dirname(__DIR__, 3).'/public/map.css');

        self::assertStringContainsString('.leaflet-marker-icon > svg { overflow: visible; pointer-events: none; }', $css);
        self::assertStringContainsString('.lv-core,', $css);
        self::assertMatchesRegularExpression('/\.lv-ini \{ pointer-events: auto; \}/', $css);
    }

    /**
     * The numbers the controller ships, read out of it: the box is computed
     * there and this checks the computation rather than repeating it.
     *
     * @return array{width: float, height: float, cx: float, cy: float, r: float}
     */
    private static function liveMark(): array
    {
        $js = self::controllerJs();
        $scale = self::constantOf($js, 'LIVE_RING_SCALE');
        $stroke = self::constantOf($js, 'LIVE_RING_STROKE');
        $r = self::constantOf($js, 'LIVE_R');
        $room = self::constantOf($js, 'LIVE_AGE_ROOM');

        self::assertSame(self::FULL_BREATH, $scale, 'the keyframe and the box have to agree about full breath');

        $reach = (float) ceil($scale * ($r + $stroke / 2));

        return [
            'width' => $reach + $r + 3 + $room,
            'height' => $reach * 2,
            'cx' => $reach,
            'cy' => $reach,
            'r' => $r,
        ];
    }

    private static function stroke(): float
    {
        return self::constantOf(self::controllerJs(), 'LIVE_RING_STROKE');
    }

    private static function constantOf(string $js, string $name): float
    {
        $stated = 1 === preg_match('/const '.preg_quote($name, '/').' = ([0-9.]+);/', $js, $found)
            ? $found[1]
            : null;

        self::assertNotNull($stated, $name.' is stated in the controller');

        return (float) $stated;
    }

    private static function controllerJs(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 3).'/assets/controllers/map_plate_controller.js');
    }
}
