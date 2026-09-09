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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration\Zone;

use Doctrine\ORM\Mapping\ManyToOneAssociationMapping;
use Symfony\Component\Uid\UuidV7;
use Uhifadhi\Bundle\AreaBundle\Entity\Zone;
use Uhifadhi\Bundle\AreaBundle\Tests\Integration\IntegrationTestCase;

/**
 * WHAT A ZONE IS, against a real PostGIS database: a named subdivision of ONE
 * area, its own multipolygon, addressed by uuid like everything else a URL can
 * name.
 *
 * Zones live INSIDE areas — an area is the axis the product is filed under, and
 * a zone is the spatial lens across it. That containment is not a convention
 * here, it is a NOT NULL foreign key that cascades: delete the area and its
 * zones go with it, because a zone of nothing is not a record anybody can read.
 */
final class ZonePersistenceTest extends IntegrationTestCase
{
    public function testAZoneRoundTripsAndBelongsToItsArea(): void
    {
        $area = $this->anArea('Northern Conservation Reserve');
        $zone = $this->aZone($area, 'Northern Highlands', self::A_WEST_HALF);

        $id = $zone->getId();
        self::assertNotNull($id);
        $this->em->clear();

        $reloaded = $this->em->find(Zone::class, $id);
        self::assertInstanceOf(Zone::class, $reloaded);
        self::assertSame('Northern Highlands', $reloaded->getName());
        self::assertSame($area->getId(), $reloaded->getArea()?->getId());

        $geom = $reloaded->getGeom();
        self::assertNotNull($geom);
        /** @var array{type?: string} $decoded */
        $decoded = json_decode($geom, true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('MultiPolygon', $decoded['type'] ?? null);
    }

    /**
     * THE TABLE NAME IS PART OF THE PROMISE, the same one `area_of_interest`
     * carries: an installation's migration history is written against it, so it
     * is stated rather than derived and asserted rather than assumed.
     */
    public function testItStoresInTheZoneTable(): void
    {
        self::assertSame('zone', $this->em->getClassMetadata(Zone::class)->getTableName());
    }

    public function testItIsStampedWithATimeOrderedUuidOnFirstPersist(): void
    {
        $zone = $this->aZone($this->anArea(), 'North', self::A_WEST_HALF);

        self::assertInstanceOf(UuidV7::class, $zone->getUuid());
        self::assertTrue(
            (bool) $this->em->getClassMetadata(Zone::class)->getFieldMapping('uuid')->unique,
            'a zone is addressed by uuid in a URL; a duplicate is a wrong page',
        );
    }

    /**
     * NAMES ARE UNIQUE PER AREA, NOT GLOBALLY — two areas may each have a
     * "North", and an installation with several areas would otherwise be forced
     * to invent prefixes nobody says out loud.
     */
    public function testTwoAreasMayEachHaveAZoneOfTheSameName(): void
    {
        $first = $this->anArea('First Area');
        $second = $this->anArea('Second Area');

        $this->aZone($first, 'North', self::A_WEST_HALF);
        $this->aZone($second, 'North', self::A_WEST_HALF);

        self::assertCount(2, $this->em->getRepository(Zone::class)->findBy(['name' => 'North']));
    }

    public function testOneAreaCannotHaveTwoZonesOfTheSameName(): void
    {
        $area = $this->anArea();
        $this->aZone($area, 'North', self::A_WEST_HALF);

        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);
        $this->aZone($area, 'North', self::A_EAST_HALF);
    }

    /**
     * THE ZONE LIVES AND DIES WITH ITS AREA. The cascade is in the database, not
     * in application code, so a delete issued by a console command, a fixture or
     * psql leaves no orphan behind.
     */
    public function testDeletingAnAreaTakesItsZonesWithIt(): void
    {
        $area = $this->anArea();
        $this->aZone($area, 'North', self::A_WEST_HALF);

        $mapping = $this->em->getClassMetadata(Zone::class)->getAssociationMapping('area');
        self::assertInstanceOf(ManyToOneAssociationMapping::class, $mapping);
        $join = $mapping->joinColumns[0];
        self::assertFalse($join->nullable, 'a zone of no area is not a record anybody can read');
        self::assertSame('CASCADE', $join->onDelete);

        // And the cascade is proved rather than merely declared: nothing here
        // configures an ORM-level cascade, so this remove only succeeds because
        // the DATABASE carries the rule.
        $this->em->remove($area);
        $this->em->flush();
        $this->em->clear();

        self::assertCount(0, $this->em->getRepository(Zone::class)->findAll());
    }
}
