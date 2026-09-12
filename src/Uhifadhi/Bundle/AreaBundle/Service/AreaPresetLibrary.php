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

namespace Uhifadhi\Bundle\AreaBundle\Service;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Uhifadhi\Bundle\AreaBundle\Model\AreaPresetRow;
use Uhifadhi\Bundle\AreaBundle\Model\AreaRow;
use Uhifadhi\Bundle\AreaBundle\Repository\ZoneRepository;

/**
 * WHAT THE FIVE AREAS-INDEX LAYOUTS READ — every fact any of them draws,
 * gathered once, at one clock.
 *
 * WHICH LAYOUTS EXIST IS NOT HERE. The five are declared by
 * {@see \Uhifadhi\Bundle\AreaBundle\Widget\AreaIndexWidgets}, the surface's widget
 * catalogue, and which one is on is a stored preference the widget framework
 * holds — so the register and its library read the same answer from the same
 * place and cannot disagree about what is adopted. What this service owns is the
 * DATA side: the register's rows enriched with the attention items and zone
 * counts the attention board and the flagship need, and the operational column
 * headers and map payload the register and map views need.
 *
 * ONE CLOCK FOR EVERY LAYOUT, exactly as the register measures its wall: the map
 * dock, the table and the attention board all read the same rows at the same
 * instant, so no two of them disagree.
 *
 * IT NAMES NO MODULE'S CONTENT. The enrichment is the same overview contributions the
 * register already reads — the attention items are gathered, not invented — so
 * the area page lays out what a module contributed and knows what a patrol is no more
 * here than anywhere else.
 */
final readonly class AreaPresetLibrary
{
    public function __construct(
        private AreaOverview $overview,
        private ZoneRepository $zones,
        private AreaRegister $register,
        private AreaMapService $areaMap,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * EVERY FACT ANY OF THE FIVE LAYOUTS MIGHT WANT, gathered once — so the
     * landing drawing one of them and the library previewing all five are handed
     * the identical picture, and the two screens can never differ over a figure.
     *
     * @return array<string, mixed>
     */
    public function landing(\DateTimeImmutable $now): array
    {
        $rows = $this->register->rows($now);
        $presetRows = $this->enrich($rows, $now);
        $flagship = self::flagship($presetRows);

        return [
            'rows' => $rows,
            'counts' => $this->register->counts($rows),
            'presetRows' => $presetRows,
            'needsAttention' => self::needsAttention($presetRows),
            'runningSteady' => self::runningSteady($presetRows),
            'awaitingSetup' => self::awaitingSetup($presetRows),
            'flagship' => $flagship,
            'flagshipRest' => self::rest($presetRows, $flagship),
            'statColumns' => self::statColumns($rows),
            'map' => $this->areaMap->register($this->mapAreas($rows)),
        ];
    }

    /**
     * THE REGISTER TABLE'S OPERATIONAL COLUMN HEADERS — the labels the now-tile
     * contributions handed back, read from the first live area (they are uniform across
     * areas, one module contributing the same tiles to each). Empty when nothing
     * is live, so the table draws no column for a figure no module contributed —
     * the same absent-not-zero discipline the wall keeps, in a table.
     *
     * @param list<AreaRow> $rows
     *
     * @return list<string>
     */
    public static function statColumns(array $rows): array
    {
        foreach ($rows as $row) {
            if ($row->isLive()) {
                return array_map(static fn ($stat): string => $stat->label, $row->stats);
            }
        }

        return [];
    }

    /**
     * The register's rows, enriched with what the attention board and flagship
     * read — the actual attention items and the zone count — measured at the same
     * clock the rows were.
     *
     * @param list<AreaRow> $rows
     *
     * @return list<AreaPresetRow>
     */
    public function enrich(array $rows, \DateTimeImmutable $now): array
    {
        $enriched = [];
        foreach ($rows as $row) {
            $enriched[] = new AreaPresetRow(
                row: $row,
                attention: $this->overview->attentionFor($row->area, $now),
                zoneCount: $this->zones->countFor($row->area),
            );
        }

        return $enriched;
    }

    /**
     * Areas asking for the operator — anything with an open attention item, the
     * ones the board floats to the top.
     *
     * @param list<AreaPresetRow> $rows
     *
     * @return list<AreaPresetRow>
     */
    public static function needsAttention(array $rows): array
    {
        return array_values(array_filter($rows, static fn (AreaPresetRow $r): bool => $r->row->hasAlerts()));
    }

    /**
     * Areas that are live and quiet — running with nothing overdue.
     *
     * @param list<AreaPresetRow> $rows
     *
     * @return list<AreaPresetRow>
     */
    public static function runningSteady(array $rows): array
    {
        return array_values(array_filter($rows, static fn (AreaPresetRow $r): bool => $r->row->isLive() && !$r->row->hasAlerts()));
    }

    /**
     * Areas not yet live — a boundary on file, no module switched on.
     *
     * @param list<AreaPresetRow> $rows
     *
     * @return list<AreaPresetRow>
     */
    public static function awaitingSetup(array $rows): array
    {
        return array_values(array_filter($rows, static fn (AreaPresetRow $r): bool => !$r->row->isLive()));
    }

    /**
     * THE FLAGSHIP — the org's primary area, featured large. The most recently
     * active LIVE area, because "the flagship" is whichever area is the work right
     * now; null only when nothing is live, and then the portfolio read has no
     * hero to feature.
     *
     * @param list<AreaPresetRow> $rows
     */
    public static function flagship(array $rows): ?AreaPresetRow
    {
        $flagship = null;
        foreach (self::runningSteadyOrAttention($rows) as $row) {
            if (null === $flagship || $row->row->activityRank() > $flagship->row->activityRank()) {
                $flagship = $row;
            }
        }

        return $flagship;
    }

    /**
     * Every area but the flagship, in register order — the secondary strip below
     * the featured area.
     *
     * @param list<AreaPresetRow> $rows
     *
     * @return list<AreaPresetRow>
     */
    public static function rest(array $rows, ?AreaPresetRow $flagship): array
    {
        if (null === $flagship) {
            return $rows;
        }

        return array_values(array_filter($rows, static fn (AreaPresetRow $r): bool => $r !== $flagship));
    }

    /**
     * The live areas — a flagship is drawn from these, whether or not they are
     * also asking for attention.
     *
     * @param list<AreaPresetRow> $rows
     *
     * @return list<AreaPresetRow>
     */
    private static function runningSteadyOrAttention(array $rows): array
    {
        return array_values(array_filter($rows, static fn (AreaPresetRow $r): bool => $r->row->isLive()));
    }

    /**
     * The map-of-the-network payload — each area as a point the browser plate
     * draws: its name, whether it is live, the link to its overview, and its
     * boundary as GeoJSON (or null, for a boundary-less area that has no place on
     * the map but still rides the dock beside it). The geometry travels as text
     * exactly as the column holds it; it is never parsed in PHP.
     *
     * @param list<AreaRow> $rows
     *
     * @return list<array{name: string, live: bool, href: string, boundary: string|null}>
     */
    private function mapAreas(array $rows): array
    {
        $areas = [];
        foreach ($rows as $row) {
            $areas[] = [
                'name' => $row->area->getName() ?? '',
                'live' => $row->isLive(),
                'href' => $this->urls->generate('area_show', ['uuid' => $row->area->getUuidString()]),
                'boundary' => $row->area->getGeom(),
            ];
        }

        return $areas;
    }
}
