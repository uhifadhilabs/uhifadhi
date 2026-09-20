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

namespace Uhifadhi\Bundle\AreaBundle\Devkit;

/**
 * THE DEMO GROUND, WRITTEN ONCE AND READ BY THREE PROVIDERS — the areas, the
 * zoning scheme that subdivides each of them, and the posts standing on it.
 *
 * ONE TABLE BECAUSE IT IS ONE MAP. An area's boundary, its zones and its
 * stations are three tables in the database and one drawing on the screen: a
 * zone outside the boundary or a post on ground no zone covers is a defect
 * somebody sees rather than a number somebody reads. Keeping every coordinate
 * here — and deriving the zones and the posts from the same corner — is what
 * makes that drawing correct by construction instead of by three lists agreeing
 * with each other.
 *
 * IT IS ON OPEN WATER, ON PURPOSE. Every demo boundary sits at longitude −65°
 * in the South Atlantic, where there is nothing to be mistaken for anywhere
 * real: demo content that landed on a country would be a claim about that
 * country the moment somebody screenshotted it.
 *
 * NOTHING HERE IS PERSISTED AND NOTHING HERE DECIDES ANYTHING. It is a static
 * table the dev-only providers read; every row it describes is written through
 * the bundle's own services, which are what enforce the rules the geometry has
 * to satisfy.
 */
final readonly class DemoArea
{
    /**
     * THE SCHEME IS A GRID, THREE ACROSS AND TWO UP, and the names are the
     * phonetic alphabet because a demo zone that sounded like a place would be
     * read as one.
     */
    private const array ZONE_NAMES = [
        'Sector Alpha', 'Sector Bravo', 'Sector Charlie',
        'Sector Delta', 'Sector Echo', 'Sector Foxtrot',
    ];

    /** A degree square, which at this latitude is an area a register can print a size for. */
    private const float SIDE = 1.0;

    /** The scheme is inset from the boundary, so the area has ground no zone covers. */
    private const float ZONE_INSET = 0.10;

    private const float ZONE_COLUMN_PITCH = 0.20;
    private const float ZONE_ROW_PITCH = 0.30;

    /**
     * EACH ZONE IS SMALLER THAN ITS CELL. Zones may not share interior, and a
     * grid drawn edge to edge asks the tolerance a question it should never be
     * asked in demo content — so every ring stops short of the next one.
     */
    private const float ZONE_WIDTH = 0.19;
    private const float ZONE_HEIGHT = 0.28;

    private function __construct(
        public string $name,
        public string $iucnCategory,
        public int $establishedYear,
        public float $west,
        public float $south,
    ) {
    }

    /**
     * THE DEMO REGISTER: more than one, because every screen that lists areas,
     * scopes to one, or compares two is wrong in a way a single area hides.
     *
     * @return list<self>
     */
    public static function all(): array
    {
        return [
            new self('Northreach Reserve', 'II', 1978, -65.50, -31.00),
            new self('Silverwater Conservancy', 'IV', 1994, -65.50, -29.50),
        ];
    }

    /** The boundary as the column takes it: one ring, closed, longitude first. */
    public function boundary(): string
    {
        return self::encode([
            'type' => 'MultiPolygon',
            'coordinates' => [[self::ring($this->west, $this->south, self::SIDE, self::SIDE)]],
        ]);
    }

    /**
     * THE WHOLE SCHEME AS ONE FEATURECOLLECTION, one feature per zone — the
     * shape a desktop GIS exports and the only shape the import reads, so the
     * demo arrives the way a real scheme does rather than through a back door.
     */
    public function zoneScheme(): string
    {
        $features = [];

        foreach (self::ZONE_NAMES as $index => $name) {
            $column = $index % 3;
            $row = intdiv($index, 3);

            $features[] = [
                'type' => 'Feature',
                'properties' => ['name' => $name],
                'geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [self::ring(
                        $this->west + self::ZONE_INSET + $column * self::ZONE_COLUMN_PITCH,
                        $this->south + self::ZONE_INSET + $row * self::ZONE_ROW_PITCH,
                        self::ZONE_WIDTH,
                        self::ZONE_HEIGHT,
                    )],
                ],
            ];
        }

        return self::encode(['type' => 'FeatureCollection', 'features' => $features]);
    }

    /**
     * THE POSTS, laid out against the same grid: two inside most zones —
     * because a zone with two posts is what an area's staffing page is for —
     * one inside the last, and one out on ground the scheme does not reach.
     *
     * A PARK IS TWELVE POSTS AND NOT EIGHT, AND THEY ARE NOT THE SAME SIZE.
     * A main gate holds five people and a roadside marker holds nobody. The
     * demo used to give every post the same two, and every screen that
     * ranks, groups or compares posts drew a flat line — a duty board with
     * nothing to read, which is exactly the screen somebody opens the demo
     * to judge. The spread here is the one the roster design draws: one
     * five, one four, the rest between three and nothing.
     *
     * TWO OF THEM ARE EMPTY, and deliberately: one inside a zone, one out on
     * the unzoned ground. "Nobody works out of here" is a state every
     * register, board and plate has to draw, and a demo with no empty post
     * never shows it.
     *
     * @return list<DemoStation>
     */
    public function stations(): array
    {
        return [
            new DemoStation('Alpha Gate', 'STN-01', 0.150, 0.240, 1180, 'Southern gate approach', posted: 5),
            new DemoStation('Alpha Ridge', 'STN-02', 0.240, 0.240, 1465, 'Upper ridge line', posted: 3),
            new DemoStation('Bravo Crossing', 'STN-03', 0.350, 0.240, 940, 'River crossing', posted: 3),
            new DemoStation('Bravo Ford', 'STN-04', 0.440, 0.240, 985, 'Lower ford', posted: 2),
            new DemoStation('Charlie Bend', 'STN-05', 0.550, 0.240, 1025, 'Eastern bend', posted: 4),
            new DemoStation('Charlie Rise', 'STN-06', 0.640, 0.240, 1240, 'Eastern rise', posted: 2),
            new DemoStation('Delta Camp', 'STN-07', 0.150, 0.540, 1310, 'Basin camp', posted: 3),
            new DemoStation('Delta Outpost', 'STN-08', 0.240, 0.540, 1120, 'Basin margin', posted: 0),
            new DemoStation('Echo Watch', 'STN-09', 0.350, 0.540, 1580, 'Escarpment lookout', posted: 4),
            new DemoStation('Echo Gate', 'STN-10', 0.440, 0.540, 1395, 'Escarpment road', posted: 2),
            new DemoStation('Foxtrot Post', 'STN-11', 0.550, 0.540, 870, 'Northern flats', posted: 3),
            new DemoStation('Outer Marker', 'STN-12', 0.850, 0.850, 620, 'Beyond the scheme', posted: 0),
        ];
    }

    /** How many people the whole demo ground asks for, counted from the table. */
    public static function headcount(): int
    {
        $people = 0;
        foreach (self::all() as $area) {
            foreach ($area->stations() as $post) {
                $people += $post->posted;
            }
        }

        return $people;
    }

    /** @return array{0: float, 1: float} longitude then latitude, as everything here is */
    public function pointOf(DemoStation $station): array
    {
        return [$this->west + $station->eastOfCorner, $this->south + $station->northOfCorner];
    }

    /**
     * A CLOSED RECTANGULAR RING, anticlockwise, with the first position repeated
     * last as RFC 7946 requires.
     *
     * @return list<array{0: float, 1: float}>
     */
    private static function ring(float $west, float $south, float $width, float $height): array
    {
        $east = $west + $width;
        $north = $south + $height;

        return [[$west, $south], [$east, $south], [$east, $north], [$west, $north], [$west, $south]];
    }

    /** @param array<string, mixed> $document */
    private static function encode(array $document): string
    {
        return (string) json_encode($document, \JSON_THROW_ON_ERROR);
    }
}
