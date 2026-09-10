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

namespace Uhifadhi\Bundle\AtlasBundle\Model;

/**
 * The ground a plate can be drawn on, and the entries its base-layer menu
 * offers.
 *
 * The values are the keys the basemap module builds layers under, so what a map
 * asks for in PHP and what the browser builds are one vocabulary.
 *
 * @see assets/basemaps.js
 */
enum BaseLayer: string
{
    /** The configured satellite imagery — esri, google or a deployment's own. */
    case Satellite = 'satellite';

    /** Street tiles, for the times a name matters more than the ground. */
    case Street = 'osm';
}
