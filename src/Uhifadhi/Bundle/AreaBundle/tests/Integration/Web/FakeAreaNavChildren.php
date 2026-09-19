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

use Symfony\Component\HttpFoundation\RequestStack;
use Uhifadhi\Contracts\Shell\AreaNavChild;
use Uhifadhi\Contracts\Shell\AreaNavChildrenInterface;

/**
 * ANOTHER BUNDLE UNFOLDING ONE OF AN AREA'S SCREENS, tagged the way the team
 * bundle tags the departments under an area. It stands in for every such
 * contribution: the area bundle never learns what the rungs are about.
 */
final readonly class FakeAreaNavChildren implements AreaNavChildrenInterface
{
    public function __construct(private RequestStack $requests)
    {
    }

    public function screenRoute(): string
    {
        return 'area_stations';
    }

    public function childrenFor(string $areaUuid, string $areaName): array
    {
        // A CONTRIBUTOR LIGHTS ITS OWN ROWS, and only where its page is:
        // one that lit a rung everywhere would accent the tree on every
        // page in the product.
        $here = str_ends_with($this->requests->getCurrentRequest()?->getPathInfo() ?? '', '/stations');

        return [
            new AreaNavChild('Contributed one', '/areas/'.$areaUuid.'/stations?focus=1', $here),
            new AreaNavChild('Contributed two', '/areas/'.$areaUuid.'/stations?focus=2'),
        ];
    }
}
