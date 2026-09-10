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
 * How a layer's features are drawn, and therefore what its legend swatch looks
 * like.
 *
 * Three shapes and no more: a module states what the geometry MEANS, not how
 * thick the stroke is. Stroke widths, opacities and radii are the plate's, so
 * two modules cannot disagree about what "a line" looks like.
 */
enum LayerShape: string
{
    /** A stroke with no fill — a track, a route, a river. */
    case Line = 'line';

    /** A filled shape with a stroke — a zone, a block, a catchment. */
    case Fill = 'fill';

    /** A circle marker per feature — a station, a sighting, a sample. */
    case Point = 'point';
}
