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

namespace Uhifadhi\Bundle\TeamBundle\Service;

use Uhifadhi\Bundle\RegistryBundle\Service\ModuleCatalogue;
use Uhifadhi\Contracts\Kpi\FigurePeriod;
use Uhifadhi\Contracts\Performance\PerformanceScope;
use Uhifadhi\Contracts\Performance\PerformanceTopicProviderInterface;

/**
 * WHICH TOPICS A PERFORMANCE PAGE HAS, AND IN WHICH ORDER.
 *
 * THE HOST'S FIRST, THEN THE MODULES' IN THE ORDER SOMEBODY ARRANGED.
 * Staffing, Goals and Attention are figures every department has whatever
 * it attaches, so they lead; a module's topic follows, in the
 * organization's own module order — or, inside an area, in that area's.
 * Alphabetical would be the dictionary's opinion about an organization's
 * priorities, and tag order is the order the container happened to build
 * services in.
 *
 * A MODULE NOBODY RUNS PUBLISHES NOTHING. A topic whose module this
 * installation does not carry is not an empty section: it is not a
 * section. That is the whole of what makes adding a module a change to
 * this page and to nothing else.
 */
final readonly class PerformanceTopics
{
    /**
     * @param iterable<PerformanceTopicProviderInterface> $providers
     */
    public function __construct(
        private iterable $providers,
        private ModuleCatalogue $catalogue,
    ) {
    }

    /**
     * Every topic this scope has, in the ruled order.
     *
     * @return list<PerformanceTopicProviderInterface>
     */
    public function forScope(PerformanceScope $scope, FigurePeriod $period): array
    {
        $order = $this->moduleOrder();

        $host = [];
        $modules = [];
        foreach ($this->providers as $provider) {
            $slug = $provider->moduleSlug();

            if (PerformanceTopicProviderInterface::HOST === $slug) {
                $host[] = $provider;

                continue;
            }

            // A MODULE THE INSTALLATION DOES NOT CARRY has no topic — the
            // catalogue is what an installation HAS, which is rows and
            // registered providers both.
            if (!\array_key_exists($slug, $order)) {
                continue;
            }

            $modules[] = $provider;
        }

        usort(
            $modules,
            static fn (PerformanceTopicProviderInterface $a, PerformanceTopicProviderInterface $b): int => $order[$a->moduleSlug()] <=> $order[$b->moduleSlug()],
        );

        return [...$host, ...$modules];
    }

    /** One topic by its own key — the topic page's own read. */
    public function byKey(string $key, PerformanceScope $scope, FigurePeriod $period): ?PerformanceTopicProviderInterface
    {
        foreach ($this->forScope($scope, $period) as $topic) {
            if ($key === $topic->key()) {
                return $topic;
            }
        }

        return null;
    }

    /**
     * THE ORGANIZATION'S OWN MODULE ORDER, slug to its place in it.
     *
     * @return array<string, int>
     */
    private function moduleOrder(): array
    {
        $order = [];
        $place = 0;
        foreach ($this->catalogue->all() as $module) {
            $order[(string) $module->getSlug()] = $place++;
        }

        return $order;
    }
}
