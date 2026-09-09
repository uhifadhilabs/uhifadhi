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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;

/**
 * WHETHER AN AREA HAS A BOUNDARY IS A QUESTION ABOUT ITS GEOMETRY, AND NOTHING
 * ELSE.
 *
 * The overview and settings screens once answered it from `source` — the
 * provenance string — and an area imported through the upload screen carries
 * source "upload", so a fully-gazetted area (its geom column holding a real
 * MultiPolygon) rendered "Boundary: upload", reading like a prompt to upload one.
 * The presence of a boundary is `geom`, so this is what the screens now ask.
 */
final class AreaBoundaryPresenceTest extends TestCase
{
    public function testAnAreaWithAStoredGeometryHasABoundaryWhateverItsProvenance(): void
    {
        $area = new AreaOfInterest()
            ->setName('Northern Conservation Reserve')
            ->setGeom('{"type":"MultiPolygon","coordinates":[[[[-29.5,-3.2],[-29.4,-3.2],[-29.4,-3.1],[-29.5,-3.2]]]]}')
            ->setSource('upload');

        self::assertTrue($area->hasBoundary());
    }

    public function testAnAreaWithNoStoredGeometryHasNoBoundary(): void
    {
        $area = new AreaOfInterest()->setName('A place with no gazetted edge yet');

        self::assertFalse($area->hasBoundary());
    }
}
