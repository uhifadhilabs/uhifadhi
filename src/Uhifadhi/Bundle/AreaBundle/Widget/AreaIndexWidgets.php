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

namespace Uhifadhi\Bundle\AreaBundle\Widget;

use Uhifadhi\Bundle\ShellBundle\Widget\Model\Widget;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetCatalog;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetGroup;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetPreset;
use Uhifadhi\Bundle\ShellBundle\Widget\Registry\WidgetSurfaceInterface;

/**
 * THE /areas SURFACE — the five whole-page layouts the areas landing ships.
 *
 * FIVE ALTERNATIVES, NOT FIVE ADDITIONS. Unlike a dashboard that composes many
 * widgets, this surface is drawn five complete ways — a wall of workspaces, an
 * operational register, a map of the network, an attention board, a flagship
 * portfolio — and exactly one is on at a time, because two of them stacked would
 * be the same areas listed twice. So the catalogue ships each layout as a
 * full-width widget and each preset turns on exactly one of them, the same shape
 * the roster surface uses for its six roster directions.
 *
 * IT RIDES THE WIDGET FRAMEWORK, which is the whole of what this class buys: the
 * adopted layout is a stored preference row keyed by this surface, read by the
 * register and written by the library through the shared endpoint, so adopting a
 * layout here works exactly as adopting one anywhere else in the installation —
 * and the landing can never disagree with the library about what is on.
 *
 * A CATALOGUE IS A STATEMENT OF WHAT A SURFACE SHIPS, so this class has no
 * dependencies and nothing may vary it at runtime.
 */
final class AreaIndexWidgets implements WidgetSurfaceInterface
{
    /** What a stored preference row is keyed by — stable across releases. */
    public const string SURFACE = 'areas-index';

    /** The layout everybody gets until one is adopted. */
    public const string DEFAULT_PRESET = 'wall';

    public function catalog(): WidgetCatalog
    {
        return new WidgetCatalog(
            self::SURFACE,
            [
                new WidgetGroup(
                    'landing',
                    'The areas landing',
                    'Five whole ways to draw the register. They are alternatives, not additions — exactly one is on, because two of them would be the same areas listed twice.',
                ),
            ],
            [
                new Widget('wall', 'Wall of workspaces', 'landing', 12, [12], true,
                    'Each area a rich workspace card — thumbnail, the operational figures, live patrols out and last contact.'),
                new Widget('register', 'The register', 'landing', 12, [12], false,
                    'The working table, science columns swapped for operational ones and the search / filter / sort muscle kept intact.'),
                new Widget('map', 'Map of the network', 'landing', 12, [12], false,
                    'The org’s ground as the base, areas as points, the list docked beside it.'),
                new Widget('attention', 'Attention board', 'landing', 12, [12], false,
                    'A worklist — areas grouped by what needs the operator: needs-attention, running-steady, awaiting-setup.'),
                new Widget('flagship', 'The flagship', 'landing', 12, [12], false,
                    'The flagship area featured large with its full live pulse; the rest on a secondary strip.'),
            ],
            [
                new WidgetPreset(
                    'wall',
                    'Wall of workspaces',
                    'Each area a rich workspace card — thumbnail, the operational figures, live patrols out and last contact. The warmest, most product-like read; the least dense.',
                    ['wall' => 12],
                ),
                new WidgetPreset(
                    'register',
                    'The register',
                    'The working table, science columns swapped for operational ones and the search / filter / sort muscle kept intact. The densest, most direct descendant of the current page.',
                    ['register' => 12],
                ),
                new WidgetPreset(
                    'map',
                    'Map of the network',
                    'The org’s ground as the base, areas as points, the list docked beside it. Answers “where does the org work” and “what’s happening” together; the right shape for a spread-out org.',
                    ['map' => 12],
                ),
                new WidgetPreset(
                    'attention',
                    'Attention board',
                    'A worklist — areas grouped by what needs the operator: needs-attention, running-steady, awaiting-setup. The most honest about the morning; it hides a good day on purpose.',
                    ['attention' => 12],
                ),
                new WidgetPreset(
                    'flagship',
                    'The flagship',
                    'The flagship area featured large with its full live pulse; the rest on a secondary strip. The honest shape for one busy area among areas still coming online.',
                    ['flagship' => 12],
                ),
            ],
            self::DEFAULT_PRESET,
        );
    }
}
