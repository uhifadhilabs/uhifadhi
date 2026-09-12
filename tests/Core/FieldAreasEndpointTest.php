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

namespace Uhifadhi\Core\Tests\Core;

use Symfony\Component\HttpFoundation\Response;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;

/**
 * `GET /api/areas/mine` — everything a handset caches at sign-in so it can work
 * with no network afterwards: the areas the account may work in, their size, the
 * roster it may name on a record, and the boundary geometry it draws.
 *
 * "MINE" IS DECIDED BY THE PLATFORM'S OWN AUTHORITY and not by a query written
 * for this endpoint: an area is in the answer when `area.view` is granted FOR
 * THAT AREA, which is the same question every area screen asks. So an account
 * confined to one area is handed one area, and an account that may see nothing
 * is handed an empty list rather than the installation's whole estate.
 */
final class FieldAreasEndpointTest extends FieldApiTestCase
{
    private const string ENDPOINT = '/api/areas/mine';

    public function testNoTokenIsUnauthorized(): void
    {
        $this->get(self::ENDPOINT);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testARefusalCarriesTheApiErrorDocument(): void
    {
        $body = $this->get(self::ENDPOINT);

        self::assertSame(['code', 'message', 'retryable', 'details'], array_keys($body));
        self::assertSame('unauthorized', self::leaf($body, 'code'));
        self::assertFalse(self::leaf($body, 'retryable'));
    }

    public function testTheAnswerIsTheContractsExactDocument(): void
    {
        $area = $this->area('Northern Conservation Reserve');
        $ranger = $this->ranger();

        $body = $this->get(self::ENDPOINT, $this->tokenFor($ranger));

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertSame(['areas'], array_keys($body));
        self::assertCount(1, self::nested($body, 'areas'));

        $sent = self::nested($body, 'areas', 0);
        self::assertSame(['id', 'name', 'areaKm2', 'stations', 'team', 'boundary'], array_keys($sent));

        // The public address, never the sequential key: an area is a UUID
        // everywhere in this product, so a client round-trips a UUID.
        self::assertSame($area->getUuidString(), $sent['id']);
        self::assertSame('Northern Conservation Reserve', $sent['name']);
    }

    /**
     * MEASURED ON THE SPHEROID, in the database — the way a surveyor measures
     * ground rather than by multiplying degrees. The rectangle every area here is
     * drawn from is roughly 9,900 km², asserted as a range because the exact
     * figure is PostGIS's spheroid and not a number worth pinning to six digits.
     */
    public function testTheAreaIsMeasuredGeodesicallyInSquareKilometres(): void
    {
        $this->area('Northern Conservation Reserve');
        $body = $this->get(self::ENDPOINT, $this->tokenFor($this->ranger()));

        $km2 = self::leaf($body, 'areas', 0, 'areaKm2');

        self::assertIsFloat($km2);
        self::assertGreaterThan(9_000.0, $km2);
        self::assertLessThan(11_000.0, $km2);
    }

    /**
     * EMPTY, AND HONESTLY SO. This platform has no station record. Inventing one
     * from the station names typed on past work would hand a handset a list of
     * guesses dressed as a register, with positions nobody holds — so the key is
     * present, as the contract states, and the list is empty.
     */
    public function testStationsIsAnEmptyListUntilStationsAreModelled(): void
    {
        $this->area('Northern Conservation Reserve');
        $body = $this->get(self::ENDPOINT, $this->tokenFor($this->ranger()));

        self::assertSame([], self::nested($body, 'areas', 0, 'stations'));
    }

    /**
     * The roster, by name, with the identifier a handset sends back on a record:
     * the service number where there is one, the address otherwise. Whatever this
     * list calls somebody is exactly what a record's `team` may name.
     */
    public function testTheRosterNamesEverybodyByTheIdentifierARecordCarries(): void
    {
        $this->area('Northern Conservation Reserve');
        $this->officeStaff('Naomi', 'Kileo');
        $ranger = $this->ranger();

        $body = $this->get(self::ENDPOINT, $this->tokenFor($ranger));

        self::assertSame([
            ['id' => 'n.kileo@example.test', 'name' => 'Naomi Kileo'],
            ['id' => 'sl-0142', 'name' => 'Witness Mbise'],
        ], self::nested($body, 'areas', 0, 'team'));
    }

    /**
     * GeoJSON as an OBJECT in lon/lat order (RFC 7946), which is also PostGIS's
     * order.
     *
     * EITHER SPELLING OF THE RING IS CORRECT. Ground is stored as a MultiPolygon
     * because a gazetted edge is regularly more than one piece, and PostGIS hands
     * back a Polygon when it is only one; both are valid RFC 7946 and a client
     * accepts both, so pinning one here would be pinning an accident of the
     * fixture rather than the contract.
     */
    public function testTheBoundaryIsGeoJsonInLonLatOrder(): void
    {
        $this->area('Northern Conservation Reserve');
        $body = $this->get(self::ENDPOINT, $this->tokenFor($this->ranger()));

        $boundary = self::nested($body, 'areas', 0, 'boundary');

        self::assertContains($boundary['type'], ['Polygon', 'MultiPolygon']);

        // Longitude first: -30 is the ring's west edge, -3.6 its south. Compared
        // numerically because PostGIS writes a whole degree without a fraction and
        // JSON has one number type, so the west edge arrives as -30 and the south
        // as -3.6 — a difference of notation and not of value.
        $ring = 'Polygon' === $boundary['type']
            ? self::nested($boundary, 'coordinates', 0, 0)
            : self::nested($boundary, 'coordinates', 0, 0, 0);
        self::assertEqualsWithDelta(-30.0, $ring[0], 0.0001);
        self::assertEqualsWithDelta(-3.6, $ring[1], 0.0001);
    }

    /** An area whose edge has not been imported yet: null, not an empty object. */
    public function testAnAreaWithNoBoundaryIsSentANullBoundary(): void
    {
        $this->areaWithoutBoundary('Southern Block');

        $body = $this->get(self::ENDPOINT, $this->tokenFor($this->ranger()));

        self::assertNull(self::leaf($body, 'areas', 0, 'boundary'));
        self::assertSame(0.0, self::leaf($body, 'areas', 0, 'areaKm2'));
    }

    /** BY NAME, because a handset's picker must not reorder between two syncs. */
    public function testTheAreasAreOrderedByName(): void
    {
        $this->area('Western Corridor');
        $this->area('Eastern Escarpment');

        $body = $this->get(self::ENDPOINT, $this->tokenFor($this->ranger()));

        self::assertSame(
            ['Eastern Escarpment', 'Western Corridor'],
            array_column(self::nested($body, 'areas'), 'name'),
        );
    }

    /**
     * AN ACCOUNT CONFINED TO ONE AREA IS HANDED ONE AREA. Authority is the scope
     * of the position's department, and the voter compares it against each area
     * in turn — so this endpoint narrows exactly where every screen narrows.
     */
    public function testAnAreaLevelAccountIsHandedOnlyItsOwnArea(): void
    {
        $mine = $this->area('Northern Conservation Reserve');
        $this->area('Southern Block');

        $ranger = $this->ranger(department: $this->areaDepartment('Rangers', $mine));

        $body = $this->get(self::ENDPOINT, $this->tokenFor($ranger));

        self::assertSame(['Northern Conservation Reserve'], array_column(self::nested($body, 'areas'), 'name'));
    }

    /** Org-level authority is the absence of a boundary: every area. */
    public function testAnOrgLevelAccountIsHandedEveryArea(): void
    {
        $this->area('Northern Conservation Reserve');
        $this->area('Southern Block');

        $body = $this->get(self::ENDPOINT, $this->tokenFor($this->ranger()));

        self::assertCount(2, self::nested($body, 'areas'));
    }

    /**
     * AN EMPTY LIST IS AN ANSWER. An account that may see no area is told so
     * rather than refused: the contract has no "you have no areas" failure, and a
     * handset that met a 403 here would stop syncing instead of showing an empty
     * picker.
     */
    public function testAnAccountThatMaySeeNoAreaIsHandedAnEmptyList(): void
    {
        $this->area('Northern Conservation Reserve');
        $blind = $this->ranger('sl-0777', permissions: [PermissionEnum::ModuleView]);

        $body = $this->get(self::ENDPOINT, $this->tokenFor($blind));

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertSame([], self::nested($body, 'areas'));
    }
}
