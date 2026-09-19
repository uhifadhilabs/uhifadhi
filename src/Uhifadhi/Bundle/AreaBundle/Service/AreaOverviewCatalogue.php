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

use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Overview\OverviewContributorInterface;
use Uhifadhi\Bundle\AreaBundle\Widget\AreaOverviewWidgets;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\Widget;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetCatalog;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetPreset;

/**
 * THE AREA OVERVIEW'S CATALOGUE, ASSEMBLED PER AREA.
 *
 * THIS IS THE ONE SURFACE WHOSE WIDGETS ARE NOT WRITTEN BY WHOEVER OWNS THE
 * PAGE. The area bundle owns the grid, the presets and the area's own cells;
 * every operational cell arrives from a module installed in THAT area,
 * through {@see OverviewContributorInterface}. So the catalogue cannot be a
 * constant the way every other surface's is — it is different in an area
 * running patrols from one running nothing, and it changes the moment a
 * module is switched on.
 *
 * THE DEFAULT PRESET IS ASSEMBLED TOO, and that is what keeps the rule
 * open/closed. A built-in preset names the widgets it turns on; a preset
 * written as a constant here would have to name `pl_now` and `in_flow` — two
 * modules' widget ids, in the core — and an area without those modules would
 * default to a page with holes in it. Instead the area's own cells come
 * first, in the order the design draws them, and every contributed widget
 * follows at the span its module asked for, in contributor order. Installing
 * a module adds its cells to the default; uninstalling removes them; neither
 * touches this file.
 *
 * A PARTIAL BELONGS TO ITS CONTRIBUTOR. Each one names a sprintf pattern for
 * its own namespace, so a module's cell is rendered from the module's own
 * template and the area page's template contains no widget markup at all.
 */
final readonly class AreaOverviewCatalogue
{
    /** What a stored preference row is keyed by — stable across releases. */
    public const string SURFACE = 'area-overview';

    /** The assembled layout everybody gets until one is adopted. */
    public const string DEFAULT_PRESET = 'default';

    /** @param iterable<OverviewContributorInterface> $contributors */
    public function __construct(
        private iterable $contributors,
        private AreaOverview $overview,
    ) {
    }

    /**
     * THE SURFACE AS THIS AREA HAS IT — the area's own cells, then the cells
     * of every module it runs.
     */
    public function for(AreaOfInterest $area): WidgetCatalog
    {
        $groups = [];
        $widgets = [];
        $layout = [];

        foreach ($this->contributorsFor($area) as $contributor) {
            $groups[] = $contributor->group();

            foreach ($contributor->widgets() as $widget) {
                $widgets[] = $widget;
                // THE DEFAULT IS WHAT THE CONTRIBUTOR ASKED FOR: a widget it
                // ships switched on joins the page at the span it declared.
                if ($widget->on) {
                    $layout[$widget->id] = $widget->cols;
                }
            }
        }

        return new WidgetCatalog(
            self::SURFACE,
            $groups,
            $widgets,
            [new WidgetPreset(
                self::DEFAULT_PRESET,
                'The area, as it comes',
                'The area’s own identity, what is happening right now, what needs somebody, the ground — and then a cell from every module installed here, in the order they were installed.',
                $layout,
            )],
            self::DEFAULT_PRESET,
        );
    }

    /**
     * WHICH PARTIAL DRAWS WHICH CELL, for this area — widget id to template.
     *
     * @return array<string, string>
     */
    public function partialsFor(AreaOfInterest $area): array
    {
        $partials = [];
        foreach ($this->contributorsFor($area) as $contributor) {
            foreach ($contributor->widgets() as $widget) {
                $partials[$widget->id] = \sprintf($contributor->partialPattern(), $widget->id);
            }
        }

        return $partials;
    }

    /**
     * EVERYTHING EVERY CELL READS, for this one area, at one moment.
     *
     * ONE CONTEXT PER CONTRIBUTOR, MERGED. A module computes its reading of
     * the day once for all of its cards: that is the difference between one
     * query and nine, and between two cards that agree and two that were
     * measured a second apart.
     *
     * @return array<string, mixed>
     */
    public function contextFor(AreaOfInterest $area, \DateTimeImmutable $now): array
    {
        $context = [];
        foreach ($this->contributorsFor($area) as $contributor) {
            $context = [...$context, ...$contributor->context($area, $now)];
        }

        return $context;
    }

    /**
     * THE CONTRIBUTORS THIS AREA ACTUALLY HAS — its own, and one per module
     * it runs. A module the area has not switched on is not asked, which is
     * what makes its cells disappear from the library rather than go blank.
     *
     * @return list<OverviewContributorInterface>
     */
    private function contributorsFor(AreaOfInterest $area): array
    {
        $running = $this->overview->installedSlugs($area);

        $contributors = [];
        foreach ($this->contributors as $contributor) {
            $slug = $contributor->moduleSlug();
            if (AreaOverviewWidgets::SLUG === $slug || \in_array($slug, $running, true)) {
                $contributors[] = $contributor;
            }
        }

        return $contributors;
    }
}
