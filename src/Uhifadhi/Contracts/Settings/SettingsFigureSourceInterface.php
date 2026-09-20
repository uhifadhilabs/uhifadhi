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

namespace Uhifadhi\Contracts\Settings;

/**
 * WHOEVER OWNS A FACT ABOUT THE WHOLE INSTALLATION PUBLISHES IT HERE.
 *
 * The Settings overview's figure row is the one place in the product that
 * states how big this installation is, and no single bundle knows: areas are
 * the area bundle's, people the team's, what is kept the storage module's.
 * So the row is collected, in {@see position()} order, and the section adds
 * its own first card.
 *
 * READ LIVE, NEVER CACHED. An area registered this morning is on the card
 * this morning; a figure that lagged a deploy would be read as a bug in the
 * thing it counts.
 *
 * GATING IS THE SOURCE'S, exactly as it is for the sidebar. The section holds
 * no authorization service and asks nothing about the viewer, so a figure
 * somebody may not see is simply not returned.
 *
 * TAG IT BY HAND, in your own extension, because a reusable bundle's services
 * are not autoconfigured; a service in an application carries
 * `#[AutoconfigureTag(SettingsFigureSourceInterface::TAG)]` on its own class,
 * because Symfony reads autoconfigure attributes off the definition's class
 * and PHP does not inherit them from an interface.
 */
interface SettingsFigureSourceInterface
{
    public const string TAG = 'shell.settings_figure';

    /**
     * Where this source's cards sit in the row — lower first, registration
     * order as the tie-break. A declared number rather than a hope about
     * container compilation order, which is what the field is for.
     */
    public function position(): int;

    /**
     * @return iterable<SettingsFigure>
     */
    public function settingsFigures(): iterable;
}
