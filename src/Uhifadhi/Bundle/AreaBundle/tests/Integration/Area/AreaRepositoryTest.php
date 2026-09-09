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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration\Area;

use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Repository\AreaOfInterestRepository;
use Uhifadhi\Bundle\AreaBundle\Tests\Integration\IntegrationTestCase;

/**
 * THE TWO THINGS ANYTHING HOLDING AN AREA NEEDS: find it by the identifier the
 * product actually uses, and measure it on the spheroid.
 *
 * The measuring is not this repository's code — it is
 * {@see \FundiStadi\PostGISBundle\Repository\SpatialEntityRepository}, extended
 * rather than reimplemented, which is why there is no SQL in this module. It is
 * exercised here anyway: a base class that was never called against a real
 * PostGIS from this entity's metadata would be a geometry column nobody has
 * proved is a geometry column.
 */
final class AreaRepositoryTest extends IntegrationTestCase
{
    private function areas(): AreaOfInterestRepository
    {
        $repository = self::getContainer()->get('test_public.area.repository');
        \assert($repository instanceof AreaOfInterestRepository);

        return $repository;
    }

    public function testAnAreaIsFoundByThePublicIdentifier(): void
    {
        $area = $this->anArea('Northern Conservation Reserve');
        $uuid = $area->getUuidString();
        self::assertNotNull($uuid);

        $this->em->clear();

        $found = $this->areas()->findOneByUuid($uuid);

        self::assertInstanceOf(AreaOfInterest::class, $found);
        self::assertSame('Northern Conservation Reserve', $found->getName());
    }

    public function testAnUnknownIdentifierIsNullAndNotAnError(): void
    {
        self::assertNull($this->areas()->findOneByUuid('01234567-89ab-7cde-8f01-23456789abcd'));
    }

    public function testAMalformedIdentifierIsNullRatherThanAnException(): void
    {
        self::assertNull($this->areas()->findOneByUuid('not-a-uuid'));
    }

    /**
     * MEASURED ON THE SPHEROID, in the database. The rectangle every test here
     * uses spans one degree of longitude by 0.8 of latitude around 3°S, which is
     * roughly 9,900 km² — asserted as a range because the exact figure is
     * PostGIS's spheroid, not a number worth pinning to six digits.
     */
    public function testTheBoundaryIsMeasuredGeodesically(): void
    {
        $this->anArea();

        $km2 = $this->areas()->stAreaKm2();

        self::assertGreaterThan(9_000.0, $km2);
        self::assertLessThan(11_000.0, $km2);
    }
}
