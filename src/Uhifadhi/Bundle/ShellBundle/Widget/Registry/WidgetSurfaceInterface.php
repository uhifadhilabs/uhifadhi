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

namespace Uhifadhi\Bundle\ShellBundle\Widget\Registry;

use Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetCatalog;

/**
 * HOW A MODULE DECLARES A DASHBOARD. One implementation is one surface: the
 * widgets it ships, the headed sections its library files them under, and the
 * whole layouts a person may adopt in a click.
 *
 * The same plug-point pattern a module registers everything else through — a
 * class implementing a published interface, tagged in the module's own
 * extension — so the shell never has to know which dashboards exist and a
 * module written by somebody else declares one without either of them changing.
 *
 * A MODULE IS NOT AUTOCONFIGURED. A reusable bundle's services are wired
 * explicitly, so the tag goes on by hand:
 *
 *     $services->set('sightings.widget_surface', SightingsWidgetSurface::class)
 *         ->tag(WidgetSurfaceInterface::TAG);
 *
 * A surface that forgot the tag has a working dashboard and an unreachable
 * registry entry: nothing renders differently until the day someone runs
 * `widget:prune`, which would then read its stored layouts as orphans. Tag it.
 */
interface WidgetSurfaceInterface
{
    /**
     * The tag that puts a surface in the registry.
     *
     * A CONSTANT, so a module spells it once and a rename is a compile error
     * rather than a dashboard that quietly stops being claimed.
     */
    public const string TAG = 'uhifadhi.widget_surface';

    /**
     * THE CATALOGUE, which names its own surface ({@see WidgetCatalog::$surface}).
     *
     * The surface string is what every stored row is keyed by, so it is stable
     * across releases: two modules may both ship a widget called "map" and
     * neither ever sees the other's rows.
     */
    public function catalog(): WidgetCatalog;
}
