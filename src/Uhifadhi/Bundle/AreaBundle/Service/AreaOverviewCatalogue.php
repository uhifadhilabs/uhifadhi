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
use Uhifadhi\Bundle\AreaBundle\Overview\OverviewStylesheetsInterface;
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
     * EVERYTHING EVERY CELL READS, for this one area, at one moment —
     * KEYED BY THE MODULE THAT ANSWERED.
     *
     * THE SHAPE IS THE PUBLISHED CONTRACT AND NOT THIS CLASS'S CHOICE. A
     * contributed partial is rendered with `with_context: false` and given
     * ONE map, its own figures under `by.<slug>`; every module template in
     * the product says so in its header and the contract document says so
     * in its table. Merging the answers flat instead read beautifully for
     * the host's own cells — which take their facts from the page — and
     * fataled on every module's, because `by` was not there at all.
     *
     * KEYING BY SLUG IS ALSO WHAT KEEPS TWO MODULES APART. Two contributors
     * publishing `total` would otherwise overwrite each other, silently,
     * and the second card would draw the first one's number.
     *
     * ONE CALL PER CONTRIBUTOR. A module computes its reading of the day
     * once for all of its cards: the difference between one query and nine,
     * and between two cards that agree and two measured a second apart.
     *
     * @return array<string, array<string, mixed>> module slug to that module's context
     */
    public function contextFor(AreaOfInterest $area, \DateTimeImmutable $now): array
    {
        $by = [];
        foreach ($this->contributorsFor($area) as $contributor) {
            $by[$contributor->moduleSlug()] = $contributor->context($area, $now);
        }

        return $by;
    }

    /**
     * HOW MANY CELLS EACH MODULE PUTS ON THIS AREA'S PAGE, by slug.
     *
     * The area's own cards are not a contribution to anybody, so the area's
     * own contributor is left out: this answers "what does installing this
     * module actually add here", which is the question the modules card is
     * about.
     *
     * @return array<string, int>
     */
    public function widgetCountsFor(AreaOfInterest $area): array
    {
        $counts = [];
        foreach ($this->contributorsFor($area) as $contributor) {
            $slug = $contributor->moduleSlug();
            if (AreaOverviewWidgets::SLUG === $slug) {
                continue;
            }

            $counts[$slug] = \count($contributor->widgets());
        }

        return $counts;
    }

    /**
     * THE STYLESHEETS THIS AREA'S CELLS NEED, in order and each one once.
     *
     * A module's cell is drawn on the area's page, and the page links the
     * sheets it knows about — its own, the shell's, the atlas's. Anything a
     * module's own cells wear comes from the module's own sheet, so the
     * module publishes it and the surface links it after its own.
     *
     * @return list<string>
     */
    public function stylesheetsFor(AreaOfInterest $area): array
    {
        $sheets = [];
        foreach ($this->contributorsFor($area) as $contributor) {
            if (!$contributor instanceof OverviewStylesheetsInterface) {
                continue;
            }

            foreach ($contributor->stylesheets() as $sheet) {
                if ('' !== $sheet && !\in_array($sheet, $sheets, true)) {
                    $sheets[] = $sheet;
                }
            }
        }

        return $sheets;
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

        /*
         * THE AREA'S OWN CELLS LEAD, whatever order the container happened
         * to tag the contributors in. The page is read from the place
         * outwards — what this area IS, what is happening in it, what needs
         * somebody, the ground — and only then what each module has to add.
         * Leaving it to tag order made a module's card the first thing on
         * the page in one installation and the last in another.
         */
        $own = null;
        $bySlug = [];
        foreach ($this->contributors as $contributor) {
            $slug = $contributor->moduleSlug();
            if (AreaOverviewWidgets::SLUG === $slug) {
                $own = $contributor;

                continue;
            }

            $bySlug[$slug] = $contributor;
        }

        /*
         * AND THE MODULES COME IN THE AREA'S OWN ORDER — the order its
         * Modules tab lists them in, which is the order somebody arranged.
         * Tag order is the order the container happened to build services
         * in: it put incidents left of patrols on one page and the other way
         * about on another, and nobody could say why.
         */
        $modules = [];
        foreach ($running as $slug) {
            if (isset($bySlug[$slug])) {
                $modules[] = $bySlug[$slug];
            }
        }

        return array_values(array_filter([$own, ...$modules]));
    }
}
