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

use Uhifadhi\Bundle\AreaBundle\Model\AreaLayoutPreset;
use Uhifadhi\Bundle\AreaBundle\Model\AreaPresetRow;
use Uhifadhi\Bundle\AreaBundle\Model\AreaRow;
use Uhifadhi\Bundle\AreaBundle\Repository\ZoneRepository;

/**
 * THE AREAS-INDEX WIDGET LIBRARY — the five whole-page layouts the areas landing
 * ships, and the enriched rows the denser of them read.
 *
 * FIVE LAYOUT DIRECTIONS, ADOPT-ONLY. Unlike the overview surface's library,
 * which composes a dashboard out of widgets, the areas landing is drawn five
 * complete ways and adopting one swaps the whole landing. This service names the
 * five — with "Wall of workspaces" the shipped default — and enriches the
 * register's rows with the attention items and zone counts the attention board
 * and the flagship read. It picks nothing and adopts nothing: which layout is
 * live is a per-person preference the widget-preference framework holds, and in
 * this slice the preview and adoption are the page's own client-side concern.
 *
 * IT NAMES NO MODULE'S CONTENT. The enrichment is the same overview seams the
 * register already reads — the attention items are gathered, not invented — so
 * the host lays out what a module contributed and knows what a patrol is no more
 * here than anywhere else.
 */
final readonly class AreaPresetLibrary
{
    public function __construct(
        private AreaOverview $overview,
        private ZoneRepository $zones,
    ) {
    }

    /**
     * The five layouts this surface ships, in the order the library lists them —
     * the shipped default first.
     *
     * @return list<AreaLayoutPreset>
     */
    public function presets(): array
    {
        return [
            new AreaLayoutPreset(
                'wall',
                'Wall of workspaces',
                'Each area a rich workspace card — thumbnail, the operational figures, live patrols out and last contact. The warmest, most product-like read; the least dense.',
                default: true,
            ),
            new AreaLayoutPreset(
                'register',
                'The register',
                'The working table, science columns swapped for operational ones and the search / filter / sort muscle kept intact. The densest, most direct descendant of the current page.',
            ),
            new AreaLayoutPreset(
                'map',
                'Map of the network',
                'The org’s ground as the base, areas as points, the list docked beside it. Answers “where does the org work” and “what’s happening” together; the right shape for a spread-out org.',
            ),
            new AreaLayoutPreset(
                'attention',
                'Attention board',
                'A worklist — areas grouped by what needs the operator: needs-attention, running-steady, awaiting-setup. The most honest about the morning; it hides a good day on purpose.',
            ),
            new AreaLayoutPreset(
                'flagship',
                'The flagship',
                'The flagship area featured large with its full live pulse; the rest on a secondary strip. The honest shape for one busy area among areas still coming online.',
            ),
        ];
    }

    /** The key of the shipped-default layout — the landing everyone gets until one is adopted. */
    public function defaultKey(): string
    {
        foreach ($this->presets() as $preset) {
            if ($preset->default) {
                return $preset->key;
            }
        }

        return 'wall';
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
}
