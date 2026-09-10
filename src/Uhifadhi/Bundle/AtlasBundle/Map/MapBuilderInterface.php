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

namespace Uhifadhi\Bundle\AtlasBundle\Map;

use Uhifadhi\Bundle\AtlasBundle\Model\AtlasMap;

/**
 * How a module gets a map.
 *
 * ONE ENTRY POINT, for the same reason UX Chart.js has one: a map that a module
 * built by hand is a map that draws its own imagery, wears its own controls and
 * disagrees with every other map in the product. Ask the builder and the plate
 * arrives already dressed — the deployment's imagery underneath, the atlas's
 * control stack, the atlas's fullscreen — with nothing on it yet.
 *
 * @see vendor/symfony/ux-chartjs/src/Builder/ChartBuilderInterface.php
 */
interface MapBuilderInterface
{
    public function createMap(): AtlasMap;
}
