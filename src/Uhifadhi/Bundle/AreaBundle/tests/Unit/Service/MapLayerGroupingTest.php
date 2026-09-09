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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\AreaBundle\Overview\MapLayer;
use Uhifadhi\Bundle\AreaBundle\Service\AreaOverview;

/**
 * THE LEGEND IS GROUPED BY CONTRIBUTOR, and the grouping is a pure function over
 * the flat list the plate gathered — done in PHP so the legend template stays
 * renderable in a bare installation whose Twig carries no extra filters.
 */
#[CoversClass(AreaOverview::class)]
final class MapLayerGroupingTest extends TestCase
{
    private function aLayer(string $id, string $group, string $swatch = '#3ED9A8'): MapLayer
    {
        return new MapLayer(
            id: $id,
            moduleSlug: 'test',
            groupLabel: $group,
            label: $id,
            swatch: $swatch,
            features: ['type' => 'FeatureCollection', 'features' => []],
        );
    }

    public function testLayersAreCollapsedIntoOneGroupPerContributor(): void
    {
        $groups = AreaOverview::groupByContributor([
            $this->aLayer('patrols.live', 'Patrols'),
            $this->aLayer('patrols.buffer', 'Patrols'),
            $this->aLayer('incidents.open', 'Incidents'),
        ]);

        self::assertCount(2, $groups);
        self::assertSame('Patrols', $groups[0]['label']);
        self::assertCount(2, $groups[0]['layers']);
        self::assertSame('Incidents', $groups[1]['label']);
        self::assertCount(1, $groups[1]['layers']);
    }

    /** THE GROUP DOT IS DATA — it wears the first layer's own colour. */
    public function testTheGroupCarriesItsFirstLayersSwatch(): void
    {
        $groups = AreaOverview::groupByContributor([
            $this->aLayer('incidents.open', 'Incidents', '#C8553D'),
        ]);

        self::assertSame('#C8553D', $groups[0]['swatch']);
    }

    /** ORDER IS THE CONTRIBUTOR'S — group order follows first appearance. */
    public function testGroupOrderFollowsFirstAppearance(): void
    {
        $groups = AreaOverview::groupByContributor([
            $this->aLayer('incidents.open', 'Incidents'),
            $this->aLayer('patrols.live', 'Patrols'),
        ]);

        self::assertSame(['Incidents', 'Patrols'], array_column($groups, 'label'));
    }

    public function testNoLayersIsNoGroups(): void
    {
        self::assertSame([], AreaOverview::groupByContributor([]));
    }
}
