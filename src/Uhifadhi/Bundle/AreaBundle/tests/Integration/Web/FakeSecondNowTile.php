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
 * A SECOND MODULE CONTRIBUTING ONE MORE FIGURE, so two live areas can be
 * UNALIKE.
 *
 * An installation does not run the same modules everywhere: one area has
 * patrols and incidents on, its neighbour only patrols. So the register's
 * operational columns — headed from the first live area — reach rows that
 * have nothing to say in some of them, and a register that read a row's
 * figures by POSITION either printed them under the wrong heading or asked
 * for an index the row does not have and took the page down.
 *
 * ONE TILE, WITH A LABEL OF ITS OWN, because what makes the shapes ragged is
 * the LABEL set differing and not the count. It contributes to whichever
 * module slug it is constructed with, so a suite switches it on for one area
 * and leaves the other alone.
 */
final readonly class FakeSecondNowTile implements NowTileProviderInterface
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
            new NowTile(index: 'IN·N2', moduleSlug: $this->slug, label: 'cases open', value: '4', priority: 40),
        ];
    }
}
