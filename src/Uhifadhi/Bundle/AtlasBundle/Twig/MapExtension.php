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

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Uhifadhi\Bundle\AtlasBundle\Model\SatelliteSource;

/**
 * The one contract by which a deployment's configured imagery reaches the browser.
 *
 * A host writes ONE thing in its layout:
 *
 *     <body {{ map_basemap_attributes() }}>
 *
 * and every map on every page — the host's area maps, each module's plates —
 * draws the configured source. There is no per-template wiring and no per-module
 * wiring, which is the point: the map-legend contract says the same layer must
 * render identically everywhere, and the surest way to keep that promise is to
 * give the whole document exactly one place to read the answer from.
 *
 * WHY AN ATTRIBUTE AND NOT AN ENDPOINT. The scripts need this before the first
 * tile, on every page, for a value that changes only when a deployment is
 * reconfigured. A fetch would add a round trip to every map mount to learn
 * something the server already knew while rendering the page. It is published as
 * a data attribute for the same reason the Google key always was — the map
 * controllers are Stimulus controllers on a page the server rendered.
 *
 * Registered wherever there is a Twig, without a controller or a route, so a
 * host that installs this bundle for its assets alone still gets the function.
 */
final class MapExtension extends AbstractExtension
{
    public function __construct(
        private readonly SatelliteSource $satellite,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            // is_safe: the value is json_encode() output placed inside a
            // double-quoted attribute, and the encoder escapes the one character
            // that could close it (") as " only when JSON_HEX_QUOT is set —
            // which it is not. So the quotes are escaped HERE, explicitly, by
            // htmlspecialchars, and the function is marked safe because it has
            // done that escaping itself rather than because escaping is unneeded.
            new TwigFunction('map_basemap_attributes', $this->basemapAttributes(...), ['is_safe' => ['html']]),
            // The raw payload, for a host that would rather place the attribute
            // itself (a component's root element, say) than take the whole tag.
            new TwigFunction('map_basemap_payload', $this->basemapPayload(...)),
        ];
    }

    public function basemapAttributes(): string
    {
        return \sprintf('data-map-satellite="%s"', htmlspecialchars($this->satellite->toJson(), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'));
    }

    /**
     * @return array{provider: string, maxZoom: int, key?: string, urlTemplate?: string, attribution?: string}
     */
    public function basemapPayload(): array
    {
        return $this->satellite->toBrowserPayload();
    }
}
