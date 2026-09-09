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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Unit\Overview;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\AreaBundle\Overview\AttentionItem;
use Uhifadhi\Bundle\AreaBundle\Overview\AttentionSeverity;
use Uhifadhi\Bundle\AreaBundle\Overview\MapLayer;
use Uhifadhi\Bundle\AreaBundle\Overview\NowTile;

/**
 * WHAT A CONTRIBUTION REFUSES TO BE. These are the rules that decide whether a
 * bad contribution fails in the module that built it — where somebody can fix
 * it — or in the area page's template, on the page an area manager opens at 07:00.
 */
final class ContributionValueTest extends TestCase
{
    public function testANowTileNeedsAnIndexAModuleAndALabel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new NowTile(index: '', moduleSlug: 'patrols', label: 'Out now', value: '3');
    }

    /** A tone the strip does not draw is a plate rendered naked; it fails here instead. */
    public function testANowTileRefusesAToneTheStripDoesNotDraw(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new NowTile(index: 'PL·N1', moduleSlug: 'patrols', label: 'Out now', value: '3', tone: 'screaming');
    }

    public function testTheThreeTonesAreAccepted(): void
    {
        foreach ([NowTile::TONE_PLAIN, NowTile::TONE_HOT, NowTile::TONE_BAD] as $tone) {
            $tile = new NowTile(index: 'PL·N1', moduleSlug: 'patrols', label: 'Out now', value: '3', tone: $tone);
            self::assertSame($tone, $tile->tone);
        }
    }

    /**
     * SORTED BY URGENCY, NEVER BY MODULE — so the three steps have to rank, and
     * a module says which of three things it means rather than inventing a scale
     * the next module will disagree with.
     */
    public function testSeverityRanksLoudestFirst(): void
    {
        $shuffled = [AttentionSeverity::Watch, AttentionSeverity::Now, AttentionSeverity::Soon];
        usort($shuffled, static fn (AttentionSeverity $a, AttentionSeverity $b) => $a->rank() <=> $b->rank());

        self::assertSame(
            [AttentionSeverity::Now, AttentionSeverity::Soon, AttentionSeverity::Watch],
            $shuffled,
        );
    }

    /** The rail's modifier is the severity's own word — one vocabulary, not two. */
    public function testSeverityNamesTheClassTheRowWears(): void
    {
        self::assertSame('now', AttentionSeverity::Now->cssClass());
        self::assertSame('watch', AttentionSeverity::Watch->cssClass());
    }

    public function testAnAttentionItemNeedsSomewhereToGo(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AttentionItem(
            severity: AttentionSeverity::Now,
            moduleSlug: 'patrols',
            moduleLabel: 'Patrols',
            headline: 'P-0145 has not pinged.',
            kind: 'live position',
            ageLabel: '2 h 10',
            ageSeconds: 7800,
            url: '',
        );
    }

    public function testAnAttentionItemCannotBeYoungerThanNothing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AttentionItem(
            severity: AttentionSeverity::Now,
            moduleSlug: 'patrols',
            moduleLabel: 'Patrols',
            headline: 'P-0145 has not pinged.',
            kind: 'live position',
            ageLabel: '2 h 10',
            ageSeconds: -1,
            url: '/patrols/1',
        );
    }

    /**
     * A LAYER WITH NOTHING TO DRAW STILL SHIPS ITS LEGEND — so the empty answer
     * is an empty FeatureCollection, and anything that is not one is refused
     * here rather than walked in the area page's template.
     */
    public function testAMapLayerWithNothingToDrawIsAnEmptyCollectionNotAnAbsence(): void
    {
        $layer = new MapLayer(
            id: 'patrols.tracks',
            moduleSlug: 'patrols',
            groupLabel: 'Patrols',
            label: "Today's tracks",
            swatch: '#1f9d55',
            features: ['type' => 'FeatureCollection', 'features' => []],
            style: MapLayer::STYLE_LINE,
        );

        self::assertSame([], $layer->features['features']);
        self::assertTrue($layer->on);
    }

    public function testAMapLayerRefusesAnythingThatIsNotAFeatureCollection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MapLayer(
            id: 'patrols.tracks',
            moduleSlug: 'patrols',
            groupLabel: 'Patrols',
            label: "Today's tracks",
            swatch: '#1f9d55',
            features: ['type' => 'Feature'],
        );
    }

    /** The `features` list is what the plate, the dock and the legend all walk. */
    public function testAMapLayerRefusesACollectionWithNoFeaturesList(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MapLayer(
            id: 'patrols.tracks',
            moduleSlug: 'patrols',
            groupLabel: 'Patrols',
            label: "Today's tracks",
            swatch: '#1f9d55',
            features: ['type' => 'FeatureCollection'],
        );
    }

    public function testAMapLayerRefusesASwatchStyleTheLegendDoesNotDraw(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MapLayer(
            id: 'patrols.tracks',
            moduleSlug: 'patrols',
            groupLabel: 'Patrols',
            label: "Today's tracks",
            swatch: '#1f9d55',
            features: ['type' => 'FeatureCollection', 'features' => []],
            style: 'dotted',
        );
    }
}
