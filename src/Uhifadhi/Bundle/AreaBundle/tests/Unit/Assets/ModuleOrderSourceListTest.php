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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Unit\Assets;

use PHPUnit\Framework\TestCase;

/**
 * THE ORDER IS WHICHEVER LIST WAS DRAGGED IN — NOT WHICHEVER IS FIRST.
 *
 * The shop draws the active set twice, as a pin bar and as detailed rows, and
 * both are `list` targets of the same controller. So "the order" is ambiguous
 * unless the controller says which list it means: a drag moves rows in ONE of
 * them, and the other still holds the order it had a moment ago.
 *
 * Reading a fixed list — `listTargets[0]`, the pin bar, because it comes first
 * in the template — answers the question for pin-bar drags only. A drag in the
 * rows is then mirrored FROM the bar it never touched and snaps back, and the
 * order posted to the reorder route is the one the person did not express.
 *
 * So: the list the drag started in is recorded at `dragstart`, `order()` reads
 * that list, `mirror()` re-sorts every OTHER list to match it, and `persist()`
 * posts it. Either list drags, and both lists and the server agree.
 *
 * This is a text-level seam check, which is the limit of what it can promise:
 * there is no JavaScript runner in the core, so it pins WHERE the controller
 * takes its order from, not that a browser's drag lands the row where the
 * pointer was released.
 *
 * @see ../../../assets/controllers/module_order_controller.js
 * @see ../../Integration/Web/AreaModulesTest.php — the route end of it
 */
final class ModuleOrderSourceListTest extends TestCase
{
    public function testTheOrderIsReadFromTheListTheDragHappenedIn(): void
    {
        $js = self::controller();

        self::assertMatchesRegularExpression(
            '/start\(row, list\) \{[^}]*this\.draggedList = list;/',
            $js,
            'The list a drag started in is not recorded when it starts.',
        );
        self::assertMatchesRegularExpression(
            '/order\(\) \{\s*return this\.draggedList\b/',
            $js,
            'The order is not read from the list the drag happened in.',
        );
        self::assertStringNotContainsString(
            'listTargets[0]',
            $js,
            'The order may not come from whichever list the template draws first.',
        );
    }

    public function testMirroringWritesThatOrderToEveryOtherList(): void
    {
        self::assertMatchesRegularExpression(
            '/mirror\(\) \{.*?this\.listTargets\s*\n?\s*\.filter\(\(list\) => list !== this\.draggedList\)/s',
            self::controller(),
            'Mirroring does not leave the dragged list alone.',
        );
    }

    public function testWhatIsPostedIsThatSameOrder(): void
    {
        self::assertMatchesRegularExpression(
            '/persist\(\) \{.*?this\.order\(\)\.forEach/s',
            self::controller(),
            'The reorder route is posted an order from somewhere else.',
        );
    }

    private static function controller(): string
    {
        $js = file_get_contents(\dirname(__DIR__, 3).'/assets/controllers/module_order_controller.js');
        self::assertIsString($js);

        return $js;
    }
}
