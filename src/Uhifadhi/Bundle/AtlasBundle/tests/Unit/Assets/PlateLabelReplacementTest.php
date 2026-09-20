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
 * A LABEL GOES WHERE ITS ZONE IS, INCLUDING AFTER THE PLATE CHANGES SIZE.
 *
 * THE DEFECT THIS PINS. A zone that names itself wears a PERMANENT tooltip,
 * and Leaflet places a permanent tooltip once — when it is opened — and moves
 * it again only on `zoom` and `viewreset`. Everything the plate does to catch
 * up with its real frame is neither: `invalidateSize({pan: false})` fires
 * `resize`, and the `fitBounds` that follows very often lands on the zoom and
 * the centre it was already at, so Leaflet moves nothing. The labels stay
 * pinned to pixels of a frame that no longer exists — on the organisation's
 * roster plate, composed onto a widget grid that narrows it after the map is
 * built, at NEGATIVE x, outside the plate — while the boundary underneath
 * them is drawn correctly, because a path is projected from coordinates on
 * every draw and a tooltip is not.
 *
 * WHY IT LOOKED LIKE A REFRESH FIXED IT. On a second load the card already
 * has its final width when the map is created, so there is no settle to be
 * out of date with. The bug is in the first paint only, which is the one
 * everybody sees.
 *
 * THE FIX IS AT THE ROOT AND IN ONE PLACE. `refit()` is the single funnel
 * every re-frame goes through — mount, the fullscreen toggle's resize, a
 * layer arriving, the ResizeObserver's settle, the map's own `resize` — so
 * re-placing the labels at the end of it covers fullscreen and swap without
 * either of them knowing about labels. It asks each tooltip to update itself
 * rather than firing a synthetic `viewreset`: `viewreset` is somebody else's
 * event with somebody else's listeners on it, and a plate that fired one to
 * move its own labels would be paying for every one of them.
 *
 * A TEXT CHECK OVER THE SHIPPED ASSET, for the reason PlateSwapVerbTest
 * states: there is no JS runner in this project, so what can be proved is
 * that the call is there, that it is on the path every re-frame takes, and
 * that it is reached on the path that returns early.
 */
final class PlateLabelReplacementTest extends TestCase
{
    /** The label is a permanent tooltip, which is the whole reason this is needed. */
    public function testAZoneLabelIsStillAPermanentTooltip(): void
    {
        self::assertStringContainsString(
            "permanent: true, direction: 'center', className: 'zone-label'",
            self::controllerJs(),
            'If a label stops being a permanent tooltip, this test is about nothing and should go.',
        );
    }

    /** The plate has one method whose job is putting them back where they belong. */
    public function testThePlateCanRePlaceItsOwnLabels(): void
    {
        $method = self::method('replaceLabels');

        self::assertStringContainsString('eachLayer', $method, 'Every layer on the map is asked, because a label belongs to a feature.');
        self::assertStringContainsString('getTooltip?.()?.update()', $method, 'A tooltip re-places itself; the plate does not compute pixels, and a layer with no tooltip at all is not an error.');
    }

    /** And it never takes the plate down with it — a label is not worth a page. */
    public function testRePlacingALabelCannotBreakThePlate(): void
    {
        self::assertStringContainsString('try {', self::method('replaceLabels'));
    }

    /**
     * IT IS ON EVERY PATH OUT OF THE FIT, and the one that matters most is
     * the early return: a plate with nothing valid to frame still had its
     * size invalidated a few lines above, so its labels are just as stale as
     * the ones on a plate that fitted.
     */
    public function testEveryPathOutOfTheFitRePlacesThem(): void
    {
        $refit = self::method('refit');

        self::assertSame(
            3,
            substr_count($refit, 'this.replaceLabels();'),
            'The invalid-bounds return, the fitBounds path and the single-point path each leave the frame changed.',
        );
    }

    /** The synthetic event the shortcut would have used is not fired. */
    public function testItDoesNotFireSomebodyElsesEvent(): void
    {
        self::assertStringNotContainsString(
            "fire('viewreset')",
            self::controllerJs(),
            'A plate moving its own labels does not wake every viewreset listener on the map.',
        );
    }

    /** One method's body, from its signature to its own closing brace. */
    private static function method(string $name): string
    {
        $js = self::controllerJs();
        $opening = '/\n    (?:async )?'.preg_quote($name, '/').'\((?:[^)]*)\)\s*\{/';
        self::assertSame(1, preg_match($opening, $js, $found, \PREG_OFFSET_CAPTURE), \sprintf('%s() is there to be read', $name));
        $from = (int) $found[0][1];
        $rest = substr($js, $from + \strlen((string) $found[0][0]));
        $end = strpos($rest, "\n    }\n");

        return false === $end ? $rest : substr($rest, 0, $end);
    }

    private static function controllerJs(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 3).'/assets/controllers/map_plate_controller.js');
    }
}
