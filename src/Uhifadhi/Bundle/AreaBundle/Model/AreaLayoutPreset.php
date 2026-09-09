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

namespace Uhifadhi\Bundle\AreaBundle\Model;

/**
 * ONE OF THE FIVE LAYOUT DIRECTIONS THE AREAS LANDING SHIPS — a full-page
 * preset, not a widget.
 *
 * THE AREAS-INDEX PRESETS ARE LAYOUT DIRECTIONS, NOT WIDGET COMPOSITIONS. Where
 * the overview surface's library composes a dashboard out of many widgets, the
 * areas landing is drawn five WHOLE WAYS — a wall of workspaces, a register, a
 * map, an attention board, a flagship portfolio — and adopting one swaps the
 * entire landing. So a preset here is a name, a description and the key of the
 * inline layout it previews, and nothing finer: there is no widget set to pick.
 *
 * ADOPT-ONLY, AND ONE IS THE SHIPPED DEFAULT. The surface ships exactly these
 * five to adopt — there is no compose-your-own and no "my presets". "Wall of
 * workspaces" is the org's shipped default; adopting another makes it the landing
 * and retires none of the rest.
 */
final readonly class AreaLayoutPreset
{
    /** @param bool $default whether this is the org's shipped default landing */
    public function __construct(
        public string $key,
        public string $name,
        public string $summary,
        public bool $default = false,
    ) {
    }
}
