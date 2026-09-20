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

use Uhifadhi\Bundle\AreaBundle\Overview\ContributesStylesheetInterface;
use Uhifadhi\Bundle\AreaBundle\Overview\NowTile;
use Uhifadhi\Bundle\AreaBundle\Overview\OrgOverviewContributorInterface;
use Uhifadhi\Bundle\AreaBundle\Widget\OrgOverviewWidgets;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\Widget;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetCatalog;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetPreset;
use Uhifadhi\Contracts\Shell\Scope;

/**
 * THE ORGANISATION DASHBOARD'S CATALOGUE.
 *
 * `/` IS A WIDGET SURFACE, and almost nothing on it is the host's. The
 * organisation contributes five cells of its own — the figures strip, the
 * queue, the ground, the areas and what runs where — and every operational
 * cell arrives from a module through
 * {@see OrgOverviewContributorInterface}. Installing a module adds its cells
 * to the library here; uninstalling removes them; neither touches this file.
 *
 * THE FIVE PRESETS ARE THE FIVE DIRECTIONS THE PAGE WAS DRAWN IN, and their
 * layouts are the design's to the twelfth. They name cells this installation
 * may not have — `watches`, `patrols`, `incidents`, `goals`, `files` are
 * modules' — and that is deliberate rather than an oversight: a preset is a
 * SHAPE, and the resolver drops the cells nobody contributes, so the same
 * five designs get richer as an installation grows instead of being rewritten
 * per deployment.
 *
 * E IS THE SHIPPED DEFAULT (ruled). The compare index recommends A — the duty
 * officer's live-first screen — and the owner ruled E, everything in
 * contributor order: on an installation that has just been set up, the
 * catalogue of what the organisation CAN see is worth more than a tight
 * reading of what it does, and somebody composing their own starts from the
 * whole.
 */
final readonly class OrgOverviewCatalogue
{
    /** What a stored preference row is keyed by — stable across releases. */
    public const string SURFACE = 'org-overview';

    /** Everything, in contributor order. Ruled as the one this ships on. */
    public const string DEFAULT_PRESET = 'e';

    /** @param iterable<OrgOverviewContributorInterface> $contributors */
    public function __construct(private iterable $contributors)
    {
    }

    /**
     * THE SURFACE AS THIS INSTALLATION HAS IT — the organisation's own cells,
     * then the cells of every module that answers at organisation level.
     */
    public function catalog(): WidgetCatalog
    {
        $groups = [];
        $widgets = [];

        foreach ($this->contributors as $contributor) {
            $groups[] = $contributor->group();
            foreach ($contributor->widgets() as $widget) {
                $widgets[] = $widget;
            }
        }

        return new WidgetCatalog(
            self::SURFACE,
            $groups,
            $widgets,
            self::composed(array_column($widgets, 'id')),
            self::DEFAULT_PRESET,
        );
    }

    /**
     * THE FIVE DESIGNS, COMPOSED DOWN TO WHAT THIS INSTALLATION HAS.
     *
     * A PRESET IS A SHAPE, NOT A LIST OF CELLS. The designs name `watches`,
     * `patrols`, `incidents`, `goals` and `files`, which are modules' — so
     * the same five get richer as an installation grows instead of being
     * rewritten per deployment. What the CATALOGUE holds, though, is this
     * installation's own composition of them: a surface refuses a preset that
     * names a cell it does not ship, and rightly, because on every other
     * surface that is a typo.
     *
     * SO THE DECLARATION AND THE COMPOSITION ARE TWO THINGS. {@see presets()}
     * is the design, verbatim and pinned by test; this is the design as this
     * installation can draw it. A preset left with nothing at all is not a
     * shape any more and is dropped rather than offered as an empty screen.
     *
     * @param list<string> $shipped
     *
     * @return list<WidgetPreset>
     */
    private static function composed(array $shipped): array
    {
        $composed = [];
        foreach (self::presets() as $preset) {
            $layout = array_filter(
                $preset->layout,
                static fn (string $id): bool => \in_array($id, $shipped, true),
                \ARRAY_FILTER_USE_KEY,
            );

            if ([] === $layout) {
                continue;
            }

            $composed[] = new WidgetPreset($preset->id, $preset->label, $preset->description, $layout);
        }

        return $composed;
    }

    /**
     * THE FIVE COMPOSITIONS, in the library's own order, with the layouts and
     * the spans the design declares.
     *
     * @return list<WidgetPreset>
     */
    public static function presets(): array
    {
        return [
            new WidgetPreset('a', 'The duty officer',
                'Live first: what needs a decision, then the ground, then who is on it. The shape the person answering the radio needs — everything on it is true this minute, and nothing on it is a plan. Weakest at the question a director asks: is any of this getting better?',
                ['kpis' => 12, 'attention' => 12, 'plate' => 12, 'areas' => 6, 'watches' => 6, 'patrols' => 12]),
            new WidgetPreset('b', 'The areas wall',
                'The organisation is its areas: one row per area, then the ground under them. Scales to forty areas without changing shape, and the only direction in which an area that reports nothing cannot be missed. With one live area it spends its best row on three empty ones.',
                ['kpis' => 12, 'areas' => 12, 'plate' => 6, 'watches' => 6, 'attention' => 12]),
            new WidgetPreset('c', 'Needs a decision first',
                'The queue at the top, full width, and the ground under it. Reads as an inbox: the page is empty when nothing is wrong, which is the honest state of a good day. It buries the live figures a control room is opened for.',
                ['kpis' => 12, 'attention' => 12, 'incidents' => 6, 'watches' => 6, 'plate' => 12]),
            new WidgetPreset('d', 'The director',
                'Outcomes over operations: goals off track, the areas, the term failures. The only direction that answers "is this getting better", and the one to leave on a wall in a management meeting. It cannot answer "where is everybody" at all.',
                ['kpis' => 12, 'goals' => 6, 'areas' => 6, 'incidents' => 6, 'modules' => 6]),
            new WidgetPreset('e', 'Everything',
                'Every contributed cell, in contributor order, nothing left out. The honest catalogue of what the organisation can see, and the place to start when composing your own. Too long to read at a glance, which is what the other four are for.',
                ['kpis' => 12, 'attention' => 12, 'plate' => 12, 'areas' => 6, 'watches' => 6, 'patrols' => 6, 'incidents' => 6, 'goals' => 6, 'files' => 6, 'modules' => 12]),
        ];
    }

    /**
     * WHICH PARTIAL DRAWS WHICH CELL — cell id to template.
     *
     * @return array<string, string>
     */
    public function partials(): array
    {
        $partials = [];
        foreach ($this->contributors as $contributor) {
            foreach ($contributor->widgets() as $widget) {
                $partials[$widget->id] = \sprintf($contributor->partialPattern(), $widget->id);
            }
        }

        return $partials;
    }

    /**
     * EVERYTHING EVERY CELL READS, at one moment, KEYED BY THE CONTRIBUTOR
     * THAT ANSWERED.
     *
     * The shape is the published contract and not this class's choice: a
     * contributed partial is rendered with `with_context: false` and given
     * its own figures under `by.<slug>`, exactly as on the area overview, so
     * a module template written for one reads the same on the other.
     *
     * @return array<string, array<string, mixed>>
     */
    public function contextFor(Scope $scope, \DateTimeImmutable $now): array
    {
        $by = [];
        foreach ($this->contributors as $contributor) {
            $by[$contributor->moduleSlug()] = $contributor->context($scope, $now);
        }

        return $by;
    }

    /**
     * THE FIGURES STRIP, ASSEMBLED — one tile per contributor that publishes
     * one, in declared priority and then in contributor order.
     *
     * FOUR TO A ROW is the page's rule, not this method's: the strip is
     * returned whole and the partial decides how many fit and what fills a
     * slot nobody contributed. A service that truncated here would make
     * "what could this installation show" unanswerable.
     *
     * @return list<NowTile>
     */
    public function figures(Scope $scope, \DateTimeImmutable $now): array
    {
        $tiles = [];
        foreach ($this->contributors as $contributor) {
            foreach ($contributor->figures($scope, $now) as $tile) {
                $tiles[] = $tile;
            }
        }

        usort($tiles, static fn (NowTile $a, NowTile $b): int => $a->priority <=> $b->priority);

        return $tiles;
    }

    /**
     * HOW MANY CELLS EACH MODULE PUTS ON THIS PAGE, by slug — the question
     * "what does installing this module actually add here" is about, so the
     * organisation's own cells are left out.
     *
     * @return array<string, int>
     */
    public function widgetCounts(): array
    {
        $counts = [];
        foreach ($this->contributors as $contributor) {
            $slug = $contributor->moduleSlug();
            if (OrgOverviewWidgets::SLUG === $slug) {
                continue;
            }

            $counts[$slug] = \count($contributor->widgets());
        }

        return $counts;
    }

    /**
     * THE STYLESHEETS THIS PAGE'S CELLS NEED, in order and each one once.
     *
     * A module's cell is drawn here and everything it wears comes from the
     * module's own sheet; nothing collecting them is how an incident cell's
     * flow bar came out as blue underlined links on the area overview.
     *
     * @return list<string>
     */
    public function stylesheets(): array
    {
        $sheets = [];
        foreach ($this->contributors as $contributor) {
            if (!$contributor instanceof ContributesStylesheetInterface) {
                continue;
            }

            $sheet = $contributor->stylesheet();
            if ('' !== $sheet && !\in_array($sheet, $sheets, true)) {
                $sheets[] = $sheet;
            }
        }

        return $sheets;
    }

    /**
     * THE CELLS THE LIBRARY OFFERS, so a caller can tell a contributed cell
     * from one of the organisation's own without knowing any module.
     *
     * @return array<string, string> cell id => the slug that contributed it
     */
    public function contributorOf(): array
    {
        $by = [];
        foreach ($this->contributors as $contributor) {
            foreach ($contributor->widgets() as $widget) {
                $by[$widget->id] = $contributor->moduleSlug();
            }
        }

        return $by;
    }

    /**
     * Every cell this installation actually has.
     *
     * @return list<Widget>
     */
    public function cells(): array
    {
        $cells = [];
        foreach ($this->contributors as $contributor) {
            foreach ($contributor->widgets() as $widget) {
                $cells[] = $widget;
            }
        }

        return $cells;
    }
}
