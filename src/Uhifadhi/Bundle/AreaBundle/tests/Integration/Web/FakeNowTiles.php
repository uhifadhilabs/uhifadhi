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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration\Web;

use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Overview\NowTile;
use Uhifadhi\Bundle\AreaBundle\Overview\NowTileProviderInterface;

/**
 * A MODULE'S NOW-TILE CONTRIBUTION, STOOD IN FOR.
 *
 * The register card's operational figures — its stat cells and its "out right
 * now" chip — are the same now-tiles the area overview draws, gathered through
 * {@see NowTileProviderInterface}. This bundle must depend on no real module, so
 * the suite ships its own contributor over the registry. What is being tested is that
 * the area page LAYS OUT a contributed figure on a card where the module is switched
 * on — not what any particular module counts.
 *
 * It returns three standing figures (the four-up grid fills with these beside the
 * the area page's own "modules live") and one LIVE figure (the card foots with it), so both
 * the stat cells and the "out right now" chip are exercised.
 */
final readonly class FakeNowTiles implements NowTileProviderInterface
{
    public function __construct(private string $slug)
    {
    }

    public function moduleSlug(): string
    {
        return $this->slug;
    }

    public function nowTilesFor(AreaOfInterest $area, \DateTimeImmutable $now): array
    {
        return [
            new NowTile(index: 'PL·N1', moduleSlug: $this->slug, label: 'patrols this wk', value: '23', priority: 10),
            new NowTile(index: 'IN·N1', moduleSlug: $this->slug, label: 'open incidents', value: '7', priority: 20),
            new NowTile(index: 'RO·N1', moduleSlug: $this->slug, label: 'team on duty', value: '14/22', priority: 30),
            new NowTile(index: 'PL·N2', moduleSlug: $this->slug, label: 'patrols out now', value: '3', tone: NowTile::TONE_HOT, live: true, priority: 5),
        ];
    }
}
