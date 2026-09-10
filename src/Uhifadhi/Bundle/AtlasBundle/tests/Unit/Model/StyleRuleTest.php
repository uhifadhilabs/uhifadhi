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
use Uhifadhi\Bundle\AtlasBundle\Exception\LayerException;
use Uhifadhi\Bundle\AtlasBundle\Model\StyleRule;

/**
 * A RULE IS A CONDITION ON A FEATURE'S OWN PROPERTIES AND THE STYLE IT EARNS.
 *
 * No callback crosses the wire — the condition is a property name and the
 * values that satisfy it, which is a thing JSON can carry and a browser can
 * evaluate without being handed code.
 */
final class StyleRuleTest extends TestCase
{
    public function testARuleNamesAPropertyOneValueAndTheStyleItEarns(): void
    {
        self::assertSame(
            ['property' => 'status', 'values' => ['closed'], 'style' => ['fill' => false]],
            StyleRule::when('status', 'closed')->fill(false)->toArray(),
        );
    }

    public function testARuleMayNameSeveralValues(): void
    {
        self::assertSame(
            ['property' => 'severity', 'values' => ['high', 'critical'], 'style' => ['dashArray' => '4 3']],
            StyleRule::when('severity', ['high', 'critical'])->dashArray('4 3')->toArray(),
        );
    }

    /** A boolean property is written the way the browser will read it back. */
    public function testABooleanValueTravelsAsABoolean(): void
    {
        self::assertSame(
            ['property' => 'open', 'values' => [true], 'style' => ['fillOpacity' => 0.9]],
            StyleRule::when('open', true)->fillOpacity(0.9)->toArray(),
        );
    }

    public function testEveryStyleStatementIsAvailableOnARule(): void
    {
        $rule = StyleRule::when('kind', 'buffer')
            ->color('#3ED9A8')
            ->weight(1.0)
            ->opacity(0.5)
            ->fillColor('#3ED9A8')
            ->fillOpacity(0.12)
            ->radius(9.0)
            ->zIndex(390)
        ;

        self::assertSame([
            'color' => '#3ED9A8',
            'weight' => 1.0,
            'opacity' => 0.5,
            'fillColor' => '#3ED9A8',
            'fillOpacity' => 0.12,
            'radius' => 9.0,
            'zIndex' => 390,
        ], $rule->toArray()['style']);
    }

    /** A rule that names no property can match nothing, so it is refused where it was written. */
    public function testARuleWithNoPropertyIsRefused(): void
    {
        $this->expectException(LayerException::class);

        StyleRule::when('', 'closed');
    }

    /** A rule that names no value is the same fault said differently. */
    public function testARuleWithNoValueIsRefused(): void
    {
        $this->expectException(LayerException::class);

        StyleRule::when('status', []);
    }

    public function testAStatementLeavesTheRuleItWasMadeFromAlone(): void
    {
        $rule = StyleRule::when('status', 'closed');
        $filled = $rule->fill(false);

        self::assertSame([], $rule->toArray()['style']);
        self::assertSame(['fill' => false], $filled->toArray()['style']);
    }
}
