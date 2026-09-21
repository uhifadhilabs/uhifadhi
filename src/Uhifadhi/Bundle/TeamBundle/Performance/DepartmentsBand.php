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

namespace Uhifadhi\Bundle\TeamBundle\Performance;

use Uhifadhi\Contracts\Kpi\FigurePeriod;
use Uhifadhi\Contracts\Performance\PerformanceScope;
use Uhifadhi\Contracts\Performance\PerformanceTopicProviderInterface;
use Uhifadhi\Contracts\Performance\TopicKpi;

/**
 * THE BAND ON THE DEPARTMENTS REGISTER — what the organization's
 * departments DID, not how many of them there are.
 *
 * IT IS NOT THE CARDS ADDED UP AND IT IS NOT A COUNT OF ROWS. The
 * register used to open with "8 departments · 6 org-wide · 2
 * area-level", which is a fact about the page rather than about the
 * organization — a reader already sees how many cards there are. Those
 * counts moved to the caption under the filters, where a count of what
 * is being listed belongs, and the band now carries what the
 * departments have been doing: the modules' own figures, over the
 * scope the page is showing.
 *
 * WHERE THE FIGURES COME FROM: the same performance seam the
 * Performance section reads, so a figure in this band and the same
 * figure on that page cannot disagree.
 *
 * THE RULE, STATED, because the design's band is one module's three
 * figures and that cannot be the rule when two modules are installed:
 *
 *   THREE SLOTS FOR THE MODULES, filled a figure at a time in the
 *   organization's own module order — round by round, so one module
 *   installed fills all three from its own five (which is the design
 *   exactly), two modules take two and one, and three take one each.
 *   Then the three the host always has: the areas, the seats, the
 *   goals.
 *
 * A BAND OF ONE FIGURE IS STILL A BAND. An installation running no
 * module at all shows the host's three and nothing else, which is the
 * honest reading of an organization that measures nothing yet.
 */
final readonly class DepartmentsBand
{
    /** How many of the band's slots the modules may fill. */
    public const int MODULE_SLOTS = 3;

    /** The host's own three, by the keys their topics publish them under. */
    public const array HOST_FIGURES = [
        'staffing.filled' => 'Seats filled',
        'goals.declared' => 'Goals',
    ];

    /**
     * @param list<PerformanceTopicProviderInterface> $topics
     *
     * @return list<array{label: string, figure: string, caption: string, delta: string, tone: string}>
     */
    public function build(array $topics, PerformanceScope $scope, FigurePeriod $period, int $areas): array
    {
        $band = [];
        foreach ($this->fromModules($topics, $scope, $period) as $kpi) {
            $band[] = self::slot($kpi->label, $kpi);
        }

        // THE AREAS ARE THE GROUND, and no topic publishes them: it is
        // the one figure here that is the installation's own shape
        // rather than a measurement of work.
        $band[] = [
            'label' => 'Areas',
            'figure' => Figures::figure((float) $areas),
            'caption' => 1 === $areas ? 'in the organization' : 'in the organization',
            'delta' => '',
            'tone' => '',
        ];

        $published = [];
        foreach ($topics as $topic) {
            if (PerformanceTopicProviderInterface::HOST !== $topic->moduleSlug()) {
                continue;
            }

            foreach ($topic->kpis($scope, $period) as $kpi) {
                $published[$kpi->key] = $kpi;
            }
        }

        foreach (self::HOST_FIGURES as $key => $label) {
            $band[] = self::slot($label, $published[$key] ?? null);
        }

        return $band;
    }

    /**
     * THE MODULES' FIGURES, ROUND BY ROUND. Taking a module's first
     * three before asking the second would give one module the whole
     * band on an installation that runs four.
     *
     * @param list<PerformanceTopicProviderInterface> $topics
     *
     * @return list<TopicKpi>
     */
    private function fromModules(array $topics, PerformanceScope $scope, FigurePeriod $period): array
    {
        $byModule = [];
        foreach ($topics as $topic) {
            if (PerformanceTopicProviderInterface::HOST === $topic->moduleSlug()) {
                continue;
            }

            $byModule[] = $topic->kpis($scope, $period);
        }

        $taken = [];
        for ($round = 0; \count($taken) < self::MODULE_SLOTS; ++$round) {
            $found = false;
            foreach ($byModule as $kpis) {
                if (!isset($kpis[$round])) {
                    continue;
                }

                $taken[] = $kpis[$round];
                $found = true;

                if (self::MODULE_SLOTS === \count($taken)) {
                    return $taken;
                }
            }

            // EVERY MODULE IS OUT OF FIGURES: the band is as long as the
            // installation has things to say, and no longer.
            if (!$found) {
                break;
            }
        }

        return $taken;
    }

    /** @return array{label: string, figure: string, caption: string, delta: string, tone: string} */
    private static function slot(string $label, ?TopicKpi $kpi): array
    {
        return [
            'label' => $label,
            'figure' => null === $kpi || !$kpi->isKnown() ? '' : Figures::figure((float) $kpi->value),
            'caption' => $kpi->caption ?? '',
            'delta' => null === $kpi ? '' : Figures::delta($kpi->delta),
            'tone' => null === $kpi ? '' : Figures::tone($kpi->delta, $kpi->polarity),
        ];
    }
}
