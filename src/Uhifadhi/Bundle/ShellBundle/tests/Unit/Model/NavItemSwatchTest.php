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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Unit\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\ShellBundle\Model\NavItem;

/**
 * A ROW MAY CARRY A COLOUR IT OWNS, not only one a stylesheet names for it.
 *
 * TWO WAYS TO COLOUR A DOT, AND THEY ARE NOT THE SAME KIND OF THING. `tone` is
 * a CLASS a module declares in its own stylesheet — the module's identity hue,
 * fixed at build time, one rule per module. `swatch` is a VALUE the source
 * computed — a zone's hue comes from a palette by the zone's position in its
 * area's set, so there is no class to declare and no stylesheet that could
 * know how many there will be.
 *
 * A VALUE, BECAUSE A CLASS WOULD RESTATE THE PALETTE. The alternative was
 * eleven `.mdot.zone-1 … .zone-11` rules in a sheet, which puts the palette in
 * two places — and the configure page's plate, its key and its cards all
 * already read it from one, so the second copy is the one that goes stale.
 *
 * THE SHELL STILL INTERPRETS NEITHER. Both are passthroughs: one printed into
 * `class`, the other into `style`, and the shell names no module and no zone
 * either way.
 */
#[CoversClass(NavItem::class)]
final class NavItemSwatchTest extends TestCase
{
    public function testARowCarriesNoColourByDefault(): void
    {
        $row = new NavItem(label: 'Crater');

        self::assertNull($row->tone);
        self::assertNull($row->swatch);
    }

    public function testARowMayCarryAHueItComputed(): void
    {
        $row = new NavItem(label: 'Crater', swatch: '#3FC7D4');

        self::assertSame('#3FC7D4', $row->swatch);
    }

    /** The two live side by side: a module keeps its class, a zone brings its value. */
    public function testAToneAndASwatchAreIndependent(): void
    {
        $module = new NavItem(label: 'Incidents', tone: 'inc');
        $zone = new NavItem(label: 'Crater', swatch: '#3FC7D4');

        self::assertSame('inc', $module->tone);
        self::assertNull($module->swatch);
        self::assertNull($zone->tone);
        self::assertSame('#3FC7D4', $zone->swatch);
    }

    /**
     * ONLY A COLOUR, AND THE SHELL CHECKS. The value is printed into a `style`
     * attribute, so a source that handed over anything else would be writing
     * CSS into the page through a hole the shell opened for it.
     */
    public function testAValueThatIsNotAHexColourIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new NavItem(label: 'Crater', swatch: 'red; background:url(x)');
    }

    public function testBothHexLengthsAreAccepted(): void
    {
        self::assertSame('#fff', new NavItem(label: 'A', swatch: '#fff')->swatch);
        self::assertSame('#3FC7D4', new NavItem(label: 'B', swatch: '#3FC7D4')->swatch);
    }
}
