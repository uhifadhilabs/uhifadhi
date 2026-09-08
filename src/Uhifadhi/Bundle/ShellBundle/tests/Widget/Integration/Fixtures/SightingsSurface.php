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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Widget\Integration\Fixtures;

use Uhifadhi\Bundle\ShellBundle\Widget\Model\Widget;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetCatalog;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetGroup;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetPreset;
use Uhifadhi\Bundle\ShellBundle\Widget\Registry\WidgetSurfaceInterface;

/**
 * A module standing in for every module that declares a dashboard.
 *
 * NOT A STUB: it impersonates nobody. It is an ordinary implementation of a
 * published interface, written here because the registry, the resolver and
 * `widget:prune` all need a surface to be about and this bundle ships none of
 * its own — which is the boundary these tests exist to hold.
 */
final class SightingsSurface implements WidgetSurfaceInterface
{
    /** What a stored row is keyed by. */
    public const string SURFACE = 'sightings';

    public function catalog(): WidgetCatalog
    {
        return new WidgetCatalog(
            self::SURFACE,
            [
                new WidgetGroup('counts', 'Counts', 'How much was seen, and when.'),
                new WidgetGroup('places', 'Places', 'Where the sightings fell.'),
            ],
            [
                new Widget('total', 'Total sightings', 'counts', 6, [12, 6, 3]),
                new Widget('by-species', 'By species', 'counts', 6, [12, 6]),
                new Widget('map', 'Where they were', 'places', 12, [12], false),
            ],
            [
                new WidgetPreset('wide', 'Wide', 'One thing at a time, full width.', ['total' => 12, 'map' => 12]),
            ],
        );
    }
}
