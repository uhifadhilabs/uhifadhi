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

namespace Uhifadhi\Bundle\AtlasBundle\Twig;

use Symfony\UX\Map\Renderer\RendererInterface;
use Twig\Environment;
use Twig\Extension\RuntimeExtensionInterface;
use Uhifadhi\Bundle\AtlasBundle\Model\AtlasMap;
use Uhifadhi\Bundle\AtlasBundle\Model\LegendItem;

/**
 * `render_map()` — the whole of what a module writes to have a map.
 *
 * IT RENDERS A PLATE, NOT A MAP ELEMENT. UX Map renders the element its bridge
 * controller mounts on; a plate is that element plus everything around it that
 * must not be a module's decision: the wrapper that goes fullscreen, the filter
 * row above the map, the legend floating over it. Those three have to be laid
 * out together — the wrapper is a flex column so the map grows into fullscreen
 * — so they are emitted together, here, once.
 *
 * A RUNTIME rather than work done in the extension, because rendering needs Twig
 * itself and the configured UX Map renderer, and a page that draws no map should
 * pay for neither.
 *
 * @see vendor/symfony/ux-map/src/Twig/MapRuntime.php
 */
final class MapPlateRuntime implements RuntimeExtensionInterface
{
    /**
     * The plate's Stimulus identifier — the asset package name and the
     * controller name, as StimulusBundle derives it from a bundle's
     * assets/package.json.
     *
     * @see https://symfony.com/bundles/StimulusBundle/current/index.html
     */
    public const string CONTROLLER = 'uhifadhi--atlas-bundle--map-plate';

    /** The plate template, rendered through the namespace the bundle prepends. */
    private const string TEMPLATE = '@Atlas/plate.html.twig';

    /**
     * The classes the map element carries whatever a caller asks for: the
     * imagery frame's canvas, and the stacking context that keeps a zoom pill
     * from floating over a dialog.
     */
    private const string CANVAS_CLASSES = 'map-canvas map-chrome-host';

    public function __construct(
        private readonly Environment $twig,
        private readonly RendererInterface $renderer,
    ) {
    }

    /**
     * @param array<string, bool|string> $attributes attributes for the MAP element — an aria-label,
     *                                               a role, a module's own data attribute
     * @param string|null                $filters    markup for the row above the map; already-escaped
     *                                               HTML, as a `{% set %}` block or a rendered include
     */
    public function renderMap(AtlasMap $map, array $attributes = [], ?string $filters = null): string
    {
        $class = $attributes['class'] ?? null;
        $attributes['class'] = \is_string($class) && '' !== $class
            ? self::CANVAS_CLASSES.' '.$class
            : self::CANVAS_CLASSES;

        return $this->twig->render(self::TEMPLATE, [
            'controller' => self::CONTROLLER,
            'element' => $this->renderer->renderMap($map->toUxMap(), $attributes),
            'groups' => self::group($map->legend()),
            'filters' => $filters,
        ]);
    }

    /**
     * The legend's rows under their headings, each heading in the order its
     * first row appeared — so a contributor's rows read as that contributor's
     * and the order is the one the map stated.
     *
     * @param list<LegendItem> $items
     *
     * @return list<array{label: string|null, items: list<LegendItem>}>
     */
    private static function group(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $key = $item->group ?? '';
            $groups[$key] ??= ['label' => $item->group, 'items' => []];
            $groups[$key]['items'][] = $item;
        }

        return array_values($groups);
    }
}
