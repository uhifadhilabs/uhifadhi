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
use Uhifadhi\Bundle\AreaBundle\Overview\AttentionItem;
use Uhifadhi\Bundle\AreaBundle\Overview\AttentionProviderInterface;
use Uhifadhi\Bundle\AreaBundle\Overview\MapLayer;
use Uhifadhi\Bundle\AreaBundle\Overview\MapLayerProviderInterface;
use Uhifadhi\Bundle\AreaBundle\Overview\NowTile;
use Uhifadhi\Bundle\AreaBundle\Overview\NowTileProviderInterface;
use Uhifadhi\Bundle\AreaBundle\Overview\PulseProviderInterface;
use Uhifadhi\Bundle\RegistryBundle\Repository\AreaModuleRepository;

/**
 * WHAT AN AREA'S OVERVIEW IS MADE OF — gathered from every module installed in
 * the area, and from nothing else.
 *
 * THE HOST OWNS THE SURFACE AND WRITES NONE OF THE OPERATIONAL CONTENT. This
 * class asks each contributor for its parts, keeps the ones whose module is
 * actually switched on here, and orders them. It does not know what a patrol is
 * and must not: the day it does, uninstalling a module leaves a hard-coded row
 * behind.
 *
 * ASKED PER AREA, ON EVERY RENDER. Nothing is stored and nothing is cached, so
 * an attention item leaves the list when the thing that raised it is dealt with
 * and nobody dismisses one by hand.
 *
 * ABSENT IS NOT ZERO, AND THAT IS THE WHOLE DISCIPLINE. An empty strip means no
 * installed module had anything to say — it does not mean every count is 0, and
 * the page says the former rather than drawing the latter.
 */
final readonly class AreaOverview
{
    /**
     * @param iterable<NowTileProviderInterface>   $nowTiles
     * @param iterable<AttentionProviderInterface> $attention
     * @param iterable<MapLayerProviderInterface>  $mapLayers
     * @param iterable<PulseProviderInterface>     $pulse
     */
    public function __construct(
        private iterable $nowTiles,
        private iterable $attention,
        private iterable $mapLayers,
        private iterable $pulse,
        private AreaModuleRepository $areaModules,
    ) {
    }

    /**
     * The right-now strip, in provider priority order then registration order.
     *
     * @return list<NowTile>
     */
    public function nowTilesFor(AreaOfInterest $area, \DateTimeImmutable $now): array
    {
        $installed = $this->installedSlugs($area);

        $tiles = [];
        foreach ($this->nowTiles as $provider) {
            if (!\in_array($provider->moduleSlug(), $installed, true)) {
                continue;
            }
            foreach ($provider->nowTilesFor($area, $now) as $tile) {
                $tiles[] = $tile;
            }
        }

        // A stable sort: equal priorities keep the order the providers were
        // asked in, so a strip does not reshuffle itself between renders.
        usort($tiles, static fn (NowTile $a, NowTile $b): int => $a->priority <=> $b->priority);

        return $tiles;
    }

    /**
     * Everything asking for attention, SORTED BY URGENCY AND NEVER BY MODULE —
     * then oldest first inside a severity, because a thing that has been waiting
     * longer is the more embarrassing one.
     *
     * @return list<AttentionItem>
     */
    public function attentionFor(AreaOfInterest $area, \DateTimeImmutable $now): array
    {
        $installed = $this->installedSlugs($area);

        $items = [];
        foreach ($this->attention as $provider) {
            if (!\in_array($provider->moduleSlug(), $installed, true)) {
                continue;
            }
            foreach ($provider->attentionFor($area, $now) as $item) {
                $items[] = $item;
            }
        }

        usort($items, static fn (AttentionItem $a, AttentionItem $b): int => [$a->severity->rank(), -$a->ageSeconds] <=> [$b->severity->rank(), -$b->ageSeconds]);

        return $items;
    }

    /**
     * EVERY MODULE LAYER FOR THE HOST'S ONE PLATE, in provider order then the
     * order each provider lists its own layers — never sorted, because a layer's
     * place in the legend is the module's to decide and a stable order is what
     * lets a person find the same row twice.
     *
     * Asked only where the module is switched on, exactly like the tiles and the
     * attention rows: a layer from a module that is off here is not gathered, so
     * its legend group leaves the plate rather than lingering empty. The host
     * draws these from their own colour and style and knows nothing of what a
     * patrol track or an incident point IS — the day it does, the seam is broken.
     *
     * @return list<MapLayer>
     */
    public function mapLayersFor(AreaOfInterest $area, \DateTimeImmutable $now): array
    {
        $installed = $this->installedSlugs($area);

        $layers = [];
        foreach ($this->mapLayers as $provider) {
            if (!\in_array($provider->moduleSlug(), $installed, true)) {
                continue;
            }
            foreach ($provider->mapLayersFor($area, $now) as $layer) {
                $layers[] = $layer;
            }
        }

        return $layers;
    }

    /**
     * WHEN THE AREA WAS LAST TOUCHED — the most recent move any installed module
     * made in it, over the window between `$since` and `$now`, or null where
     * nothing moved.
     *
     * The register card's "last check-in" and its "last activity" sort read this.
     * It is the pulse seam asked the same way as everything else — only where a
     * module is switched on — so the host learns WHEN an area last did something
     * without knowing WHAT: the day a module leaves, its moves stop counting
     * toward the area's recency with no host edit. Null is a legitimate answer
     * and the card says "no recent activity" rather than inventing a time.
     */
    public function latestActivityFor(AreaOfInterest $area, \DateTimeImmutable $since, \DateTimeImmutable $now): ?\DateTimeImmutable
    {
        $installed = $this->installedSlugs($area);

        $latest = null;
        foreach ($this->pulse as $provider) {
            if (!\in_array($provider->moduleSlug(), $installed, true)) {
                continue;
            }
            foreach ($provider->pulseFor($area, $since, $now) as $event) {
                if (null === $latest || $event->at > $latest) {
                    $latest = $event->at;
                }
            }
        }

        return $latest;
    }

    /**
     * THE MODULE LAYERS AS THEIR LEGEND GROUPS — one group per contributor, in the
     * order the layers arrived, each carrying the module's own colour for its dot.
     *
     * The legend is grouped by contributor because provenance is the only thing
     * that lets a person tell why a layer vanished. The grouping is done here, on
     * the flat list the plate already gathered, rather than in the template —
     * a Twig template must be renderable in a bare installation whose Twig carries
     * no extra filters, so the shape it loops over is built in PHP.
     *
     * @param list<MapLayer> $layers
     *
     * @return list<array{label: string, swatch: string, layers: list<MapLayer>}>
     */
    public static function groupByContributor(array $layers): array
    {
        $groups = [];
        foreach ($layers as $layer) {
            if (!isset($groups[$layer->groupLabel])) {
                $groups[$layer->groupLabel] = [
                    'label' => $layer->groupLabel,
                    'swatch' => $layer->swatch,
                    'layers' => [],
                ];
            }
            $groups[$layer->groupLabel]['layers'][] = $layer;
        }

        return array_values($groups);
    }

    /**
     * The slugs of the modules this area has switched on.
     *
     * READ FROM THE SEAM'S LEDGER, never from what happens to be installed in
     * the container: a module present in the application but switched off for
     * THIS area contributes nothing here, which is what makes the per-area
     * switch mean anything.
     *
     * @return list<string>
     */
    public function installedSlugs(AreaOfInterest $area): array
    {
        $slugs = [];
        foreach ($this->areaModules->activeForArea($area) as $areaModule) {
            $slug = $areaModule->getModule()?->getSlug();
            if (\is_string($slug)) {
                $slugs[] = $slug;
            }
        }

        return $slugs;
    }
}
