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

namespace Uhifadhi\Bundle\AtlasBundle\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\AtlasBundle\Model\LayerStyle;

/**
 * A STYLE STATES ONLY WHAT IT CHANGES.
 *
 * The plate already has one answer per shape for what a line, a fill and a
 * point look like. A style is what a module says ON TOP of that answer, so an
 * unstated property must not travel: a serialised `null` would arrive in the
 * browser as an instruction to unset a colour nobody asked to unset.
 */
final class LayerStyleTest extends TestCase
{
    public function testAnEmptyStyleStatesNothing(): void
    {
        self::assertSame([], new LayerStyle()->toArray());
    }

    public function testEveryStatementReachesTheBrowserUnderLeafletsOwnName(): void
    {
        $style = new LayerStyle()
            ->color('#E5C15A')
            ->weight(3.0)
            ->opacity(0.8)
            ->fill(true)
            ->fillColor('#0A0F0C')
            ->fillOpacity(0.25)
            ->dashArray('4 3')
            ->radius(7.0)
            ->zIndex(640)
        ;

        self::assertSame([
            'color' => '#E5C15A',
            'weight' => 3.0,
            'opacity' => 0.8,
            'fill' => true,
            'fillColor' => '#0A0F0C',
            'fillOpacity' => 0.25,
            'dashArray' => '4 3',
            'radius' => 7.0,
            'zIndex' => 640,
        ], $style->toArray());
    }

    /** Switching the fill OFF is a statement, and must survive the trip. */
    public function testFillOffIsStatedRatherThanOmitted(): void
    {
        self::assertSame(['fill' => false], new LayerStyle()->fill(false)->toArray());
    }

    /** Each statement answers a fresh object, so a shared base cannot be edited from a rule. */
    public function testAStatementLeavesTheStyleItWasMadeFromAlone(): void
    {
        $base = new LayerStyle()->weight(2.0);
        $heavier = $base->weight(4.0);

        self::assertSame(['weight' => 2.0], $base->toArray());
        self::assertSame(['weight' => 4.0], $heavier->toArray());
    }

    /** The named arguments are the same vocabulary, for a style stated in one breath. */
    public function testAStyleMayBeStatedWithNamedArguments(): void
    {
        self::assertSame(
            ['color' => '#B9C8BD', 'dashArray' => '2 4'],
            new LayerStyle(color: '#B9C8BD', dashArray: '2 4')->toArray(),
        );
    }
}
