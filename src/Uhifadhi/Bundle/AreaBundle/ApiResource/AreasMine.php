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

namespace Uhifadhi\Bundle\AreaBundle\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Uhifadhi\Bundle\AreaBundle\Api\State\AreasMineProvider;

/**
 * `GET /api/areas/mine` — EVERYTHING A FIELD CLIENT CACHES AT SIGN-IN so it can
 * work with no network afterwards: the areas the account may work in, how big each
 * one is, the roster it may name on a record, and the boundary it draws.
 *
 * IT LIVES IN THIS BUNDLE BECAUSE THE GROUND DOES. An area, its gazetted edge and
 * the measurement of it are this bundle's; a module that served them would be
 * answering for data it does not own, and every other module would then need its
 * own copy of the same endpoint.
 *
 * THE CLASS IS FOUND WITHOUT REGISTRATION. `ApiResource/` in a registered bundle
 * is a mapped resource path, read off `kernel.bundles_metadata` — so neither this
 * bundle nor an installation writes a `mapping.paths` line, and writing one would
 * DISABLE the project-dir defaults an installation's own resources rely on.
 *
 * @see https://api-platform.com/docs/symfony/state-providers/
 * @see vendor/api-platform/core/src/Symfony/Bundle/DependencyInjection/ApiPlatformExtension.php — `getBundlesResourcesPaths()` adds "<bundle path>/ApiResource"
 */
#[ApiResource(
    shortName: 'AreasMine',
    operations: [
        new Get(
            uriTemplate: '/areas/mine',
            description: 'The areas this account may work in, with their roster and boundaries.',
            provider: AreasMineProvider::class,
        ),
    ],
)]
final class AreasMine
{
    /**
     * HAND-BUILT ASSOCIATIVE ARRAYS rather than nested objects: these key names
     * are an external contract a released client reads, and an array says exactly
     * what goes across without depending on serializer naming conventions.
     *
     * `stations` is present and EMPTY. This platform has no station record, and
     * publishing the station names typed on past work as a register would hand a
     * client a list of guesses with positions nobody holds. `boundary` is GeoJSON
     * in lon/lat order (RFC 7946), a MultiPolygon or a Polygon depending on
     * whether the ground is one piece, and null for an area whose edge has not
     * been imported.
     *
     * @param list<array{
     *     id: string,
     *     name: string,
     *     areaKm2: float,
     *     stations: list<array{id: string, name: string, position: array{lat: float, lon: float}}>,
     *     team: list<array{id: string, name: string}>,
     *     boundary: array<string, mixed>|null
     * }> $areas
     */
    public function __construct(
        public array $areas = [],
    ) {
    }
}
